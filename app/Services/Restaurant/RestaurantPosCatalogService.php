<?php

namespace App\Services\Restaurant;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Models\RestaurantMenuItem;
use App\Support\PublicImageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RestaurantPosCatalogService
{
    // TISH's full active menu fits on the initial POS page; larger catalogues still paginate.
    public const PAGE_SIZE = 96;

    private function availableAt(int $locationId): Builder
    {
        return Product::query()
            ->active()
            ->whereHas('restaurantMenuItems', fn (Builder $menuItem) => $menuItem
                ->where('location_id', $locationId)
                ->where('is_active', true)
                ->where('show_in_pos', true))
            ->whereHas('locationProducts', fn (Builder $locationProduct) => $locationProduct
                ->where('location_id', $locationId)
                ->where('is_available', true));
    }

    private function detailed(Builder $query, int $locationId): Builder
    {
        return $query->with([
            'category:id,name,name_ar',
            'brand:id,name,name_ar',
            'restaurantMenuItems' => fn ($menuItem) => $menuItem
                ->where('location_id', $locationId)
                ->where('is_active', true)
                ->where('show_in_pos', true),
            'locationProducts' => fn ($locationProduct) => $locationProduct
                ->where('location_id', $locationId),
            'activeVariants.size',
            'activeVariants.color',
            'activeVariants.attributeValues.attribute',
            'modifierGroupLinks' => fn ($links) => $links
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id'),
            'modifierGroupLinks.group.modifiers' => fn ($modifiers) => $modifiers
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id'),
        ])->orderBy(RestaurantMenuItem::query()
            ->select('sort_order')
            ->whereColumn('restaurant_menu_items.product_id', 'products.id')
            ->where('location_id', $locationId)
            ->limit(1))
            ->orderBy('products.id');
    }

    /**
     * Rank the branch's currently enabled menu by units in completed restaurant
     * orders during the last 30 days; cancelled, deleted and draft orders are excluded.
     * No revenue, customer or transaction details are exposed to the cashier.
     */
    public function popularIds(int $locationId): array
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $order) => $order
                ->where('location_id', $locationId)
                ->whereNotNull('restaurant_service_type')
                ->where('status', OrderStatus::Completed->value)
                ->where('completed_at', '>=', now()->subDays(30)))
            ->whereIn('product_id', $this->availableAt($locationId)->select('products.id'))
            ->select('product_id')
            ->selectRaw('SUM(quantity) as units')
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->orderBy('product_id')
            ->limit(12)
            ->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    public function page(
        int $locationId,
        int $page = 1,
        ?string $search = null,
        ?int $categoryId = null,
        ?int $brandId = null,
        bool $popular = false,
    ): LengthAwarePaginator {
        $query = $this->availableAt($locationId);

        if ($popular) {
            $ids = $this->popularIds($locationId);
            $query->whereIn('products.id', $ids);
            if ($ids !== []) {
                $cases = implode(' ', array_map(fn ($rank) => 'WHEN ? THEN '.($rank + 1), array_keys($ids)));
                $query->orderByRaw('CASE products.id '.$cases.' ELSE 999 END', $ids);
            }
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $query->where(function (Builder $products) use ($term, $locationId): void {
                $pattern = '%'.$term.'%';
                $products->where('name', 'like', $pattern)
                    ->orWhere('name_ar', 'like', $pattern)
                    ->orWhere('sku', 'like', $pattern)
                    ->orWhere('barcode', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern)
                    ->orWhereHas('restaurantMenuItems', fn (Builder $menu) => $menu
                        ->where('location_id', $locationId)
                        ->where('is_active', true)
                        ->where('show_in_pos', true)
                        ->where(fn (Builder $names) => $names
                            ->where('display_name', 'like', $pattern)
                            ->orWhere('display_name_ar', 'like', $pattern)
                            ->orWhere('description', 'like', $pattern)))
                    ->orWhereHas('category', fn (Builder $category) => $category
                        ->where('name', 'like', $pattern)
                        ->orWhere('name_ar', 'like', $pattern))
                    ->orWhereHas('brand', fn (Builder $brand) => $brand
                        ->where('name', 'like', $pattern)
                        ->orWhere('name_ar', 'like', $pattern));
            });
        }

        return $this->detailed($query, $locationId)
            ->paginate(self::PAGE_SIZE, ['products.*'], 'page', $page);
    }

    public function byIds(int $locationId, array $ids): Collection
    {
        return $this->detailed(
            $this->availableAt($locationId)->whereIn('products.id', $ids),
            $locationId
        )->get();
    }

    public function facets(int $locationId): array
    {
        $categoryCounts = $this->availableAt($locationId)
            ->select('category_id')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');

        $brandCounts = $this->availableAt($locationId)
            ->whereNotNull('brand_id')
            ->select('brand_id')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('brand_id')
            ->pluck('product_count', 'brand_id');

        return [
            'categories' => Category::query()->whereIn('id', $categoryCounts->keys())
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'name_ar'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name_ar ?: $category->name,
                    'count' => (int) $categoryCounts[$category->id],
                ])->values(),
            'brands' => Brand::query()->whereIn('id', $brandCounts->keys())
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'name_ar'])
                ->map(fn (Brand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->displayName(),
                    'count' => (int) $brandCounts[$brand->id],
                ])->values(),
        ];
    }

    public function payload(Collection $products): Collection
    {
        return $products->map(function (Product $product): array {
            $menuItem = $product->restaurantMenuItems->first();
            $price = (float) ($product->locationProducts->first()?->local_selling_price
                ?? $product->base_selling_price);

            return [
                'id' => (int) $product->id,
                'name' => $menuItem?->display_name_ar ?: $menuItem?->display_name
                    ?: $product->name_ar ?: $product->name,
                'category' => $product->category?->name_ar ?: $product->category?->name ?: 'بدون فئة',
                'category_id' => $product->category_id ? (int) $product->category_id : null,
                'brand_id' => $product->brand_id ? (int) $product->brand_id : null,
                'price' => $price,
                'image' => PublicImageUrl::url($menuItem?->image) ?? PublicImageUrl::url($product->image),
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => $menuItem?->description ?: $product->description,
                'brand' => $product->brand?->displayName(),
                'requires_variant' => $product->isVariantProduct() && $product->activeVariants->isNotEmpty(),
                'variants' => $product->activeVariants->map(fn ($variant) => [
                    'id' => (int) $variant->id,
                    'name' => $variant->displayName(),
                    'price' => (float) ($variant->selling_price ?? $price),
                    'is_default' => (bool) $variant->is_default,
                ])->values(),
                'modifier_groups' => $product->modifierGroupLinks
                    ->filter(fn ($link) => $link->group && $link->group->is_active)
                    ->map(fn ($link) => [
                        'id' => (int) $link->group->id,
                        'product_variant_id' => $link->product_variant_id ? (int) $link->product_variant_id : null,
                        'name' => $link->group->name_ar ?: $link->group->name,
                        'selection_type' => $link->group->selection_type?->value ?? (string) $link->group->selection_type,
                        'is_required' => $link->is_required_override ?? (bool) $link->group->is_required,
                        'min_selections' => $link->min_selections_override ?? (int) $link->group->min_selections,
                        'max_selections' => $link->max_selections_override ?? $link->group->max_selections,
                        'modifiers' => $link->group->modifiers->map(fn ($modifier) => [
                            'id' => (int) $modifier->id,
                            'name' => $modifier->name_ar ?: $modifier->name,
                            'price_delta' => (float) $modifier->price_delta,
                            'allow_quantity' => (bool) $modifier->allow_quantity,
                            'max_quantity' => (int) $modifier->max_quantity,
                            'is_default' => (bool) $modifier->is_default,
                        ])->values(),
                    ])->values(),
            ];
        })->values();
    }
}

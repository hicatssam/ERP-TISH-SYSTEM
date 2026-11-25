<?php

namespace App\Http\Controllers\Production;

use App\Enums\RecipeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreRecipeRequest;
use App\Http\Requests\Production\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Category;
use App\Models\Recipe;
use App\Services\Production\ProductionContextService;
use App\Services\Production\RecipeCostService;
use App\Services\Production\RecipeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipes,
        private RecipeCostService $costs,
        private ProductionContextService $context
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        // Keep this user's recipe filters while they open, edit and save recipes.
        // A deliberate reset is the only action that clears the saved selection.
        $sessionKey = 'production.recipes.index_filters';
        $filterKeys = ['q', 'category_id', 'product_id', 'status', 'visibility'];

        if ($request->query('reset') === '1') {
            $request->session()->forget($sessionKey);

            return redirect()->route('production.recipes.index');
        }

        $hasExplicitFilters = collect($filterKeys)
            ->contains(fn (string $key) => $request->query->has($key));

        if ($hasExplicitFilters) {
            $saved = collect($request->only($filterKeys))
                ->filter(fn ($value) => is_scalar($value) && strlen((string) $value) <= 255)
                ->all();
            $request->session()->put($sessionKey, $saved);
        } else {
            $saved = $request->session()->get($sessionKey, []);
            if (is_array($saved) && $saved !== []) {
                $page = $request->query('page');
                $params = $saved;
                if (is_scalar($page) && ctype_digit((string) $page) && (int) $page > 0) {
                    $params['page'] = (int) $page;
                }

                return redirect()->route('production.recipes.index', $params);
            }
        }

        $visibility = $request->string('visibility')->toString();
        if (! in_array($visibility, ['active', 'inactive', 'all'], true)) {
            $visibility = 'active';
        }

        $categoryId = $request->integer('category_id');
        $productId = $request->integer('product_id');
        $status = $request->string('status')->toString();

        $productsQuery = Product::query()
            ->where('is_raw_material', false)
            ->whereHas('recipes')
            ->when($visibility === 'active', fn ($q) => $q->where('is_active', true))
            ->when($visibility === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId));

        $products = (clone $productsQuery)
            ->orderByRaw('COALESCE(name_ar, name)')
            ->get(['id', 'category_id', 'name', 'name_ar']);

        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q
                ->where('is_raw_material', false)
                ->whereHas('recipes')
                ->when($visibility === 'active', fn ($q) => $q->where('is_active', true))
                ->when($visibility === 'inactive', fn ($q) => $q->where('is_active', false)))
            ->orderBy('sort_order')
            ->orderByRaw('COALESCE(name_ar, name)')
            ->get(['id', 'name', 'name_ar']);

        $query = Recipe::query()
            ->with(['product.category', 'productVariant', 'approver'])
            ->withCount('items')
            ->whereHas('product', fn ($q) => $q
                ->where('is_raw_material', false)
                ->when($visibility === 'active', fn ($q) => $q->where('is_active', true))
                ->when($visibility === 'inactive', fn ($q) => $q->where('is_active', false))
                ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
                ->when($productId > 0, fn ($q) => $q->whereKey($productId)));

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('name_ar', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%"));
            });
        }

        if ($status !== '' && in_array($status, array_column(RecipeStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $missing = (clone $query)->whereDoesntHave('items')->count();
        $draft = (clone $query)->where('status', RecipeStatus::Draft->value)->count();

        return view('production.recipes.index', [
            'recipes' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'statuses' => RecipeStatus::cases(),
            'products' => $products,
            'categories' => $categories,
            'visibility' => $visibility,
            'total' => $total,
            'missing' => $missing,
            'draft' => $draft,
        ]);
    }

    public function create(): View
    {
        return view(
            'production.recipes.form',
            [
                'recipe' =>
                    null,

                'products' =>
                    Product::query()
                        ->active()
                        ->with([
                            'unitDefinition',
                            'activeVariants.size',
                            'activeVariants.attributeValues.attribute',
                        ])
                        ->orderByRaw(
                            'COALESCE(name_ar, name)'
                        )
                        ->get(),
            ]
        );
    }

    public function store(
        StoreRecipeRequest $request
    ): RedirectResponse {
        $recipe =
            $this->recipes
                ->create(
                    $request->validated(),
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.show',
                $recipe
            )
            ->with(
                'success',
                'تم إنشاء مسودة الوصفة بنجاح.'
            );
    }

    public function show(
        Request $request,
        Recipe $recipe
    ): View {
        $recipe->load([
            'product.unitDefinition',
            'productVariant.size',
            'productVariant.attributeValues.attribute',
            'items.ingredient.unitDefinition',
            'creator',
            'approver',
        ]);

        $locations =
            collect();

        $selectedLocation =
            null;

        $estimate =
            null;

        if (
            $request->user()
                ->can('recipes.cost.view')
        ) {
            $locations =
                $this->context
                    ->selectableLocations(
                        $request->user()
                    );

            if ($locations->isNotEmpty()) {
                $selectedLocation =
                    $this->context
                        ->resolveLocation(
                            $request->user(),
                            $request->integer(
                                'location_id'
                            )
                            ?: null
                        );

                $estimate =
                    $this->costs
                        ->estimate(
                            $recipe,
                            $selectedLocation->id
                        );
            }
        }

        return view(
            'production.recipes.show',
            compact(
                'recipe',
                'locations',
                'selectedLocation',
                'estimate'
            )
        );
    }

    public function edit(
        Recipe $recipe
    ): View {
        abort_unless(
            $recipe->isEditable(),
            422,
            'الوصفة المعتمدة لا تعدّل مباشرة. أنشئ إصدارًا جديدًا.'
        );

        $recipe->load(
            'items'
        );

        return view(
            'production.recipes.form',
            [
                'recipe' =>
                    $recipe,

                'products' =>
                    Product::query()
                        ->active()
                        ->with([
                            'unitDefinition',
                            'activeVariants.size',
                            'activeVariants.attributeValues.attribute',
                        ])
                        ->orderByRaw(
                            'COALESCE(name_ar, name)'
                        )
                        ->get(),
            ]
        );
    }

    public function update(
        UpdateRecipeRequest $request,
        Recipe $recipe
    ): RedirectResponse {
        $recipe =
            $this->recipes
                ->updateDraft(
                    $recipe,
                    $request->validated(),
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.show',
                $recipe
            )
            ->with(
                'success',
                'تم تحديث مسودة الوصفة.'
            );
    }

    public function approve(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.approve'),
            403
        );

        $this->recipes
            ->approve(
                $recipe,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم اعتماد الوصفة وأصبحت الإصدار الفعال للمنتج.'
            );
    }

    public function newVersion(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.manage'),
            403
        );

        $copy =
            $this->recipes
                ->createNewVersion(
                    $recipe,
                    $request->user()
                );

        return redirect()
            ->route(
                'production.recipes.edit',
                $copy
            )
            ->with(
                'success',
                'تم إنشاء إصدار جديد كمسودة مع نسخ المكونات.'
            );
    }

    public function archive(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        abort_unless(
            $request->user()
                ->can('recipes.approve'),
            403
        );

        $this->recipes
            ->archive(
                $recipe,
                $request->user()
            );

        return back()
            ->with(
                'success',
                'تم أرشفة الوصفة.'
            );
    }

    public function cost(
        Request $request,
        Recipe $recipe
    ): View {
        return $this->show($request, $recipe);
    }
}

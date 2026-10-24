<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_ar', 'slug', 'description', 'image', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Categories allowed in branch sweets production requests:
     * Eastern sweets and every Kunafa / Knafeh variety only.
     *
     * This category-level filter intentionally excludes unrelated products,
     * even if their product names mention Kunafa.
     */
    public function scopeForBranchSweetsOrders($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where(function ($categories) {
                $categories
                    ->where('name_ar', 'like', '%شرقي%')
                    ->orWhere('name', 'like', '%oriental%')
                    ->orWhere('name', 'like', '%eastern%')
                    ->orWhere('slug', 'like', '%oriental%')
                    ->orWhere('slug', 'like', '%eastern%');

                foreach (['%كناف%', '%كنائ%', '%كوناف%'] as $arabic) {
                    $categories->orWhere('name_ar', 'like', $arabic)
                        ->orWhere('name', 'like', $arabic)
                        ->orWhere('slug', 'like', $arabic);
                }

                foreach (['%kunaf%', '%kunef%', '%knafe%', '%knafa%', '%kanafe%', '%konaf%'] as $english) {
                    $categories->orWhere('name', 'like', $english)
                        ->orWhere('slug', 'like', $english);
                }
            });
    }

    public function scopeActive($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}

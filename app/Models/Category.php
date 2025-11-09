<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\{HasMedia, InteractsWithMedia};
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
use Illuminate\Support\Str;

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'parent_id',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Boot method to generate slug automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Parent category relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * All courses in this category
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Get all courses including subcategories
     */
    public function allCourses()
    {
        $categoryIds = $this->getAllChildIds([$this->id]);

        return Course::whereIn('category_id', $categoryIds);
    }

    /**
     * Get all child category IDs recursively
     */
    public function getAllChildIds(array $categoryIds): array
    {
        $childIds = Category::whereIn('parent_id', $categoryIds)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        if (empty($childIds)) {
            return $categoryIds;
        }

        return array_merge($categoryIds, $this->getAllChildIds($childIds));
    }

    /**
     * Get breadcrumb trail (parent -> current)
     */
    public function getBreadcrumb(): array
    {
        $breadcrumb = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }

        return $breadcrumb;
    }

    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            array_unshift($ancestors, $current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public function getDescendants(): array
    {
        $descendants = [];
        $children = $this->children()->active()->get();

        foreach ($children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category has courses
     */
    public function hasCourses(): bool
    {
        return $this->courses()->exists();
    }

    /**
     * Get total courses count (including subcategories)
     */
    public function getTotalCoursesCount(): int
    {
        return $this->allCourses()->count();
    }

    /**
     * Media collections for icon
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp']);
    }

    /**
     * Get icon URL attribute
     */
    public function getIconUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('icon');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for top-level categories (no parent)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for ordered categories
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }

    /**
     * Get hierarchical tree structure
     */
    public static function getTree(): array
    {
        $categories = self::active()
            ->ordered()
            ->get()
            ->groupBy('parent_id');

        return self::buildTree($categories, null);
    }

    /**
     * Build hierarchical tree
     */
    protected static function buildTree($categories, $parentId = null): array
    {
        $tree = [];

        if (!isset($categories[$parentId])) {
            return $tree;
        }

        foreach ($categories[$parentId] as $category) {
            $tree[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon_url,
                'children' => self::buildTree($categories, $category->id),
            ];
        }

        return $tree;
    }

    /**
     * Get flat list with indentation
     */
    public static function getFlatList($indent = '— '): array
    {
        $categories = self::active()->ordered()->get();
        $list = [];

        foreach ($categories as $category) {
            $depth = count($category->getAncestors());
            $prefix = str_repeat($indent, $depth);

            $list[$category->id] = $prefix . $category->name;
        }

        return $list;
    }
}

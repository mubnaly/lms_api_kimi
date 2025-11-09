<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get hierarchical category tree
     */
    public function index(Request $request)
    {
        $categories = Category::active()
            ->topLevel()
            ->with(['children' => function ($query) {
                $query->active()->ordered()->with('children');
            }])
            ->ordered()
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Get flat category list (for dropdowns)
     */
    public function flat(Request $request)
    {
        $categories = Category::active()
            ->ordered()
            ->get()
            ->map(function ($category) {
                $depth = count($category->getAncestors());
                $prefix = str_repeat('— ', $depth);

                return [
                    'id' => $category->id,
                    'name' => $prefix . $category->name,
                    'slug' => $category->slug,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get single category with breadcrumb
     */
    public function show(Category $category)
    {
        $category->load(['parent', 'children', 'courses' => function ($query) {
            $query->published()->limit(6);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => new CategoryResource($category),
                'breadcrumb' => $category->getBreadcrumb(),
                'children' => CategoryResource::collection($category->children),
                'courses' => \App\Http\Resources\CourseResource::collection($category->courses),
            ],
        ]);
    }

    /**
     * Get category courses (with subcategories)
     */
    public function courses(Category $category, Request $request)
    {
        $courses = $category->allCourses()
            ->published()
            ->with('instructor', 'category')
            ->paginate($request->get('per_page', 15));

        return \App\Http\Resources\CourseResource::collection($courses);
    }
}

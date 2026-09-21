<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\Category\ReorderCategoriesRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display custom category settings.
     */
    public function edit(Request $request): Response
    {
        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->withCount('transactions')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/Categories', [
            'categories' => [
                'cost' => $categories
                    ->where('type', TransactionType::Cost)
                    ->values()
                    ->map(fn (Category $category) => [
                        ...(new CategoryResource($category))->resolve($request),
                        'transactions_count' => $category->transactions_count,
                    ]),
                'income' => $categories
                    ->where('type', TransactionType::Income)
                    ->values()
                    ->map(fn (Category $category) => [
                        ...(new CategoryResource($category))->resolve($request),
                        'transactions_count' => $category->transactions_count,
                    ]),
            ],
            // Every category that could be a parent, defaults included: the
            // list above only holds the user's own, but Food is the parent a
            // Restaurant subcategory most often wants.
            'parentCandidates' => Category::query()
                ->availableFor($request->user())
                ->whereNull('parent_id')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => (new CategoryResource($category))->resolve($request)),
        ]);
    }

    /**
     * Store a newly created category for the authenticated user.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->categoryData();

        // New categories join the end of their type's list — the client owns
        // reordering from there via reorder().
        $nextSortOrder = Category::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $data['type'])
            ->max('sort_order');

        $category = Category::query()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'],
            'type' => $data['type'],
            'for_both_types' => $data['for_both_types'],
            'name' => $data['name'],
            'color' => $data['color'],
            'sort_order' => $nextSortOrder === null ? 0 : $nextSortOrder + 1,
        ]);

        return back()->with('createdCategory', [
            'id' => $category->id,
            'type' => $category->type->value,
        ]);
    }

    /**
     * Update an owned custom category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->categoryData();

        $category->update([
            'name' => $data['name'],
            'slug' => Category::slugForName($data['name']),
            'color' => $data['color'],
            'parent_id' => $data['parent_id'],
            'for_both_types' => $data['for_both_types'],
        ]);

        return back();
    }

    /**
     * Persist the order the client dragged an owned type-group's categories
     * into. Ids outside that owned type group are silently ignored rather
     * than rejected — a stray id changes nothing, so there is nothing to
     * protect against by erroring.
     */
    public function reorder(ReorderCategoriesRequest $request): RedirectResponse
    {
        $data = $request->reorderData();

        $owned = Category::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $data['type'])
            ->pluck('id')
            ->all();

        $ordered = array_values(array_intersect($data['ids'], $owned));

        foreach ($ordered as $index => $id) {
            Category::query()->whereKey($id)->update(['sort_order' => $index]);
        }

        return back();
    }

    /**
     * Remove an owned custom category when no transactions still use it.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        abort_unless(
            $category->user_id !== null
            && (int) $category->user_id === (int) $request->user()->id,
            404,
        );

        if ($category->transactions()->exists()) {
            return back()->withErrors([
                'category' => __('settings.categories.delete_in_use'),
            ]);
        }

        // The foreign key does this too; stated here so it holds on a database
        // that was never told to enforce it.
        $category->children()->update(['parent_id' => null]);
        $category->delete();

        return back();
    }
}

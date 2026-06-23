<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
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
        ]);
    }

    /**
     * Store a newly created category for the authenticated user.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->categoryData();

        $category = Category::query()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'name' => $data['name'],
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
        ]);

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

        $category->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
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
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->availableFor($request->user())
            ->when(
                TransactionType::tryFrom((string) $request->query('type')),
                fn ($query, TransactionType $type) => $query->where('type', $type->value),
            )
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}

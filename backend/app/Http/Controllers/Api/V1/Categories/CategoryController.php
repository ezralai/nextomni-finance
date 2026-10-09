<?php

namespace App\Http\Controllers\Api\V1\Categories;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\TransactionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of accessible categories grouped with children.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        $query = TransactionCategory::accessibleBy($request->user()->id)
            ->topLevel()
            ->with('children')
            ->where('is_active', true)
            ->orderBy('name');

        if ($type) {
            $query->where('type', $type);
        }

        $categories = $query->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Budgets\StoreBudgetRequest;
use App\Http\Requests\Api\V1\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Budget;
use App\Services\Budgets\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    public function __construct(
        protected BudgetService $budgetService
    ) {}

    /**
     * Display a listing of user budgets with real-time spending progress.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['period', 'date']);
        $progress = $this->budgetService->getBudgetsProgress($request->user(), $filters);

        $formattedBudgets = array_map(function ($item) {
            return [
                'budget' => new BudgetResource($item['budget']),
                'category' => $item['category'] ? new CategoryResource($item['category']) : null,
                'amount' => $item['amount'],
                'amount_formatted' => $item['amount_formatted'],
                'spent' => $item['spent'],
                'spent_formatted' => $item['spent_formatted'],
                'remaining' => $item['remaining'],
                'remaining_formatted' => $item['remaining_formatted'],
                'percent_used' => $item['percent_used'],
                'is_over_budget' => $item['is_over_budget'],
                'status' => $item['status'],
            ];
        }, $progress['budgets']);

        return response()->json([
            'budgets' => $formattedBudgets,
            'summary' => $progress['summary'],
        ]);
    }

    /**
     * Store a newly created budget.
     */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        Gate::authorize('create', Budget::class);

        $budget = $this->budgetService->createBudget(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Budget limit established successfully.',
            'budget' => new BudgetResource($budget->load('category')),
        ], 201);
    }

    /**
     * Display the specified budget.
     */
    public function show(Budget $budget): JsonResponse
    {
        Gate::authorize('view', $budget);

        return response()->json([
            'budget' => new BudgetResource($budget->load('category')),
        ]);
    }

    /**
     * Update the specified budget.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        Gate::authorize('update', $budget);

        $updated = $this->budgetService->updateBudget($budget, $request->validated());

        return response()->json([
            'message' => 'Budget limit updated successfully.',
            'budget' => new BudgetResource($updated->load('category')),
        ]);
    }

    /**
     * Remove the specified budget.
     */
    public function destroy(Budget $budget): JsonResponse
    {
        Gate::authorize('delete', $budget);

        $this->budgetService->deleteBudget($budget);

        return response()->json([
            'message' => 'Budget limit removed successfully.',
        ]);
    }
}

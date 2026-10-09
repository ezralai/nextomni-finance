<?php

namespace App\Http\Controllers\Api\V1\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transactions\StoreRecurringTransactionRequest;
use App\Http\Requests\Api\V1\Transactions\UpdateRecurringTransactionRequest;
use App\Http\Resources\Api\V1\RecurringTransactionResource;
use App\Models\RecurringTransaction;
use App\Services\Transactions\RecurringTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RecurringTransactionController extends Controller
{
    public function __construct(
        protected RecurringTransactionService $recurringService
    ) {}

    /**
     * Display a listing of user recurring transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_active', 'type', 'account_id']);
        $recurringItems = $this->recurringService->getRecurringTransactions($request->user(), $filters);

        return response()->json([
            'data' => RecurringTransactionResource::collection($recurringItems),
        ]);
    }

    /**
     * Store a newly created recurring transaction schedule.
     */
    public function store(StoreRecurringTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create', RecurringTransaction::class);

        $recurring = $this->recurringService->createRecurring(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Recurring transaction schedule established successfully.',
            'recurring_transaction' => new RecurringTransactionResource($recurring->load(['account.institution', 'category'])),
        ], 201);
    }

    /**
     * Display the specified recurring transaction.
     */
    public function show(RecurringTransaction $recurringTransaction): JsonResponse
    {
        Gate::authorize('view', $recurringTransaction);

        return response()->json([
            'recurring_transaction' => new RecurringTransactionResource($recurringTransaction->load(['account.institution', 'category'])),
        ]);
    }

    /**
     * Update the specified recurring transaction.
     */
    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction): JsonResponse
    {
        Gate::authorize('update', $recurringTransaction);

        $updated = $this->recurringService->updateRecurring(
            $recurringTransaction,
            $request->validated()
        );

        return response()->json([
            'message' => 'Recurring transaction schedule updated successfully.',
            'recurring_transaction' => new RecurringTransactionResource($updated),
        ]);
    }

    /**
     * Remove the specified recurring transaction.
     */
    public function destroy(RecurringTransaction $recurringTransaction): JsonResponse
    {
        Gate::authorize('delete', $recurringTransaction);

        $this->recurringService->deleteRecurring($recurringTransaction);

        return response()->json([
            'message' => 'Recurring transaction schedule deleted successfully.',
        ]);
    }
}

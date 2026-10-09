<?php

namespace App\Http\Controllers\Api\V1\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transactions\StoreTransactionRequest;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Transaction;
use App\Services\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Display a listing of user transactions with filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'account_id',
            'category_id',
            'type',
            'status',
            'start_date',
            'end_date',
            'search',
        ]);

        $perPage = (int) $request->query('per_page', 25);
        $paginator = $this->transactionService->getTransactions($request->user(), $filters, $perPage);

        return response()->json([
            'data' => TransactionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        Gate::authorize('create', Transaction::class);

        $transaction = $this->transactionService->createTransaction(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Transaction recorded successfully.',
            'transaction' => new TransactionResource($transaction->load(['account.institution', 'category'])),
        ], 201);
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        Gate::authorize('view', $transaction);

        return response()->json([
            'transaction' => new TransactionResource($transaction->load(['account.institution', 'category'])),
        ]);
    }

    /**
     * Remove the specified transaction and reverse account balance.
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        Gate::authorize('delete', $transaction);

        $this->transactionService->deleteTransaction($transaction);

        return response()->json([
            'message' => 'Transaction deleted and account balance restored.',
        ]);
    }
}

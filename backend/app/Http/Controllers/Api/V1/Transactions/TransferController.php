<?php

namespace App\Http\Controllers\Api\V1\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transactions\StoreTransferRequest;
use App\Http\Resources\Api\V1\TransferResource;
use App\Models\Transfer;
use App\Services\Transactions\TransactionService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    /**
     * Record an atomic inter-account transfer.
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = $this->transactionService->createTransfer(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Transfer executed successfully.',
            'transfer' => new TransferResource(
                $transfer->load(['sourceAccount.institution', 'destinationAccount.institution'])
            ),
        ], 201);
    }
}

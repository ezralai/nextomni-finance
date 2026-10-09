<?php

namespace App\Http\Controllers\Api\V1\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Accounts\StoreAccountRequest;
use App\Http\Requests\Api\V1\Accounts\UpdateAccountRequest;
use App\Http\Resources\Api\V1\AccountResource;
use App\Models\Account;
use App\Services\Accounts\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Display a listing of user accounts alongside net worth metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $overview = $this->accountService->getAccountsOverview($request->user());

        return response()->json([
            'accounts' => AccountResource::collection($overview['accounts']),
            'summary' => [
                'net_worth' => $overview['net_worth'],
                'net_worth_formatted' => $overview['net_worth_formatted'],
                'currency' => $overview['currency'],
                'breakdown' => $overview['breakdown'],
            ],
        ]);
    }

    /**
     * Store a newly created financial account.
     */
    public function store(StoreAccountRequest $request): JsonResponse
    {
        Gate::authorize('create', Account::class);

        $account = $this->accountService->createAccount(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Account created successfully.',
            'account' => new AccountResource($account->load('institution')),
        ], 201);
    }

    /**
     * Display the specified account.
     */
    public function show(Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        return response()->json([
            'account' => new AccountResource($account->load('institution')),
        ]);
    }

    /**
     * Update the specified account.
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        Gate::authorize('update', $account);

        $updated = $this->accountService->updateAccount($account, $request->validated());

        return response()->json([
            'message' => 'Account updated successfully.',
            'account' => new AccountResource($updated),
        ]);
    }

    /**
     * Remove the specified account.
     */
    public function destroy(Account $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        $this->accountService->deleteAccount($account);

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}

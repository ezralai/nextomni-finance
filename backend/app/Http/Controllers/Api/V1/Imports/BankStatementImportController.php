<?php

namespace App\Http\Controllers\Api\V1\Imports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Imports\UploadStatementRequest;
use App\Models\Account;
use App\Models\StatementImport;
use App\Services\Imports\BankStatementParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankStatementImportController extends Controller
{
    public function __construct(
        protected BankStatementParserService $parserService
    ) {}

    /**
     * Upload and parse a bank statement CSV to reconcile transactions into the specified account.
     */
    public function import(UploadStatementRequest $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::where('user_id', $user->id)->findOrFail($request->validated('account_id'));

        $result = $this->parserService->parseAndImport(
            $user,
            $account,
            $request->file('file')
        );

        return response()->json([
            'message' => "Statement reconciled successfully. {$result['imported_count']} imported, {$result['duplicate_count']} skipped as duplicates.",
            'data' => $result,
        ]);
    }

    /**
     * List user's statement import history.
     */
    public function history(Request $request): JsonResponse
    {
        $imports = StatementImport::where('user_id', $request->user()->id)
            ->with('account')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $imports,
        ]);
    }
}

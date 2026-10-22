<?php

namespace Tests\Feature\Audit;

use App\Models\Account;
use App\Models\Goal;
use App\Models\Institution;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Accounts\AccountService;
use App\Services\Goals\GoalService;
use App\Services\Transactions\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Institution $institution;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'base_currency' => 'MYR',
        ]);

        $this->institution = Institution::create([
            'name' => 'Maybank',
            'code' => 'MBB',
            'type' => 'bank',
            'country' => 'MYS',
            'is_active' => true,
        ]);

        $this->account = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->institution->id,
            'name' => 'Maybank Savings',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '5000.0000',
            'available_balance' => '5000.0000',
            'is_active' => true,
        ]);
    }

    public function test_user_can_query_paginated_audit_logs(): void
    {
        // 1. Transaction creation
        $txnService = app(TransactionService::class);
        $txnService->createTransaction($this->user, [
            'account_id' => $this->account->id,
            'type' => 'expense',
            'amount' => '75.00',
            'description' => 'Groceries Purchase',
        ]);

        // 2. Goal creation & contribution
        $goalService = app(GoalService::class);
        $goal = $goalService->createGoal($this->user, [
            'name' => 'Emergency Fund',
            'target_amount' => '10000.00',
            'current_amount' => '1000.00',
            'currency' => 'MYR',
        ]);
        $goalService->contributeToGoal($goal, '500.00');

        $response = $this->actingAs($this->user)->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'event',
                        'auditable_type',
                        'auditable_id',
                        'old_values',
                        'new_values',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $events = collect($response->json('data'))->pluck('event')->all();
        $this->assertContains('transaction.created', $events);
        $this->assertContains('goal.created', $events);
        $this->assertContains('goal.contributed', $events);
    }

    public function test_tenant_isolation_prevents_viewing_other_users_audit_logs(): void
    {
        $otherUser = User::factory()->create();
        $otherAccount = Account::create([
            'user_id' => $otherUser->id,
            'institution_id' => $this->institution->id,
            'name' => 'Secret Stash',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'current_balance' => '100000.0000',
            'available_balance' => '100000.0000',
            'is_active' => true,
        ]);

        $txnService = app(TransactionService::class);
        $txnService->createTransaction($otherUser, [
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => '9999.00',
            'description' => 'Confidential Acquisition',
        ]);

        // Query as primary user
        $response = $this->actingAs($this->user)->getJson('/api/v1/audit-logs');

        $response->assertStatus(200);
        $descriptions = collect($response->json('data'))->pluck('new_values.description')->filter();
        $this->assertFalse($descriptions->contains('Confidential Acquisition'));
    }

    public function test_audit_log_filters_by_event_and_auditable_type(): void
    {
        $goalService = app(GoalService::class);
        $goal = $goalService->createGoal($this->user, [
            'name' => 'Japan Travel',
            'target_amount' => '8000.00',
            'currency' => 'MYR',
        ]);
        $goalService->contributeToGoal($goal, '1200.00');

        $accountService = app(AccountService::class);
        $accountService->createAccount($this->user, [
            'institution_id' => $this->institution->id,
            'name' => 'Travel Vault',
            'account_type' => 'savings',
            'currency' => 'MYR',
            'opening_balance' => '0.0000',
        ]);

        // Filter by event
        $resEvent = $this->actingAs($this->user)->getJson('/api/v1/audit-logs?event=goal.contributed');
        $resEvent->assertStatus(200);
        $this->assertCount(1, $resEvent->json('data'));
        $this->assertEquals('goal.contributed', $resEvent->json('data.0.event'));

        // Filter by auditable_type
        $resType = $this->actingAs($this->user)->getJson('/api/v1/audit-logs?auditable_type=Account');
        $resType->assertStatus(200);
        foreach ($resType->json('data') as $log) {
            $this->assertEquals('Account', $log['auditable_type']);
        }
    }

    public function test_transfer_creation_and_deletion_logs_audit_events(): void
    {
        $destAccount = Account::create([
            'user_id' => $this->user->id,
            'institution_id' => $this->institution->id,
            'name' => 'CIMB Checking',
            'account_type' => 'checking',
            'currency' => 'MYR',
            'current_balance' => '1000.0000',
            'available_balance' => '1000.0000',
            'is_active' => true,
        ]);

        $txnService = app(TransactionService::class);
        $transfer = $txnService->createTransfer($this->user, [
            'source_account_id' => $this->account->id,
            'destination_account_id' => $destAccount->id,
            'amount' => '300.00',
            'notes' => 'Internal monthly transfer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'event' => 'transfer.created',
            'auditable_id' => $transfer->id,
        ]);

        // Delete transfer and verify rollback audit log
        $txnService->deleteTransfer($transfer);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'event' => 'transfer.deleted',
            'auditable_id' => $transfer->id,
        ]);
    }

    public function test_statement_import_logs_audit_event(): void
    {
        $csvContent = implode("\n", [
            'Date,Description,Amount,Type',
            '2026-10-01,Test Payee,50.00,expense',
        ]);
        $file = UploadedFile::fake()->createWithContent('audit_statement.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/statements', [
            'account_id' => $this->account->id,
            'file' => $file,
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'event' => 'statement.imported',
        ]);
    }

    public function test_user_registration_and_login_logs_audit_events(): void
    {
        // 1. Registration audit logging
        $regResponse = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100', 'HTTP_USER_AGENT' => 'NextOmniTest/1.0'])
            ->postJson('/api/v1/auth/register', [
                'name' => 'Alice Auditor',
                'email' => 'alice.audit@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'base_currency' => 'SGD',
            ]);

        $regResponse->assertStatus(201);
        $newUserId = $regResponse->json('user.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $newUserId,
            'event' => 'user.registered',
            'auditable_type' => User::class,
            'auditable_id' => (string) $newUserId,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'NextOmniTest/1.0',
        ]);

        $regLog = \App\Models\AuditLog::where('user_id', $newUserId)->where('event', 'user.registered')->first();
        $this->assertNotNull($regLog);
        $this->assertArrayNotHasKey('password', $regLog->new_values ?? []);
        $this->assertEquals('alice.audit@example.com', $regLog->new_values['email']);

        // 2. Login audit logging
        $loginResponse = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.101', 'HTTP_USER_AGENT' => 'NextOmniTest/1.0'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'alice.audit@example.com',
                'password' => 'Password123!',
            ]);

        $loginResponse->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $newUserId,
            'event' => 'user.login',
            'auditable_type' => User::class,
            'auditable_id' => (string) $newUserId,
            'ip_address' => '192.168.1.101',
        ]);
    }

    public function test_user_profile_updated_and_logout_logs_audit_events(): void
    {
        $oldName = $this->user->name;

        // Profile update
        $updateResponse = $this->actingAs($this->user)->putJson('/api/v1/auth/profile', [
            'name' => 'Renamed User',
            'base_currency' => 'USD',
        ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'event' => 'user.profile_updated',
            'auditable_type' => User::class,
            'auditable_id' => (string) $this->user->id,
        ]);

        $updateLog = \App\Models\AuditLog::where('user_id', $this->user->id)->where('event', 'user.profile_updated')->first();
        $this->assertNotNull($updateLog);
        $this->assertEquals($oldName, $updateLog->old_values['name']);
        $this->assertEquals('Renamed User', $updateLog->new_values['name']);
        $this->assertEquals('USD', $updateLog->new_values['base_currency']);

        // Logout
        $logoutResponse = $this->actingAs($this->user)->postJson('/api/v1/auth/logout');
        $logoutResponse->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'event' => 'user.logout',
            'auditable_type' => User::class,
            'auditable_id' => (string) $this->user->id,
        ]);
    }
}

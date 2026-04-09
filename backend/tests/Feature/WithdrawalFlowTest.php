<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reject_withdrawal_and_restore_author_balance(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->author()->create([
            'wallet_balance' => 200,
        ]);

        Sanctum::actingAs($author);

        $requestResponse = $this->postJson('/api/withdrawals', [
            'amount' => 150,
        ]);

        $requestResponse
            ->assertCreated()
            ->assertJsonPath('withdrawal.status', 'pending');

        $withdrawalId = $requestResponse->json('withdrawal.id');

        $this->assertDatabaseHas('users', [
            'id' => $author->id,
            'wallet_balance' => 50,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/withdrawals/{$withdrawalId}/reject", [
            'admin_notes' => 'Bank details were missing.',
        ])->assertOk()->assertJsonPath('withdrawal.status', 'rejected');

        $this->assertDatabaseHas('users', [
            'id' => $author->id,
            'wallet_balance' => 200,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $author->id,
            'source' => 'withdrawal_request',
            'status' => 'reversed',
            'amount' => 150,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $author->id,
            'source' => 'withdrawal_reversal',
            'status' => 'completed',
            'amount' => 150,
        ]);
    }
}

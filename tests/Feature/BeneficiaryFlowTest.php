<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BeneficiaryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_lookup_add_remove_and_owner_scoping(): void
    {
        $recipient = User::factory()->create(['name' => 'Sara', 'email' => 'sara@example.test']);
        $other = User::factory()->create();
        $this->post('/signup', ['name' => 'Ali', 'email' => 'ali@example.test', 'password' => 'SecretPassword!2026', 'password_confirmation' => 'SecretPassword!2026'])->assertRedirect('/wallet');
        $owner = User::where('email', 'ali@example.test')->firstOrFail();
        $this->assertDatabaseHas('wallets', ['user_id' => $owner->id, 'balance_paisa' => 0]);
        $this->post('/beneficiaries/lookup', ['email' => 'sara@example.test'])->assertSessionHas('lookup.name', 'Sara');
        $this->post('/beneficiaries', ['email' => 'sara@example.test'])->assertRedirect('/wallet');
        $beneficiary = DB::table('beneficiaries')->where('owner_id', $owner->id)->first();
        $this->assertSame($recipient->id, $beneficiary->recipient_id);
        $this->actingAs($other)->delete('/beneficiaries/'.$beneficiary->id)->assertNotFound();
        $this->actingAs($owner)->delete('/beneficiaries/'.$beneficiary->id)->assertRedirect('/wallet');
    }

    public function test_risky_transfer_is_held_without_moving_money(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        DB::table('wallets')->insert([['user_id' => $owner->id, 'balance_paisa' => 10000000], ['user_id' => $recipient->id, 'balance_paisa' => 0]]);
        $id = DB::table('beneficiaries')->insertGetId(['owner_id' => $owner->id, 'recipient_id' => $recipient->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->post('/transfer', ['beneficiary_id' => $id, 'amount' => '50000.01', 'request_id' => (string) Str::uuid()])->assertRedirect('/wallet');
        $this->assertDatabaseCount('transfer_approvals', 1);
        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseHas('wallets', ['user_id' => $owner->id, 'balance_paisa' => 10000000]);
    }

    public function test_receipts_are_private(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $stranger = User::factory()->create();
        $id = DB::table('transfers')->insertGetId(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'amount_paisa' => 100, 'request_id' => (string) Str::uuid(), 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($stranger)->get('/receipt/'.$id)->assertNotFound();
        $this->get('/statement')->assertDontSee('100.00');
        $this->actingAs($sender)->get('/receipt/'.$id)->assertOk();
    }
}

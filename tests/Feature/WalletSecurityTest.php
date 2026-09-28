<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Tests\TestCase;
class WalletSecurityTest extends TestCase {
    use RefreshDatabase;
    private function accounts(): array {
        $a=User::create(['name'=>'Ali','email'=>'ali@example.test','password'=>Hash::make('secret')]);
        $b=User::create(['name'=>'Sara','email'=>'sara@example.test','password'=>Hash::make('secret')]);
        DB::table('wallets')->insert([['user_id'=>$a->id,'balance_paisa'=>10000],['user_id'=>$b->id,'balance_paisa'=>1000]]);
        return [$a,$b];
    }
    public function test_guests_cannot_view_wallet_or_transfer(): void {
        $this->get('/wallet')->assertRedirect('/login');
        $this->post('/transfer',[])->assertRedirect('/login');
    }
    public function test_transfer_ignores_forged_sender_and_balance_and_updates_atomically(): void {
        [$a,$b]=$this->accounts();
        $this->actingAs($a)->post('/transfer',['recipient_id'=>$b->id,'amount'=>'20.00','request_id'=>(string)Str::uuid(),'sender_id'=>$b->id,'balance_paisa'=>999999])->assertRedirect('/wallet');
        $this->assertDatabaseHas('wallets',['user_id'=>$a->id,'balance_paisa'=>8000]);
        $this->assertDatabaseHas('wallets',['user_id'=>$b->id,'balance_paisa'=>3000]);
        $this->assertDatabaseHas('transfers',['sender_id'=>$a->id,'recipient_id'=>$b->id,'amount_paisa'=>2000]);
    }
    public function test_duplicate_request_moves_money_only_once(): void {
        [$a,$b]=$this->accounts(); $id=(string)Str::uuid();
        $form=['recipient_id'=>$b->id,'amount'=>'20.00','request_id'=>$id];
        $this->actingAs($a)->post('/transfer',$form)->assertRedirect('/wallet');
        $this->post('/transfer',$form)->assertRedirect('/wallet');
        $this->assertDatabaseCount('transfers',1);
        $this->assertDatabaseHas('wallets',['user_id'=>$a->id,'balance_paisa'=>8000]);
    }
    public function test_overdraft_and_bad_amount_leave_both_balances_intact(): void {
        [$a,$b]=$this->accounts();
        $this->actingAs($a)->post('/transfer',['recipient_id'=>$b->id,'amount'=>'200.00','request_id'=>(string)Str::uuid()])->assertSessionHasErrors('amount');
        $this->post('/transfer',['recipient_id'=>$b->id,'amount'=>'-2','request_id'=>(string)Str::uuid()])->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('wallets',['user_id'=>$a->id,'balance_paisa'=>10000]);
        $this->assertDatabaseHas('wallets',['user_id'=>$b->id,'balance_paisa'=>1000]);
        $this->assertDatabaseCount('transfers',0);
    }
    public function test_wallet_history_is_scoped_to_authenticated_user(): void {
        [$a,$b]=$this->accounts();
        $c=User::create(['name'=>'Ayesha','email'=>'ayesha@example.test','password'=>Hash::make('secret')]);
        DB::table('wallets')->insert(['user_id'=>$c->id,'balance_paisa'=>1000]);
        DB::table('transfers')->insert(['sender_id'=>$b->id,'recipient_id'=>$c->id,'amount_paisa'=>100,'request_id'=>(string)Str::uuid(),'status'=>'completed','created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($a)->get('/wallet?wallet_id='.$b->id)->assertOk()->assertDontSee('completed')->assertSee('100.00');
    }
}

<?php
namespace App\Http\Controllers;

use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller {
    private function audit(?int $userId, string $event, Request $request, array $details = []): void {
        DB::table('audit_events')->insert(['user_id'=>$userId,'event'=>$event,'ip_address'=>$request->ip(), 'details'=>json_encode($details), 'created_at'=>now(),'updated_at'=>now()]);
    }
    public function loginForm() { return view('login'); }
    public function login(Request $request) {
        $data = $request->validate(['email'=>'required|email','password'=>'required|string']);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) throw ValidationException::withMessages(['email'=>'Too many attempts. Try again later.']);
        if (!Auth::attempt($data)) {
            RateLimiter::hit($key, 60);
            $this->audit(null, 'login_failed', $request);
            throw ValidationException::withMessages(['email'=>'Invalid credentials.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->audit(Auth::id(), 'login_success', $request);
        return redirect()->route('wallet');
    }
    public function logout(Request $request) {
        $this->audit(Auth::id(), 'logout', $request);
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
    public function dashboard(Request $request) {
        $user = $request->user();
        $wallet = DB::table('wallets')->where('user_id', $user->id)->firstOrFail();
        $history = DB::table('transfers as t')->join('users as s','s.id','=','t.sender_id')->join('users as r','r.id','=','t.recipient_id')
            ->where(fn($q)=>$q->where('t.sender_id',$user->id)->orWhere('t.recipient_id',$user->id))
            ->select('t.*','s.name as sender_name','r.name as recipient_name')->orderByDesc('t.id')->limit(50)->get();
        $recipients = DB::table('users')->where('id','!=',$user->id)->orderBy('name')->get(['id','name']);
        return view('wallet', compact('user','wallet','history','recipients'));
    }
    public function transfer(Request $request, TransferService $service) {
        $data = $request->validate([
            'recipient_id'=>'required|integer|exists:users,id',
            'amount'=>'required|regex:/^([1-9][0-9]{0,8})(\.[0-9]{1,2})?$/',
            'request_id'=>'required|uuid',
        ]);
        $parts = explode('.', $data['amount']);
        $amount = ((int)$parts[0] * 100) + (int)str_pad($parts[1] ?? '', 2, '0');
        try {
            $transfer = $service->send($request->user()->id, (int)$data['recipient_id'], $amount, $data['request_id']);
            $this->audit($request->user()->id, 'transfer_completed', $request, ['transfer_id'=>$transfer->id]);
            return redirect()->route('wallet')->with('success', 'Transfer completed. Reference #'.$transfer->id);
        } catch (ValidationException $e) {
            $this->audit($request->user()->id, 'transfer_rejected', $request);
            throw $e;
        }
    }
}

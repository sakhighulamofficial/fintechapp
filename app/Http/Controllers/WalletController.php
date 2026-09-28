<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    private function audit(?int $userId, string $event, Request $request, array $details = []): void
    {
        DB::table('audit_events')->insert(['user_id' => $userId, 'event' => $event, 'ip_address' => $request->ip(), 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function signupForm()
    {
        return view('signup');
    }

    public function signup(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|confirmed']);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
            DB::table('wallets')->insert(['user_id' => $user->id, 'balance_paisa' => 0, 'created_at' => now(), 'updated_at' => now()]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $this->audit($user->id, 'signup', $request);

        return redirect()->route('wallet');
    }

    public function loginForm()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again later.']);
        }
        if (! Auth::attempt($data)) {
            RateLimiter::hit($key, 60);
            $this->audit(null, 'login_failed', $request);
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->audit(Auth::id(), 'login_success', $request);

        return redirect()->route('wallet');
    }

    public function logout(Request $request)
    {
        $this->audit(Auth::id(), 'logout', $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $wallet = DB::table('wallets')->where('user_id', $user->id)->firstOrFail();
        $history = DB::table('transfers as t')->join('users as s', 's.id', '=', 't.sender_id')->join('users as r', 'r.id', '=', 't.recipient_id')
            ->where(fn ($q) => $q->where('t.sender_id', $user->id)->orWhere('t.recipient_id', $user->id))
            ->select('t.*', 's.name as sender_name', 'r.name as recipient_name')->orderByDesc('t.id')->limit(50)->get();
        $recipients = DB::table('beneficiaries as b')->join('users as u', 'u.id', '=', 'b.recipient_id')->where('b.owner_id', $user->id)->orderBy('u.name')->get(['b.id', 'b.created_at', 'u.name', 'u.email']);

        return view('wallet', compact('user', 'wallet', 'history', 'recipients'));
    }

    public function transfer(Request $request, TransferService $service)
    {
        $data = $request->validate([
            'beneficiary_id' => 'required|integer',
            'amount' => 'required|regex:/^([1-9][0-9]{0,8})(\.[0-9]{1,2})?$/',
            'request_id' => 'required|uuid',
        ]);
        $parts = explode('.', $data['amount']);
        $amount = ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
        try {
            $beneficiary = DB::table('beneficiaries')->where('id', $data['beneficiary_id'])->where('owner_id', $request->user()->id)->first();
            if (! $beneficiary) {
                throw ValidationException::withMessages(['beneficiary_id' => 'Select one of your beneficiaries.']);
            }
            $pending = DB::table('transfer_approvals')->where('request_id', $data['request_id'])->first();
            if ($pending) {
                if ($pending->sender_id !== $request->user()->id || $pending->beneficiary_id !== $beneficiary->id || $pending->amount_paisa !== $amount) {
                    throw ValidationException::withMessages(['request_id' => 'This request ID is already in use.']);
                }

                return redirect()->route('wallet')->with('success', 'Transfer remains pending approval. No funds moved.');
            }
            $prior = DB::table('transfers')->where('request_id', $data['request_id'])->first();
            if (! $prior && ($amount > 5000000 || Carbon::parse($beneficiary->created_at)->greaterThan(now()->subDay()) || ! $request->session()->get('trusted_device', false))) {
                DB::table('transfer_approvals')->insertOrIgnore(['sender_id' => $request->user()->id, 'beneficiary_id' => $beneficiary->id, 'amount_paisa' => $amount, 'request_id' => $data['request_id'], 'status' => 'pending', 'reason' => 'High-value, new beneficiary, or untrusted device; FIDO2 and risk approval required.', 'created_at' => now(), 'updated_at' => now()]);
                $this->audit($request->user()->id, 'transfer_pending', $request, ['request_id' => $data['request_id']]);

                return redirect()->route('wallet')->with('success', 'Transfer held for FIDO2 and risk approval. No funds moved.');
            }
            $transfer = $service->send($request->user()->id, (int) $beneficiary->recipient_id, $amount, $data['request_id']);
            $this->audit($request->user()->id, 'transfer_completed', $request, ['transfer_id' => $transfer->id]);

            return redirect()->route('wallet')->with('success', 'Transfer completed. Reference #'.$transfer->id);
        } catch (ValidationException $e) {
            $this->audit($request->user()->id, 'transfer_rejected', $request);
            throw $e;
        }
    }
}

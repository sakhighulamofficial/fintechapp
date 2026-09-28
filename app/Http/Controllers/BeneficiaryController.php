<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BeneficiaryController extends Controller
{
    public function lookup(Request $request): mixed
    {
        $email = $request->validate(['email' => 'required|email|max:255'])['email'];
        $user = DB::table('users')->where('email', $email)->where('id', '!=', $request->user()->id)->first(['id', 'name']);
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No eligible account found.']);
        }

        return back()->with('lookup', ['name' => $user->name, 'email' => $email]);
    }

    public function store(Request $request): mixed
    {
        $email = $request->validate(['email' => 'required|email|max:255'])['email'];
        $user = DB::table('users')->where('email', $email)->where('id', '!=', $request->user()->id)->first(['id']);
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No eligible account found.']);
        }
        DB::table('beneficiaries')->insertOrIgnore(['owner_id' => $request->user()->id, 'recipient_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('audit_events')->insert(['user_id' => $request->user()->id, 'event' => 'beneficiary_added', 'ip_address' => $request->ip(), 'details' => json_encode(['recipient_id' => $user->id]), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('wallet')->with('success', 'Beneficiary added.');
    }

    public function destroy(Request $request, int $beneficiary): mixed
    {
        $deleted = DB::table('beneficiaries')->where('id', $beneficiary)->where('owner_id', $request->user()->id)->delete();
        abort_unless($deleted, 404);
        DB::table('audit_events')->insert(['user_id' => $request->user()->id, 'event' => 'beneficiary_removed', 'ip_address' => $request->ip(), 'details' => json_encode(['beneficiary_id' => $beneficiary]), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('wallet')->with('success', 'Beneficiary removed.');
    }
}

@extends('layout')
@section('content')
<header><div><h1>Shield Wallet</h1><small>Signed in as {{ $user->name }}</small></div><form method="post" action="/logout">@csrf<button class="secondary">Log out</button></form></header>
@if(session('success'))<div class="card success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="card error">{{ $errors->first() }}</div>@endif
<div class="grid"><section class="card"><h2>Available balance</h2><div class="balance">Rs. {{ number_format($wallet->balance_paisa / 100, 2) }}</div><p><small>Your balance is read from the server.</small></p></section>
<section class="card"><h2>Send money</h2><form method="post" action="/transfer">@csrf<input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><label>Recipient</label><select name="recipient_id" required><option value="">Select customer</option>@foreach($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }}</option>@endforeach</select><label>Amount (PKR)</label><input name="amount" inputmode="decimal" placeholder="2000.00" required><p><button>Confirm transfer</button></p></form></section></div>
<section class="card"><h2>Transaction history</h2><table><thead><tr><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($history as $t)<tr><td>{{ $t->created_at }}</td><td>{{ $t->sender_name }}</td><td>{{ $t->recipient_name }}</td><td>Rs. {{ number_format($t->amount_paisa / 100, 2) }}</td><td>{{ $t->status }}</td></tr>@empty<tr><td colspan="5">No transfers yet.</td></tr>@endforelse</tbody></table></section>
@endsection

@extends('layout')
@section('content')
<h1>Statement</h1><p><a href="{{ route('wallet') }}">Back to wallet</a></p>
<section class="card"><table><thead><tr><th>Date</th><th>From</th><th>To</th><th>Amount</th><th>Receipt</th></tr></thead><tbody>@forelse($transactions as $t)<tr><td>{{ $t->created_at }}</td><td>{{ $t->sender_name }}</td><td>{{ $t->recipient_name }}</td><td>Rs. {{ number_format($t->amount_paisa / 100, 2) }}</td><td><a href="{{ route('receipt', $t->id) }}">#{{ $t->id }}</a></td></tr>@empty<tr><td colspan="5">No transactions.</td></tr>@endforelse</tbody></table>{{ $transactions->links() }}</section>
@endsection

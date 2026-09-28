@extends('layout')
@section('content')
<h1>Transfer receipt #{{ $record->id }}</h1><p><a href="{{ route('statement') }}">Back to statement</a></p><section class="card"><p>Date: {{ $record->created_at }}</p><p>From: {{ $record->sender_name }}</p><p>To: {{ $record->recipient_name }}</p><p>Amount: Rs. {{ number_format($record->amount_paisa / 100, 2) }}</p><p>Status: {{ $record->status }}</p><p>Reference: {{ $record->request_id }}</p></section>
@endsection

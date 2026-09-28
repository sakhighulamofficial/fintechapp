@extends('layout')
@section('content')
<div class="card" style="max-width:440px;margin:9vh auto"><h1>Shield Wallet</h1><p>Secure systems design demonstration</p>
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
<form method="post" action="/login">@csrf<label>Email</label><input name="email" type="email" required autocomplete="username" value="{{ old('email') }}"><label>Password</label><input name="password" type="password" required autocomplete="current-password"><p><button>Sign in</button></p></form>
<small>Simulated funds. Demo accounts are documented in README.</small></div>
@endsection

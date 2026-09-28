@extends('layout')
@section('content')
<div class="card" style="max-width:440px;margin:9vh auto"><h1>Create account</h1>
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
<form method="post" action="{{ route('signup') }}">@csrf<label>Name</label><input name="name" required value="{{ old('name') }}"><label>Email</label><input name="email" type="email" required value="{{ old('email') }}"><label>Password (at least 12 characters)</label><input name="password" type="password" required minlength="12"><label>Confirm password</label><input name="password_confirmation" type="password" required><p><button>Create account</button></p></form><a href="{{ route('login') }}">Sign in</a></div>
@endsection

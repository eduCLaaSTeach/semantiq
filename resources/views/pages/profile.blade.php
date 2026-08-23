@extends('layouts.shell')

@section('title', 'Profile · '.config('app.name'))
@section('page-title', 'Profile')

@section('content')
    <div class="card" style="padding: var(--space-4); display:flex; flex-direction:column; gap:var(--space-3); max-width:520px">
        <div class="field">
            <span class="field-label">Name</span>
            <span>{{ auth()->user()->name }}</span>
        </div>
        <div class="field">
            <span class="field-label">Email</span>
            <span>{{ auth()->user()->email }}</span>
        </div>
        <div class="field">
            <span class="field-label">Role</span>
            <span><span class="badge badge-info">{{ auth()->user()->role->label() }}</span></span>
        </div>
        <div class="field">
            <span class="field-label">Sign-in method</span>
            <span>{{ auth()->user()->isFederated() ? 'Microsoft Entra ID' : 'Email and password' }}</span>
        </div>
    </div>
@endsection

@extends('layouts.shell')

@section('title', 'Dashboard · '.config('app.name'))
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Your working area.')

@section('content')
    <div class="card" style="padding: var(--space-4); display:flex; flex-direction:column; gap:var(--space-3)">
        <h3>Signed in as {{ auth()->user()->name }}</h3>
        <p style="color: var(--text-muted)">
            Role: <span class="badge badge-info">{{ auth()->user()->role->label() }}</span>
        </p>
        <p style="color: var(--text-muted)">
            The shell is in place. What sits inside it is decided by the navigation
            tree, which has not been confirmed for this application yet.
        </p>
    </div>
@endsection

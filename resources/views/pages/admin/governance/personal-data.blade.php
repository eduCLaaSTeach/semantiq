{{--
    ADM-014 Personal / Sensitive Data.

    The register of what kinds of personal data this application holds about
    people. PDPA-01 answers a subject access request from these categories in
    R1.4c, which is why each one names the tables it lives in rather than
    describing itself in the abstract.

    THE CATEGORIES CAME FROM A SCHEMA SCAN, not a privacy template. DEC-002
    named five tables holding personal data; a re-scan of the live schema found
    it in 19. That is why this list names `audit_events` and
    `password_reset_tokens` - places a generic template would never think to
    look.

    A standard-tier table: a row per category, most sensitive first so the
    classification is the thing the eye lands on.
--}}
@extends('layouts.shell')

@section('title', 'Personal / Sensitive Data · '.config('app.name'))
@section('page-title', 'Personal / Sensitive Data')
@section('page-subtitle', 'What kinds of personal data SemantIQ holds about people, and where each kind lives.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        @unless ($storageReady)
            <div class="alert alert-warning" role="alert">
                <svg class="icon" aria-hidden="true"><use href="#i-alert-triangle"/></svg>
                <span>
                    <strong>The personal data register has not been initialised.</strong>
                    {{ $storageBlocker }}
                </span>
            </div>
        @endunless

        <section class="card" aria-labelledby="categories-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="categories-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    Categories
                    @if ($storageReady && $categories->isNotEmpty())
                        <span class="badge">{{ $categories->count() }}</span>
                    @endif
                </h2>
            </div>

            @if (! $storageReady)
                {{-- A DIFFERENT state from "none recorded", deliberately.
                     "Nothing is here" and "we cannot see what is here" mean
                     opposite things, and showing the second as the first is
                     the defect gate 3 shipped to production. SEC-DEC-057. --}}
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-slash"/></svg>
                    <span class="empty-title">Migration required</span>
                    <span class="empty-note">
                        This screen cannot show what personal data is being recorded, because the table
                        that holds the register has not been created yet. It is not empty - it does not
                        exist. It does not mean no personal data is held: a re-scan of this application's
                        schema found personal data in 19 of its 23 tables.
                    </span>
                </div>
            @elseif ($categories->isEmpty())
                <div class="empty">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    <span class="empty-title">No categories recorded</span>
                    <span class="empty-note">
                        SemantIQ normally writes a starting set on first visit, taken from a scan of its
                        own schema. An empty register here means that did not happen, and it should be
                        investigated rather than left: with no categories, a subject access request has
                        nothing to answer from.
                    </span>
                </div>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="visually-hidden">Personal data categories, with their classification and where each lives</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-primary">Category</th>
                                <th scope="col">Classification</th>
                                <th scope="col">Where it lives</th>
                                <th scope="col">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <th scope="row" class="cell-heading">
                                        <a href="{{ route('admin.governance.personal-data.edit', $category) }}">{{ $category->name }}</a>
                                        <span class="cell-note">{{ $category->description }}</span>
                                    </th>
                                    <td>
                                        <span class="{{ $category->classification->badge() }}">
                                            {{ $category->classification->label() }}
                                        </span>
                                        @if ($category->contains_sensitive)
                                            <span class="badge badge-warning">Sensitive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($category->tables() === [])
                                            {{-- Named, not left blank. A category
                                                 claiming no table is invisible to
                                                 the R1.4c coverage test and is a
                                                 gap, not a tidy row. --}}
                                            <span class="cell-empty">No tables named</span>
                                        @else
                                            <span class="cell-reference">{{ implode(', ', $category->tables()) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($category->isActive())
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge">Retired</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="meaning-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="meaning-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-help-circle"/></svg>
                    What the classifications mean
                </h2>
            </div>
            <div class="record-list">
                @foreach ($classifications as $classification)
                    <div class="record-row">
                        <span class="record-label">{{ $classification->label() }}</span>
                        <span class="record-value">{{ $classification->description() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection

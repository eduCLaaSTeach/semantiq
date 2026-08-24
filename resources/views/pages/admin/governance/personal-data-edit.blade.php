{{--
    ADM-014, editing one personal data category.

    THE CODE IS SHOWN AND CANNOT BE CHANGED. It is the identifier the R1.4c
    collector resolves against, so renaming one would silently break the link
    between a category and the data it describes. Shown rather than hidden,
    because somebody looking at this screen to debug a coverage failure needs to
    see it.

    THERE IS NO DELETE. A category is part of the record of how data was
    treated; deleting it removes the explanation without removing the data. It
    is retired instead, which the state field below does.
--}}
@extends('layouts.shell')

@section('title', $category->name.' · '.config('app.name'))
@section('page-title', $category->name)
@section('page-subtitle', 'A category of personal data this application holds, and where it lives.')

@section('content')
    <div class="stack">
        @include('partials.form-status')

        <section class="card" aria-labelledby="edit-heading">
            <div class="panel-head card-head">
                <h2 class="panel-title" id="edit-heading">
                    <svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>
                    Category
                </h2>
                <span class="badge">{{ $category->code }}</span>
            </div>

            <form method="POST" action="{{ route('admin.governance.personal-data.update', $category) }}" class="settings-form">
                @csrf
                @method('PUT')

                <div class="settings-fields">
                    <div class="field">
                        <label class="field-label" for="name">Name<span class="field-required" aria-hidden="true">*</span></label>
                        <input class="input" type="text" id="name" name="name" required
                               value="{{ old('name', $category->name) }}"
                               @error('name') aria-invalid="true" aria-describedby="name-message" @enderror>
                        <p class="field-help">What a person would call this if you told them you held it. The code, <strong>{{ $category->code }}</strong>, is fixed and is what the system resolves against.</p>
                        <p class="field-message" id="name-message">@error('name'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="description">Description<span class="field-required" aria-hidden="true">*</span></label>
                        <textarea class="input" id="description" name="description" rows="4" required
                                  @error('description') aria-invalid="true" aria-describedby="description-message" @enderror>{{ old('description', $category->description) }}</textarea>
                        <p class="field-help">Written for the data subject, not for an engineer. This is the wording a person receives when they ask what is held about them.</p>
                        <p class="field-message" id="description-message">@error('description'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="classification">Classification<span class="field-required" aria-hidden="true">*</span></label>
                        <select class="input" id="classification" name="classification" required>
                            @foreach ($classifications as $classification)
                                <option value="{{ $classification->value }}"
                                    @selected(old('classification', $category->classification->value) === $classification->value)>
                                    {{ $classification->label() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-help">How much harm disclosure would cause.</p>
                        <p class="field-message" id="classification-message">@error('classification'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" name="contains_sensitive" value="1"
                                   @checked(old('contains_sensitive', $category->contains_sensitive))>
                            <span>Includes data most regimes treat with extra care</span>
                        </label>
                        <p class="field-help">Health, finances, biometrics and the like. A different question from classification: one is how much harm disclosure causes, the other is what kind of data it is.</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="source_tables">Where it lives</label>
                        <textarea class="input" id="source_tables" name="source_tables" rows="4"
                                  @error('source_tables') aria-invalid="true" aria-describedby="source_tables-message" @enderror>{{ old('source_tables', implode("\n", $category->tables())) }}</textarea>
                        <p class="field-help">One database table per line. This is what lets a subject access request find the data, so a table left out here is data that will not be found.</p>
                        <p class="field-message" id="source_tables-message">@error('source_tables'){{ $message }}@enderror</p>
                    </div>

                    <div class="field">
                        <label class="field-label" for="status">State<span class="field-required" aria-hidden="true">*</span></label>
                        <select class="input" id="status" name="status" required>
                            <option value="active" @selected(old('status', $category->status) === 'active')>Active</option>
                            <option value="retired" @selected(old('status', $category->status) === 'retired')>Retired</option>
                        </select>
                        <p class="field-help">Retired keeps the category and stops it being used. There is no delete: a category is part of the record of how data was treated.</p>
                        <p class="field-message" id="status-message">@error('status'){{ $message }}@enderror</p>
                    </div>
                </div>

                <div class="settings-foot">
                    <button type="submit" class="btn btn-solid btn-primary">
                        <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                        <span class="btn-label">Save category</span>
                    </button>
                    <a class="btn btn-secondary" href="{{ route('admin.governance.personal-data') }}">
                        <span class="btn-label">Cancel</span>
                    </a>
                </div>
            </form>
        </section>
    </div>
@endsection

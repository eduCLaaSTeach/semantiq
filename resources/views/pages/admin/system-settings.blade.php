{{--
    ADM-021 System Configuration - General Settings and Environment Settings.

    One template for both screens: they differ only in which catalogue category
    they render, and the fields are generated from config/platform.php rather
    than written out here. A hand-written field would be a second copy of the
    catalogue and would drift from it.

    Page-hosted form, roomy sizing, one column - the template's forms section.
    Every field is a visible label, the control, its help text and a RESERVED
    validation slot, so an appearing error cannot shift the fields below it.
    Validation is error-only: nothing turns green.

    No setting on this screen can hold a secret. SystemSettings::set() refuses a
    secret-bearing key outright, so there is no masked field here and no path to
    one.
--}}
@extends('layouts.shell')

@section('title', $title.' · '.config('app.name'))
@section('page-title', $title)
@section('page-subtitle', $subtitle)

@section('content')
    <div class="stack">

        @if (session('status'))
            <div class="alert alert-success" role="status">
                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form class="card settings-form"
              method="POST"
              action="{{ route('admin.system.settings.update', ['category' => $category]) }}"
              novalidate>
            @csrf
            @method('PUT')

            <div class="settings-fields">
                @foreach ($definitions as $key => $definition)
                    @php
                        /* Setting keys contain dots and Laravel reads a dot as
                           nesting, so the field name is slugged and mapped back
                           in UpdateSystemSettingsRequest. */
                        $field = \App\Modules\Platform\Http\Requests\UpdateSystemSettingsRequest::slug($key);
                        $errorKey = 'settings.'.$field;
                        $current = old('settings.'.$field, $values[$key]);
                        $type = $definition['type'];
                    @endphp

                    <div class="field">
                        @if ($type === \App\Modules\Platform\Enums\SettingType::Boolean)
                            {{-- A checkbox carries its own label, so it does not
                                 get a second one above it. --}}
                            <label class="checkbox" for="{{ $field }}">
                                <input type="checkbox"
                                       id="{{ $field }}"
                                       name="settings[{{ $field }}]"
                                       value="1"
                                       @checked((bool) $current)>
                                {{ $definition['label'] }}
                            </label>
                        @else
                            <label class="field-label" for="{{ $field }}">
                                {{ $definition['label'] }}
                                @if (in_array('required', (array) ($definition['rules'] ?? []), true))
                                    <span class="field-required" aria-hidden="true">*</span>
                                @endif
                            </label>

                            @if ($type === \App\Modules\Platform\Enums\SettingType::Choice)
                                <select class="input"
                                        id="{{ $field }}"
                                        name="settings[{{ $field }}]"
                                        @error($errorKey) aria-invalid="true" aria-describedby="{{ $field }}-message" @enderror>
                                    @foreach ($definition['choices'] as $value => $label)
                                        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input class="input"
                                       type="{{ $type === \App\Modules\Platform\Enums\SettingType::Integer ? 'number' : 'text' }}"
                                       id="{{ $field }}"
                                       name="settings[{{ $field }}]"
                                       value="{{ $current }}"
                                       @error($errorKey) aria-invalid="true" aria-describedby="{{ $field }}-message" @enderror>
                            @endif
                        @endif

                        <p class="field-help">{{ $definition['help'] }}</p>

                        {{-- Reserved whether or not it holds a message. --}}
                        <p class="field-message" id="{{ $field }}-message">@error($errorKey){{ $message }}@enderror</p>
                    </div>
                @endforeach
            </div>

            <div class="settings-foot">
                <button type="submit" class="btn btn-solid btn-primary" data-async>
                    <span class="btn-label">Save changes</span>
                </button>

                {{-- Every change here is audited. Saying so on the form is not
                     decoration: an administrator should know that before they
                     press the button, not discover it afterwards. --}}
                <span class="field-help">Changes are recorded in the audit trail with your name against them.</span>
            </div>

            {{-- The form-foot alert: one message about the form as a whole, for
                 a refusal that belongs to no single field. Field errors are
                 already inline and are not repeated here. --}}
            @error('settings')
                <div class="alert" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </form>

    </div>
@endsection

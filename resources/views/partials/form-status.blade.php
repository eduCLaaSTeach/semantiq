{{--
    The two messages every administration screen shows in the same place: a
    success flash from the previous request, and a form-level error belonging to
    no single field.

    Shared so that "saved" looks identical on all fourteen screens. Field errors
    are NOT shown here - they are already inline beside their input, and
    repeating them makes the reader track one problem in two places, which the
    design template's validation contract forbids.
--}}
@if (session('status'))
    <div class="alert alert-success" role="status">
        <svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg>
        <span>{{ session('status') }}</span>
    </div>
@endif

@foreach (['form', 'authority', 'roles', 'entitlements', 'review'] as $slot)
    @error($slot)
        <div class="alert" role="alert">
            <svg class="icon" aria-hidden="true"><use href="#i-alert-circle"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror
@endforeach

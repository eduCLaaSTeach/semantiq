@props(['name', 'label' => null])

{{--
    One icon from the registry.

    Sized at 1em, so a caller changes its size with font-size rather than by
    scaling the SVG. Decorative by default; pass a label to give an icon that
    carries meaning on its own an accessible name.
--}}
<svg
    {{ $attributes->merge(['class' => 'icon']) }}
    viewBox="0 0 24 24"
    width="1em"
    height="1em"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if ($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
><use href="#{{ $name }}"/></svg>

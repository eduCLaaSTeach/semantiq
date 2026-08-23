{{--
    The breadcrumb: the full path from the cluster down.

    Rendered only once a page sits inside a group, since a trail with one entry
    tells nobody anything. The trail IS the way back - there is never a separate
    back link anywhere in the application.
--}}
@php($trail = app(App\Support\Navigation::class)->trailFor(auth()->user(), request()->route()?->getName() ?? ''))

@if (count($trail) > 2)
    <nav class="breadcrumb" aria-label="Breadcrumb">
        @foreach ($trail as $index => $crumb)
            @if ($index > 0)
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"/></svg>
            @endif

            @if ($loop->last)
                <span aria-current="page">{{ $crumb['label'] }}</span>
            @elseif ($crumb['route'])
                <a href="{{ route($crumb['route']) }}">{{ $crumb['label'] }}</a>
            @else
                <span>{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif

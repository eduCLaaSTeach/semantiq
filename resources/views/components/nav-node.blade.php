@props(['node', 'depth' => 0])

{{--
    One node in the rail: an accordion group, a link, or an unbuilt destination.

    Recursive, so it renders a group's children with the same rules one level
    deeper. Groups nest at most three levels, which the navigation config
    enforces by shape rather than this component by check.
--}}

@if ($node['is_group'])
    <div class="nav-group" data-nav-group data-group-key="{{ Str::slug($node['group']) }}">
        <button
            class="nav-item nav-group-toggle"
            type="button"
            data-nav-toggle
            data-nav-label="{{ $node['group'] }}"
            aria-expanded="{{ $node['in_active_trail'] ? 'true' : 'false' }}"
            @class(['is-trail' => $node['in_active_trail']])
            style="--nav-depth: {{ $depth }}"
        >
            <x-icon :name="$node['icon']" class="nav-icon"/>
            <span class="nav-label">{{ $node['group'] }}</span>
            <x-icon name="i-chevron-down" class="nav-chevron"/>
        </button>

        <div class="nav-children" data-nav-children @if (! $node['in_active_trail']) hidden @endif>
            @foreach ($node['children'] as $child)
                <x-nav-node :node="$child" :depth="$depth + 1"/>
            @endforeach
        </div>
    </div>
@elseif ($node['is_built'])
    <a
        class="nav-item nav-leaf @if ($node['is_active']) is-active @endif"
        href="{{ route($node['route']) }}"
        data-nav-leaf
        data-nav-label="{{ $node['label'] }}"
        @if ($node['is_active']) aria-current="page" @endif
        style="--nav-depth: {{ $depth }}"
    >
        <x-icon :name="$node['icon']" class="nav-icon"/>
        <span class="nav-label">{{ $node['label'] }}</span>
    </a>
@else
    {{--
        A destination that is not built yet. It stays visible and disabled with
        a "Soon" pill rather than disappearing, so the shape of the application
        is legible before every screen exists. This is not the same as a node
        the person's role cannot reach, which is absent entirely.
    --}}
    <div
        class="nav-item nav-leaf is-soon"
        data-nav-leaf
        data-nav-label="{{ $node['label'] }}"
        aria-disabled="true"
        style="--nav-depth: {{ $depth }}"
    >
        <x-icon :name="$node['icon']" class="nav-icon"/>
        <span class="nav-label">{{ $node['label'] }}</span>
        <span class="soon-pill">Soon</span>
    </div>
@endif

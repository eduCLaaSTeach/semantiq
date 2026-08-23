{{--
    One navigation node: a leaf, an unbuilt leaf, or a group.

    Nothing the viewer cannot reach reaches this partial - Navigation::for()
    removed it server-side, so it is absent rather than dimmed. An unbuilt
    destination is the opposite case and stays visible with a "Soon" pill.

    `route_parameters` exists because two nodes may share one route name and
    differ only by a segment - General Settings and Environment Settings are
    both `admin.system.settings`. Both the link and the active check therefore
    compare the parameters as well as the name, or one of the pair would
    permanently look active while the other was open.
--}}
@php($children = $node['children'] ?? null)
@php($navigation = app(App\Support\Navigation::class))
@php($currentRoute = request()->route()?->getName() ?? '')
@php($currentParameters = request()->route()?->parameters() ?? [])

@if (is_array($children))
    @php($holdsActive = collect($children)->contains(fn ($c) => $navigation->matches($c, $currentRoute, $currentParameters)))
    <div class="nav-group" data-nav-group>
        <button type="button"
                class="nav-item"
                data-nav-group-toggle
                data-label="{{ $node['label'] }}"
                aria-expanded="{{ $holdsActive ? 'true' : 'false' }}">
            <svg class="icon" aria-hidden="true"><use href="#{{ $node['icon'] }}"/></svg>
            <span class="nav-label">{{ $node['label'] }}</span>
            <svg class="icon nav-chevron" aria-hidden="true"><use href="#i-chevron-down"/></svg>
        </button>
        <div class="nav-group-body" @unless($holdsActive) hidden @endunless>
            @foreach ($children as $child)
                @include('shell.nav-node', ['node' => $child])
            @endforeach
        </div>
    </div>
@elseif (empty($node['route']))
    {{-- Built later. Visible and disabled, so the shape of the product reads,
         with no link at all rather than a link that goes nowhere. --}}
    <span class="nav-item" aria-disabled="true" data-label="{{ $node['label'] }}">
        <svg class="icon" aria-hidden="true"><use href="#{{ $node['icon'] }}"/></svg>
        <span class="nav-label">{{ $node['label'] }}</span>
        <span class="nav-soon">Soon</span>
    </span>
@else
    @php($active = $navigation->matches($node, $currentRoute, $currentParameters))
    <a href="{{ route($node['route'], $node['route_parameters'] ?? []) }}"
       class="nav-item @if($active) is-active @endif"
       data-label="{{ $node['label'] }}"
       @if($active) aria-current="page" @endif>
        <svg class="icon" aria-hidden="true"><use href="#{{ $node['icon'] }}"/></svg>
        <span class="nav-label">{{ $node['label'] }}</span>
    </a>
@endif

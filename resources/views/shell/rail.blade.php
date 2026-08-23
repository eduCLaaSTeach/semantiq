{{--
    The nav rail.

    The brand block is the home link and the shell's ONLY collapse control. The
    brand block and the filter are pinned; only the nav list scrolls, so neither
    the logo nor the filter ever scrolls out of view.
--}}
@php($navigation = app(App\Support\Navigation::class))
@php($clusters = $navigation->for(auth()->user()))

<div class="rail-container">
    <button type="button"
            class="rail-head"
            data-rail-toggle
            aria-label="Home"
            aria-expanded="true"
            data-expanded-label="Collapse sidebar"
            data-collapsed-label="Expand sidebar">
        {{-- Both marks are absolute overlays that cross-fade at fixed positions
             and reserve no layout box, so the mark never slides as the rail
             animates. Kept as supplied, at natural size, on the chrome. --}}
        <span class="rail-mark rail-mark-wide">
            <img src="{{ asset(config('brand.assets_path').'/logo-full-light.png') }}"
                 alt="CLaaS2SaaS"
                 data-theme-image
                 data-light="{{ asset(config('brand.assets_path').'/logo-full-light.png') }}"
                 data-dark="{{ asset(config('brand.assets_path').'/logo-full-dark.png') }}">
        </span>
        <span class="rail-mark rail-mark-short">
            <img src="{{ asset(config('brand.assets_path').'/logo-short-light.png') }}"
                 alt=""
                 data-theme-image
                 data-light="{{ asset(config('brand.assets_path').'/logo-short-light.png') }}"
                 data-dark="{{ asset(config('brand.assets_path').'/logo-short-dark.png') }}">
        </span>
        {{-- One glyph in both directions. Never swapped for a chevron: that is
             the accordion group's control, not this one. --}}
        <span class="rail-toggle" aria-hidden="true">
            <svg class="icon"><use href="#i-panel"/></svg>
        </span>
    </button>

    <div class="rail-filter">
        <label class="visually-hidden" for="nav-filter">Filter navigation</label>
        <input type="search" id="nav-filter" placeholder="Filter..." autocomplete="off" data-nav-filter>
    </div>

    <nav class="rail-nav" aria-label="Main">
        @foreach ($clusters as $cluster => $nodes)
            <div class="nav-cluster" data-cluster>
                <div class="nav-cluster-label">{{ $cluster }}</div>
                @foreach ($nodes as $node)
                    @include('shell.nav-node', ['node' => $node])
                @endforeach
            </div>
        @endforeach
    </nav>
</div>

{{--
    The health check list. Shared by Platform Overview (ADM-001) and Diagnostics
    (ADM-024), because both answer the same question and two copies would drift.

    Simple table tier, section 8 of the design template: a short read-only list
    with a header, rows and hover, and none of the sorting, filtering or
    pagination the Standard tier requires.

    Every `detail` string here was produced by HealthProbe, which redacts. No
    value from configuration, no host name and no exception message reaches this
    partial unscrubbed - see the class docblock on App\Modules\Platform\Support\HealthProbe.

    @param list<\App\Modules\Platform\Support\HealthCheck> $checks
--}}
<div class="table-scroll">
    <table class="data-table">
        <caption class="visually-hidden">Platform health checks and their current state</caption>
        <thead>
            <tr>
                <th scope="col" class="col-label">Check</th>
                <th scope="col">State</th>
                <th scope="col">Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($checks as $check)
                <tr>
                    <th scope="row" class="cell-heading">{{ $check->name }}</th>
                    <td><span class="{{ $check->state->badgeClass() }}">{{ $check->state->label() }}</span></td>
                    <td>{{ $check->detail }}</td>
                </tr>
            @empty
                {{-- The template forbids a bare blank box. No checks at all is a
                     fault worth naming, not an empty list. --}}
                <tr>
                    <td colspan="3" class="cell-empty">No checks ran. This is itself a fault - the health probe returned nothing.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

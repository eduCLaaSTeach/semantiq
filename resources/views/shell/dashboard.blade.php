<x-shell title="Dashboard" subtitle="Where SemantIQ stands right now." :trail="$trail">

    <div class="card-grid">
        <section class="card card-pad">
            <h3>You are signed in</h3>
            <p class="text-muted card-body-text">
                Identity is federated to Microsoft Entra ID. SemantIQ holds no password for
                your account, and your role comes from this application rather than from
                the directory.
            </p>
            <dl class="detail-list">
                <div class="detail-row">
                    <dt class="text-small text-muted">Name</dt>
                    <dd>{{ auth()->user()->name }}</dd>
                </div>
                <div class="detail-row">
                    <dt class="text-small text-muted">Address</dt>
                    <dd>{{ auth()->user()->email }}</dd>
                </div>
                <div class="detail-row">
                    <dt class="text-small text-muted">Role</dt>
                    <dd><span class="badge badge-info">{{ auth()->user()->role->label() }}</span></dd>
                </div>
                <div class="detail-row">
                    <dt class="text-small text-muted">Last signed in</dt>
                    <dd>
                        {{ auth()->user()->last_signed_in_at?->format('j M Y, H:i') ?? 'Not recorded' }}
                        <span class="text-muted">UTC</span>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="card card-pad">
            <h3>What is built</h3>
            <p class="text-muted card-body-text">
                The sidebar shows the whole application, not only the parts that exist. A
                destination marked <span class="soon-pill">Soon</span> is planned and not
                built yet; it stays visible so the shape of the product is legible. Anything
                your role cannot reach is absent entirely rather than dimmed.
            </p>
            <ul class="tick-list">
                <li><x-icon name="i-check" class="tick"/> Design system, both themes</li>
                <li><x-icon name="i-check" class="tick"/> Sign in with Microsoft Entra ID</li>
                <li><x-icon name="i-check" class="tick"/> Roles and access gating</li>
                <li><x-icon name="i-check" class="tick"/> The shell you are looking at</li>
            </ul>
        </section>
    </div>

</x-shell>

"use strict"; var $ = function (s, r) { return (r || document).querySelector(s); }; var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }; var ic = function (n) { return '<svg class="ic" aria-hidden="true"><use href="#i-' + n + '"></use></svg>'; }; function hideIcons(root) { var l = (root || document).querySelectorAll('.ic'); for (var i = 0; i < l.length; i++) l[i].setAttribute('aria-hidden', 'true'); } var store = { get: function (k, d) { try { var v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { } } }; var NAV = [{ key: 'workspace', label: 'Workspace', nodes: [{ type: 'leaf', label: 'Dashboard', route: 'dashboard', icon: 'grid', access: 'workspace' }, { type: 'group', label: 'Tickets', icon: 'ticket', access: 'workspace', children: [{ type: 'leaf', label: 'All Tickets', route: 'tickets', icon: 'list', access: 'workspace' }, { type: 'leaf', label: 'New Ticket', route: 'ticket-form', icon: 'plus', access: 'workspace' }, { type: 'leaf', label: 'Recycle Bin', route: 'recycle', icon: 'trash', access: 'workspace' }] }, { type: 'leaf', label: 'Assets', route: 'assets', icon: 'server', access: 'workspace' }, { type: 'leaf', label: 'Knowledge Base', route: 'kb', icon: 'book', access: 'workspace', soon: true }] }, { key: 'compliance', label: 'Compliance', nodes: [{ type: 'leaf', label: 'SLA Monitor', route: 'sla', icon: 'clock', access: 'compliance' }, { type: 'leaf', label: 'Changes', route: 'changes', icon: 'refresh', access: 'compliance', soon: true }, { type: 'leaf', label: 'Problems', route: 'problems', icon: 'warning', access: 'compliance', soon: true }] }, { key: 'app-admin', label: 'Application Administration', nodes: [{ type: 'group', label: 'Access Control', icon: 'shield', access: 'app-admin', children: [{ type: 'leaf', label: 'Users', route: 'users', icon: 'users', access: 'app-admin' }, { type: 'leaf', label: 'Teams', route: 'teams', icon: 'users', access: 'app-admin', soon: true }] }, { type: 'leaf', label: 'Application Settings', route: 'app-settings', icon: 'sliders', access: 'app-admin', soon: true }] }, { key: 'sys-admin', label: 'System Administration', nodes: [{ type: 'group', label: 'Integrations', icon: 'plug', access: 'sys-admin', children: [{ type: 'leaf', label: 'Connections', route: 'integrations', icon: 'network', access: 'sys-admin' }, { type: 'leaf', label: 'AI Providers', route: 'models', icon: 'cpu', access: 'sys-admin' }, { type: 'leaf', label: 'Email Channel', route: 'ch-email', icon: 'mail', access: 'sys-admin' }, { type: 'leaf', label: 'Chat Channel', route: 'ch-chat', icon: 'chat', access: 'sys-admin', soon: true }] }, { type: 'group', label: 'Platform', icon: 'cog', access: 'sys-admin', children: [{ type: 'group', label: 'Configuration', icon: 'sliders', access: 'sys-admin', children: [{ type: 'group', label: 'Security', icon: 'lock', access: 'sys-admin', children: [{ type: 'leaf', label: 'Sign-in Methods', route: 'signin', icon: 'key', access: 'sys-admin' }, { type: 'leaf', label: 'Sessions', route: 'sessions', icon: 'monitor', access: 'sys-admin', soon: true }] }] }] }] }]; var ACCESS = {
    workspace: { roles: ['admin', 'collaborator', 'contributor', 'readonly'] },
    compliance: { roles: ['admin', 'collaborator'] },
    'app-admin': { roles: [], admin_only: true },
    'sys-admin': { roles: [], sysadmin_only: true }
}; var currentRole = store.get('c2s-role', 'sysadmin'); function canAccess(role, policy) { if (!policy) return true;
    /* Cumulative tiers: system admin is the top tier and reaches every cluster. */
    if (role === 'sysadmin') return true;
    if (policy.sysadmin_only) return false;
    if (role === 'admin') return true;
    if (policy.admin_only) return false;
    return (policy.roles || []).indexOf(role) !== -1; } function canAccessKey(role, key) { return canAccess(role, ACCESS[key] || {}); } var openGroups = store.get('c2s-groups', {}); function nodeGKey(sectionKey, path) { return sectionKey + '/' + path.join('/'); } function renderNodes(sectionKey, nodes, depth, trail) { var html = ''; for (var i = 0; i < nodes.length; i++) { var n = nodes[i]; if (n.type === 'group') { if (n.access && !canAccessKey(currentRole, n.access)) continue; var kids = renderNodes(sectionKey, n.children, depth + 1, trail.concat(n.label)); if (!kids) continue; var gkey = nodeGKey(sectionKey, trail.concat(n.label)); var open = !!openGroups[gkey]; html += '<div class="nav-group' + (open ? ' open' : '') + '" data-gkey="' + gkey + '" data-label="' + n.label.toLowerCase() + '">' + '<button type="button" class="group-header" aria-expanded="' + open + '">' + ic(n.icon) + '<span class="nav-label">' + n.label + '</span>' + '<span class="group-flyhint" aria-hidden="true">' + ic('chevron-left') + '</span>' + '<svg class="ic group-chevron" aria-hidden="true"><use href="#i-chevron-right"></use></svg>' + '</button>' + '<div class="group-body">' + kids + '</div>' + '</div>'; } else { if (!canAccessKey(currentRole, n.access)) continue; if (n.soon) { html += '<div class="nav-item is-soon" aria-disabled="true" data-label="' + n.label.toLowerCase() + '">' + ic(n.icon) + '<span class="nav-label">' + n.label + '</span><span class="soon-pill">Soon</span></div>'; } else { html += '<a class="nav-item" href="#' + n.route + '" data-route="' + n.route + '" data-label="' + n.label.toLowerCase() + '">' + ic(n.icon) + '<span class="nav-label">' + n.label + '</span></a>'; } } } return html; } function renderRail() { var html = ''; for (var s = 0; s < NAV.length; s++) { var sec = NAV[s]; var nodesHtml = renderNodes(sec.key, sec.nodes, 0, []); if (!nodesHtml) continue; html += '<div class="nav-section"><div class="nav-section-label">' + sec.label + '</div>' + nodesHtml + '</div>'; } $('#navBody').innerHTML = html; wireGroups(); applyNavFilter($('#navFilter').value); setActiveNav(currentPrimary()); } function wireGroups() { $$('.nav-group > .group-header').forEach(function (h) { h.addEventListener('click', function () { if ($('#rail').classList.contains('collapsed')) return; var g = h.parentNode, gkey = g.getAttribute('data-gkey'); var open = g.classList.toggle('open'); h.setAttribute('aria-expanded', open); openGroups[gkey] = open; store.set('c2s-groups', openGroups); }); }); } var rail = $('#rail'); if (store.get('c2s-collapsed', false)) rail.classList.add('collapsed'); function setCollapsed(v) { rail.classList.toggle('collapsed', v); store.set('c2s-collapsed', v); $('#railCollapse').setAttribute('aria-label', v ? 'Expand navigation' : 'Collapse navigation'); $('#railBrand').setAttribute('aria-label', v ? 'Expand navigation' : 'Application Name here - Home'); } $('#railCollapse').addEventListener('click', function (e) { e.stopPropagation(); setCollapsed(true); }); $('#railBrand').addEventListener('click', function () { if (window.matchMedia('(max-width: 860px)').matches) { navigate('dashboard'); closeMobileNav(); return; } if (rail.classList.contains('collapsed')) { setCollapsed(false); } else { navigate('dashboard'); } }); $('#railBrand').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); } }); $('#navFilter').addEventListener('input', function () { applyNavFilter(this.value); }); function applyNavFilter(qraw) { var q = (qraw || '').trim().toLowerCase(); $$('#navBody .nav-item, #navBody .nav-group, #navBody .nav-section').forEach(function (el) { el.style.display = ''; }); if (!q) { $$('#navBody .nav-group').forEach(function (g) { var open = !!openGroups[g.getAttribute('data-gkey')]; g.classList.toggle('open', open); var h = g.querySelector(':scope > .group-header'); if (h) h.setAttribute('aria-expanded', open); }); return; } $$('#navBody .nav-section').forEach(function (sec) { var anySec = false; $$(':scope > .nav-item, :scope > .nav-group', sec).forEach(function (node) { anySec = filterNode(node, q) || anySec; }); sec.style.display = anySec ? '' : 'none'; }); } function filterNode(node, q) { if (node.classList.contains('nav-item')) { var m = node.getAttribute('data-label').indexOf(q) !== -1; node.style.display = m ? '' : 'none'; return m; } var groupMatch = node.getAttribute('data-label').indexOf(q) !== -1; var anyChild = false; $$(':scope > .group-body > .nav-item, :scope > .group-body > .nav-group', node).forEach(function (c) { var cm = groupMatch ? showAll(c) : filterNode(c, q); anyChild = cm || anyChild; }); var visible = groupMatch || anyChild; node.style.display = visible ? '' : 'none'; if (visible) node.classList.add('open'); return visible; } function showAll(node) { node.style.display = ''; if (node.classList.contains('nav-group')) { node.classList.add('open'); $$(':scope > .group-body > *', node).forEach(showAll); } return true; } function setActiveNav(primary) { var activeRoute = primary === 'ticket' ? 'tickets' : (primary === 'model-edit' ? 'models' : primary); $$('#navBody .nav-item').forEach(function (a) { a.classList.remove('active'); a.removeAttribute('aria-current'); }); $$('#navBody .group-header').forEach(function (h) { h.classList.remove('trail'); }); var item = $('#navBody .nav-item[data-route="' + activeRoute + '"]'); if (!item) return; item.classList.add('active'); item.setAttribute('aria-current', 'page'); var g = item.closest('.nav-group'); while (g) { g.classList.add('open'); var h = g.querySelector(':scope > .group-header'); if (h) { h.setAttribute('aria-expanded', 'true'); h.classList.add('trail'); } openGroups[g.getAttribute('data-gkey')] = true; g = g.parentNode.closest('.nav-group'); } store.set('c2s-groups', openGroups); } var flyout = $('#navFlyout'), tooltip = $('#navTooltip'), flyTimer = null, flyOwner = null; function collapsedActive() { return rail.classList.contains('collapsed') && !window.matchMedia('(max-width: 860px)').matches; } function fIcon(el) { var u = el.querySelector('.ic use'); return u ? '<svg class="ic"><use href="' + u.getAttribute('href') + '"></use></svg>' : ''; } function flyoutChildrenHTML(groupEl, depth) { var html = ''; var pl = ' style="padding-left:' + (10 + depth * 12) + 'px"'; $$(':scope > .group-body > *', groupEl).forEach(function (c) { if (c.classList.contains('nav-group')) { var h = c.querySelector(':scope > .group-header'); html += '<div class="nav-flyout-title"' + pl + '>' + fIcon(h) + '<span>' + h.querySelector('.nav-label').textContent + '</span></div>'; html += flyoutChildrenHTML(c, depth + 1); } else if (c.classList.contains('is-soon')) { html += '<div class="flyout-item is-soon"' + pl + '>' + fIcon(c) + '<span>' + c.querySelector('.nav-label').textContent + '</span><span class="soon-pill" style="opacity:1">Soon</span></div>'; } else { var route = c.getAttribute('data-route'); html += '<button type="button" class="flyout-item" data-route="' + route + '"' + pl + '>' + fIcon(c) + '<span>' + c.querySelector('.nav-label').textContent + '</span></button>'; } }); return html; } function openFlyout(groupEl, headerEl) { clearTimeout(flyTimer); flyOwner = groupEl; var hn = groupEl.querySelector(':scope > .group-header'); var title = hn.querySelector('.nav-label').textContent; flyout.innerHTML = '<div class="nav-flyout-title nav-flyout-head">' + fIcon(hn) + '<span>' + title + '</span></div>' + flyoutChildrenHTML(groupEl, 0); flyout.classList.add('open'); var r = headerEl.getBoundingClientRect(); flyout.style.left = '60px'; var top = Math.min(r.top, window.innerHeight - flyout.offsetHeight - 10); flyout.style.top = Math.max(8, top) + 'px'; $$('.flyout-item', flyout).forEach(function (b) { b.addEventListener('click', function () { navigate(b.getAttribute('data-route')); hideFlyout(true); }); }); } function hideFlyout(now) { clearTimeout(flyTimer); if (now) { flyout.classList.remove('open'); flyOwner = null; return; } flyTimer = setTimeout(function () { flyout.classList.remove('open'); flyOwner = null; }, 220); } function showTooltip(text, el) { tooltip.textContent = text; tooltip.classList.add('open'); var r = el.getBoundingClientRect(); tooltip.style.left = '60px'; tooltip.style.top = (r.top + r.height / 2 - tooltip.offsetHeight / 2) + 'px'; } function hideTooltip() { tooltip.classList.remove('open'); } $('#navBody').addEventListener('mouseover', function (e) { if (!collapsedActive()) return; var header = e.target.closest('.group-header'); var leaf = e.target.closest('.nav-item'); if (header) { hideTooltip(); openFlyout(header.parentNode, header); } else if (leaf) { hideFlyout(); showTooltip(leaf.querySelector('.nav-label').textContent, leaf); } }); $('#navBody').addEventListener('mouseout', function (e) { if (!collapsedActive()) return; if (e.target.closest('.group-header')) hideFlyout(false); if (e.target.closest('.nav-item')) hideTooltip(); }); $('#navBody').addEventListener('focusin', function (e) { if (!collapsedActive()) return; var header = e.target.closest('.group-header'), leaf = e.target.closest('.nav-item'); if (header) { hideTooltip(); openFlyout(header.parentNode, header); } else if (leaf) { hideFlyout(); showTooltip(leaf.querySelector('.nav-label').textContent, leaf); } }); $('#navBody').addEventListener('focusout', function (e) { if (!collapsedActive()) return; if (e.target.closest('.nav-item')) hideTooltip(); }); flyout.addEventListener('mouseenter', function () { clearTimeout(flyTimer); }); flyout.addEventListener('mouseleave', function () { hideFlyout(false); }); document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hideFlyout(true); hideTooltip(); } }); var REAL = { dashboard: 1, tickets: 1, ticket: 1, 'ticket-form': 1, recycle: 1, integrations: 1, users: 1, sla: 1, assets: 1, 'ch-email': 1, signin: 1, models: 1, 'model-edit': 1, profile: 1 }; var PH = { kb: 'Knowledge Base', changes: 'Changes', problems: 'Problems', teams: 'Teams', 'app-settings': 'Application Settings', 'ch-chat': 'Chat Channel', sessions: 'Sessions' }; var ROUTE_ACCESS = { dashboard: 'workspace', tickets: 'workspace', ticket: 'workspace', 'ticket-form': 'workspace', recycle: 'workspace', assets: 'workspace', kb: 'workspace', profile: 'workspace', sla: 'compliance', changes: 'compliance', problems: 'compliance', users: 'app-admin', teams: 'app-admin', 'app-settings': 'app-admin', integrations: 'sys-admin', models: 'sys-admin', 'model-edit': 'sys-admin', 'ch-email': 'sys-admin', 'ch-chat': 'sys-admin', signin: 'sys-admin', sessions: 'sys-admin' }; function parseHash() { var h = location.hash.replace(/^#/, ''); var q = h.indexOf('?'); if (q !== -1) h = h.slice(0, q); return (h || 'dashboard').split('/'); } function currentPrimary() { return parseHash()[0]; } function navigate(route) { if (('#' + route) === location.hash) render(); else location.hash = route; } function render() { var parts = parseHash(), primary = parts[0], viewId, crumbLeaf = ''; if (REAL[primary]) { viewId = 'view-' + primary; } else if (PH[primary]) { viewId = 'view-placeholder'; $('#phTitle').textContent = PH[primary]; $('#phText').textContent = 'The "' + PH[primary] + '" area is part of the navigation structure but is not built in this mockup.'; } else { primary = 'dashboard'; viewId = 'view-dashboard'; if (location.hash !== '#dashboard') history.replaceState(null, '', '#dashboard'); } var acc = ROUTE_ACCESS[primary]; if (acc && !canAccessKey(currentRole, acc)) { toast('warning', 'That area is not available for the ' + currentRole + ' role.'); primary = 'dashboard'; viewId = 'view-dashboard'; if (location.hash && location.hash !== '#dashboard') history.replaceState(null, '', '#dashboard'); } $$('.view').forEach(function (v) { v.hidden = (v.id !== viewId); }); setActiveNav(primary); if (primary === 'ticket') { crumbLeaf = parts[1] || 'INC-1042'; $('#tkDetailEdit').setAttribute('href', '#ticket-form/' + (parts[1] || 'INC-1042')); activateTabs($('#view-ticket'), parts[2] || 'overview'); } if (primary === 'integrations') { activateTabs($('#view-integrations'), parts[1] || 'connected'); } if (primary === 'signin') { activateTabs($('#view-signin'), parts[1] || 'password'); } if (primary === 'models') { applyModelsUrlState(); loadModels(); } if (primary === 'ticket-form') { enterTicketForm(parts[1] || ''); crumbLeaf = tkFormRef; } if (primary === 'model-edit') { enterModelEdit(parts[1] || ''); crumbLeaf = mdEditLeaf; } if (primary === 'ch-email') { renderEmailGate(); } if (primary === 'tickets') { applyTicketsUrlState(); loadTickets(); } if (primary === 'profile') { var _pl = { sysadmin: 'System Admin', admin: 'Admin', collaborator: 'Collaborator', contributor: 'Contributor', readonly: 'Read-only' }; var _pc = { sysadmin: 'badge-danger', admin: 'badge-violet', collaborator: 'badge-info', contributor: 'badge-success', readonly: 'badge-neutral' }; var _pr = $('#profileRoles'); if (_pr) { _pr.innerHTML = '<span class="badge ' + (_pc[currentRole] || 'badge-neutral') + '"><span class="dot"></span>' + (_pl[currentRole] || currentRole) + '</span>'; } var _pa = $('#profileAccess'); if (_pa) { var _secs = [['workspace', 'Workspace'], ['compliance', 'Compliance'], ['app-admin', 'Application Administration'], ['sys-admin', 'System Administration']].filter(function (s) { return canAccessKey(currentRole, s[0]); }).map(function (s) { return s[1]; }); _pa.textContent = _secs.join(', ') || '-'; } } renderCrumbs(viewId, primary, crumbLeaf); closePopovers(); hideFlyout(true); hideTooltip(); closeMobileNav(); var main = $('#main-content'); main.scrollTop = 0; if (render._ready) main.focus(); } function activateTabs(viewEl, tabName) { $$('.tab', viewEl).forEach(function (t) { var on = t.getAttribute('data-tab') === tabName; if (on) t.setAttribute('aria-current', 'page'); else t.removeAttribute('aria-current'); }); $$('.tab-panel', viewEl).forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-panel') === tabName); }); } window.addEventListener('hashchange', render); var mq = window.matchMedia('(prefers-color-scheme: dark)'); function applyTheme() { var pref = store.get('c2s-theme', 'system'); var eff = pref === 'system' ? (mq.matches ? 'dark' : 'light') : pref; document.documentElement.setAttribute('data-theme', eff); var fav = $('#favicon'); if (fav) { fav.href = 'assets/images/favicon-' + eff + '.ico'; } $$('#themeSeg button').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-theme-val') === pref); }); } $$('#themeSeg button').forEach(function (b) { b.addEventListener('click', function () { store.set('c2s-theme', b.getAttribute('data-theme-val')); applyTheme(); toast('info', 'Theme set to ' + b.getAttribute('data-theme-val') + '.'); }); }); mq.addEventListener('change', function () { if (store.get('c2s-theme', 'system') === 'system') applyTheme(); }); var TICONS = { success: 'check-circle', error: 'warning', warning: 'warning', info: 'info' }; function toast(type, msg, opts) { opts = opts || {}; var region = (type === 'error' || type === 'warning') ? $('#toastAssertive') : $('#toastPolite'); var el = document.createElement('div'); el.className = 'toast ' + type; el.innerHTML = ic(TICONS[type] || 'info') + '<div class="tmsg">' + msg + '</div>' + '<button class="icon-btn icon-btn-sm tclose" type="button" aria-label="Dismiss">' + ic('x') + '</button>'; region.appendChild(el); var remove = function () { el.classList.add('leaving'); setTimeout(function () { el.remove(); }, 220); };
    el.querySelector('.tclose').addEventListener('click', remove);
    /* An error persists until dismissed (toasts.md 5): it is now the only thing that says the
       submit was blocked. Anything that auto-dismisses pauses while the pointer or keyboard
       focus is on it, so reading a toast never costs you the toast. */
    var life = opts.duration !== undefined ? opts.duration : (type === 'error' ? 0 : type === 'warning' ? 7000 : 4000);
    if (life > 0) {
        var timer = setTimeout(remove, life), left = life, startedAt = Date.now();
        var hold = function () { clearTimeout(timer); left -= (Date.now() - startedAt); };
        var resume = function () { if (left <= 0) { remove(); return; } startedAt = Date.now(); timer = setTimeout(remove, left); };
        el.addEventListener('mouseenter', hold); el.addEventListener('focusin', hold);
        el.addEventListener('mouseleave', resume); el.addEventListener('focusout', resume);
    }
} window.toast = toast; var mBackdrop = $('#modalBackdrop'), mEl = $('#modal'), mResolve = null, mLastFocus = null; function openModal(cfg) { if (mResolve) { var prev = mResolve; mResolve = null; prev(false); } return new Promise(function (resolve) { mResolve = resolve; mLastFocus = document.activeElement; $('#modalTitle').textContent = cfg.title || ''; mEl.setAttribute('role', cfg.danger ? 'alertdialog' : 'dialog'); var bodyHtml = '<div>' + (cfg.body || '') + '</div>'; if (cfg.requireWord) bodyHtml += '<input class="confirm-input" id="confirmWord" placeholder="Type ' + cfg.requireWord + ' to confirm" autocomplete="off" />'; $('#modalDesc').innerHTML = bodyHtml; var foot = $('#modalFoot'); foot.innerHTML = '<button class="btn btn-secondary" id="mCancel" type="button">' + (cfg.cancelLabel || 'Cancel') + '</button>' + '<button class="btn ' + (cfg.danger ? 'btn-danger' : 'btn-primary') + '" id="mConfirm" type="button">' + (cfg.confirmLabel || 'Confirm') + '</button>'; mBackdrop.classList.add('open'); var confirmBtn = $('#mConfirm'), cancelBtn = $('#mCancel'); if (cfg.requireWord) { confirmBtn.disabled = true; var wi = $('#confirmWord'); wi.addEventListener('input', function () { confirmBtn.disabled = wi.value.trim() !== cfg.requireWord; }); } confirmBtn.addEventListener('click', function () { closeModal(true); }); cancelBtn.addEventListener('click', function () { closeModal(false); }); (cfg.danger ? cancelBtn : (cfg.requireWord ? cancelBtn : confirmBtn)).focus(); }); } function closeModal(result) { mBackdrop.classList.remove('open'); if (mLastFocus && mLastFocus.focus) mLastFocus.focus(); var r = mResolve; mResolve = null; if (r) r(result); } mBackdrop.addEventListener('click', function (e) { if (e.target === mBackdrop) closeModal(false); }); document.addEventListener('keydown', function (e) { if (!mBackdrop.classList.contains('open')) return; if (e.key === 'Escape') { e.preventDefault(); closeModal(false); } if (e.key === 'Tab') { var f = $$('button, input, [tabindex]', mEl).filter(function (x) { return !x.disabled && x.offsetParent !== null; }); if (!f.length) return; var first = f[0], last = f[f.length - 1]; if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } } }); var TK = [{ ref: 'INC-1042', subject: 'VPN drops every few minutes on 4th floor', req: 'M. Okafor', pr: 'High', st: 'In progress', stc: 'warning' }, { ref: 'INC-1050', subject: 'Email delivery delayed for sales team', req: 'D. Alvarez', pr: 'Medium', st: 'Open', stc: 'info' }, { ref: 'REQ-861', subject: 'New starter laptop + accounts', req: 'HR Onboarding', pr: 'Low', st: 'Pending', stc: 'warning' }, { ref: 'INC-1047', subject: 'CRM 500 error on export', req: 'S. Kaur', pr: 'Critical', st: 'Open', stc: 'danger' }, { ref: 'INC-1033', subject: 'Password reset loop after SSO change', req: 'T. Brooks', pr: 'High', st: 'Resolved', stc: 'success' }, { ref: 'REQ-855', subject: 'Software license: design suite', req: 'Creative', pr: 'Low', st: 'Resolved', stc: 'success' }, { ref: 'INC-1051', subject: 'Shared drive read-only for finance team', req: 'P. Duarte', pr: 'Medium', st: 'In progress', stc: 'warning' }, { ref: 'INC-1039', subject: 'Meeting room display shows no signal', req: 'Facilities', pr: 'Low', st: 'Open', stc: 'info' }, { ref: 'REQ-870', subject: 'Access to the payroll reporting folder', req: 'A. Novak', pr: 'Medium', st: 'Pending', stc: 'warning' }, { ref: 'INC-1055', subject: 'Two-factor prompt loops on a new phone', req: 'J. Whitfield', pr: 'High', st: 'Open', stc: 'info' }, { ref: 'INC-1048', subject: 'Printer queue stuck on the second floor', req: 'R. Mensah', pr: 'Low', st: 'In progress', stc: 'warning' }, { ref: 'REQ-864', subject: 'Second monitor for the support desk', req: 'L. Fontaine', pr: 'Low', st: 'Resolved', stc: 'success' }, { ref: 'INC-1053', subject: 'Warehouse scanners drop off the wireless', req: 'Warehouse Ops', pr: 'High', st: 'In progress', stc: 'warning' }, { ref: 'INC-1036', subject: 'Invoice PDFs render blank in the portal', req: 'Finance', pr: 'Medium', st: 'Resolved', stc: 'success' }, { ref: 'REQ-872', subject: 'Contractor account for a six-week project', req: 'B. Adeyemi', pr: 'Medium', st: 'Open', stc: 'info' }, { ref: 'INC-1058', subject: 'Payment gateway timeouts at checkout', req: 'E. Sorensen', pr: 'Critical', st: 'In progress', stc: 'warning' }, { ref: 'INC-1044', subject: 'Mail search returns no results', req: 'K. Yamada', pr: 'Low', st: 'Pending', stc: 'warning' }, { ref: 'REQ-858', subject: 'Move the team mailbox to the new domain', req: 'Comms', pr: 'Low', st: 'Resolved', stc: 'success' }, { ref: 'INC-1061', subject: 'Backup job failed three nights running', req: 'Infrastructure', pr: 'Critical', st: 'Open', stc: 'info' }, { ref: 'INC-1029', subject: 'Laptop battery drains within an hour', req: 'N. Petrov', pr: 'Medium', st: 'Resolved', stc: 'success' }, { ref: 'REQ-875', subject: 'Purchase approval for design tablets', req: 'Creative', pr: 'Low', st: 'Pending', stc: 'warning' }, { ref: 'INC-1057', subject: 'Badge readers offline at the side entrance', req: 'Security', pr: 'High', st: 'In progress', stc: 'warning' }, { ref: 'INC-1041', subject: 'Report export cuts off after 1,000 rows', req: 'S. Kaur', pr: 'Medium', st: 'Open', stc: 'info' }, { ref: 'REQ-867', subject: 'Restore a deleted project folder', req: 'G. Halvorsen', pr: 'High', st: 'Resolved', stc: 'success' }, { ref: 'INC-1052', subject: 'Conference calls drop after ten minutes', req: 'C. Mwangi', pr: 'Medium', st: 'In progress', stc: 'warning' }, { ref: 'INC-1035', subject: 'Shared calendar not syncing on mobile', req: 'T. Brooks', pr: 'Low', st: 'Resolved', stc: 'success' }, { ref: 'REQ-878', subject: 'Training licences for the new analysts', req: 'People Team', pr: 'Low', st: 'Open', stc: 'info' }, { ref: 'INC-1060', subject: 'Card terminal rejects contactless payments', req: 'Retail Ops', pr: 'Critical', st: 'Pending', stc: 'warning' }, { ref: 'INC-1046', subject: 'Antivirus quarantines the payroll export', req: 'Finance', pr: 'High', st: 'Resolved', stc: 'success' }, { ref: 'REQ-853', subject: 'Decommission the old file server', req: 'Infrastructure', pr: 'Low', st: 'Resolved', stc: 'success' }]; var tkState = 'data';

/* Paging is a window onto the FILTERED, sorted set (tables.md 9): the total below is the filtered
   total, and any filter, sort, or page-size change returns to page 1. Default size 25, options
   25/50/75/100. The page and the size ride in the URL beside the sort and the filters, so a
   refresh or a shared link reproduces the view. */
var TK_PAGE_SIZES = [25, 50, 75, 100];
var tkPage = 1, tkPageSize = TK_PAGE_SIZES[0];

/* The Many state pads the demo set so a long page range and the rows-per-page select can be seen
   working. Every padded row carries a real subject from the set above under its own reference,
   the way a service desk really does log the same fault several times. */
var tkManyCache = null;
function tkManyRows() {
    if (tkManyCache) { return tkManyCache; }
    if (!TK.length) { return []; }                       /* nothing to pad from, so the empty state is honest */
    var out = TK.slice(), i = 0;
    while (out.length < 247) {
        var t = TK[i % TK.length];
        out.push({ ref: t.ref.slice(0, 3) + '-' + (1100 + out.length), subject: t.subject, req: t.req, pr: t.pr, st: t.st, stc: t.stc });
        i++;
    }
    tkManyCache = out;
    return out;
}

/* Long ranges collapse: first page, a window around the current one, last page, with a gap
   marker between (1 2 3 ... 10). */
function pageWindow(page, pages) {
    var out = [], i;
    if (pages <= 7) { for (i = 1; i <= pages; i++) { out.push(i); } return out; }
    out.push(1);
    var from = Math.max(2, Math.min(page - 1, pages - 3));
    var to = Math.min(pages - 1, Math.max(page + 1, 4));
    if (from > 2) { out.push('gap'); }
    for (i = from; i <= to; i++) { out.push(i); }
    if (to < pages - 1) { out.push('gap'); }
    out.push(pages);
    return out;
}
function renderTicketsPager(total, pages) {
    var el = $('#ticketsPager');
    /* Offer nothing only when there is nothing to offer: one page AND the default size. If a
       non-default size is active the select has to stay reachable, or the size is stuck at a value
       the user can see in the URL and cannot change. */
    if (total <= TK_PAGE_SIZES[0] && tkPageSize === TK_PAGE_SIZES[0]) { el.innerHTML = ''; return; }
    /* Paging replaces this subtree, so remember what had focus and give it back afterwards. */
    var active = document.activeElement, keep = null;
    if (active && el.contains(active)) { keep = active.classList.contains('page-size') ? 'size' : active.getAttribute('data-page'); }
    var h = '<select class="field page-size" aria-label="Rows per page">';
    TK_PAGE_SIZES.forEach(function (n) {
        h += '<option value="' + n + '"' + (n === tkPageSize ? ' selected' : '') + '>' + n + ' / page</option>';
    });
    h += '</select>';
    if (pages > 1) {
        h += '<div class="page-btns">';
        h += '<button type="button" class="page-btn" data-page="' + (tkPage - 1) + '" aria-label="Previous page"'
            + (tkPage === 1 ? ' disabled' : '') + '><svg class="ic" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>';
        pageWindow(tkPage, pages).forEach(function (n) {
            if (n === 'gap') { h += '<span class="page-gap" aria-hidden="true">...</span>'; return; }
            h += '<button type="button" class="page-btn page-num' + (n === tkPage ? ' active' : '') + '" data-page="' + n + '"'
                + (n === tkPage ? ' aria-current="page"' : '') + '>' + n + '</button>';
        });
        h += '<button type="button" class="page-btn" data-page="' + (tkPage + 1) + '" aria-label="Next page"'
            + (tkPage === pages ? ' disabled' : '') + '><svg class="ic" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>';
        h += '</div>';
        /* Small screens show Prev / Next and this line instead of the numbered set and the size
           select, per the responsive pagination rule. CSS decides which of the two is visible. */
        h += '<span class="page-of">Page ' + tkPage + ' of ' + pages + '</span>';
    }
    el.innerHTML = h;
    if (keep) {
        var back = keep === 'size' ? el.querySelector('.page-size') : el.querySelector('.page-btn[data-page="' + keep + '"]:not([disabled])');
        /* The button that was clicked may now be the disabled end of the range, so fall back to the
           other arrow rather than dropping focus to the document. */
        if (!back) { back = el.querySelector('.page-btn:not([disabled])'); }
        if (back) { back.focus(); }
    }
}
function badge(st, stc) { return '<span class="badge badge-' + stc + '"><span class="dot"></span>' + st + '</span>'; } function prBadge(pr) { var m = { Critical: 'danger', High: 'warning', Medium: 'info', Low: 'neutral' }; return '<span class="badge badge-' + (m[pr] || 'neutral') + '">' + pr + '</span>'; } function renderDashRecent() { $('#dashRecent').innerHTML = TK.slice(0, 4).map(function (t) { return '<tr><td><span class="code-chip">' + t.ref + '</span></td>' + '<td><a class="row-link" href="#ticket/' + t.ref + '/overview">' + t.subject + '</a></td>' + '<td>' + prBadge(t.pr) + '</td><td>' + badge(t.st, t.stc) + '</td></tr>'; }).join(''); } function currentFilters() { return { q: $('#tkSearch').value.trim().toLowerCase(), st: $('#tkStatus').value, pr: $('#tkPriority').value }; } function filtersActive(f) { return !!(f.q || f.st || f.pr); } function loadTickets() { syncTicketsUrl(); var body = $('#ticketsBody'), foot = $('#ticketsFoot'); foot.hidden = true; $('#tkClear').hidden = !filtersActive(currentFilters()); body.setAttribute('aria-busy', 'true'); body.innerHTML = skeletonRows(Math.min(tkPageSize, 10), 6); setTimeout(function () { body.removeAttribute('aria-busy'); if (tkState === 'error') { body.innerHTML = stateRow('warning', 'Could not load tickets', 'Something went wrong fetching the list.', 'Retry', 'retry'); return; } var f = currentFilters(); var source = tkState === 'many' ? tkManyRows() : TK; var rows = tkState === 'empty' ? [] : source.filter(function (t) { return (!f.q || (t.ref + ' ' + t.subject).toLowerCase().indexOf(f.q) !== -1) && (!f.st || t.st === f.st) && (!f.pr || t.pr === f.pr); }); rows = sortTickets(rows); $('#tkClear').hidden = !filtersActive(f); if (!rows.length) { if (tkState !== 'empty' && filtersActive(f)) body.innerHTML = stateRow('search', 'No matching tickets', 'No tickets match your filters.', 'Clear filters', 'clear'); else body.innerHTML = stateRow('inbox', 'No tickets yet', 'When tickets are logged they show up here.', 'New Ticket', 'new'); return; } var total = rows.length, pages = Math.max(1, Math.ceil(total / tkPageSize));
    if (tkPage > pages) { tkPage = pages; }
    var start = (tkPage - 1) * tkPageSize;
    rows = rows.slice(start, start + tkPageSize);
    body.innerHTML = rows.map(function (t) { return '<tr data-ref="' + t.ref + '">' + '<td><span class="code-chip">' + t.ref + '</span></td>' + '<td><a class="row-link" href="#ticket/' + t.ref + '/overview">' + t.subject + '</a></td>' + '<td class="muted">' + t.req + '</td><td>' + prBadge(t.pr) + '</td><td>' + badge(t.st, t.stc) + '</td>' + '<td class="col-actions"><a class="btn btn-secondary btn-sm" href="#ticket/' + t.ref + '/overview"><svg class="ic"><use href="#i-eye"></use></svg> View</a>' + ' <a class="btn btn-secondary btn-sm" href="#ticket-form/' + t.ref + '" aria-label="Edit ' + t.ref + '"><svg class="ic"><use href="#i-pencil"></use></svg> Edit</a>' + ' <button class="btn btn-secondary btn-sm btn-text-danger" data-del="' + t.ref + '" aria-label="Delete ' + t.ref + '"><svg class="ic"><use href="#i-trash"></use></svg> Delete</button></td></tr>'; }).join(''); foot.hidden = false; $('#ticketsCount').textContent = 'Showing ' + (start + 1) + '-' + (start + rows.length) + ' of ' + total; renderTicketsPager(total, pages);
        /* The page may have been clamped above, so write the URL again - but only while the
           tickets list is still the open route. This runs 650ms late, and replaceState fires no
           hashchange, so writing it after the user has navigated away would strand the address
           bar on #tickets and leave the sidebar link looking dead. */
        if (location.hash.replace(/^#/, '').split('?')[0] === 'tickets') { syncTicketsUrl(); }
    }, 650); } function skeletonRows(n, cols) { var r = ''; for (var i = 0; i < n; i++) { var c = ''; for (var j = 0; j < cols; j++) c += '<td><div class="skel skel-line" style="width:' + (55 + (j * 7) % 40) + '%"></div></td>'; r += '<tr>' + c + '</tr>'; } return r; } function stateRow(icon, title, text, btn, action) { return '<tr class="row-empty"><td colspan="6"><div class="state-block" role="' + (icon === 'warning' ? 'alert' : 'status') + '">' + '<span class="icon-chip chip-primary"><svg class="ic"><use href="#i-' + (icon === 'search' ? 'search' : icon === 'inbox' ? 'inbox' : 'warning') + '"></use></svg></span>' + '<h3>' + title + '</h3><p>' + text + '</p>' + '<button class="btn ' + (action === 'clear' ? 'btn-secondary' : 'btn-primary') + '" data-state-action="' + action + '">' + btn + '</button></div></td></tr>'; } $('#ticketsBody').addEventListener('click', function (e) { var del = e.target.closest('[data-del]'); if (del) { var ref = del.getAttribute('data-del'); openModal({ title: 'Move to Recycle Bin?', body: 'Ticket <strong>' + ref + '</strong> will be moved to the Recycle Bin. You can restore it later.', confirmLabel: 'Move to bin', danger: true }).then(function (ok) { if (ok) { TK = TK.filter(function (t) { return t.ref !== ref; }); if (tkManyCache) { tkManyCache = tkManyCache.filter(function (t) { return t.ref !== ref; }); } loadTickets(); toast('success', ref + ' moved to Recycle Bin.'); } }); } var sa = e.target.closest('[data-state-action]'); if (sa) { var a = sa.getAttribute('data-state-action'); if (a === 'retry' || a === 'clear') { if (a === 'clear') { $('#tkSearch').value = ''; $('#tkStatus').value = ''; $('#tkPriority').value = ''; tkState = tkFilterableState(); } else { tkState = 'data'; } tkPage = 1; syncStateSeg(); loadTickets(); } if (a === 'new') navigate('ticket-form'); } }); $('#ticketsPager').addEventListener('click', function (e) {
    var b = e.target.closest('.page-btn');
    if (!b || b.disabled) { return; }
    var n = parseInt(b.getAttribute('data-page'), 10);
    if (!n || n === tkPage) { return; }
    tkPage = n; loadTickets();
});
$('#ticketsPager').addEventListener('change', function (e) {
    if (!e.target.classList.contains('page-size')) { return; }
    tkPageSize = parseInt(e.target.value, 10) || TK_PAGE_SIZES[0];
    tkPage = 1;                                           /* a page-size change returns to page 1 */
    loadTickets();
});
/* Apply and Clear keep whichever demo set is loaded, so the Many state can show that a filter
   runs over all 247 rows rather than the 25 on screen. Only the states with no rows to filter
   (empty, error) fall back to data. */
function tkFilterableState() { return (tkState === 'empty' || tkState === 'error') ? 'data' : tkState; }
$('#tkApply').addEventListener('click', function () { tkState = tkFilterableState(); tkPage = 1; syncStateSeg(); loadTickets(); }); $('#tkClear').addEventListener('click', function () { $('#tkSearch').value = ''; $('#tkStatus').value = ''; $('#tkPriority').value = ''; tkState = tkFilterableState(); tkPage = 1; syncStateSeg(); loadTickets(); }); $$('#tkStateSeg button').forEach(function (b) { b.addEventListener('click', function () { tkState = b.getAttribute('data-state'); tkPage = 1; syncStateSeg(); loadTickets(); }); }); function syncStateSeg() { $$('#tkStateSeg button').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-state') === tkState); }); } var VALIDATORS = { subject: function (v) { return v.trim() ? '' : 'Enter a subject.'; }, priority: function (v) { return v ? '' : 'Select a priority.'; }, email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) ? '' : 'Enter a valid email address.'; }, description: function (v) { return v.trim().length >= 10 ? '' : 'Give at least 10 characters of detail.'; } }; function fieldEl(name) { return $('#view-ticket-form [data-field="' + name + '"]'); } function validateField(name) { var wrap = fieldEl(name); if (!wrap) return true; var input = $('.field', wrap), msg = $('.field-err-msg', wrap); var err = VALIDATORS[name](input.value); wrap.classList.toggle('invalid', !!err); if (msg) msg.textContent = err; input.setAttribute('aria-invalid', err ? 'true' : 'false'); return !err; } Object.keys(VALIDATORS).forEach(function (name) { var wrap = fieldEl(name); if (!wrap) return; var input = $('.field', wrap); input.addEventListener('blur', function () { validateField(name); }); input.addEventListener('input', function () { if (wrap.classList.contains('invalid')) validateField(name); }); }); $('#ticketForm').addEventListener('submit', function (e) { e.preventDefault(); var names = Object.keys(VALIDATORS), bad = []; names.forEach(function (n) { if (!validateField(n)) bad.push(n); }); /* Reported once: the message under each field is the record of what is wrong, and one error
       toast announces that the submit was blocked while focus moves to the first bad field. No
       summary card, because it would repeat the same sentences the fields already carry. */
    if (bad.length) { var first = $('.field', fieldEl(bad[0])); first.focus(); first.scrollIntoView({ block: 'center' }); toast('error', bad.length + ' field' + (bad.length > 1 ? 's need' : ' needs') + ' attention.'); return; } var btn = $('#ticketSubmit'); btn.disabled = true; btn.setAttribute('aria-busy', 'true'); var orig = btn.innerHTML; btn.innerHTML = '<span class="spinner"></span> ' + (tkFormRef ? 'Saving...' : 'Creating...'); setTimeout(function () { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerHTML = orig; $('#ticketForm').reset(); $$('#view-ticket-form .field-group').forEach(function (w) { w.classList.remove('invalid'); }); $$('#view-ticket-form .field-err-msg').forEach(function (m) { m.textContent = ''; }); toast('success', tkFormRef ? tkFormRef + ' updated.' : 'Ticket created and added to the queue.'); navigate('tickets'); }, 800); }); $('#binBody').addEventListener('click', function (e) { var r = e.target.closest('[data-restore]'), p = e.target.closest('[data-purge]'); if (r) { var ref = r.getAttribute('data-restore'); r.closest('tr').remove(); toast('success', ref + ' restored.'); } if (p) { var pref = p.getAttribute('data-purge'); openModal({ title: 'Delete forever?', body: '<strong>' + pref + '</strong> will be permanently deleted. This cannot be undone.', confirmLabel: 'Delete forever', danger: true }).then(function (ok) { if (ok) { p.closest('tr').remove(); toast('success', pref + ' permanently deleted.'); } }); } }); $('#emptyBinBtn').addEventListener('click', function () { openModal({ title: 'Empty the Recycle Bin?', body: 'This permanently deletes <strong>all</strong> items in the bin. This cannot be undone.', confirmLabel: 'Empty everything', danger: true, requireWord: 'DELETE' }).then(function (ok) { if (ok) { $('#binBody').innerHTML = '<tr class="row-empty"><td colspan="4">The Recycle Bin is empty.</td></tr>'; toast('success', 'Recycle Bin emptied.'); } }); }); function closePopovers() { $('#notifPanel').classList.remove('open'); $('#userMenu').classList.remove('open'); } function togglePop(panel, btn) { var isOpen = panel.classList.contains('open'); closePopovers(); if (!isOpen) { panel.classList.add('open'); setTimeout(function () { document.addEventListener('click', function handler(ev) { if (!panel.contains(ev.target) && ev.target !== btn && !btn.contains(ev.target)) { panel.classList.remove('open'); document.removeEventListener('click', handler); } }); }, 10); } } $('#notifBtn').addEventListener('click', function (e) { e.stopPropagation(); togglePop($('#notifPanel'), this); $('#notifDot').style.display = 'none'; }); $('#avatarBtn').addEventListener('click', function (e) { e.stopPropagation(); togglePop($('#userMenu'), this); }); $('#signOutBtn').addEventListener('click', function () { closePopovers(); toast('info', 'Sign-out is a mockup stub.'); }); document.addEventListener('click', function (e) { var nav = e.target.closest('[data-nav]'); if (nav) { e.preventDefault(); navigate(nav.getAttribute('data-nav')); closePopovers(); } }); var railBackdrop = $('#railBackdrop'); function openMobileNav() { rail.classList.add('mobile-open'); railBackdrop.classList.add('show'); } function closeMobileNav() { rail.classList.remove('mobile-open'); railBackdrop.classList.remove('show'); } $('#mobileMenuBtn').addEventListener('click', openMobileNav); railBackdrop.addEventListener('click', closeMobileNav); $('#roleSelect').addEventListener('change', function () { currentRole = this.value; store.set('c2s-role', currentRole); renderRail(); document.body.setAttribute('data-role', currentRole); toast('info', 'Viewing as ' + this.value + '. Sidebar re-filtered.'); if (currentPrimary() !== 'dashboard' && !$('#navBody .nav-item[data-route="' + (currentPrimary() === 'ticket' ? 'tickets' : currentPrimary()) + '"]')) navigate('dashboard'); }); function applyRoleVisibility() { $$('.admin-only').forEach(function (el) { el.style.display = (currentRole === 'admin' || currentRole === 'sysadmin') ? '' : 'none'; }); } var _origRenderRail = renderRail; renderRail = function () { _origRenderRail(); applyRoleVisibility(); }; 
/* =========================================================================
   AI PROVIDERS - catalog list and the add/edit form.
   One record per model. The API key lives on the record and is referenced as
   {{api_key}}; the prompt arrives per call as {{prompt}}.
   ========================================================================= */

/* Currency is one project-wide constant, never a per-record field. */
var CURRENCY = '$';

var MODELS = [
    { key: 'openai-gpt-5-6', name: 'OpenAI GPT-5.6', method: 'POST', url: 'https://api.openai.com/v1/chat/completions', priceIn: 5.00, priceOut: 30.00, status: 'Active', statusRole: 'success', tested: 'passed', testedWhen: '2 days ago' , testedAt: '2026-08-15T09:12:00Z' },
    { key: 'claude-opus-5', name: 'Claude Opus 5', method: 'POST', url: 'https://api.anthropic.com/v1/messages', priceIn: 5.00, priceOut: 25.00, status: 'Active', statusRole: 'success', tested: 'passed', testedWhen: '6 hours ago' , testedAt: '2026-08-17T04:40:00Z' },
    { key: 'gemini-3-1-pro', name: 'Gemini 3.1 Pro', method: 'POST', url: 'https://generativelanguage.googleapis.com/v1beta/models/<MODEL_ID>:generateContent', priceIn: 2.00, priceOut: 12.00, status: 'Active', statusRole: 'success', tested: 'failed', testedWhen: '3 days ago' , testedAt: '2026-08-14T16:05:00Z' },
    { key: 'perplexity-sonar-pro', name: 'Perplexity Sonar Pro', method: 'POST', url: 'https://api.perplexity.ai/chat/completions', priceIn: null, priceOut: null, status: 'Draft', statusRole: 'neutral', tested: 'never', testedWhen: 'never' , testedAt: null }
];
var mdState = 'data';

/* Declared sort defaults for the two lists. Every list opens on a declared order (tables.md §4). */
var tkSort = { key: 'ref', dir: 'asc' };
var mdSort = { key: 'name', dir: 'asc' };

/* Cost in exact decimal terms: prices are held as integer micro-units per
   million tokens, so no floating-point accumulation reaches the figure shown.
   Rounding is for display only, and a missing price reports unknown, never zero. */
function priceMicros(p) { return (p === null || p === undefined) ? null : Math.round(p * 1e6); }
function costOf(tokens, pricePerMillion) {
    var pm = priceMicros(pricePerMillion);
    if (pm === null || tokens === null || tokens === undefined) { return null; }
    return Math.round(tokens * pm / 1e6);
}
function fmtMicros(micros) {
    if (micros === null) { return 'Unknown'; }
    return CURRENCY + (micros / 1e6).toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
}
function costCell(m) {
    if (m.priceIn === null || m.priceOut === null) { return '<span class="muted">Unknown</span>'; }
    return '<span class="mono">' + CURRENCY + m.priceIn.toFixed(2) + ' / ' + CURRENCY + m.priceOut.toFixed(2) + '</span>';
}
function testedCell(m) {
    if (m.tested === 'passed') { return '<span class="muted">' + m.testedWhen + '</span>'; }
    if (m.tested === 'failed') { return mdBadge('Failed', 'danger') + ' <span class="muted" style="font-size:11px">' + m.testedWhen + '</span>'; }
    return mdBadge('Never tested', 'warning');
}
function mdBadge(text, role) { return '<span class="badge badge-' + role + '"><span class="dot"></span>' + text + '</span>'; }

function mdFilters() { return { q: $('#mdSearch').value.trim().toLowerCase(), st: $('#mdStatus').value, tt: $('#mdTested').value }; }
function mdActive(f) { return !!(f.q || f.st || f.tt); }

function loadModels() {
    syncModelsUrl();
    var body = $('#modelsBody'), foot = $('#modelsFoot');
    foot.hidden = true;
    $('#mdClear').hidden = !mdActive(mdFilters());
    body.setAttribute('aria-busy', 'true'); body.innerHTML = skeletonRows(4, 7);
    setTimeout(function () {
        body.removeAttribute('aria-busy');
        if (mdState === 'error') { body.innerHTML = mdStateRow('warning', 'Could not load the catalog', 'Something went wrong fetching the entries.', 'Retry', 'retry'); return; }
        var f = mdFilters();
        var rows = mdState === 'empty' ? [] : MODELS.filter(function (m) {
            return (!f.q || (m.name + ' ' + m.url).toLowerCase().indexOf(f.q) !== -1)
                && (!f.st || m.status === f.st) && (!f.tt || m.tested === f.tt);
        });
        rows = sortModels(rows);
        $('#mdClear').hidden = !mdActive(f);
        if (!rows.length) {
            if (mdState !== 'empty' && mdActive(f)) { body.innerHTML = mdStateRow('search', 'No matching entries', 'No catalog entries match your filters.', 'Clear filters', 'clear'); }
            else { body.innerHTML = mdStateRow('inbox', 'No models yet', 'Add a model to make it callable. Each entry is configuration, not code.', 'Add model', 'new'); }
            return;
        }
        body.innerHTML = rows.map(function (m) {
            return '<tr>'
                + '<td><strong>' + m.name + '</strong><br><span class="muted mono" style="font-size:11px">' + m.key + '</span></td>'
                + '<td><span class="badge badge-neutral mono">' + m.method + '</span></td>'
                + '<td><span class="mono" style="display:inline-block;max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom" title="' + m.url + '">' + m.url + '</span></td>'
                + '<td>' + costCell(m) + '</td>'
                + '<td>' + mdBadge(m.status, m.statusRole) + '</td>'
                + '<td>' + testedCell(m) + '</td>'
                + '<td class="col-actions">'
                + '<a class="btn btn-secondary btn-sm" href="#model-edit/' + m.key + '"><svg class="ic"><use href="#i-pencil"></use></svg> Edit</a>'
                + ' <button class="btn btn-secondary btn-sm" data-dup="' + m.key + '" aria-label="Duplicate ' + m.name + '"><svg class="ic"><use href="#i-copy"></use></svg> Duplicate</button>'
                + ' <button class="btn btn-secondary btn-sm btn-text-danger" data-mdel="' + m.key + '" aria-label="Delete ' + m.name + '"><svg class="ic"><use href="#i-trash"></use></svg> Delete</button>'
                + '</td></tr>';
        }).join('');
        foot.hidden = false;
        /* The count reports the FILTERED total, never the unfiltered set (tables.md §4a). */
        $('#modelsCount').textContent = 'Showing ' + rows.length + ' of ' + rows.length;
    }, 600);
}
function mdStateRow(icon, title, text, btn, action) {
    return '<tr class="row-empty"><td colspan="7"><div class="state-block" role="' + (icon === 'warning' ? 'alert' : 'status') + '">'
        + '<span class="icon-chip chip-primary"><svg class="ic"><use href="#i-' + (icon === 'search' ? 'search' : icon === 'inbox' ? 'inbox' : 'warning') + '"></use></svg></span>'
        + '<h3>' + title + '</h3><p>' + text + '</p>'
        /* No-data-yet offers the one solid create action and an error offers the one solid Retry;
           a no-results state only clears the filters, which commits nothing, so it stays secondary. */
        + '<button class="btn ' + (action === 'clear' ? 'btn-secondary' : 'btn-primary') + '" data-md-action="' + action + '">' + btn + '</button></div></td></tr>';
}
$('#modelsBody').addEventListener('click', function (e) {
    var dup = e.target.closest('[data-dup]');
    if (dup) { toast('success', 'Duplicated as a new draft with a new key, test state reset to untested.'); return; }
    var del = e.target.closest('[data-mdel]');
    if (del) {
        var key = del.getAttribute('data-mdel');
        openModal({ title: 'Move to Recycle Bin?', body: 'Catalog entry <strong>' + key + '</strong> will be moved to the Recycle Bin. Calling code referencing it will stop resolving.', confirmLabel: 'Move to bin', danger: true })
            .then(function (ok) { if (ok) { MODELS = MODELS.filter(function (m) { return m.key !== key; }); loadModels(); toast('success', key + ' moved to Recycle Bin.'); } });
        return;
    }
    var sa = e.target.closest('[data-md-action]');
    if (sa) {
        var a = sa.getAttribute('data-md-action');
        if (a === 'retry' || a === 'clear') { if (a === 'clear') { $('#mdSearch').value = ''; $('#mdStatus').value = ''; $('#mdTested').value = ''; } mdState = 'data'; syncMdSeg(); loadModels(); }
        if (a === 'new') { navigate('model-edit'); }
    }
});
$('#mdApply').addEventListener('click', function () { mdState = 'data'; syncMdSeg(); loadModels(); });
$('#mdClear').addEventListener('click', function () { $('#mdSearch').value = ''; $('#mdStatus').value = ''; $('#mdTested').value = ''; mdState = 'data'; syncMdSeg(); loadModels(); });
$$('#mdStateSeg button').forEach(function (b) { b.addEventListener('click', function () { mdState = b.getAttribute('data-state'); syncMdSeg(); loadModels(); }); });
function syncMdSeg() { $$('#mdStateSeg button').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-state') === mdState); }); }

/* --------------------------- Typed row repeaters --------------------------- */
var BODY_TYPES = ['Text', 'Number', 'True/False', 'JSON'];

/* Rows hint with a placeholder and start empty, so nothing on the form reads as
   saved configuration. Each row still shows what belongs in its columns. */
function headerRowHTML(namePh, valuePh) {
    return '<div class="rep-row">'
        + '<div><span class="rep-lbl">Name</span><input class="field mono" data-hdr-name aria-label="Header name" placeholder="' + (namePh || 'Content-Type') + '" /></div>'
        + '<div><span class="rep-lbl">Value</span><input class="field mono" data-hdr-value aria-label="Header value" placeholder="' + (valuePh || 'application/json') + '" /></div>'
        + '<div><button class="icon-btn icon-btn-sm btn-text-danger" type="button" data-rep-remove aria-label="Remove header row (blank)"><svg class="ic"><use href="#i-x"></use></svg></button></div>'
        + '<div class="rep-msg"></div></div>';
}
function bodyRowHTML(type, keyPh, valuePh) {
    var opts = BODY_TYPES.map(function (t) { return '<option' + (t === type ? ' selected' : '') + '>' + t + '</option>'; }).join('');
    return '<div class="rep-row">'
        + '<div><span class="rep-lbl">Type</span><select class="field" data-body-type aria-label="Field type">' + opts + '</select></div>'
        + '<div><span class="rep-lbl">Key</span><input class="field mono" data-body-key aria-label="Field key" placeholder="' + (keyPh || 'max_tokens') + '" /></div>'
        + '<div><span class="rep-lbl">Value</span><input class="field mono" data-body-value aria-label="Field value" placeholder="' + (valuePh || '1024') + '" /></div>'
        + '<div><button class="icon-btn icon-btn-sm btn-text-danger" type="button" data-rep-remove aria-label="Remove body row (blank)"><svg class="ic"><use href="#i-x"></use></svg></button></div>'
        + '<div class="rep-msg"></div></div>';
}
function wireRepeater(rowsId, addId, makeRow, firstSel, labelSel, prefix) {
    var rows = $('#' + rowsId);
    $('#' + addId).addEventListener('click', function () {
        rows.insertAdjacentHTML('beforeend', makeRow());
        var f = rows.lastElementChild.querySelector(firstSel);
        if (f) { f.focus(); }
        markTestedDirty();
    });
    rows.addEventListener('click', function (e) {
        var rm = e.target.closest('[data-rep-remove]');
        if (!rm) { return; }
        var row = rm.closest('.rep-row'), lbl = row.querySelector(labelSel);
        row.remove();
        if (!rows.children.length) { rows.innerHTML = '<div class="rep-empty">No rows. Use Add row to create one.</div>'; }
        markTestedDirty();
        toast('info', 'Removed ' + prefix + ' ' + ((lbl && lbl.value) || 'row') + '.');
    });
    rows.addEventListener('input', function (e) {
        var row = e.target.closest('.rep-row');
        if (row) {
            var lbl = row.querySelector(labelSel), btn = row.querySelector('[data-rep-remove]');
            if (lbl && btn) { btn.setAttribute('aria-label', 'Remove ' + prefix + ' ' + (lbl.value || '(blank)')); }
            validateRepRow(row);
        }
        markTestedDirty();
    });
    rows.addEventListener('change', markTestedDirty);
}
/* A structured value that does not parse fails the call, named by field. */
function validateRepRow(row) {
    var typeEl = row.querySelector('[data-body-type]'), msg = row.querySelector('.rep-msg');
    if (!typeEl || !msg) { return true; }
    var val = row.querySelector('[data-body-value]').value;
    var key = row.querySelector('[data-body-key]').value || '(blank)';
    var t = typeEl.value, err = '';
    if (val.trim() !== '') {
        if (t === 'Number' && isNaN(Number(val.trim()))) { err = key + ' is typed Number but the value is not a number.'; }
        if (t === 'True/False' && ['true', 'false'].indexOf(val.trim().toLowerCase()) === -1) { err = key + ' is typed True/False but the value is neither.'; }
        if (t === 'JSON') {
            /* A quoted placeholder is matched including its surrounding quotes and
               replaced whole by the JSON encoding; a bare one by the JSON literal.
               Probe with both, in that order, so a correct row parses. */
            var probe = val.replace(/"\{\{[A-Za-z0-9_.]+\}\}"/g, '"x"').replace(/\{\{[A-Za-z0-9_.]+\}\}/g, '"x"');
            try { JSON.parse(probe); } catch (ex) { err = key + ' is typed JSON but does not parse after substitution.'; }
        }
    }
    msg.textContent = err;
    return !err;
}
function seedRepeaters() {
    $('#hdrRows').innerHTML = headerRowHTML('Content-Type', 'application/json') + headerRowHTML('Authorization', 'Bearer {{api_key}}');
    $('#bodyRows').innerHTML = bodyRowHTML('Text', 'model', '<MODEL_ID>') + bodyRowHTML('Number', 'max_tokens', '1024') + bodyRowHTML('JSON', 'messages', '[{"role":"user","content":"{{prompt}}"}]');
}
wireRepeater('hdrRows', 'hdrAdd', function () { return headerRowHTML('', ''); }, '[data-hdr-name]', '[data-hdr-name]', 'header');
wireRepeater('bodyRows', 'bodyAdd', function () { return bodyRowHTML('Text', '', ''); }, '[data-body-type]', '[data-body-key]', 'body field');

/* ----------------- The test-before-save gate on the model form ----------------- */
var modelTest = 'untested';
var lastTestResult = null;

function markTestedDirty() {
    if (modelTest === 'passed' || modelTest === 'failed') {
        modelTest = 'untested'; lastTestResult = null; renderModelEdit();
        toast('info', 'A tested field changed. The entry is untested again and cannot be saved.');
    }
}
/* One shared form for create and edit, keyed on whether the record exists, per the form
   archetype. The demo rows carry only what the list shows, so the two fields a row does not hold
   are derived here rather than shipping a half-populated edit form; a real app reads them from the
   record. */
function tkDemoEmail(req) { return req.toLowerCase().replace(/[^a-z]+/g, '.').replace(/^\.|\.$/g, '') + '@claas2saas.com'; }
function tkRowByRef(ref) {
    var hit = TK.filter(function (x) { return x.ref === ref; })[0];
    /* A row from the padded Many set is not in TK, and opening its Edit must not silently fall
       through to create mode. */
    if (!hit && tkManyCache) { hit = tkManyCache.filter(function (x) { return x.ref === ref; })[0]; }
    return hit;
}
function enterTicketForm(ref) {
    var t = tkRowByRef(ref);
    tkFormRef = t ? t.ref : '';
    $('#tkFormTitle').textContent = t ? 'Edit Ticket' : 'New Ticket';
    $('#tkFormSub').textContent = t ? 'Update the details of ' + t.ref + '.' : 'Log an incident or service request.';
    $('#tkFormSubmitLabel').textContent = t ? 'Save changes' : 'Create ticket';
    $('#fSubject').value = t ? t.subject : '';
    $('#fPriority').value = t ? t.pr : '';
    $('#fCategory').selectedIndex = 0;
    $('#fEmail').value = t ? tkDemoEmail(t.req) : '';
    $('#fDesc').value = t ? t.subject + '. Reported by ' + t.req + '.' : '';
    /* A previous visit's error state never carries into this one. */
    $$('#view-ticket-form .field-group').forEach(function (w) { w.classList.remove('invalid'); });
    $$('#view-ticket-form .field-err-msg').forEach(function (m) { m.textContent = ''; });
    $$('#view-ticket-form .field').forEach(function (f) { f.setAttribute('aria-invalid', 'false'); });
}
function enterModelEdit(key) {
    var entry = MODELS.filter(function (m) { return m.key === key; })[0];
    $('#mdEditTitle').textContent = entry ? 'Edit model' : 'Add model';
    mdEditLeaf = entry ? entry.name : 'Add model';
    /* Opening the form always shows placeholders: clear anything typed on a
       previous visit and put the repeaters back to their default rows. */
    $$('#view-model-edit input, #view-model-edit textarea').forEach(function (el) { el.value = ''; });
    $$('#view-model-edit select').forEach(function (el) { el.selectedIndex = 0; });
    seedRepeaters();
    modelTest = entry ? (entry.tested === 'passed' ? 'passed' : (entry.tested === 'failed' ? 'failed' : 'untested')) : 'untested';
    lastTestResult = (entry && entry.tested === 'passed') ? sampleResult(entry) : null;
    renderModelEdit();
}
function renderModelEdit() {
    var foot = $('#mFoot'), res = $('#mTestResult'), note = $('#mGateNote');
    if (modelTest === 'passed' && lastTestResult) {
        res.innerHTML = resultPanelHTML(lastTestResult);
        note.innerHTML = 'The test passed on the values currently on screen, so <strong>Save</strong> is available. Edit any tested field and the entry returns to untested.';
    } else if (modelTest === 'failed') {
        res.innerHTML = '<div class="result-panel"><div class="result-head">' + mdBadge('Test failed', 'danger')
            + '<span class="muted">The call was made and the provider rejected it.</span></div>'
            + '<div class="result-body"><div class="result-answer">Error path <span class="mono">error.message</span> resolved to: URL is required before a call can be made.</div></div></div>';
        note.innerHTML = 'The test failed, so this entry cannot be saved and cannot be called. Fix the configuration and test again.';
    } else if (modelTest === 'testing') {
        res.innerHTML = '<div class="result-panel"><div class="result-head">' + mdBadge('Testing', 'info')
            + '<span class="muted">Sending one real call on the current values.</span></div>'
            + '<div class="result-body"><div class="skel skel-line" style="width:70%;margin-bottom:8px"></div><div class="skel skel-line" style="width:45%"></div></div></div>';
        note.textContent = 'A test call is in flight. Nothing is saved by testing.';
    } else {
        res.innerHTML = '<div class="result-panel"><div class="result-head">' + mdBadge('Never tested', 'warning')
            + '<span class="muted">No call has been made on these values.</span></div>'
            + '<div class="result-body"><p class="muted" style="margin:0">Run <strong>Test call</strong> to verify every path against a real response. Verify paths against the raw response, not against documented shapes.</p></div></div>';
        note.innerHTML = 'This entry has not passed a test call, so it cannot be saved and cannot be called. There is no save-anyway path.';
    }
    var html = '<button class="btn btn-secondary" type="button" id="mReset"' + (modelTest === 'testing' ? ' disabled' : '') + '>Reset</button>';
    if (modelTest === 'testing') {
        html += '<button class="btn btn-secondary" type="button" disabled aria-busy="true"><span class="spinner"></span> Testing...</button>';
    } else {
        html += '<button class="btn btn-secondary" type="button" id="mTest"><svg class="ic"><use href="#i-zap"></use></svg> Test call</button>';
    }
    if (modelTest === 'passed') { html += '<button class="btn btn-primary" type="button" id="mSave"><svg class="ic"><use href="#i-check"></use></svg> Save</button>'; }
    foot.innerHTML = html;
    if ($('#mReset')) { $('#mReset').addEventListener('click', function () { modelTest = 'untested'; lastTestResult = null; renderModelEdit(); toast('info', 'Reverted to the last saved values.'); }); }
    if ($('#mTest')) { $('#mTest').addEventListener('click', runTestCall); }
    if ($('#mSave')) { $('#mSave').addEventListener('click', function () { toast('success', 'Entry saved with its test outcome and timestamp recorded.'); navigate('models'); }); }
}
function runTestCall() {
    var bad = $$('#bodyRows .rep-row').filter(function (r) { return !validateRepRow(r); })[0];
    if (bad) {
        toast('error', 'A structured field does not parse. Fix it before the call is made.');
        var f = bad.querySelector('[data-body-value]');
        if (f) { f.focus(); }
        return;
    }
    modelTest = 'testing';
    renderModelEdit();
    setTimeout(function () {
        if ($('#mUrl').value.trim()) {
            modelTest = 'passed';
            lastTestResult = sampleResult({ name: $('#mName').value || 'new entry', priceIn: parseFloat($('#mPriceIn').value), priceOut: parseFloat($('#mPriceOut').value) });
            toast('success', 'Test call succeeded. Save is now available.');
        } else {
            modelTest = 'failed'; lastTestResult = null;
            toast('error', 'Test call failed. A URL is required.');
        }
        renderModelEdit();
    }, 1200);
}
$$('#view-model-edit [data-tested-field]').forEach(function (el) {
    el.addEventListener('input', markTestedDirty);
    el.addEventListener('change', markTestedDirty);
});

/* ---------------------- The shared call-result panel ---------------------- */
function sampleResult(entry) {
    var tin = 412, tout = 128;
    var pIn = isNaN(entry.priceIn) ? null : entry.priceIn;
    var pOut = isNaN(entry.priceOut) ? null : entry.priceOut;
    var cIn = costOf(tin, pIn), cOut = costOf(tout, pOut);
    return {
        label: entry.name, answer: 'ready', tokensIn: tin, tokensOut: tout,
        cost: (cIn === null || cOut === null) ? null : cIn + cOut,
        ms: 940, finish: 'stop', pathMiss: false, correlation: 'c2s-4f19a7',
        raw: '{\n  "id": "<RESPONSE_ID>",\n  "model": "<MODEL_ID>",\n  "choices": [\n    { "index": 0, "message": { "role": "assistant", "content": "ready" }, "finish_reason": "stop" }\n  ],\n  "usage": { "prompt_tokens": 412, "completion_tokens": 128 }\n}'
    };
}
/* Provider text is escaped: model output is text, never markup. */
function escOut(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
function resultPanelHTML(r) {
    return '<div class="result-panel">'
        + '<div class="result-head">' + mdBadge('Call succeeded', 'success')
        + '<span class="muted" style="font-size:12px">' + escOut(r.label) + '</span>'
        + '<span class="muted" style="font-size:12px">' + r.ms + ' ms</span>'
        + '<span class="muted" style="font-size:12px">finish: <span class="mono">' + escOut(r.finish) + '</span></span>'
        + '<span class="muted" style="font-size:12px">correlation <span class="mono">' + escOut(r.correlation) + '</span></span>'
        + '</div><div class="result-body">'
        + '<div class="result-answer">' + escOut(r.answer) + '</div>'
        + '<div class="help-note" style="margin-top:7px">Rendered as text, never as markup. The catalog owns the envelope up to the content path; what the model generated inside it belongs to the calling code.</div>'
        + '<div class="usage-strip">'
        + '<div class="usage-cell"><span class="k">Tokens in</span><span class="v">' + r.tokensIn + '</span></div>'
        + '<div class="usage-cell"><span class="k">Tokens out</span><span class="v">' + r.tokensOut + '</span></div>'
        + '<div class="usage-cell"><span class="k">Cost</span><span class="v">' + fmtMicros(r.cost) + '</span></div>'
        + '<div class="usage-cell"><span class="k">Duration</span><span class="v">' + r.ms + ' ms</span></div>'
        + '</div>'
        + '<details class="raw-box"><summary>Raw response (always returned untouched)</summary><pre class="mono">' + escOut(r.raw) + '</pre></details>'
        + '</div></div>';
}

/* ------------- Email channel: the same test-before-save contract ------------- */
var emState = 'untested';
function renderEmailGate() {
    var st = $('#emStatus'), foot = $('#emFoot');
    if (emState === 'passed') { st.innerHTML = mdBadge('Connected', 'success') + ' <span class="muted" style="font-size:12px">tested on the values currently on screen</span>'; }
    else if (emState === 'failed') { st.innerHTML = mdBadge('Connection failed', 'danger') + ' <span class="muted" style="font-size:12px">mailbox did not respond within the timeout</span>'; }
    else if (emState === 'testing') { st.innerHTML = mdBadge('Testing', 'info') + ' <span class="muted" style="font-size:12px">running a real connection check</span>'; }
    else { st.innerHTML = mdBadge('Never tested', 'warning') + ' <span class="muted" style="font-size:12px">a successful test is required before saving</span>'; }

    var html = '<button class="btn btn-secondary" type="button" id="emReset"' + (emState === 'testing' ? ' disabled' : '') + '>Reset</button>';
    if (emState === 'testing') { html += '<button class="btn btn-secondary" type="button" disabled aria-busy="true"><span class="spinner"></span> Testing...</button>'; }
    else { html += '<button class="btn btn-secondary" type="button" id="emTest"><svg class="ic"><use href="#i-zap"></use></svg> Test Configuration</button>'; }
    if (emState === 'passed') { html += '<button class="btn btn-primary" type="button" id="emSave"><svg class="ic"><use href="#i-check"></use></svg> Save</button>'; }
    foot.innerHTML = html;

    if ($('#emReset')) { $('#emReset').addEventListener('click', function () { emState = 'untested'; renderEmailGate(); toast('info', 'Reverted to the last saved values.'); }); }
    if ($('#emTest')) {
        $('#emTest').addEventListener('click', function () {
            emState = 'testing'; renderEmailGate();
            setTimeout(function () {
                emState = $('#emAddr').value.trim() ? 'passed' : 'failed';
                renderEmailGate();
                if (emState === 'passed') { toast('success', 'Mailbox connected. Save is now available.'); }
                else { toast('error', 'Connection failed. A support address is required.'); }
            }, 1100);
        });
    }
    if ($('#emSave')) { $('#emSave').addEventListener('click', function () { toast('success', 'Email channel saved.'); }); }
}
$$('#view-ch-email [data-tested-field]').forEach(function (el) {
    var reset = function () {
        if (emState === 'passed' || emState === 'failed') {
            emState = 'untested'; renderEmailGate();
            toast('info', 'A tested field changed. Test again before saving.');
        }
    };
    el.addEventListener('input', reset);
    el.addEventListener('change', reset);
});

/* Declared above the boot render() below: the first render reads these, and a var is not
   hoisted the way a function declaration is. */
var tkFormRef = '';
var mdEditLeaf = 'Add model';

/* Breadcrumbs are generated from NAV, the same config the sidebar renders, so a trail can never
   drift from the tree and no page hand-writes its own path. It is deliberately plain: the full
   path from the cluster down, saying where you are, with every ancestor that is a real page as a
   link so one click steps back. A cluster or an accordion group is a heading with no page of its
   own, so it reads as text.
   A record page and a record form are not nav leaves themselves. A record page declares its list
   here; a form declares it on the container, since a form is a sibling of its list in the menu
   rather than a child of it. Either way the list joins the trail as the segment you click to get
   back, so no page needs a separate back link. */
var CRUMB_PARENT = { ticket: 'tickets', 'model-edit': 'models' };

function navTrail(route) {
    function walk(nodes, trail) {
        for (var i = 0; i < nodes.length; i++) {
            var n = nodes[i];
            if (n.type === 'group') {
                var hit = walk(n.children, trail.concat([{ label: n.label }]));   /* a heading, no route */
                if (hit) { return hit; }
            } else if (n.route === route) {
                return trail.concat([{ label: n.label, route: n.route }]);
            }
        }
        return null;
    }
    for (var s = 0; s < NAV.length; s++) {
        var found = walk(NAV[s].nodes, [{ label: NAV[s].label }]);
        if (found) { return found; }
    }
    return [];
}

function renderCrumbs(viewId, route, leafLabel) {
    var el = $('#' + viewId + ' [data-crumb]');
    if (!el) { return; }
    var parent = CRUMB_PARENT[route];
    var trail = navTrail(parent || route);
    if (parent) { trail = trail.concat([{ label: leafLabel || route }]); }
    else if (leafLabel) { trail = trail.slice(0, -1).concat([{ label: leafLabel }]); }
    var declared = el.getAttribute('data-parent');
    if (declared) {
        var already = false;
        for (var d = 0; d < trail.length; d++) { if (trail[d].route === declared) { already = true; } }
        if (!already) {
            var up = navTrail(declared).slice(-1)[0];
            if (up) { trail = trail.slice(0, -1).concat([up, trail[trail.length - 1]]); }
        }
    }
    /* A page whose whole path is its cluster and itself needs no trail; the sidebar already says
       that much. The line earns its place once the page sits inside a group. */
    if (trail.length < 3) { el.innerHTML = ''; return; }
    var h = '<ol>';
    for (var i = 0; i < trail.length; i++) {
        var c = trail[i], last = i === trail.length - 1;
        h += '<li>' + (i ? '<span class="sep" aria-hidden="true">/</span>' : '');
        if (last) { h += '<span class="cur" aria-current="page">' + c.label + '</span>'; }
        else if (c.route && c.route !== route) { h += '<a href="#' + c.route + '">' + c.label + '</a>'; }
        else { h += '<span>' + c.label + '</span>'; }
        h += '</li>';
    }
    el.innerHTML = h + '</ol>';
}

$('#roleSelect').value = currentRole; document.body.setAttribute('data-role', currentRole); applyTheme(); seedRepeaters(); syncMdSeg(); renderDashRecent(); syncStateSeg(); setCollapsed(store.get('c2s-collapsed', false)); renderRail(); render(); render._ready = true; hideIcons(); if (window.MutationObserver) { new MutationObserver(function (muts) { for (var i = 0; i < muts.length; i++) { var an = muts[i].addedNodes; for (var j = 0; j < an.length; j++) { var n = an[j]; if (n.nodeType !== 1) continue; if (n.classList && n.classList.contains('ic')) n.setAttribute('aria-hidden', 'true'); if (n.querySelectorAll) { var q = n.querySelectorAll('.ic'); for (var k = 0; k < q.length; k++) q[k].setAttribute('aria-hidden', 'true'); } } } }).observe(document.body, { childList: true, subtree: true }); }

/* Sortable list columns (tables.md §4). The demo holds its whole result set in memory, so it sorts
   the array before rendering - never just the rows already in the DOM. A server-fed list sends the
   column and direction as query parameters instead and takes the ordered page back. */
var PR_RANK = { Critical: 4, High: 3, Medium: 2, Low: 1 };
function sortTickets(rows) {
    var k = tkSort.key, sign = tkSort.dir === 'asc' ? 1 : -1;
    return rows.slice().sort(function (a, b) {
        var x = a[k], y = b[k];
        if (k === 'pr') { return ((PR_RANK[x] || 0) - (PR_RANK[y] || 0)) * sign; }
        return String(x).localeCompare(String(y), undefined, { numeric: true }) * sign;
    });
}
function syncSortHeaders() {
    $$('#view-tickets thead th[aria-sort]').forEach(function (th) {
        var btn = th.querySelector('.th-sort');
        var on = btn && btn.getAttribute('data-sort') === tkSort.key;
        th.setAttribute('aria-sort', on ? (tkSort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
}
$$('#view-tickets .th-sort').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-sort');
        if (tkSort.key === key) { tkSort.dir = tkSort.dir === 'asc' ? 'desc' : 'asc'; }
        else { tkSort.key = key; tkSort.dir = 'asc'; }
        tkPage = 1;                                       /* a new sort re-windows from the top */
        syncSortHeaders();
        loadTickets();                                    /* re-query, then re-render */
    });
});
syncSortHeaders();

/* Same sort contract on the AI Providers list. Default: name ascending, per catalog-ui.md. */
function sortModels(rows) {
    var k = mdSort.key, sign = mdSort.dir === 'asc' ? 1 : -1;
    return rows.slice().sort(function (a, b) {
        var x = a[k], y = b[k];
        if (k === 'priceIn') {                        /* unknown price sorts last, never as zero */
            if (x === null && y === null) return 0;
            if (x === null) return 1;
            if (y === null) return -1;
            return (x - y) * sign;
        }
        if (k === 'tested') {                         /* order by WHEN it was tested, never tested last */
            x = a.testedAt; y = b.testedAt;
            if (!x && !y) return 0;
            if (!x) return 1;
            if (!y) return -1;
            return (x < y ? -1 : x > y ? 1 : 0) * sign;
        }
        return String(x).localeCompare(String(y), undefined, { numeric: true }) * sign;
    });
}
function syncMdSortHeaders() {
    $$('#view-models thead th[aria-sort]').forEach(function (th) {
        var btn = th.querySelector('.th-sort');
        var on = btn && btn.getAttribute('data-sort') === mdSort.key;
        th.setAttribute('aria-sort', on ? (mdSort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
    });
}
$$('#view-models .th-sort').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-sort');
        if (mdSort.key === key) { mdSort.dir = mdSort.dir === 'asc' ? 'desc' : 'asc'; }
        else { mdSort.key = key; mdSort.dir = 'asc'; }
        syncMdSortHeaders();
        loadModels();
    });
});
syncMdSortHeaders();

/* ===== List state in the URL (tables.md §4a) =====
   Sort and filter state belongs in the URL query, so a refresh, the back button, a bookmark and a link
   pasted to a colleague all reproduce the same view. This mockup has no server router, so it carries the
   query in the hash the router already reads; a real app puts it in the query string and reads it there.
   Written with replaceState so updating the view does not push a history entry per keystroke. */
function hashQuery() {
    var h = location.hash.replace(/^#/, ''), i = h.indexOf('?'), out = {};
    if (i === -1) return out;
    h.slice(i + 1).split('&').forEach(function (pair) {
        if (!pair) return;
        var kv = pair.split('=');
        out[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' '));
    });
    return out;
}
function writeListState(route, params) {
    var parts = [];
    Object.keys(params).forEach(function (k) {
        var v = params[k];
        if (v !== '' && v !== null && v !== undefined) {
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
        }
    });
    var next = '#' + route + (parts.length ? '?' + parts.join('&') : '');
    if (location.hash !== next) history.replaceState(null, '', next);   /* no hashchange, so no re-render */
}
function applyTicketsUrlState() {
    var q = hashQuery();
    $('#tkSearch').value = q.q || '';
    $('#tkStatus').value = q.status || '';
    $('#tkPriority').value = q.priority || '';
    tkSort.key = q.sort || 'ref';
    tkSort.dir = q.dir === 'desc' ? 'desc' : 'asc';
    tkPage = Math.max(1, parseInt(q.page, 10) || 1);
    tkPageSize = TK_PAGE_SIZES.indexOf(parseInt(q.size, 10)) !== -1 ? parseInt(q.size, 10) : TK_PAGE_SIZES[0];
    syncSortHeaders();
}
function syncTicketsUrl() {
    var atDefault = tkSort.key === 'ref' && tkSort.dir === 'asc';       /* the default sort stays implicit */
    writeListState('tickets', {
        q: $('#tkSearch').value.trim(),
        status: $('#tkStatus').value,
        priority: $('#tkPriority').value,
        sort: atDefault ? '' : tkSort.key,
        dir: atDefault ? '' : tkSort.dir,
        page: tkPage > 1 ? tkPage : '',                   /* page 1 and the default size stay implicit */
        size: tkPageSize !== TK_PAGE_SIZES[0] ? tkPageSize : ''
    });
}
function applyModelsUrlState() {
    var q = hashQuery();
    $('#mdSearch').value = q.q || '';
    $('#mdStatus').value = q.status || '';
    $('#mdTested').value = q.test || '';
    mdSort.key = q.sort || 'name';
    mdSort.dir = q.dir === 'desc' ? 'desc' : 'asc';
    syncMdSortHeaders();
}
function syncModelsUrl() {
    var atDefault = mdSort.key === 'name' && mdSort.dir === 'asc';
    writeListState('models', {
        q: $('#mdSearch').value.trim(),
        status: $('#mdStatus').value,
        test: $('#mdTested').value,
        sort: atDefault ? '' : mdSort.key,
        dir: atDefault ? '' : mdSort.dir
    });
}

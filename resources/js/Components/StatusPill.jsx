export default function StatusPill({ status }) {
    return <span className={`org-pill org-pill-${status}`}>{status === 'active' ? 'Active' : 'Inactive'}</span>
}

import { router } from '@inertiajs/react'

/**
 * Page controls for a server-paginated list.
 *
 * The first version of these screens printed "Page 1 of 4" and nothing else,
 * which states that more exists and offers no way to reach it. A count is not
 * navigation.
 *
 * Rendered only when there is more than one page: a single page needs no
 * controls, and two greyed arrows under every short list are noise.
 */
export default function Pagination({ path, query, page, lastPage, total, noun }) {
    const go = (target) => {
        router.get(path, { ...query, page: target }, { preserveState: true, preserveScroll: true })
    }

    if (lastPage <= 1) {
        return (
            <p className="org-meta">
                {total} {total === 1 ? noun.one : noun.many}
            </p>
        )
    }

    return (
        <nav className="org-pagination" aria-label={`${noun.many} pages`}>
            <button type="button" className="org-action" disabled={page <= 1} onClick={() => go(page - 1)}>
                Previous
            </button>

            <span className="org-meta">
                Page {page} of {lastPage} &middot; {total} {total === 1 ? noun.one : noun.many}
            </span>

            <button type="button" className="org-action" disabled={page >= lastPage} onClick={() => go(page + 1)}>
                Next
            </button>
        </nav>
    )
}

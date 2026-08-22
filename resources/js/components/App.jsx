import { useEffect, useState } from "react";

/**
 * The application's temporary landing screen.
 *
 * It keeps the scaffold's two API probes as a deployment smoke test, but is
 * built entirely from the approved design system in resources/css/app.css: no
 * component-level colors, no inline stylesheet, and no hardcoded hex. It is
 * replaced by the real shell and sign-in route as those land.
 */
export default function App() {
    const [query, setQuery] = useState("");
    const [getResult, setGetResult] = useState(null);
    const [getError, setGetError] = useState(null);
    const [getLoading, setGetLoading] = useState(false);

    const [name, setName] = useState("");
    const [postResult, setPostResult] = useState(null);
    const [postError, setPostError] = useState(null);
    const [postLoading, setPostLoading] = useState(false);

    const callGet = () => {
        setGetLoading(true);
        setGetError(null);
        const url = query
            ? `/api/example?q=${encodeURIComponent(query)}`
            : "/api/example";
        fetch(url)
            .then((response) => response.json())
            .then(setGetResult)
            .catch((err) => setGetError(err.message))
            .finally(() => setGetLoading(false));
    };

    useEffect(() => {
        callGet();
    }, []);

    const callPost = (event) => {
        event.preventDefault();
        setPostLoading(true);
        setPostError(null);
        fetch("/api/echo", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ name }),
        })
            .then((response) => response.json())
            .then(setPostResult)
            .catch((err) => setPostError(err.message))
            .finally(() => setPostLoading(false));
    };

    return (
        <main className="page">
            <div className="card" style={{ width: "100%", maxWidth: "480px", padding: "24px" }}>
                <div className="stack">
                    <header className="stack-tight">
                        <h1>CLaaS2SaaS SemantIQ</h1>
                        <p className="text-muted">
                            Environment check. The design system is loaded; the application
                            shell is not built yet.
                        </p>
                    </header>

                    <Probe
                        method="GET"
                        endpoint="/api/example"
                        value={query}
                        onChange={setQuery}
                        placeholder="Query parameter, for example hello"
                        buttonLabel="Send request"
                        loading={getLoading}
                        error={getError}
                        onSubmit={(event) => {
                            event.preventDefault();
                            callGet();
                        }}
                        result={getResult}
                        resultLabel="Query"
                        resultValue={getResult?.query}
                    />

                    <Probe
                        method="POST"
                        endpoint="/api/echo"
                        value={name}
                        onChange={setName}
                        placeholder="Data to send"
                        buttonLabel="Send request"
                        loading={postLoading}
                        error={postError}
                        onSubmit={callPost}
                        result={postResult}
                        resultLabel="Received"
                        resultValue={postResult?.received}
                    />
                </div>
            </div>
        </main>
    );
}

/**
 * One API probe: a labeled endpoint, a field with its send button, and the
 * outcome. The send button is the group's one solid action; nothing else in the
 * group is solid.
 */
function Probe({
    method,
    endpoint,
    value,
    onChange,
    placeholder,
    buttonLabel,
    loading,
    error,
    onSubmit,
    result,
    resultLabel,
    resultValue,
}) {
    const fieldId = `probe-${method.toLowerCase()}`;

    return (
        <section className="stack-tight divide-top">
            <div className="stack-tight">
                <span>
                    <span className={`badge badge-${method === "GET" ? "success" : "warning"}`}>
                        {method}
                    </span>{" "}
                    <span className="text-small text-muted">{endpoint}</span>
                </span>
                <label className="text-small" htmlFor={fieldId}>
                    Request value
                </label>
            </div>

            <form className="field-row" onSubmit={onSubmit}>
                <input
                    id={fieldId}
                    className="input"
                    type="text"
                    value={value}
                    placeholder={placeholder}
                    onChange={(event) => onChange(event.target.value)}
                />
                <button
                    className="btn btn-solid btn-primary"
                    type="submit"
                    disabled={loading}
                    aria-busy={loading || undefined}
                >
                    {buttonLabel}
                </button>
            </form>

            {error && (
                <p className="text-small" style={{ color: "var(--badge-danger-fg)" }}>
                    {error}
                </p>
            )}

            {result && (
                <div className="stack-tight">
                    <span>
                        <span className="badge badge-success">{result.status ?? "success"}</span>{" "}
                        <span className="text-xs text-muted">{result.timestamp}</span>
                    </span>
                    <p className="text-small">{result.message}</p>
                    <p className="text-small text-muted">
                        {resultLabel}: <strong>{resultValue || "—"}</strong>
                    </p>
                </div>
            )}
        </section>
    );
}

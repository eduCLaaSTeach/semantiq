import { useEffect, useState } from "react";

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
        <div className="app">
            <style>{css}</style>

            <div className="card">
                <header className="card__header">
                    <h1 className="card__title">Laravel + React</h1>
                    <p className="card__subtitle">API connection playground</p>
                </header>

                <div className="card__body">
                    <section className="panel">
                        <div className="panel__head">
                            <span className="badge badge--get">GET</span>
                            <span className="endpoint">/api/example</span>
                        </div>
                        <form
                            className="row"
                            onSubmit={(event) => {
                                event.preventDefault();
                                callGet();
                            }}
                        >
                            <input
                                className="input"
                                type="text"
                                value={query}
                                placeholder="Query param, e.g. hello (?q=)"
                                onChange={(event) => setQuery(event.target.value)}
                            />
                            <button className="btn" type="submit" disabled={getLoading}>
                                {getLoading ? "…" : "Send"}
                            </button>
                        </form>
                        {getError && <p className="error">{getError}</p>}
                        {getResult && (
                            <Result data={getResult} label="Query" value={getResult.query} />
                        )}
                    </section>

                    <section className="panel">
                        <div className="panel__head">
                            <span className="badge badge--post">POST</span>
                            <span className="endpoint">/api/echo</span>
                        </div>
                        <form className="row" onSubmit={callPost}>
                            <input
                                className="input"
                                type="text"
                                value={name}
                                placeholder="Data to send…"
                                onChange={(event) => setName(event.target.value)}
                            />
                            <button className="btn" type="submit" disabled={postLoading}>
                                {postLoading ? "…" : "Send"}
                            </button>
                        </form>
                        {postError && <p className="error">{postError}</p>}
                        {postResult && (
                            <Result data={postResult} label="Received" value={postResult.received} />
                        )}
                    </section>
                </div>
            </div>
        </div>
    );
}

function Result({ data, label, value }) {
    return (
        <div className="result">
            <div className="result__top">
                <span className="status">
                    <span className="status__dot" />
                    {data.status ?? "success"}
                </span>
                <span className="time">{data.timestamp}</span>
            </div>
            <p className="result__msg">{data.message}</p>
            <p className="field">
                <span>{label}</span>
                <span className="field__val">{value || "—"}</span>
            </p>
        </div>
    );
}

const css = `
* { box-sizing: border-box; }
html, body, #root { margin: 0; height: 100%; }
body {
    overflow: hidden;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.app {
    width: 100dvw;
    height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    overflow: auto;
    background: radial-gradient(1200px 600px at 50% -10%, #1e293b, #0f172a);
}

.card {
    width: 100%;
    max-width: 460px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(2, 6, 23, 0.45);
    overflow: hidden;
}

.card__header {
    padding: 22px 24px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
}
.card__title { margin: 0; font-size: 19px; font-weight: 700; letter-spacing: -0.01em; }
.card__subtitle { margin: 4px 0 0; font-size: 13px; color: rgba(255, 255, 255, 0.82); }

.card__body { padding: 8px 24px 24px; }

.panel { padding: 18px 0; }
.panel + .panel { border-top: 1px solid #eef2f7; }

.panel__head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.badge {
    font: 700 11px/1 ui-monospace, monospace;
    letter-spacing: 0.06em;
    padding: 5px 8px;
    border-radius: 6px;
    color: #ffffff;
}
.badge--get { background: #059669; }
.badge--post { background: #ea580c; }
.endpoint { font: 500 13px/1 ui-monospace, "SF Mono", Menlo, monospace; color: #475569; }

.row { display: flex; gap: 8px; }
.input {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 14px;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.input::placeholder { color: #94a3b8; }
.input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.16); }

.btn {
    padding: 10px 16px;
    border: 0;
    border-radius: 9px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.05s;
}
.btn:hover { background: #4338ca; }
.btn:active { transform: translateY(1px); }
.btn:disabled { background: #c7d2fe; cursor: default; }

.result {
    margin-top: 14px;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    background: #f8fafc;
    padding: 12px 14px;
}
.result__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: #059669;
    text-transform: capitalize;
}
.status__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
}
.time { font: 500 12px/1 ui-monospace, monospace; color: #94a3b8; }
.result__msg { margin: 0 0 8px; font-size: 14px; color: #0f172a; }
.field { display: flex; gap: 8px; margin: 0; font-size: 13px; color: #64748b; }
.field__val { color: #0f172a; font-weight: 600; word-break: break-word; }

.error { margin: 10px 0 0; font-size: 13px; color: #dc2626; }
`;

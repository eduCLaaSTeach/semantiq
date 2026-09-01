import { useEffect, useState } from 'react'
import Icon from './Icon'

/**
 * System / Light / Dark, per the shared standard (D-20).
 *
 * There is one theme architecture and this is it. The tokens already define
 * both themes; this only chooses which set applies:
 *
 *   System -> no data-theme attribute, so prefers-color-scheme decides
 *   Light  -> data-theme="light"
 *   Dark   -> data-theme="dark"
 *
 * The same key is read by an inline script in the root view before first paint,
 * so a dark-mode reload never flashes light.
 */
const KEY = 'semantiq.theme'

const OPTIONS = [
    { value: 'system', label: 'System', icon: 'i-monitor' },
    { value: 'light', label: 'Light', icon: 'i-sun' },
    { value: 'dark', label: 'Dark', icon: 'i-moon' },
]

function read() {
    try {
        const stored = localStorage.getItem(KEY)

        return stored === 'light' || stored === 'dark' ? stored : 'system'
    } catch (e) {
        // Storage can be unavailable. System is the correct fallback.
        return 'system'
    }
}

function apply(theme) {
    const root = document.documentElement

    if (theme === 'system') {
        root.removeAttribute('data-theme')
    } else {
        root.setAttribute('data-theme', theme)
    }

    try {
        theme === 'system' ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, theme)
    } catch (e) {
        // A preference that cannot be stored still applies for this visit.
    }
}

export default function ThemeSwitcher() {
    const [theme, setTheme] = useState('system')

    // Read on mount rather than during render: the server has no localStorage,
    // and reading it during render would differ between server and client.
    useEffect(() => setTheme(read()), [])

    const choose = (value) => {
        apply(value)
        setTheme(value)
    }

    return (
        <div className="theme-switcher" role="group" aria-label="Appearance">
            {OPTIONS.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    className="theme-option"
                    aria-pressed={theme === option.value}
                    title={option.label}
                    onClick={() => choose(option.value)}
                >
                    <Icon name={option.icon} />
                    <span className="sr-only">{option.label}</span>
                </button>
            ))}
        </div>
    )
}

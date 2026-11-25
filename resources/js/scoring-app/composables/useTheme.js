// Dark/light theme for the scoring app. Dark is the default so nothing
// changes for existing users. "sun" mode is a per-device preference,
// persisted in localStorage, applied as `data-theme` on <html> so the
// CSS variable swap in scoring.css takes effect.
//
// Why localStorage (not a server round-trip): the Android WebView is
// often offline mid-match, and this is a tablet-level setting — the
// same match director tablet is bright, the next tablet in the
// bag is dark, same account signed in on both.

import { ref } from 'vue';

const STORAGE_KEY = 'deadcenter_theme';
const DEFAULT_THEME = 'dark';

export const currentTheme = ref(DEFAULT_THEME);

function readStored() {
    if (typeof window === 'undefined') return DEFAULT_THEME;
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return stored === 'light' || stored === 'dark' ? stored : DEFAULT_THEME;
    } catch (_err) {
        // Private mode / disabled storage — fall back silently.
        return DEFAULT_THEME;
    }
}

function writeStored(value) {
    if (typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch (_err) {
        // Non-fatal — the toggle still works for this session.
    }
}

function applyToDocument(value) {
    if (typeof document === 'undefined') return;
    document.documentElement.dataset.theme = value;
    document.documentElement.style.colorScheme = value;
}

/**
 * Call once at app boot, before Vue mounts, so the CSS variable
 * swap runs on the first paint and nobody sees a flash of dark
 * content on a tablet that is set to light.
 */
export function initTheme() {
    const value = readStored();
    currentTheme.value = value;
    applyToDocument(value);
}

export function setTheme(value) {
    const next = value === 'light' ? 'light' : 'dark';
    currentTheme.value = next;
    writeStored(next);
    applyToDocument(next);
}

export function toggleTheme() {
    setTheme(currentTheme.value === 'light' ? 'dark' : 'light');
}

export function useTheme() {
    return { currentTheme, setTheme, toggleTheme };
}

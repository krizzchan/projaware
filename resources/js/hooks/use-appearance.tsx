import { useEffect, useSyncExternalStore } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

const validAppearance = (value: unknown): Appearance => (value === 'light' || value === 'dark' ? value : 'system');
const readAppearance = (): Appearance => {
    try {
        return typeof window === 'undefined' ? 'system' : validAppearance(window.localStorage.getItem('appearance'));
    } catch {
        return 'system';
    }
};
let currentAppearance = readAppearance();
let initialized = false;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());
const subscribe = (listener: () => void) => {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
};
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyTheme = (appearance: Appearance) => {
    if (typeof document === 'undefined') return;
    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

export function initializeTheme() {
    if (typeof window === 'undefined' || initialized) return;
    initialized = true;
    currentAppearance = readAppearance();
    applyTheme(currentAppearance);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => applyTheme(currentAppearance));
    window.addEventListener('storage', (event) => {
        if (event.key === 'appearance' || event.key === null) {
            currentAppearance = event.key === null ? 'system' : validAppearance(event.newValue);
            applyTheme(currentAppearance);
            notify();
        }
    });
    notify();
}

export function useAppearance() {
    const appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system' as Appearance,
    );

    const updateAppearance = (mode: Appearance) => {
        currentAppearance = validAppearance(mode);
        try {
            window.localStorage.setItem('appearance', currentAppearance);
        } catch {
            // Keep the theme usable when the browser blocks persistent storage.
        }
        applyTheme(currentAppearance);
        notify();
    };

    useEffect(initializeTheme, []);

    return { appearance, updateAppearance };
}

function applyTheme(isDark: boolean): void {
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const syncTheme = (): void => applyTheme(systemTheme.matches);

    syncTheme();
    systemTheme.addEventListener('change', syncTheme);
}

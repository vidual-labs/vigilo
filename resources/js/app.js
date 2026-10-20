// Livewire 3 ships Alpine and starts both; nothing to import here.

// Theme switcher: 'light' | 'dark' | 'system', stored per browser.
window.vigiloTheme = {
    get() {
        try {
            return localStorage.getItem('vigilo-theme') || 'system';
        } catch {
            return 'system';
        }
    },
    set(theme) {
        try {
            localStorage.setItem('vigilo-theme', theme);
        } catch {
            // private mode: the choice just isn't remembered
        }
        this.apply(theme);
    },
    apply(theme) {
        const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    },
};

const KEY = '_x_theme';

export function applyTheme() {
    let choice = 'system';
    try {
        choice = localStorage.getItem(KEY) || 'system';
    } catch (e) {}

    const dark = choice === 'dark' || (choice === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('themeSwitcher', () => ({
        choice: 'system',
        init() {
            try {
                this.choice = localStorage.getItem(KEY) || 'system';
            } catch (e) {}
        },
        set(value) {
            this.choice = value;
            try {
                localStorage.setItem(KEY, value);
            } catch (e) {}
            applyTheme();
        },
    }));
});

document.addEventListener('livewire:navigated', applyTheme);
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);

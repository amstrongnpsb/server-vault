import { computed, onMounted, ref } from 'vue';

const STORAGE_KEY = 'server-vault-theme';

// Shared reactive theme state
const theme = ref('light');
let initialized = false;

export function useTheme() {
    const prefersDark = () =>
        window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;

    const applyTheme = (value) => {
        const nextTheme =
            value === 'system' ? (prefersDark() ? 'dark' : 'light') : value;

        document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    };

    const isDark = computed(() => {
        if (theme.value === 'system') {
            return prefersDark();
        }

        return theme.value === 'dark';
    });

    const currentTheme = computed(() => {
        if (theme.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }
        return theme.value;
    });

    const toggleTheme = () => {
        theme.value = isDark.value ? 'light' : 'dark';
        localStorage.setItem(STORAGE_KEY, theme.value);
        applyTheme(theme.value);
    };

    const initTheme = () => {
        if (initialized) return;
        initialized = true;
        
        theme.value = localStorage.getItem(STORAGE_KEY) || 'system';
        applyTheme(theme.value);

        // Listen for system theme changes
        window
            .matchMedia?.('(prefers-color-scheme: dark)')
            .addEventListener('change', () => {
                if (theme.value === 'system') {
                    applyTheme(theme.value);
                }
            });
    };

    return {
        theme,
        isDark,
        currentTheme,
        toggleTheme,
        initTheme,
    };
}

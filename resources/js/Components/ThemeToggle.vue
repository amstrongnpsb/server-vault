<script setup>
import { computed, onMounted, ref } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';

const storageKey = 'server-vault-theme';
const theme = ref('light');

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

const toggleTheme = () => {
    theme.value = isDark.value ? 'light' : 'dark';
    localStorage.setItem(storageKey, theme.value);
    applyTheme(theme.value);
};

onMounted(() => {
    theme.value = localStorage.getItem(storageKey) || 'system';
    applyTheme(theme.value);

    window
        .matchMedia?.('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (theme.value === 'system') {
                applyTheme(theme.value);
            }
        });
});
</script>

<template>
    <button
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-background text-foreground transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
        :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        @click="toggleTheme"
    >
        <Sun v-if="isDark" class="h-4 w-4" aria-hidden="true" />
        <Moon v-else class="h-4 w-4" aria-hidden="true" />
    </button>
</template>

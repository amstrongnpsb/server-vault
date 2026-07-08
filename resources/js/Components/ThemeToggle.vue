<script setup>
import { onMounted } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme, initTheme } = useTheme();

onMounted(() => {
    initTheme();
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

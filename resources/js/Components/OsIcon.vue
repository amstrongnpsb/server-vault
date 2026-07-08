<script setup>
import { computed } from 'vue';
import { Server } from 'lucide-vue-next';

const props = defineProps({
    os: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'h-4 w-4',
    },
});

// Get icon path and color based on OS
const osConfig = computed(() => {
    const configs = {
        'Ubuntu': {
            color: 'text-orange-500',
            useSvg: true,
            svg: `<svg viewBox="0 0 24 24" fill="currentColor">
                <circle cx="19.3" cy="5.3" r="2.2"/>
                <circle cx="6.1" cy="19.3" r="2.2"/>
                <circle cx="19.3" cy="19.3" r="2.2"/>
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
            </svg>`
        },
        'Debian': {
            color: 'text-red-500',
            useSvg: true,
            svg: `<svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M13.88 12.09c-.01.03-.01.03-.02.05.02-.02.03-.03.02-.05M14.15 10.83c-.03.05-.04.09-.04.09s.02-.04.04-.09m-.94-1.79c0-.01.01-.01 0 0-.01.01 0 0 0 0m5.89 6.39c-.01.02-.03.04-.04.05.01 0 .03-.02.04-.05M12.08 4c.25.01.5.03.75.07-.26-.05-.53-.08-.78-.08.01.01.02.01.03.01m2.15.47c.29.13.57.28.84.45-.28-.17-.56-.32-.84-.45M21.84 15c.03-.44.05-.88.03-1.33 0 .44-.01.88-.03 1.33m-1.99 2.18v.01M8.47 21.19v.01M12 22c5.05 0 9.18-3.73 9.9-8.6.01-.03.01-.07.02-.1-.72 4.88-4.86 8.7-9.92 8.7-5.52 0-10-4.48-10-10S6.48 2 12 2c.07 0 .14 0 .21.01C6.68 2.28 2.28 6.81 2.28 12.33c0 5.52 4.48 10 10 10h-.01.02c.57 0 1.13-.05 1.68-.14-.56.09-1.12.14-1.69.14z"/>
            </svg>`
        },
        'CentOS': {
            color: 'text-purple-600',
            useSvg: true,
            svg: `<svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L19.82 8 12 11.82 4.18 8 12 4.18zM4 9.5l7 3.5v7l-7-3.5v-7zm9 11v-7l7-3.5v7l-7 3.5z"/>
            </svg>`
        },
        'Windows': {
            color: 'text-blue-500',
            useSvg: true,
            svg: `<svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 5.45v6.55h8V3.36zm9 0v6.55h9V3.36zm-9 7.5v6.55l8-1.09V12.95zm9 0v5.46l9-1.09V12.95z"/>
            </svg>`
        },
    };

    // Return config if OS matches, otherwise return default (gray server icon)
    return configs[props.os] || { 
        color: 'text-gray-500', 
        useSvg: false 
    };
});
</script>

<template>
    <div v-if="osConfig.useSvg" 
         :class="[size, osConfig.color]" 
         v-html="osConfig.svg"
    />
    <Server 
        v-else 
        :class="[size, osConfig.color]"
    />
</template>

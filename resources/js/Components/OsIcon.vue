<script setup>
import { computed } from 'vue';
import { Server } from 'lucide-vue-next';
import { Icon } from '@iconify/vue';

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

// Get icon name and color based on OS
const osConfig = computed(() => {
    const configs = {
        'Ubuntu': {
            color: 'text-orange-500',
            icon: 'simple-icons:ubuntu'
        },
        'Debian': {
            color: 'text-red-500',
            icon: 'simple-icons:debian'
        },
        'CentOS': {
            color: 'text-purple-600',
            icon: 'simple-icons:centos'
        },
        'Windows': {
            color: 'text-blue-500',
            icon: 'simple-icons:windows'
        },
    };

    // Return config if OS matches, otherwise return default (no icon, will fallback to Server)
    return configs[props.os] || { 
        color: 'text-gray-500', 
        icon: null 
    };
});
</script>

<template>
    <Icon 
        v-if="osConfig.icon" 
        :icon="osConfig.icon" 
        :class="[size, osConfig.color]" 
    />
    <Server 
        v-else 
        :class="[size, osConfig.color]"
    />
</template>

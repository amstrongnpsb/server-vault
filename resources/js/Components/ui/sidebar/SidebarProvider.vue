<script setup>
import { computed, provide, ref } from 'vue';
import { TooltipProvider } from '@/Components/ui/tooltip';

const props = defineProps({
    defaultOpen: {
        type: Boolean,
        default: true,
    },
});

const open = ref(props.defaultOpen);
const mobileOpen = ref(false);

const state = computed(() => (open.value ? 'expanded' : 'collapsed'));
const toggleSidebar = () => {
    open.value = !open.value;
};

provide('sidebar', {
    open,
    mobileOpen,
    state,
    toggleSidebar,
});
</script>

<template>
    <TooltipProvider>
        <div
            data-slot="sidebar-wrapper"
            :data-state="state"
            class="group/sidebar-wrapper flex min-h-screen w-full bg-background text-foreground"
            style="--sidebar-width: 16rem; --sidebar-width-icon: 4.5rem"
        >
            <slot />
        </div>
    </TooltipProvider>
</template>

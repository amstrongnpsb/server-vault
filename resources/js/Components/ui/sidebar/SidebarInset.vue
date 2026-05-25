<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: {
        type: [Boolean, null, String, Object, Array],
        required: false,
        skipCheck: true,
    },
});
</script>

<template>
    <div
        data-slot="sidebar-inset"
        :class="
            cn(
                'min-h-screen flex-1 pl-[--sidebar-width] transition-[padding] duration-200 group-data-[state=collapsed]/sidebar-wrapper:pl-[--sidebar-width-icon]',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>

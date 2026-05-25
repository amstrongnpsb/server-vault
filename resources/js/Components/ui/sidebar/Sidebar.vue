<script setup>
import { inject } from 'vue';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    class: {
        type: [Boolean, null, String, Object, Array],
        required: false,
        skipCheck: true,
    },
});

const sidebar = inject('sidebar');
</script>

<template>
    <aside
        data-slot="sidebar"
        :data-state="sidebar.state.value"
        :class="
            cn(
                'fixed inset-y-0 left-0 z-30 flex w-[--sidebar-width] flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-[width] duration-200 group-data-[state=collapsed]/sidebar-wrapper:w-[--sidebar-width-icon]',
                props.class,
            )
        "
        v-bind="$attrs"
    >
        <slot />
    </aside>

    <Sheet v-model:open="sidebar.mobileOpen.value">
        <SheetContent
            side="left"
            class="w-72 gap-0 border-sidebar-border bg-sidebar p-0 text-sidebar-foreground sm:max-w-72"
            :show-close-button="false"
        >
            <slot />
        </SheetContent>
    </Sheet>
</template>

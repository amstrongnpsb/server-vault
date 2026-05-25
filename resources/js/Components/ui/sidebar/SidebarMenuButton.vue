<script setup>
import { inject } from "vue";
import { Link } from "@inertiajs/vue3";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/Components/ui/tooltip";
import { cn } from "@/lib/utils";

const props = defineProps({
    href: {
        type: String,
        default: "",
    },
    tooltip: {
        type: String,
        default: "",
    },
    isActive: {
        type: Boolean,
        default: false,
    },
    class: {
        type: [Boolean, null, String, Object, Array],
        required: false,
        skipCheck: true,
    },
});

const sidebar = inject("sidebar");
</script>

<template>
    <Tooltip :key="sidebar.state.value">
        <TooltipTrigger as-child>
            <Link
                v-if="href"
                :href="href"
                @click="sidebar.mobileOpen.value = false"
                :class="
                    cn(
                        'flex h-10 w-full items-center gap-3 rounded-md px-3 text-sm font-medium transition hover:bg-muted hover:text-sidebar-accent-foreground group-data-[state=collapsed]/sidebar-wrapper:justify-center group-data-[state=collapsed]/sidebar-wrapper:px-0',
                        isActive
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-muted-foreground',
                        props.class,
                    )
                "
            >
                <slot />
            </Link>
            <button
                v-else
                type="button"
                @click="$emit('click')"
                :class="
                    cn(
                        'flex h-10 w-full items-center gap-3 rounded-md px-3 text-sm font-medium transition hover:bg-muted hover:text-sidebar-accent-foreground group-data-[state=collapsed]/sidebar-wrapper:justify-center group-data-[state=collapsed]/sidebar-wrapper:px-0',
                        isActive
                            ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                            : 'text-muted-foreground',
                        props.class,
                    )
                "
            >
                <slot />
            </button>
        </TooltipTrigger>

        <TooltipContent
            v-if="tooltip && sidebar.state.value === 'collapsed'"
            side="right"
            :side-offset="8"
            class="block"
        >
            {{ tooltip }}
        </TooltipContent>
    </Tooltip>
</template>

<script setup>
import { Motion } from "motion-v";

defineProps({
    delay: {
        type: Number,
        default: 0,
    },
    floatDuration: {
        type: Number,
        default: 3,
    },
    floatDistance: {
        type: Number,
        default: 6,
    },
});
</script>

<template>
    <Motion
        :initial="{ opacity: 0, y: 20 }"
        :animate="{
            opacity: 1,
            y: [0, -floatDistance, 0],
        }"
        :transition="{
            opacity: { duration: 0.5, delay },
            y: {
                duration: floatDuration,
                repeat: Infinity,
                ease: 'easeInOut',
                delay: delay + 0.5,
            },
        }"
        :whileHover="{
            scale: 1.05,
            transition: { duration: 0.2 },
        }"
        class="rounded-lg border border-border bg-background/80 p-4 shadow-sm cursor-pointer backdrop-blur-sm"
    >
        <slot />
    </Motion>
</template>

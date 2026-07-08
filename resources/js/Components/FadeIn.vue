<script setup>
import { Motion } from "motion-v";

defineProps({
    delay: {
        type: Number,
        default: 0,
    },
    direction: {
        type: String,
        default: "up", // up, down, left, right, none
        validator: (value) =>
            ["up", "down", "left", "right", "none"].includes(value),
    },
    distance: {
        type: Number,
        default: 10,
    },
    duration: {
        type: Number,
        default: 0.5,
    },
    tag: {
        type: String,
        default: "div",
    },
});

const getInitialPosition = (direction, distance) => {
    switch (direction) {
        case "up":
            return { y: distance };
        case "down":
            return { y: -distance };
        case "left":
            return { x: distance };
        case "right":
            return { x: -distance };
        default:
            return {};
    }
};
</script>

<template>
    <Motion
        :tag="tag"
        :initial="{ opacity: 0, ...getInitialPosition(direction, distance) }"
        :animate="{ opacity: 1, x: 0, y: 0 }"
        :transition="{ duration, delay }"
    >
        <slot />
    </Motion>
</template>

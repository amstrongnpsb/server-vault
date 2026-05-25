<script setup>
import { Eye, EyeOff } from "lucide-vue-next";
import { computed, onMounted, ref, useAttrs } from "vue";

defineOptions({
    inheritAttrs: false,
});

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);
const attrs = useAttrs();
const showPassword = ref(false);

const isPassword = computed(() => attrs.type === "password");
const inputType = computed(() =>
    isPassword.value && showPassword.value ? "text" : attrs.type,
);
const inputAttrs = computed(() => {
    const { class: _class, type: _type, ...rest } = attrs;

    return rest;
});

onMounted(() => {
    if (input.value.hasAttribute("autofocus")) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <div v-if="isPassword" class="relative" :class="attrs.class">
        <input
            class="h-10 w-full rounded-md border-input bg-background px-3 py-2 pr-10 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:ring-ring"
            v-bind="inputAttrs"
            :type="inputType"
            v-model="model"
            ref="input"
        />

        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-muted-foreground transition hover:text-foreground"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            :title="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
        >
            <EyeOff v-if="showPassword" class="h-4 w-4" aria-hidden="true" />
            <Eye v-else class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>

    <input
        v-else
        class="h-10 rounded-md border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus:border-ring focus:ring-ring"
        v-bind="inputAttrs"
        :class="attrs.class"
        :type="inputType"
        v-model="model"
        ref="input"
    />
</template>

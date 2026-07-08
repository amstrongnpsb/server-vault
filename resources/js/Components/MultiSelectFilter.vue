<script setup>
import { ref, computed } from "vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import { Plus, X } from "lucide-vue-next";

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: "Select items",
    },
    label: {
        type: String,
        default: "Select",
    },
    customLabel: {
        type: String,
        default: "Add Custom",
    },
    customPlaceholder: {
        type: String,
        default: "Type custom value...",
    },
    allowCustom: {
        type: Boolean,
        default: true,
    },
    icon: {
        type: Object,
        default: null,
    },
    maxBadges: {
        type: Number,
        default: 3,
    },
    // Optional: component to render for each option icon
    optionIcon: {
        type: Object,
        default: null,
    },
    // Optional: prop name to pass to option icon component
    optionIconProp: {
        type: String,
        default: "value",
    },
});

const emit = defineEmits(["update:modelValue"]);

const customInput = ref("");

const selectedItems = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const toggleItem = (item) => {
    if (selectedItems.value.includes(item)) {
        selectedItems.value = selectedItems.value.filter((i) => i !== item);
    } else {
        selectedItems.value = [...selectedItems.value, item];
    }
};

const addCustomItem = () => {
    const custom = customInput.value.trim();
    if (custom && !selectedItems.value.includes(custom)) {
        selectedItems.value = [...selectedItems.value, custom];
        customInput.value = "";
    }
};

const removeItem = (itemToRemove) => {
    selectedItems.value = selectedItems.value.filter(
        (item) => item !== itemToRemove,
    );
};

const clearAll = () => {
    selectedItems.value = [];
};

const customItems = computed(() => {
    return selectedItems.value.filter((item) => !props.options.includes(item));
});
</script>

<template>
    <div class="flex items-center gap-2">
        <Popover>
            <PopoverTrigger as-child>
                <Button
                    variant="outline"
                    class="w-48 justify-start text-left font-normal"
                >
                    <component v-if="icon" :is="icon" class="mr-2 h-4 w-4" />
                    <span class="truncate">
                        {{
                            selectedItems.length > 0
                                ? `${selectedItems.length} selected`
                                : placeholder
                        }}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-64 p-0" align="start">
                <div class="p-2 space-y-2">
                    <!-- Predefined options -->
                    <div class="space-y-1">
                        <div
                            class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ label }}
                        </div>
                        <div
                            v-for="option in options"
                            :key="option"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-accent cursor-pointer transition-colors"
                            @click="toggleItem(option)"
                        >
                            <div
                                class="h-4 w-4 border rounded flex items-center justify-center"
                                :class="
                                    selectedItems.includes(option)
                                        ? 'bg-primary border-primary'
                                        : 'border-input'
                                "
                            >
                                <svg
                                    v-if="selectedItems.includes(option)"
                                    class="h-3 w-3 text-primary-foreground"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="3"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                            <component
                                v-if="optionIcon"
                                :is="optionIcon"
                                v-bind="{ [optionIconProp]: option }"
                                size="h-4 w-4"
                            />
                            <span class="text-sm">{{ option }}</span>
                        </div>
                    </div>

                    <!-- Custom input -->
                    <template v-if="allowCustom">
                        <div class="border-t border-border"></div>
                        <div class="space-y-2">
                            <div
                                class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ customLabel }}
                            </div>
                            <div class="flex gap-1 px-2">
                                <Input
                                    v-model="customInput"
                                    :placeholder="customPlaceholder"
                                    class="h-8 text-sm"
                                    @keyup.enter="addCustomItem"
                                    @keydown.stop
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="addCustomItem"
                                    class="h-8 px-2 rounded-sm hover:opacity-80"
                                    :disabled="!customInput.trim()"
                                >
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                        </div>
                    </template>

                    <!-- Selected custom items -->
                    <div
                        v-if="allowCustom && customItems.length > 0"
                        class="space-y-1"
                    >
                        <div class="border-t border-border"></div>
                        <div
                            class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            Custom Items
                        </div>
                        <div
                            v-for="item in customItems"
                            :key="item"
                            class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md hover:bg-accent transition-colors"
                        >
                            <div class="flex items-center gap-2">
                                <component
                                    v-if="optionIcon"
                                    :is="optionIcon"
                                    v-bind="{ [optionIconProp]: item }"
                                    size="h-4 w-4"
                                />
                                <span class="text-sm">{{ item }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeItem(item)"
                                class="text-muted-foreground hover:text-destructive"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>

        <!-- Selected badges -->
        <div v-if="selectedItems.length > 0" class="flex items-center gap-1">
            <div class="flex flex-wrap gap-1 max-w-md">
                <Badge
                    v-for="item in selectedItems.slice(0, maxBadges)"
                    :key="item"
                    variant="secondary"
                    class="flex items-center gap-1 px-2 py-0.5"
                >
                    <component
                        v-if="optionIcon"
                        :is="optionIcon"
                        v-bind="{ [optionIconProp]: item }"
                        size="h-3 w-3"
                    />
                    <span class="text-xs">{{ item }}</span>
                    <button
                        type="button"
                        @click="removeItem(item)"
                        class="ml-1 hover:text-destructive"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </Badge>
                <Badge
                    v-if="selectedItems.length > maxBadges"
                    variant="secondary"
                    class="px-2 py-0.5 text-xs"
                >
                    +{{ selectedItems.length - maxBadges }} more
                </Badge>
            </div>
        </div>

        <!-- Clear button -->
        <button
            v-if="selectedItems.length > 0"
            type="button"
            @click="clearAll"
            class="flex items-center justify-center w-8 h-8 text-muted-foreground transition-all duration-200 hover:text-foreground hover:scale-110 hover:bg-muted/50 rounded-md"
            title="Clear all"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>

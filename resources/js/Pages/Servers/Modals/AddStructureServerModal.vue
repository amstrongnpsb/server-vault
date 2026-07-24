<script setup>
import { ref, watch, computed } from "vue";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Badge } from "@/Components/ui/badge";
import { Search, Loader2, Check } from "lucide-vue-next";
import { toast } from "vue-sonner";
import axios from "axios";

const props = defineProps({
    show: { type: Boolean, default: false },
    excludeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "add"]);

const query = ref("");
const results = ref([]);
const searching = ref(false);
const selectedIds = ref(new Set());

const selectionCount = computed(() => selectedIds.value.size);
const hasSelection = computed(() => selectedIds.value.size > 0);

watch(() => props.show, (val) => {
    if (val) {
        query.value = "";
        results.value = [];
        selectedIds.value = new Set();
    }
});

const searchServers = async () => {
    if (!query.value.trim()) {
        results.value = [];
        return;
    }
    searching.value = true;
    try {
        const { data } = await axios.get(route("servers.search"), {
            params: { query: query.value, exclude: props.excludeIds },
        });
        results.value = data;
    } catch {
        toast.error("Search failed");
    } finally {
        searching.value = false;
    }
};

const toggleServer = (id) => {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
};

const addServers = () => {
    if (selectedIds.value.size === 0) return;
    const servers = results.value.filter((s) => selectedIds.value.has(s.id));
    emit("add", servers);
};

const getStatusColor = (status) => status === "Online" ? "bg-green-500" : "bg-red-500";
</script>

<template>
    <Dialog :open="show" @update:open="(v) => !v && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add server to structure</DialogTitle>
                <DialogDescription>
                    Search and select servers to add to the canvas.
                </DialogDescription>
            </DialogHeader>

            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="query"
                    placeholder="Search by name or host..."
                    class="pl-9"
                    @input="searchServers"
                />
            </div>

            <div v-if="searching" class="flex items-center justify-center py-8">
                <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
            </div>

            <div v-else-if="results.length === 0 && query.trim()" class="py-8 text-center text-sm text-muted-foreground">
                No servers found.
            </div>

            <div v-else-if="results.length > 0" class="max-h-60 space-y-1 overflow-y-auto">
                <button
                    v-for="server in results"
                    :key="server.id"
                    :class="[
                        'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors',
                        selectedIds.has(server.id)
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-accent/50',
                    ]"
                    @click="toggleServer(server.id)"
                >
                    <span
                        :class="[
                            'flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors',
                            selectedIds.has(server.id)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input',
                        ]"
                    >
                        <Check v-if="selectedIds.has(server.id)" class="h-3 w-3" />
                    </span>
                    <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" :class="getStatusColor(server.status)" />
                    <div class="flex-1 min-w-0">
                        <div class="font-medium truncate">{{ server.name }}</div>
                        <div class="text-xs text-muted-foreground truncate">{{ server.host }}</div>
                    </div>
                    <Badge variant="secondary" class="shrink-0 text-[10px]">{{ server.os }}</Badge>
                </button>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('close')">
                    Cancel
                </Button>
                <Button :disabled="!hasSelection" @click="addServers">
                    Add to canvas{{ hasSelection ? ` (${selectionCount})` : '' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

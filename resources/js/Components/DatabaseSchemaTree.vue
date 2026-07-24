<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ChevronRight, ChevronDown, Table2, Database, Loader2 } from 'lucide-vue-next';
import { Skeleton } from '@/Components/ui/skeleton';

const props = defineProps({
    database: Object,
    selectedTable: String,
});

const emit = defineEmits(['select-table']);

const schemas = ref([]);
const expandedSchemas = ref(new Set());
const tables = ref({});
const loading = ref(true);
const loadingTables = ref({});
const error = ref(null);

onMounted(async () => {
    try {
        const { data } = await axios.get(route('databases.schemas', props.database.id));
        schemas.value = data.data;
    } catch (err) {
        error.value = err.response?.data?.error || 'Failed to load schemas';
    } finally {
        loading.value = false;
    }
});

const toggleSchema = async (schema) => {
    if (expandedSchemas.value.has(schema)) {
        expandedSchemas.value.delete(schema);
        return;
    }
    expandedSchemas.value.add(schema);
    if (!tables.value[schema]) {
        loadingTables.value[schema] = true;
        try {
            const { data } = await axios.get(route('databases.tables', props.database.id), {
                params: { schema },
            });
            tables.value[schema] = data.data;
        } catch (err) {
            tables.value[schema] = [];
        } finally {
            loadingTables.value[schema] = false;
        }
    }
};
</script>

<template>
    <div class="p-2 space-y-1">
        <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2 px-2">
            Databases
        </div>

        <div v-if="loading" class="px-2 space-y-2">
            <Skeleton v-for="i in 4" :key="i" class="h-4 w-full" />
        </div>
        <div v-else-if="error" class="px-2 text-sm text-destructive">{{ error }}</div>

        <div v-for="schema in schemas" :key="schema">
            <button
                @click="toggleSchema(schema)"
                class="flex items-center gap-1 w-full text-left px-2 py-1 text-sm rounded hover:bg-accent transition-colors"
            >
                <ChevronRight v-if="!expandedSchemas.has(schema)" class="h-3 w-3 shrink-0" />
                <ChevronDown v-else class="h-3 w-3 shrink-0" />
                <Database class="h-4 w-4 text-amber-500 shrink-0" />
                <span class="truncate">{{ schema }}</span>
            </button>
            <div v-if="expandedSchemas.has(schema)" class="ml-4 space-y-0.5">
                <div v-if="loadingTables[schema]" class="px-2 py-1 flex items-center gap-2 text-xs text-muted-foreground">
                    <Loader2 class="h-3 w-3 animate-spin" />
                    Loading tables...
                </div>
                <button
                    v-for="table in tables[schema]"
                    :key="table"
                    @click="$emit('select-table', { schema, table })"
                    class="flex items-center gap-1.5 w-full text-left px-2 py-1 text-sm rounded transition-colors"
                    :class="selectedTable === table ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50'"
                >
                    <Table2 class="h-3.5 w-3.5 text-blue-500 shrink-0" />
                    <span class="truncate">{{ table }}</span>
                </button>
                <div v-if="!loadingTables[schema] && !tables[schema]?.length" class="px-2 py-1 text-xs text-muted-foreground">
                    No tables
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import DatabaseSchemaTree from '@/Components/DatabaseSchemaTree.vue';
import SqlEditor from '@/Components/SqlEditor.vue';
import QueryResultsGrid from '@/Components/QueryResultsGrid.vue';
import axios from 'axios';
import { onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { ArrowLeft, Database, Wifi, WifiOff } from 'lucide-vue-next';

const props = defineProps({
    database: Object,
});

const activeTab = ref('browse');
const selectedTable = ref(null);
const selectedSchema = ref(null);
const columns = ref([]);
const rows = ref(null);
const total = ref(0);
const loading = ref(false);
const error = ref(null);
const { isDark } = useTheme();
const connectionOk = ref(null);
const testingConnection = ref(false);
const dbTables = ref([]);

onMounted(async () => {
    try {
        const { data } = await axios.get(route('databases.tables', props.database.id), {
            params: { schema: props.database.name },
        });
        dbTables.value = data.data || [];
    } catch {
        // tables might not load if connection fails
    }
});

const testConnection = async () => {
    testingConnection.value = true;
    try {
        const res = await axios.post(route('databases.test', props.database.id));
        connectionOk.value = true;
        toast.success(res.data.message);
    } catch (err) {
        connectionOk.value = false;
        toast.error(err.response?.data?.message || 'Connection failed');
    } finally {
        testingConnection.value = false;
    }
};

const sortBy = ref(null);
const sortDir = ref('asc');

const browseTable = async (opts = {}) => {
    const schema = opts.schema ?? selectedSchema.value;
    const table = opts.table ?? selectedTable.value;
    if (!schema || !table) return;

    selectedSchema.value = schema;
    selectedTable.value = table;
    activeTab.value = 'browse';
    loading.value = true;
    error.value = null;
    await nextTick();

    if (opts.sortBy !== undefined) {
        sortBy.value = opts.sortBy;
        sortDir.value = opts.sortDir;
    } else {
        sortBy.value = null;
        sortDir.value = 'asc';
    }

    const params = { schema, table, page: 1, per_page: 100 };
    if (sortBy.value) {
        params.sort_by = sortBy.value;
        params.sort_dir = sortDir.value;
    }

    try {
        const { data } = await axios.get(route('databases.browse', props.database.id), { params });
        columns.value = data.columns;
        rows.value = data.data;
        total.value = data.total;
    } catch (err) {
        error.value = err.response?.data?.error || 'Failed to browse table';
        columns.value = [];
        rows.value = null;
    } finally {
        loading.value = false;
    }
};

const executeQuery = async (sql) => {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post(route('databases.query', props.database.id), { sql });

        if (data.type === 'select') {
            columns.value = data.columns;
            rows.value = data.data;
            total.value = data.affected;
        } else if (data.type === 'modification') {
            toast.success(data.message);
            columns.value = [];
            rows.value = [];
            total.value = 0;
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Query execution failed';
        columns.value = [];
        rows.value = null;
    } finally {
        loading.value = false;
    }
};

const goBack = () => {
    router.visit(route('servers.index'));
};
</script>

<template>
    <Head :title="`${database.name} — ${database.server.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" class="h-8 w-8 -ml-1" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <Database class="h-4 w-4 text-primary" />
                    <span class="text-sm font-medium">{{ database.name }}</span>
                    <span class="text-xs text-muted-foreground">{{ database.server.name }}:{{ database.port }}</span>
                    <span class="text-xs text-muted-foreground">({{ database.type }})</span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 ml-3"
                    :disabled="testingConnection"
                    @click="testConnection"
                >
                    <Wifi v-if="connectionOk" class="h-3.5 w-3.5 text-green-500" />
                    <WifiOff v-else-if="connectionOk === false" class="h-3.5 w-3.5 text-red-500" />
                    <Wifi v-else class="h-3.5 w-3.5" />
                    {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                </Button>
            </div>
        </template>

        <div class="flex h-[calc(100vh-65px)] overflow-hidden">
            <aside class="w-60 border-r border-border bg-muted/30 overflow-y-auto shrink-0">
                <DatabaseSchemaTree
                    :database="database"
                    :selected-table="selectedTable"
                    @select-table="browseTable"
                />
            </aside>

            <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <Tabs v-model="activeTab" class="flex-1 flex flex-col min-w-0 overflow-hidden">
                    <div class="border-b border-border shrink-0">
                        <TabsList variant="line" class="w-full gap-0 h-10 bg-transparent">
                            <TabsTrigger value="browse" class="border-0 border-b-2 border-transparent data-[state=active]:border-b-2 data-[state=active]:border-active data-[state=active]:text-foreground rounded-none h-full px-6 text-muted-foreground hover:text-foreground transition-colors text-sm font-medium">
                                Browse
                            </TabsTrigger>
                            <TabsTrigger value="query" class="border-0 border-b-2 border-transparent data-[state=active]:border-b-2 data-[state=active]:border-active data-[state=active]:text-foreground rounded-none h-full px-6 text-muted-foreground hover:text-foreground transition-colors text-sm font-medium">
                                Query
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="browse" class="flex-1 min-w-0 m-0 overflow-hidden data-[state=active]:flex data-[state=active]:flex-col">
                        <div class="h-full overflow-auto p-4">
                            <div v-if="!selectedTable" class="flex items-center justify-center h-full text-sm text-muted-foreground">
                                Select a table from the sidebar to browse its data
                            </div>
                            <QueryResultsGrid
                                v-else
                                :columns="columns"
                                :rows="rows"
                                :total="total"
                                :loading="loading"
                                :error="error"
                                :sort-by="sortBy"
                                :sort-dir="sortDir"
                                @request-sort="browseTable"
                            />
                        </div>
                    </TabsContent>

                    <TabsContent value="query" class="flex-1 min-w-0 m-0 overflow-hidden data-[state=active]:flex data-[state=active]:flex-col">
                        <div class="h-full overflow-auto p-4 flex flex-col gap-4">
                            <div class="shrink-0">
                                <SqlEditor
                                    :dialect="database.type"
                                    :is-dark="isDark"
                                    :tables="dbTables"
                                    :current-schema="database.name"
                                    @execute="executeQuery"
                                />
                            </div>
                            <div class="flex-1 min-w-0 overflow-auto">
                                <QueryResultsGrid
                                    :columns="columns"
                                    :rows="rows"
                                    :total="total"
                                    :loading="loading"
                                    :error="error"
                                />
                            </div>
                        </div>
                    </TabsContent>

                </Tabs>

                <div class="flex items-center justify-between px-4 py-1 border-t border-border bg-muted/50 text-xs text-muted-foreground shrink-0">
                    <span>{{ database.type }} — {{ database.server.host }}:{{ database.port }}</span>
                    <span>
                        <span v-if="connectionOk === true" class="text-green-500">Connected</span>
                        <span v-else-if="connectionOk === false" class="text-red-500">Disconnected</span>
                        <span v-else>Not tested</span>
                    </span>
                </div>
            </main>
        </div>
    </AuthenticatedLayout>
</template>

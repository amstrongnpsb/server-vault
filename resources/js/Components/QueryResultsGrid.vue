<script setup>
import { computed, ref } from 'vue';
import { AgGridVue } from 'ag-grid-vue3';
import { ModuleRegistry, ValidationModule } from 'ag-grid-community';
import { AllCommunityModule } from 'ag-grid-community';
import 'ag-grid-community/styles/ag-theme-quartz.css';
import { useTheme } from '@/composables/useTheme';
import { Skeleton } from '@/Components/ui/skeleton';
import { Loader2 } from 'lucide-vue-next';

const ALLOW_FILTER = false;

ModuleRegistry.registerModules([AllCommunityModule, ValidationModule]);

const emit = defineEmits(['request-sort']);

const props = defineProps({
    columns: Array,
    rows: Array,
    total: Number,
    loading: Boolean,
    error: String,
    sortBy: String,
    sortDir: String,
});

const { isDark } = useTheme();

const themeClass = computed(() =>
    isDark.value ? 'ag-theme-quartz-dark' : 'ag-theme-quartz'
);

const gridRef = ref(null);

const columnDefs = computed(() => {
    if (!props.columns?.length) return [];
    return props.columns.map((col) => ({
        field: col,
        headerName: col,
        sortable: true,
        filter: ALLOW_FILTER,
        resizable: true,
        cellRenderer: (params) => {
            if (params.value === null) {
                return '<span class="text-muted-foreground italic">NULL</span>';
            }
            return String(params.value);
        },
    }));
});

const defaultColDef = {
    flex: 1,
    minWidth: 100,
    resizable: true,
    sortable: true,
    filter: ALLOW_FILTER,
    cellStyle: { fontFamily: 'monospace', fontSize: '13px' },
};

const gridOptions = {
    rowHeight: 32,
    headerHeight: 36,
    suppressMovableColumns: false,
    enableCellTextSelection: true,
    ensureDomOrder: true,
    domLayout: 'normal',
    tooltipShowDelay: 300,
};

const onSortChanged = (event) => {
    const sortModel = event.api.getSortModel();
    if (!sortModel?.length) return;
    emit('request-sort', { sortBy: sortModel[0].colId, sortDir: sortModel[0].sort });
};
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden flex flex-col h-full relative">
        <div v-if="loading && !columns?.length" class="flex-1 flex flex-col p-4 gap-3">
            <div class="flex gap-3">
                <Skeleton v-for="i in 6" :key="'h'+i" class="h-4 flex-1" />
            </div>
            <Skeleton v-for="i in 8" :key="'r'+i" class="h-3 w-full" />
        </div>

        <div v-else-if="error && !rows?.length" class="p-4 text-sm text-destructive bg-destructive/5">
            {{ error }}
        </div>

        <div v-else-if="columns?.length || rows?.length" class="flex-1 w-full relative" :class="themeClass">
            <div v-if="loading" class="absolute inset-0 z-50 flex items-center justify-center bg-background/60">
                <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
            <AgGridVue
                ref="gridRef"
                :columnDefs="columnDefs"
                :rowData="rows"
                :defaultColDef="defaultColDef"
                :gridOptions="gridOptions"
                :animateRows="false"
                @sort-changed="onSortChanged"
                style="height: 100%; width: 100%"
            />
        </div>

        <div v-else-if="rows !== null" class="p-8 text-center text-sm text-muted-foreground">
            Query executed successfully. 0 rows returned.
        </div>

        <div v-else class="flex-1 flex items-center justify-center text-sm text-muted-foreground">
            Run a query or select a table from the sidebar to see results.
        </div>

        <div v-if="rows?.length > 0" class="px-3 py-1.5 bg-muted border-t border-border text-xs text-muted-foreground shrink-0">
            {{ rows.length }} row(s) returned
            <span v-if="total > rows.length"> ({{ total }} total)</span>
        </div>
    </div>
</template>

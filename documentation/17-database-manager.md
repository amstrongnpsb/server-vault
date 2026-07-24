# Plan: Database Manager (HeidiSQL-like Browser)

This document outlines the implementation plan for a **database manager** feature — a browser-based database browser and query tool similar to HeidiSQL, DBeaver, or phpMyAdmin. It allows users to connect to databases stored in ServerVault (via `ServerDatabase`), browse schemas/tables, view data, and run SQL queries.

> **Scope for this version:** MySQL/MariaDB and PostgreSQL only. Other database types fall back to a generic connection test.

---

## Current State

| Component | Status |
|-----------|--------|
| `ServerDatabase` model with encrypted credentials | ✅ Ready |
| `ServerDatabase` CRUD (store/update/destroy endpoints) | ✅ Ready |
| `DatabaseModal.vue` (create/edit form with type, port, username, password) | ✅ Ready |
| `ServerDetailModal.vue` with databases tab (table view, reveal/copy password) | ✅ Ready |
| `useServerDetails` composable with client-side caching | ✅ Ready |
| Database connection testing | ❌ Not implemented |
| Schema/table browsing | ❌ Not implemented |
| Query execution | ❌ Not implemented |
| Data browser (table grid) | ❌ Not implemented |

---

## Architecture Overview

```
Browser (Vue - DatabaseManager)
   │
   │  Axios GET/POST to Laravel API endpoints
   ▼
Laravel (auth, permissions, connection management via PDO)
   │
   │  Opens ephemeral PDO connection using decrypted credentials
   ▼
Remote Database (MySQL, PostgreSQL, etc.)
```

**Key design decisions:**
- **No persistent WebSocket bridge** for this feature (unlike SSH terminal). Database queries are request-response and don't need a long-lived duplex channel.
- **Connections are ephemeral**: opened per-request, closed after response. No persistent connection pooling in this version.
- **PDO** is used for MySQL/PostgreSQL.
- **The `ServerDatabase` host/connection info** — the current schema has `type`, `name`, `port`, `username`, `credentials` but **no `host` field**. The `host` is inherited from the parent `Server` record. This means the database must be on the same server. A follow-up can add an optional `host` override on `ServerDatabase` for remote databases.

---

## 1. Backend: Connection Management

### A. Service Class: `App\Services\DatabaseConnectionService`

A dedicated service that opens and manages database connections. This keeps controller logic clean and allows reuse.

```php
class DatabaseConnectionService
{
    /**
     * Open a PDO connection from a ServerDatabase record.
     *
     * @throws \App\Exceptions\DatabaseConnectionException
     */
    public function connect(ServerDatabase $database): PDO
    {
        $host = $database->server->host;
        $port = $database->port;
        $username = $database->username;
        $password = $database->decrypted_credentials;

        return match ($database->type) {
            'MySQL' => $this->connectMysql($host, $port, $username, $password),
            'PostgreSQL' => $this->connectPostgres($host, $port, $username, $password),
            default => throw new DatabaseConnectionException("Unsupported database type: {$database->type}"),
        };
    }

    private function connectMysql(...): PDO { /* DSN: mysql:host=...;port=... */ }
    private function connectPostgres(...): PDO { /* DSN: pgsql:host=...;port=... */ }
}
```

```php
class DatabaseConnectionException extends \RuntimeException {}
```

### B. Test Connection Endpoint

**File:** `app/Http/Controllers/DatabaseController.php` (new)

```php
class DatabaseController extends Controller
{
    public function __construct(
        private DatabaseConnectionService $connectionService
    ) {}

    /**
     * Test a database connection using stored credentials.
     */
    public function test(ServerDatabase $serverDatabase): JsonResponse
    {
        $this->authorize('view', $serverDatabase->server);

        try {
            $pdo = $this->connectionService->connect($serverDatabase);
            $pdo->query('SELECT 1');
            return response()->json(['success' => true, 'message' => 'Connection successful']);
        } catch (DatabaseConnectionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
```

**Route:**
```php
Route::post('/databases/{serverDatabase}/test', [DatabaseController::class, 'test'])
    ->name('databases.test')
    ->middleware('permission:manage database servers');
```

---

## 2. Backend: Schema & Data Browsing

### A. List Schemas/Databases

```php
public function schemas(ServerDatabase $serverDatabase): JsonResponse
{
    $pdo = $this->connectionService->connect($serverDatabase);

    return match ($serverDatabase->type) {
        'MySQL' => $this->mysqlSchemas($pdo),
        'PostgreSQL' => $this->postgresSchemas($pdo),
    };
}

private function mysqlSchemas(PDO $pdo): JsonResponse
{
    $stmt = $pdo->query('SHOW DATABASES');
    $schemas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return response()->json(['data' => $schemas]);
}

private function postgresSchemas(PDO $pdo): JsonResponse
{
    $stmt = $pdo->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name != 'information_schema'");
    $schemas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return response()->json(['data' => $schemas]);
}
```

### B. List Tables

```php
public function tables(ServerDatabase $serverDatabase, Request $request): JsonResponse
{
    $schema = $request->query('schema', 'public'); // only relevant for PostgreSQL
    $pdo = $this->connectionService->connect($serverDatabase);

    return match ($serverDatabase->type) {
        'MySQL' => $this->mysqlTables($pdo),
        'PostgreSQL' => $this->postgresTables($pdo, $schema),
    };
}
```

### C. Get Table Columns / Schema

```php
public function columns(ServerDatabase $serverDatabase, Request $request): JsonResponse
{
    $table = $request->query('table');
    $pdo = $this->connectionService->connect($serverDatabase);

    // MySQL: SHOW COLUMNS FROM `$table`
    // PostgreSQL: SELECT column_name, data_type, ... FROM information_schema.columns WHERE table_name = ?
}
```

### D. Browse Table Data (with pagination)

```php
public function browse(ServerDatabase $serverDatabase, Request $request): JsonResponse
{
    $table = $request->query('table');
    $page = $request->integer('page', 1);
    $perPage = $request->integer('per_page', 100);
    $offset = ($page - 1) * $perPage;

    $pdo = $this->connectionService->connect($serverDatabase);

    // Get total count
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
    $total = (int) $countStmt->fetch(PDO::FETCH_OBJ)->count;

    // Get data
    $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $perPage OFFSET $offset");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get column metadata from the first row or via information_schema
    $columns = array_keys($rows[0] ?? []);

    return response()->json([
        'data' => $rows,
        'columns' => $columns,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ]);
}
```

### E. Execute Arbitrary SQL

```php
public function query(ServerDatabase $serverDatabase, Request $request): JsonResponse
{
    $request->validate(['sql' => 'required|string']);

    $pdo = $this->connectionService->connect($serverDatabase);

    try {
        $stmt = $pdo->query($request->sql);
        
        // SELECT-like queries return data
        if (preg_match('/^\s*SELECT/i', $request->sql)) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = !empty($rows) ? array_keys($rows[0]) : [];
            return response()->json([
                'type' => 'select',
                'data' => $rows,
                'columns' => $columns,
                'affected' => count($rows),
            ]);
        }

        // INSERT/UPDATE/DELETE return affected rows
        return response()->json([
            'type' => 'modification',
            'affected' => $stmt->rowCount(),
            'message' => "Query executed. {$stmt->rowCount()} row(s) affected.",
        ]);
    } catch (\PDOException $e) {
        return response()->json([
            'type' => 'error',
            'message' => $e->getMessage(),
        ], 422);
    }
}
```

**Route group (all behind auth + permission middleware):**
```php
Route::middleware(['auth', 'verified', 'permission:manage database servers'])->prefix('databases/{serverDatabase}')->group(function () {
    Route::post('/test', [DatabaseController::class, 'test'])->name('databases.test');
    Route::get('/schemas', [DatabaseController::class, 'schemas'])->name('databases.schemas');
    Route::get('/tables', [DatabaseController::class, 'tables'])->name('databases.tables');
    Route::get('/columns', [DatabaseController::class, 'columns'])->name('databases.columns');
    Route::get('/browse', [DatabaseController::class, 'browse'])->name('databases.browse');
    Route::post('/query', [DatabaseController::class, 'query'])->name('databases.query');
});
```

---

## 3. Frontend: Database Browser Page

### A. New Page: `Pages/Databases/Show.vue`

A full-page database browser (similar in concept to the Terminal.vue page). Accessed via a "Browse" action in the databases table within `ServerDetailModal.vue`.

**Route:**
```php
Route::get('/databases/{serverDatabase}', [DatabaseController::class, 'show'])
    ->name('databases.show')
    ->middleware('permission:manage database servers');
```

The `show()` method returns an Inertia page:
```php
public function show(ServerDatabase $serverDatabase): Response
{
    $this->authorize('view', $serverDatabase->server);

    return Inertia::render('Databases/Show', [
        'database' => $serverDatabase->load('server:id,host,name'),
    ]);
}
```

### B. Layout Structure

The page is split into a **sidebar** (schema browser tree) and a **main area** (table grid or query editor):

```
┌─────────────────────────────────────────────────────┐
│  Header: Database name — Server name                │
├──────────┬──────────────────────────────────────────┤
│          │  [Query] [Browse] [Structure] tabs       │
│  Schema  ├──────────────────────────────────────────┤
│  Tree    │                                          │
│          │  Main content area:                      │
│  📁 db   │  - Query tab: SQL editor + results grid   │
│  📁 db2  │  - Browse tab: paginated table data      │
│  └─ 📄   │  - Structure tab: columns + indexes      │
│    tbl1  │                                          │
│    tbl2  │                                          │
│    tbl3  │                                          │
│          │                                          │
├──────────┴──────────────────────────────────────────┤
│  Status bar: Connection info / query time / rows    │
└─────────────────────────────────────────────────────┘
```

### C. Schema Tree Component

**File:** `resources/js/Components/DatabaseSchemaTree.vue`

Props:
- `database: Object` — the ServerDatabase record
- `selectedTable: String` — v-model for selected table

Functionality:
- On mount: fetches schemas/databases list via `GET /databases/{id}/schemas`
- Clicking a schema expands to show tables via `GET /databases/{id}/tables?schema=X`
- Clicking a table emits `@select-table`
- Icons: folder for schemas, file/table icon for tables

```vue
<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ChevronRight, ChevronDown, Table2, Database } from 'lucide-vue-next';

const props = defineProps({
    database: Object,
    selectedTable: String,
});

const emit = defineEmits(['select-table']);

const schemas = ref([]);
const expandedSchemas = ref(new Set());
const tables = ref({}); // schemaName => [tableName, ...]
const loading = ref(true);

onMounted(async () => {
    const { data } = await axios.get(route('databases.schemas', props.database.id));
    schemas.value = data.data;
    loading.value = false;
});

const toggleSchema = async (schema) => {
    if (expandedSchemas.value.has(schema)) {
        expandedSchemas.value.delete(schema);
    } else {
        expandedSchemas.value.add(schema);
        if (!tables.value[schema]) {
            const { data } = await axios.get(route('databases.tables', props.database.id), {
                params: { schema },
            });
            tables.value[schema] = data.data;
        }
    }
};
</script>

<template>
    <div class="p-2 space-y-1">
        <div class="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
            Databases
        </div>
        <div v-for="schema in schemas" :key="schema">
            <button
                @click="toggleSchema(schema)"
                class="flex items-center gap-1 w-full text-left px-2 py-1 text-sm rounded hover:bg-accent"
            >
                <ChevronRight v-if="!expandedSchemas.has(schema)" class="h-3 w-3 shrink-0" />
                <ChevronDown v-else class="h-3 w-3 shrink-0" />
                <Database class="h-4 w-4 text-amber-500 shrink-0" />
                {{ schema }}
            </button>
            <div v-if="expandedSchemas.has(schema)" class="ml-4 space-y-0.5">
                <button
                    v-for="table in tables[schema]"
                    :key="table"
                    @click="$emit('select-table', { schema, table })"
                    class="flex items-center gap-1.5 w-full text-left px-2 py-1 text-sm rounded"
                    :class="selectedTable === table ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50'"
                >
                    <Table2 class="h-3.5 w-3.5 text-blue-500 shrink-0" />
                    {{ table }}
                </button>
            </div>
        </div>
    </div>
</template>
```

### D. SQL Editor Component

**File:** `resources/js/Components/SqlEditor.vue`

Use a `<textarea>` enhanced with:
- **CodeMirror 6** or **Codemirror** for syntax highlighting (lightweight, Vue-friendly)
- Or a simpler approach: monospace textarea with a "Run" button for v1

```vue
<script setup>
import { ref } from 'vue';
import { Play, RotateCcw } from 'lucide-vue-next';

const sql = ref('');
const emit = defineEmits(['execute']);

const runQuery = () => {
    if (sql.value.trim()) {
        emit('execute', sql.value);
    }
};

const handleKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        runQuery();
    }
};
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-3 py-1.5 bg-muted border-b border-border">
            <span class="text-xs font-medium text-muted-foreground">SQL Query</span>
            <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" class="h-7 w-7" title="Clear">
                    <RotateCcw class="h-3.5 w-3.5" />
                </Button>
                <Button size="sm" class="h-7 gap-1" @click="runQuery">
                    <Play class="h-3.5 w-3.5" />
                    Run (Ctrl+Enter)
                </Button>
            </div>
        </div>
        <textarea
            v-model="sql"
            @keydown="handleKeydown"
            class="w-full min-h-[120px] p-3 font-mono text-sm bg-background text-foreground border-0 resize-y focus:outline-none"
            placeholder="SELECT * FROM table_name LIMIT 100"
        />
    </div>
</template>
```

For v1, a plain textarea with monospace font is sufficient. CodeMirror can be added in a follow-up.

### E. Results Grid Component

**File:** `resources/js/Components/QueryResultsGrid.vue`

A simple table that renders query results dynamically:

```vue
<script setup>
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next';

const props = defineProps({
    columns: Array,
    rows: Array,
    total: Number,
    loading: Boolean,
    error: String,
});
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden">
        <!-- Column headers -->
        <div v-if="columns.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-muted border-b border-border">
                        <th class="text-left px-3 py-2 text-xs font-medium text-muted-foreground">#</th>
                        <th
                            v-for="col in columns"
                            :key="col"
                            class="text-left px-3 py-2 text-xs font-medium text-muted-foreground font-mono"
                        >
                            {{ col }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, i) in rows"
                        :key="i"
                        class="border-b border-border hover:bg-accent/50 transition-colors"
                    >
                        <td class="px-3 py-1.5 text-xs text-muted-foreground">{{ i + 1 }}</td>
                        <td
                            v-for="col in columns"
                            :key="col"
                            class="px-3 py-1.5 whitespace-nowrap"
                        >
                            {{ row[col] ?? 'NULL' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty state -->
        <div v-if="!loading && !columns.length && !error" class="p-8 text-center text-sm text-muted-foreground">
            {{ rows === null ? 'Run a query to see results' : 'No results' }}
        </div>

        <!-- Error state -->
        <div v-if="error" class="p-4 text-sm text-destructive bg-destructive/5">
            {{ error }}
        </div>

        <!-- Loading -->
        <div v-if="loading" class="p-4 text-center text-sm text-muted-foreground">
            Running query...
        </div>

        <!-- Footer -->
        <div v-if="rows?.length > 0" class="px-3 py-1.5 bg-muted border-t border-border text-xs text-muted-foreground">
            {{ total ?? rows.length }} row(s) returned
        </div>
    </div>
</template>
```

### F. Main Page: `Pages/Databases/Show.vue`

```vue
<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import DatabaseSchemaTree from '@/Components/DatabaseSchemaTree.vue';
import SqlEditor from '@/Components/SqlEditor.vue';
import QueryResultsGrid from '@/Components/QueryResultsGrid.vue';
import axios from 'axios';
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
const connectionOk = ref(null); // null = not tested, true/false
const testingConnection = ref(false);

// Connection status
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

// Browse table data
const browseTable = async ({ schema, table }) => {
    selectedSchema.value = schema;
    selectedTable.value = table;
    activeTab.value = 'browse';
    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get(route('databases.browse', props.database.id), {
            params: { table, page: 1, per_page: 100 },
        });
        columns.value = data.columns;
        rows.value = data.data;
        total.value = data.total;
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to browse table';
        columns.value = [];
        rows.value = null;
    } finally {
        loading.value = false;
    }
};

// Execute SQL
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

// Go back to server detail
const goBack = () => {
    router.visit(route('servers.index'));
};
</script>

<template>
    <Head :title="`${database.name} — ${database.server.name}`" />

    <AuthenticatedLayout>
        <div class="flex flex-col h-[calc(100vh-3.5rem)]">
            <!-- Top bar -->
            <div class="flex items-center justify-between px-4 py-2 border-b border-border bg-background shrink-0">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="goBack">
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
                    class="h-8 gap-1.5"
                    :disabled="testingConnection"
                    @click="testConnection"
                >
                    <Wifi v-if="connectionOk" class="h-3.5 w-3.5 text-green-500" />
                    <WifiOff v-else-if="connectionOk === false" class="h-3.5 w-3.5 text-red-500" />
                    <Wifi v-else class="h-3.5 w-3.5" />
                    {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                </Button>
            </div>

            <!-- Body: sidebar + main -->
            <div class="flex flex-1 overflow-hidden">
                <!-- Sidebar: Schema tree -->
                <aside class="w-60 border-r border-border bg-muted/30 overflow-y-auto shrink-0">
                    <DatabaseSchemaTree
                        :database="database"
                        :selected-table="selectedTable"
                        @select-table="browseTable"
                    />
                </aside>

                <!-- Main content -->
                <main class="flex-1 flex flex-col overflow-hidden">
                    <Tabs v-model="activeTab" class="flex-1 flex flex-col">
                        <div class="px-4 pt-3 border-b border-border">
                            <TabsList>
                                <TabsTrigger value="browse">Browse</TabsTrigger>
                                <TabsTrigger value="query">Query</TabsTrigger>
                                <TabsTrigger value="structure">Structure</TabsTrigger>
                            </TabsList>
                        </div>

                        <!-- Browse tab -->
                        <TabsContent value="browse" class="flex-1 p-4 overflow-auto">
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
                            />
                        </TabsContent>

                        <!-- Query tab -->
                        <TabsContent value="query" class="flex-1 p-4 flex flex-col gap-4 overflow-hidden">
                            <SqlEditor @execute="executeQuery" />
                            <div class="flex-1 overflow-auto">
                                <QueryResultsGrid
                                    :columns="columns"
                                    :rows="rows"
                                    :total="total"
                                    :loading="loading"
                                    :error="error"
                                />
                            </div>
                        </TabsContent>

                        <!-- Structure tab -->
                        <TabsContent value="structure" class="flex-1 p-4 overflow-auto">
                            <div v-if="!selectedTable" class="flex items-center justify-center h-full text-sm text-muted-foreground">
                                Select a table from the sidebar to view its structure
                            </div>
                            <div v-else class="text-sm text-muted-foreground">
                                Table structure will be displayed here (columns, types, indexes)
                            </div>
                        </TabsContent>
                    </Tabs>
                </main>
            </div>

            <!-- Status bar -->
            <div class="flex items-center justify-between px-4 py-1 border-t border-border bg-muted/50 text-xs text-muted-foreground shrink-0">
                <span>
                    {{ database.type }} — {{ database.server.host }}:{{ database.port }}
                </span>
                <span>
                    <span v-if="connectionOk === true" class="text-green-500">Connected</span>
                    <span v-else-if="connectionOk === false" class="text-red-500">Disconnected</span>
                    <span v-else>Not tested</span>
                </span>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

---

## 4. Permissions

Reuse the existing `manage database servers` permission. No new permissions needed for this feature — all database management falls under the same permission.

If finer-grained control is needed later:
- `browse databases` — view schema, tables, and data
- `execute queries` — run SQL statements
- `edit data` — INSERT/UPDATE/DELETE via the browser

---

## 5. Security Considerations

| Concern | Mitigation |
|---------|------------|
| **SQL injection** | All dynamic SQL uses parameterized queries where possible. Table/schema names are validated against `information_schema` before use. The raw query endpoint is intentionally unfiltered for power users. |
| **Credentials in browser** | Credentials never leave the server. Decryption happens server-side only. The browser only receives query results. |
| **Unauthorized access** | All endpoints check `$this->authorize('view', $database->server)`. The `manage database servers` permission is required for all database operations. |
| **Query timeout** | Set `PDO::ATTR_TIMEOUT` to 30 seconds. Long-running queries are terminated server-side. |
| **Write operations** | The raw query endpoint allows INSERT/UPDATE/DELETE. This is by design (HeidiSQL-like). Users with the permission can modify data. Consider adding a read-only mode toggle. |
| **Connection pooling** | Not implemented in v1. Each request opens a new PDO connection. This is fine for occasional use but might be slow for rapid browsing. A persistent connection cache (per session, with TTL) can be added later. |

---

## 6. Implementation Order

| Step | Task | Priority |
|------|------|----------|
| Step | Task | Priority |
|------|------|----------|
| 1 | Create `DatabaseConnectionService` with MySQL and PostgreSQL PDO support | 🔴 High |
| 2 | Create `DatabaseController` with `test`, `schemas`, `tables` endpoints | 🔴 High |
| 3 | Create `DatabaseController` with `browse` and `query` endpoints | 🔴 High |
| 4 | Add routes for all database browser endpoints | 🔴 High |
| 5 | Create `DatabaseSchemaTree.vue` component | 🔴 High |
| 6 | Create `SqlEditor.vue` component | 🟡 Medium |
| 7 | Create `QueryResultsGrid.vue` component | 🟡 Medium |
| 8 | Create `Pages/Databases/Show.vue` page | 🟡 Medium |
| 9 | Add "Browse" action button in `ServerDetailModal.vue` databases table | 🟡 Medium |
| 10 | Add `databases.show` route that returns Inertia page | 🟡 Medium |
| 11 | Test Connection button in `DatabaseModal.vue` (optional: test before save) | 🟡 Medium |
| 12 | Structure tab (columns, indexes, foreign keys display) | 🟢 Low |
| 13 | Table data editing (inline cell edit) | 🟢 Low |
| 14 | Query history / saved queries | 🟢 Low |
| 15 | Export results (CSV/JSON/Excel) | 🟢 Low |

---

## 7. Files to Create / Modify

### New Files

| File | Purpose |
|------|---------|
| `app/Services/DatabaseConnectionService.php` | PDO connection management |
| `app/Exceptions/DatabaseConnectionException.php` | Custom exception |
| `app/Http/Controllers/DatabaseController.php` | All database browsing endpoints |
| `resources/js/Pages/Databases/Show.vue` | Main database browser page |
| `resources/js/Components/DatabaseSchemaTree.vue` | Schema/table tree sidebar |
| `resources/js/Components/SqlEditor.vue` | SQL editor with run button |
| `resources/js/Components/QueryResultsGrid.vue` | Dynamic results table |

### Modified Files

| File | Change |
|------|--------|
| `routes/web.php` | Add `databases.show`, `databases.test`, `databases.schemas`, `databases.tables`, `databases.columns`, `databases.browse`, `databases.query` routes |
| `resources/js/Pages/Servers/Modals/ServerDetailModal.vue` | Add "Browse" action button in databases table |

---

## 8. Future Enhancements

- **CodeMirror 6** integration for SQL syntax highlighting and autocomplete
- **Persistent connection cache** — reuse PDO connections within a user session
- **Read-only mode** — query-only toggle for safer browsing
- **Data editing** — inline cell editing in the results grid (Click to edit, Enter to save)
- **Insert row** — GUI for inserting a new row
- **Export** — download results as CSV, JSON, Excel, or SQL INSERT statements
- **Query history** — per-user query history with search
- **Saved queries** — bookmark frequently used queries per database
- **Table structure view** — full column details, indexes, foreign keys, triggers
- **ER diagram** — visual schema viewer
- **Multiple query tabs** — run multiple queries simultaneously
- **Explain plan** — visualize query execution plan
- **SSH tunnel** — connect through the server's existing SSH tunnel for databases on private network

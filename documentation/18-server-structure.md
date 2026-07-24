# Feature: Server Structure / Topology Map

## 1. Goal

Add a new **"Structure"** view per server that lets an admin visually map out
how servers relate to each other — which host runs which VM, which servers
talk to which over the network, dependency chains, etc. — using a draggable,
connectable node canvas (similar to draw.io / Lucidchart).

This is **not** a one-time diagram. It's a living representation backed by
real data: connections and node positions persist in the database, so the
diagram is always in sync with whatever's been linked, and reloading the page
shows the same layout the user left it in.

---

## 2. Where it lives in the product

- Entry in the **Actions** dropdown on the Server Management table, between
  Duplicate and Edit, called **"Structure"**.
- Clicking it opens a canvas scoped to that server.

---

## 3. Core behavior

| Step                                              | Behavior                                                                                                                |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Open Structure for a server that has no links yet | Canvas shows just that one server, alone                                                                                |
| Click "Add server"                                | Search modal (multi-select) to find servers; picking them drops them as floating, unconnected nodes around cluster center |
| Drag a connector between two nodes                | Creates a permanent link between those two servers (any handle to any handle)                                           |
| Reload the page                                   | Canvas re-renders with all previously saved nodes, positions, and connections                                           |
| Open Structure from the _other_ linked server     | Shows the same connected group — structure is shared, not duplicated per server                                         |
| Drag a node to reposition it                      | New position is saved, so the layout persists across sessions                                                           |
| Delete a connection                               | Click the edge → deletes immediately; if that leaves a server isolated, it drops out of this view on next refresh      |
| Remove a server from the canvas                   | Hover the node, click the X button → deletes all its connections and clears its position                                |

---

## 4. What counts as a "structure"

A structure is not a fixed, named diagram — it's simply **a server plus
everything connected to it, however many links away** (its whole connected
cluster). This means:

- No need to manually manage "which diagram does this belong to."
- If Server A → Server B → Server C are all linked, opening Structure on any
  of the three shows all three.
- Two servers that have never been linked to each other, even indirectly,
  will never appear on the same Structure canvas — keeping unrelated
  infrastructure visually separate automatically.

---

## 5. Connection types (relationships between servers)

Support labeling _why_ two servers are linked:

- **Hosts** — amber, 2.5px stroke
- **Network** — cyan/teal, 2px stroke
- **Depends on** — purple, 2px stroke, dashed
- **Custom** — free-text label for anything else (gray, 1.5px stroke)

The `type` column is a plain string (VARCHAR), mirroring how the `os` column
works — predefined suggestions with free-text "Other" support.

---

## 6. Implemented decisions

- **Hosts cardinality:** Enforced — a VM can only have one Hosts connection.
- **Visual differentiation:** Yes — each connection type has a distinct stroke
  color, width, and dash pattern.
- **Permissions:** Two granular permissions:
  - `view server structure` — read-only canvas access
  - `edit server structure` — add/remove nodes, create/delete connections,
    reposition nodes
  - Both assigned to `superadmin` and `admin` roles.
- **Status indicators:** Status dot pulses green (Online) or red (Offline) on
  each node.
- **Handles:** 4 handles per node (top, right, bottom, left) using
  `ConnectionMode.Loose` so any handle can act as source or target. Handles
  appear on node hover.
- **Handle persistence:** `source_handle` / `target_handle` stored per edge so
  connections redraw from the correct handles after refresh.

---

## 7. Technical implementation

### Database

- `servers` table: `canvas_x` (`float`, nullable) / `canvas_y` (`float`, nullable)
  — persisted position per server (shared across all structure views).
- `server_connections` table:
  - `id` — UUID primary key (`HasUuids`)
  - `source_server_id` — `foreignUuid` → `servers.id`, cascade delete
  - `target_server_id` — `foreignUuid` → `servers.id`, cascade delete
  - `source_handle` — nullable string (e.g. `'right'`, `'bottom'`)
  - `target_handle` — nullable string
  - `type` — string (Hosts / Network / Depends on / custom)
  - `label` — nullable string
  - timestamps
  - Unique constraint on `(source_server_id, target_server_id)`

### Models

**`App\Models\ServerConnection`:**
- Traits: `HasFactory`, `HasUuids`
- `$guarded = ['id']`
- `sourceServer(): BelongsTo`
- `targetServer(): BelongsTo`

**`App\Models\Server` additions:**
- `outgoingConnections(): HasMany`
- `incomingConnections(): HasMany`

### Routes

All under `auth` + `verified` middleware:

| Method | URL | Route Name | Permission |
|--------|-----|-----------|------------|
| GET | `/servers/{server}/structure` | `servers.structure` | `view server structure` |
| GET | `/servers/{server}/structure/fetch` | `servers.structure.fetch` | `view server structure` |
| POST | `/servers/{server}/structure/connect` | `servers.structure.connect` | `edit server structure` |
| DELETE | `/servers/{server}/structure/connect/{connection}` | `servers.structure.disconnect` | `edit server structure` |
| PUT | `/servers/{server}/structure/position` | `servers.structure.position` | `edit server structure` |
| DELETE | `/servers/{server}/structure/node/{node}` | `servers.structure.node.remove` | `edit server structure` |
| GET | `/servers/search` | `servers.search` | `view servers` |

### Backend — `ServerStructureController`

| Method | Behavior |
|--------|----------|
| `show(Server)` | Inertia page — BFS traversal from root server, returns cluster as `initialNodes`/`initialEdges` props |
| `fetch(Server)` | JSON endpoint — same BFS cluster data (for Refresh button) |
| `connect(Server, Request)` | Creates `ServerConnection` using `source_server_id`/`target_server_id` from request body. Validates Hosts cardinality, duplicate prevention |
| `disconnect(Server, ServerConnection)` | Deletes the connection |
| `updatePosition(Server, Request)` | Updates `canvas_x`/`canvas_y` on any server in the cluster |
| `removeNode(Server, string)` | Deletes all connections involving the node, clears its position. Prevents removing the root server |

**Cluster traversal:** BFS from the requested server along both outgoing and
incoming connections. Returns all reachable servers (nodes) and their
connecting edges. This is how "structure is shared" works — the same cluster
is returned regardless of which server in the group you open.

### Frontend — `Structure.vue`

- **Library:** `@vue-flow/core` + `@vue-flow/background` + `@vue-flow/controls`
  + `@vue-flow/minimap`
- **Page:** `resources/js/Pages/Servers/Structure.vue`
- **Modal:** `resources/js/Pages/Servers/Modals/AddStructureServerModal.vue`
  (multi-select, searchable)
- **Flow config:**
  - `ConnectionMode.Loose` — any handle can be source or target
  - `smoothstep` edge type with animated dash flow
  - 4 handles per node (top/right/bottom/left), hidden until hover
  - MiniMap + Controls
  - Background dot grid with theme-aware color
- **Edge styling:** Connection type determines stroke color, width, dash
- **Node design:** Card with icon, status dot (pulsing), host, OS badge,
  status badge, glow effect matching status
- **Remove button:** X button appears on node hover, positioned at top-right
  corner
- **Auto-fit:** `fitView()` called 300ms after mount and after adding/refreshing
  nodes

### Frontend — State management

- Node positions persisted on `onNodeDragStop` → `PUT position` API
- Edge handle IDs persisted on `onConnect` → `POST connect` API with
  `source_handle`/`target_handle`
- Edge handle IDs loaded back in `mapEdges()` with fallback `'right'`/`'left'`
  for legacy edges

---

## 8. Files created/modified

| File | Action |
|------|--------|
| `database/migrations/xxxx_add_canvas_position_to_servers.php` | Create |
| `database/migrations/xxxx_create_server_connections_table.php` | Create |
| `database/migrations/xxxx_add_handle_columns_to_server_connections_table.php` | Create |
| `app/Models/ServerConnection.php` | Create |
| `app/Models/Server.php` | Modify (add relationships) |
| `app/Http/Controllers/ServerStructureController.php` | Create |
| `app/Http/Controllers/ServerController.php` | Modify (add `search()` method) |
| `routes/web.php` | Modify (add structure routes) |
| `database/seeders/RoleSeeder.php` | Modify (seed new permissions) |
| `resources/js/Pages/Servers/Structure.vue` | Create |
| `resources/js/Pages/Servers/Modals/AddStructureServerModal.vue` | Create (renamed from `AddServerModal.vue`) |
| `resources/js/Pages/Servers/Index.vue` | Modify (add "Structure" dropdown item) |

---

## 9. Edge types reference

| Type | Color | Width | Dash | Use case |
|------|-------|-------|------|----------|
| Hosts | `#d97706` (amber) | 2.5 | `6 4` | Physical → VM |
| Network | `oklch(89.532% 0.16358 178.781)` (teal) | 2 | `6 4` | General network link |
| Depends on | `#7c3aed` (purple) | 2 | `6 3` | App dependency |
| (custom) | `#6b7280` (gray) | 1.5 | `6 4` | Free-text label |

---

## 10. Out of scope (possible future additions)

- Auto-layout / auto-arrange button (force-directed layout)
- Exporting the diagram as an image or PDF
- Grouping nodes visually (e.g. a bounding box around "Production" servers)
- Real-time collaborative editing

<script setup>
import { ref, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import {
    VueFlow,
    useVueFlow,
    Handle,
    Position,
    ConnectionMode,
} from "@vue-flow/core";
import { Background } from "@vue-flow/background";
import { Controls } from "@vue-flow/controls";
import { MiniMap } from "@vue-flow/minimap";
import {
    ArrowLeft,
    Plus,
    X,
    Monitor,
    Server as ServerIcon,
    Database,
    Wrench,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import axios from "axios";
import "@vue-flow/core/dist/style.css";
import "@vue-flow/core/dist/theme-default.css";
import AddStructureServerModal from "./Modals/AddStructureServerModal.vue";

const props = defineProps({
    server: Object,
    initialNodes: { type: Array, default: () => [] },
    initialEdges: { type: Array, default: () => [] },
});

const edgeTypeStyles = {
    Hosts: { stroke: "#d97706", strokeWidth: 2.5, strokeDasharray: "6 4" },
    Network: {
        stroke: "oklch(89.532% 0.16358 178.781)",
        strokeWidth: 2,
        strokeDasharray: "6 4",
    },
    "Depends on": { stroke: "#7c3aed", strokeWidth: 2, strokeDasharray: "6 3" },
};

const defaultEdgeStyle = {
    stroke: "#6b7280",
    strokeWidth: 1.5,
    strokeDasharray: "6 4",
};

const getEdgeStyle = (type) =>
    edgeTypeStyles[type] || {
        stroke: "#6b7280",
        strokeWidth: 1.5,
        strokeDasharray: "6 4",
    };

const mapNodes = (dataNodes) =>
    dataNodes.map((n) => ({
        id: n.id,
        type: "custom",
        position: { x: n.canvas_x ?? 250, y: n.canvas_y ?? 250 },
        data: {
            name: n.name,
            host: n.host,
            os: n.os,
            status: n.status,
            databases: n.databases ?? [],
            services: n.services ?? [],
        },
    }));

const buildEdge = (e, style) => ({
    id: e.id,
    source: e.source,
    target: e.target,
    sourceHandle: e.source_handle ?? "right",
    targetHandle: e.target_handle ?? "left",
    type: "smoothstep",
    animated: true,
    label: e.label || e.type,
    style: {
        stroke: style.stroke,
        strokeWidth: style.strokeWidth,
        strokeDasharray: style.strokeDasharray,
    },
    labelStyle: {
        fontSize: 11,
        fontWeight: 600,
        fill: "var(--foreground)",
        color: "var(--foreground)",
    },
    labelBgStyle: { fill: "var(--card)", fillOpacity: 0.95 },
    data: { connectionType: e.type },
});

const mapEdges = (dataEdges) =>
    dataEdges.map((e) => buildEdge(e, getEdgeStyle(e.type)));

const clusterServerIds = ref(props.initialNodes.map((n) => n.id));
const loading = ref(false);
const showAddModal = ref(false);

const initialNodePositions = () => {
    const mapped = mapNodes(props.initialNodes);
    const hasSaved = mapped.some(
        (_, i) =>
            props.initialNodes[i]?.canvas_x != null ||
            props.initialNodes[i]?.canvas_y != null,
    );
    if (!hasSaved) {
        mapped.forEach((n, i) => {
            n.position = { x: 100 + i * 350, y: 200 };
        });
    }
    return mapped;
};

const nodes = ref(initialNodePositions());
const edges = ref(mapEdges(props.initialEdges));

const {
    onConnect,
    onNodeDragStop,
    onEdgeClick,
    removeEdges,
    addEdges,
    removeNodes,
    fitView,
} = useVueFlow({ id: "structure" });

const fitViewDelayed = () => {
    setTimeout(() => fitView({ padding: 0.4, duration: 200 }), 100);
};

const initFromData = (dataNodes, dataEdges) => {
    clusterServerIds.value = dataNodes.map((n) => n.id);
    nodes.value = mapNodes(dataNodes);
    edges.value = mapEdges(dataEdges);
    fitViewDelayed();
};

const fetchCluster = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(
            route("servers.structure.fetch", props.server.id),
        );
        initFromData(data.nodes, data.edges);
    } catch {
        toast.error("Failed to load structure data");
    } finally {
        loading.value = false;
    }
};

onConnect((connection) => {
    if (!connection.source || !connection.target) return;

    axios
        .post(route("servers.structure.connect", props.server.id), {
            source_server_id: connection.source,
            target_server_id: connection.target,
            type: "Network",
            source_handle: connection.sourceHandle,
            target_handle: connection.targetHandle,
        })
        .then(({ data }) => {
            const style = getEdgeStyle(data.connection.type);
            data.connection.type = data.connection.type;
            addEdges([buildEdge(data.connection, style)]);
            toast.success("Connection created");
        })
        .catch((err) => {
            toast.error(
                err.response?.data?.message || "Failed to create connection",
            );
        });
});

onNodeDragStop(({ node }) => {
    const pos = {
        x: Math.round(node.position.x),
        y: Math.round(node.position.y),
    };
    axios
        .put(route("servers.structure.position", props.server.id), {
            server_id: node.id,
            canvas_x: pos.x,
            canvas_y: pos.y,
        })
        .catch(() => {});
});

onEdgeClick(({ edge }) => {
    axios
        .delete(
            route("servers.structure.disconnect", [props.server.id, edge.id]),
        )
        .then(() => {
            removeEdges([edge.id]);
            toast.success("Connection deleted");
        })
        .catch(() => toast.error("Failed to delete connection"));
});

const removeServer = (serverId) => {
    if (serverId === props.server.id) return;
    axios
        .delete(
            route("servers.structure.node.remove", [props.server.id, serverId]),
        )
        .then(() => {
            removeNodes([serverId]);
            removeEdges(
                edges.value
                    .filter(
                        (e) => e.source === serverId || e.target === serverId,
                    )
                    .map((e) => e.id),
            );
            clusterServerIds.value = clusterServerIds.value.filter(
                (id) => id !== serverId,
            );
            toast.success("Server removed from structure");
        })
        .catch(() => toast.error("Failed to remove server"));
};

const handleAddServers = (serversToAdd) => {
    const existing = nodes.value;
    const cx = existing.length
        ? existing.reduce((s, n) => s + n.position.x, 0) / existing.length
        : 400;
    const cy = existing.length
        ? existing.reduce((s, n) => s + n.position.y, 0) / existing.length
        : 250;
    const offset = Math.min(120 + existing.length * 30, 400);
    serversToAdd.forEach((server, i) => {
        if (clusterServerIds.value.includes(server.id)) return;
        clusterServerIds.value.push(server.id);
        const angle = (i / serversToAdd.length) * Math.PI * 2;
        nodes.value.push({
            id: server.id,
            type: "custom",
            position: {
                x: cx + Math.cos(angle) * offset,
                y: cy + Math.sin(angle) * offset - 80,
            },
            data: {
                name: server.name,
                host: server.host,
                os: server.os,
                status: server.status,
                databases: [],
                services: [],
            },
        });
    });
    showAddModal.value = false;
    fitViewDelayed();
    const count = serversToAdd.length;
    toast.success(`${count} server${count > 1 ? "s" : ""} added to canvas`);
};

onMounted(() => {
    setTimeout(() => fitView({ padding: 0.4, duration: 200 }), 300);
});
</script>

<template>
    <Head title="Structure" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('servers.index')"
                    class="text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <h2 class="text-xl font-semibold text-foreground">
                    Structure: {{ server.name }}
                </h2>
            </div>
        </template>

        <div class="flex h-[calc(100vh-8rem)] flex-col gap-4 p-4">
            <div class="flex items-center gap-2">
                <Button
                    size="sm"
                    class="rounded-md px-4"
                    @click="showAddModal = true"
                >
                    <Plus class="mr-1 h-4 w-4" />
                    Add server
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    class="rounded-md px-4"
                    @click="fetchCluster"
                    :disabled="loading"
                >
                    Refresh
                </Button>
            </div>

            <div
                v-if="loading"
                class="flex flex-1 items-center justify-center text-muted-foreground"
            >
                Loading structure...
            </div>

            <div
                v-else
                class="relative flex-1 rounded-xl border border-border bg-card/30"
            >
                <VueFlow
                    id="structure"
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    :connection-mode="ConnectionMode.Loose"
                    :default-edge-options="{
                        type: 'smoothstep',
                        animated: true,
                        style: defaultEdgeStyle,
                    }"
                    :connection-line-style="{
                        stroke: '#3b82f6',
                        strokeWidth: 2,
                    }"
                    :delete-key-code="['Backspace', 'Delete']"
                    class="structure-flow"
                >
                    <Background
                        :gap="24"
                        :size="1.5"
                        pattern-color="var(--pattern-dots)"
                    />

                    <template #node-custom="{ data, id }">
                        <Handle
                            id="top"
                            type="source"
                            :position="Position.Top"
                            class="custom-handle"
                        />
                        <Handle
                            id="right"
                            type="source"
                            :position="Position.Right"
                            class="custom-handle"
                        />
                        <Handle
                            id="bottom"
                            type="source"
                            :position="Position.Bottom"
                            class="custom-handle"
                        />
                        <Handle
                            id="left"
                            type="source"
                            :position="Position.Left"
                            class="custom-handle"
                        />
                        <div
                            class="structure-node group relative min-w-[200px] rounded-xl border-2 bg-card p-4 shadow-md transition-shadow hover:shadow-lg"
                            :class="
                                data.status === 'Online'
                                    ? 'border-emerald-500/40 node-glow-online'
                                    : 'border-red-500/30 node-glow-offline'
                            "
                        >
                            <button
                                v-if="id !== server.id"
                                @click.stop="removeServer(id)"
                                class="remove-btn absolute -right-3 -top-3 flex h-6 w-6 items-center justify-center rounded-full border-2 border-background bg-background text-muted-foreground opacity-0 transition-all duration-200 group-hover:opacity-100 hover:!bg-destructive hover:!text-destructive-foreground hover:scale-110 shadow-md"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                    :class="
                                        data.status === 'Online'
                                            ? 'bg-emerald-500/10 text-emerald-500'
                                            : 'bg-red-500/10 text-red-500'
                                    "
                                >
                                    <Monitor
                                        v-if="data.os === 'Windows'"
                                        class="h-5 w-5"
                                    />
                                    <ServerIcon v-else class="h-5 w-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="relative inline-block h-2 w-2 shrink-0 rounded-full"
                                            :class="
                                                data.status === 'Online'
                                                    ? 'bg-emerald-500 status-pulse-online'
                                                    : 'bg-red-500 status-pulse-offline'
                                            "
                                        />
                                        <span
                                            class="truncate text-sm font-semibold text-card-foreground"
                                            >{{ data.name }}</span
                                        >
                                    </div>
                                    <div
                                        class="mt-0.5 truncate text-xs text-muted-foreground"
                                    >
                                        {{ data.host }}
                                    </div>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-1.5"
                                    >
                                        <Badge
                                            variant="secondary"
                                            class="text-[10px] px-1.5 py-0 font-normal"
                                        >
                                            {{ data.os }}
                                        </Badge>
                                        <Badge
                                            :variant="
                                                data.status === 'Online'
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                            class="text-[10px] px-1.5 py-0 font-normal"
                                        >
                                            {{ data.status }}
                                        </Badge>
                                    </div>
                                    <div
                                        v-if="
                                            data.databases?.length > 0 ||
                                            data.services?.length > 0
                                        "
                                        class="mt-2 flex flex-col gap-1 border-t border-border/50 pt-2"
                                    >
                                        <div
                                            v-if="data.databases?.length > 0"
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <Database
                                                class="h-3 w-3 shrink-0 text-muted-foreground"
                                            />
                                            <span
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                {{
                                                    data.databases
                                                        .map((d) => d.name || d.type)
                                                        .join(", ")
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            v-if="data.services?.length > 0"
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <Wrench
                                                class="h-3 w-3 shrink-0 text-muted-foreground"
                                            />
                                            <span
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                {{
                                                    data.services
                                                        .map((s) => s.name)
                                                        .join(", ")
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <MiniMap
                        :node-color="
                            (n) =>
                                n.data?.status === 'Online'
                                    ? '#10b981'
                                    : '#ef4444'
                        "
                        :mask-color="'var(--background)'"
                        class="border border-border rounded-lg"
                    />
                    <Controls class="border border-border rounded-lg" />
                </VueFlow>
            </div>
        </div>

        <AddStructureServerModal
            :show="showAddModal"
            :exclude-ids="clusterServerIds"
            @close="showAddModal = false"
            @add="handleAddServers"
        />
    </AuthenticatedLayout>
</template>

<style>
.structure-flow {
    width: 100%;
    height: 100%;
}

.structure-flow .vue-flow__node {
    cursor: grab;
}

.structure-flow .vue-flow__node:active {
    cursor: grabbing;
}

.structure-flow .vue-flow__edge-path {
    cursor: pointer;
    filter: drop-shadow(0 0 3px currentColor);
}

.structure-flow .vue-flow__edge.animated .vue-flow__edge-path {
    animation: dashdraw 0.6s linear infinite;
}

@keyframes dashdraw {
    to {
        stroke-dashoffset: -20;
    }
}

.structure-flow .vue-flow__background {
    background:
        radial-gradient(
            circle at 50% 30%,
            rgba(59, 130, 246, 0.05),
            transparent 60%
        ),
        var(--background);
}

:root {
    --pattern-dots: rgba(0, 0, 0, 0.08);
}

.dark {
    --pattern-dots: rgba(255, 255, 255, 0.06);
}

.vue-flow__minimap {
    border-radius: 8px;
    overflow: hidden;
}

.vue-flow__controls {
    border-radius: 8px;
    overflow: hidden;
}

.vue-flow__node .custom-handle {
    opacity: 0;
    transition: opacity 0.15s ease;
    width: 14px !important;
    height: 14px !important;
    border: 2.5px solid var(--background) !important;
    background: var(--primary) !important;
    border-radius: 50% !important;
    z-index: 10 !important;
}

.vue-flow__node:hover .custom-handle {
    opacity: 1;
}

.vue-flow__node .custom-handle:hover {
    width: 18px !important;
    height: 18px !important;
    background: var(--accent) !important;
}

.structure-node {
    position: relative;
    border-radius: 0.75rem;
}

.structure-node::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.04),
        transparent 60%
    );
    pointer-events: none;
}

.node-glow-online {
    box-shadow: 0 0 18px -4px rgba(16, 185, 129, 0.35);
}

.node-glow-offline {
    box-shadow: 0 0 18px -4px rgba(239, 68, 68, 0.3);
}

.status-pulse-online,
.status-pulse-offline {
    position: relative;
}

.status-pulse-online::after,
.status-pulse-offline::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: 50%;
    animation: statuspulse 1.6s ease-out infinite;
}

.status-pulse-online::after {
    background: rgba(16, 185, 129, 0.6);
}

.status-pulse-offline::after {
    background: rgba(239, 68, 68, 0.6);
}

@keyframes statuspulse {
    0% {
        transform: scale(1);
        opacity: 0.7;
    }
    100% {
        transform: scale(2.8);
        opacity: 0;
    }
}
</style>

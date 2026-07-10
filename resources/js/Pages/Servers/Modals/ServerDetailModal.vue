<script setup>
import { ref, computed, reactive } from "vue";
import { Dialog, DialogContent, DialogTitle } from "@/Components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Button } from "@/Components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import {
    Database,
    Plus,
    Pencil,
    Trash2,
    MoreHorizontal,
    Server,
    Component,
    Video,
    Zap,
    Eye,
    EyeOff,
    Copy,
    Check,
} from "lucide-vue-next";
import DatabaseModal from "./DatabaseModal.vue";
import ServiceModal from "./ServiceModal.vue";
import { router } from "@inertiajs/vue3";
import { toast } from "vue-sonner";

const props = defineProps({
    open: Boolean,
    server: Object,
});

const emit = defineEmits(["update:open"]);

const activeTab = ref("databases");

// Database modal state
const databaseModalOpen = ref(false);
const databaseToEdit = ref(null);
const isDatabaseEdit = ref(false);

// Service modal state
const serviceModalOpen = ref(false);
const serviceToEdit = ref(null);
const isServiceEdit = ref(false);

const dbCount = computed(() => props.server?.databases?.length || 0);
const svcCount = computed(() => props.server?.services?.length || 0);

// Password reveal state per row
const revealedPasswords = reactive(new Set());
const copiedPasswords = reactive(new Set());

const togglePassword = (id) => {
    if (revealedPasswords.has(id)) {
        revealedPasswords.delete(id);
    } else {
        revealedPasswords.add(id);
    }
};

const copyPassword = async (id, password) => {
    try {
        await navigator.clipboard.writeText(password);
        copiedPasswords.add(id);
        setTimeout(() => copiedPasswords.delete(id), 2000);
    } catch {
        // fallback
    }
};

const openAddDatabase = () => {
    isDatabaseEdit.value = false;
    databaseToEdit.value = null;
    databaseModalOpen.value = true;
};

const openEditDatabase = (database) => {
    isDatabaseEdit.value = true;
    databaseToEdit.value = database;
    databaseModalOpen.value = true;
};

const deleteDatabase = (id) => {
    if (confirm("Are you sure you want to delete this database?")) {
        router.delete(route("servers.databases.destroy", id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => toast.success("Database deleted successfully."),
            onError: () => toast.error("Failed to delete database."),
        });
    }
};

const openAddService = () => {
    isServiceEdit.value = false;
    serviceToEdit.value = null;
    serviceModalOpen.value = true;
};

const openEditService = (service) => {
    isServiceEdit.value = true;
    serviceToEdit.value = service;
    serviceModalOpen.value = true;
};

const deleteService = (id) => {
    if (confirm("Are you sure you want to delete this service?")) {
        router.delete(route("servers.services.destroy", id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => toast.success("Service deleted successfully."),
            onError: () => toast.error("Failed to delete service."),
        });
    }
};

const handleHeaderAction = () => {
    if (activeTab.value === "databases") {
        openAddDatabase();
    } else {
        openAddService();
    }
};

const formatDate = (dateString) => {
    if (!dateString) return "—";
    const d = new Date(dateString);
    return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`;
};

const getServiceIcon = (name) => {
    const n = name.toLowerCase();
    if (n.includes("cctv") || n.includes("camera") || n.includes("video"))
        return Video;
    if (n.includes("rectifier") || n.includes("power")) return Zap;
    return Component;
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <!-- Remove default padding to match the mockup structure closely -->
        <DialogContent
            class="sm:max-w-5xl md:max-w-6xl w-[65vw] p-0 gap-0 overflow-hidden bg-card border-border"
        >
            <DialogTitle class="sr-only">Server Details</DialogTitle>

            <div v-if="server" class="flex flex-col h-full max-h-[85vh]">
                <!-- Custom Header matching mockup -->
                <div
                    class="px-6 py-5 flex items-center justify-between border-b border-border"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex items-center justify-center h-12 w-12 rounded-lg bg-background"
                        >
                            <Server class="h-6 w-6" />
                        </div>
                        <div class="flex flex-col">
                            <h2 class="text-xl font-semibold tracking-tight">
                                {{ server.name }}
                            </h2>
                            <p
                                class="text-sm text-muted-foreground font-medium mt-0.5"
                            >
                                {{ server.host || server.ip_address }} &middot;
                                {{ server.os }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <Button
                            @click="handleHeaderAction"
                            variant="outline"
                            class="bg-background text-foreground hover:bg-muted font-medium"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            {{
                                activeTab === "databases"
                                    ? "Add database"
                                    : "Add service"
                            }}
                        </Button>
                    </div>
                </div>

                <div class="flex-1 flex flex-col overflow-hidden">
                    <Tabs
                        v-model="activeTab"
                        class="w-full flex flex-col h-full"
                    >
                        <!-- Tabs matching the minimal line style in mockup -->
                        <div class="px-6 border-b border-border">
                            <TabsList class="h-auto p-0 bg-transparent gap-2">
                                <TabsTrigger
                                    value="databases"
                                    class="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:border-b-2 data-[state=active]:border-active data-[state=active]:text-foreground rounded-none border-0 border-b-2 border-transparent px-4 py-3 font-semibold text-muted-foreground hover:text-foreground"
                                >
                                    Databases
                                    <span
                                        class="ml-2 font-normal text-muted-foreground/70"
                                        >{{ dbCount }}</span
                                    >
                                </TabsTrigger>
                                <TabsTrigger
                                    value="services"
                                    class="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:border-b-2 data-[state=active]:border-active data-[state=active]:text-foreground rounded-none border-0 border-b-2 border-transparent px-4 py-3 font-semibold text-muted-foreground hover:text-foreground"
                                >
                                    Services
                                    <span
                                        class="ml-2 font-normal text-muted-foreground/70"
                                        >{{ svcCount }}</span
                                    >
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <div class="flex-1 overflow-auto bg-card">
                            <!-- Databases Tab -->
                            <TabsContent value="databases" class="m-0 h-full">
                                <Table>
                                    <TableHeader>
                                        <TableRow
                                            class="border-border hover:bg-transparent"
                                        >
                                            <TableHead
                                                class="font-medium h-10 w-[250px] pl-6"
                                                >Name</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Type</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Port</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Username</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Password</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Created</TableHead
                                            >
                                            <TableHead
                                                class="h-10 w-[50px] pr-6"
                                            ></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-if="dbCount === 0"
                                            class="border-border"
                                        >
                                            <TableCell
                                                colspan="7"
                                                class="text-center py-10 text-muted-foreground"
                                            >
                                                No databases configured for this
                                                server.
                                            </TableCell>
                                        </TableRow>
                                        <TableRow
                                            v-for="db in server.databases"
                                            :key="db.id"
                                            class="border-border group transition-colors hover:bg-muted/50"
                                        >
                                            <TableCell class="pl-6 font-medium">
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <Database
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    <span v-if="db.name">{{
                                                        db.name
                                                    }}</span>
                                                    <span
                                                        v-else
                                                        class="text-muted-foreground font-normal"
                                                        >Unnamed</span
                                                    >
                                                </div>
                                            </TableCell>
                                            <TableCell class="font-semibold">{{
                                                db.type
                                            }}</TableCell>
                                            <TableCell class="font-semibold">{{
                                                db.port || "—"
                                            }}</TableCell>
                                            <TableCell class="font-semibold">{{
                                                db.username || "—"
                                            }}</TableCell>
                                            <TableCell>
                                                <div v-if="db.decrypted_credentials" class="flex items-center gap-1.5">
                                                    <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">
                                                        {{ revealedPasswords.has(db.id) ? db.decrypted_credentials : '••••••••' }}
                                                    </code>
                                                    <button
                                                        type="button"
                                                        @click="togglePassword(db.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none"
                                                        :title="revealedPasswords.has(db.id) ? 'Hide' : 'Reveal'"
                                                    >
                                                        <EyeOff v-if="revealedPasswords.has(db.id)" class="h-3.5 w-3.5" />
                                                        <Eye v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyPassword(db.id, db.decrypted_credentials)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none"
                                                        title="Copy password"
                                                    >
                                                        <Check v-if="copiedPasswords.has(db.id)" class="h-3.5 w-3.5 text-green-500" />
                                                        <Copy v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </TableCell>
                                            <TableCell
                                                class="text-muted-foreground"
                                                >{{
                                                    formatDate(db.created_at)
                                                }}</TableCell
                                            >
                                            <TableCell class="pr-6 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                                                        >
                                                            <MoreHorizontal
                                                                class="h-4 w-4 text-muted-foreground"
                                                            />
                                                            <span
                                                                class="sr-only"
                                                                >Open menu</span
                                                            >
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuItem
                                                            @click="
                                                                openEditDatabase(
                                                                    db,
                                                                )
                                                            "
                                                            class="cursor-pointer"
                                                        >
                                                            <Pencil
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            @click="
                                                                deleteDatabase(
                                                                    db.id,
                                                                )
                                                            "
                                                            class="text-destructive focus:text-destructive cursor-pointer"
                                                        >
                                                            <Trash2
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </TabsContent>

                            <!-- Services Tab -->
                            <TabsContent value="services" class="m-0 h-full">
                                <Table>
                                    <TableHeader>
                                        <TableRow
                                            class="border-border hover:bg-transparent"
                                        >
                                            <TableHead
                                                class="font-medium h-10 w-[200px] pl-6"
                                                >Name</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Port</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Username</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Password</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Description</TableHead
                                            >
                                            <TableHead class="font-medium h-10"
                                                >Created</TableHead
                                            >
                                            <TableHead
                                                class="h-10 w-[50px] pr-6"
                                            ></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-if="svcCount === 0"
                                            class="border-border"
                                        >
                                            <TableCell
                                                colspan="7"
                                                class="text-center py-10 text-muted-foreground"
                                            >
                                                No services configured for this
                                                server.
                                            </TableCell>
                                        </TableRow>
                                        <TableRow
                                            v-for="svc in server.services"
                                            :key="svc.id"
                                            class="border-border group transition-colors hover:bg-muted/50"
                                        >
                                            <TableCell class="pl-6 font-medium">
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <component
                                                        :is="
                                                            getServiceIcon(
                                                                svc.name,
                                                            )
                                                        "
                                                        class="h-4 w-4 text-muted-foreground"
                                                    />
                                                    <span>{{ svc.name }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="font-semibold">{{
                                                svc.port
                                            }}</TableCell>
                                            <TableCell class="font-semibold">{{
                                                svc.username || "—"
                                            }}</TableCell>
                                            <TableCell>
                                                <div v-if="svc.decrypted_credentials" class="flex items-center gap-1.5">
                                                    <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">
                                                        {{ revealedPasswords.has(svc.id) ? svc.decrypted_credentials : '••••••••' }}
                                                    </code>
                                                    <button
                                                        type="button"
                                                        @click="togglePassword(svc.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none"
                                                        :title="revealedPasswords.has(svc.id) ? 'Hide' : 'Reveal'"
                                                    >
                                                        <EyeOff v-if="revealedPasswords.has(svc.id)" class="h-3.5 w-3.5" />
                                                        <Eye v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyPassword(svc.id, svc.decrypted_credentials)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none"
                                                        title="Copy password"
                                                    >
                                                        <Check v-if="copiedPasswords.has(svc.id)" class="h-3.5 w-3.5 text-green-500" />
                                                        <Copy v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </TableCell>
                                            <TableCell
                                                class="text-muted-foreground max-w-[200px] truncate"
                                                :title="svc.description"
                                                >{{
                                                    svc.description || "—"
                                                }}</TableCell
                                            >
                                            <TableCell
                                                class="text-muted-foreground"
                                                >{{
                                                    formatDate(svc.created_at)
                                                }}</TableCell
                                            >
                                            <TableCell class="pr-6 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                                                        >
                                                            <MoreHorizontal
                                                                class="h-4 w-4 text-muted-foreground"
                                                            />
                                                            <span
                                                                class="sr-only"
                                                                >Open menu</span
                                                            >
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuItem
                                                            @click="
                                                                openEditService(
                                                                    svc,
                                                                )
                                                            "
                                                            class="cursor-pointer"
                                                        >
                                                            <Pencil
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            @click="
                                                                deleteService(
                                                                    svc.id,
                                                                )
                                                            "
                                                            class="text-destructive focus:text-destructive cursor-pointer"
                                                        >
                                                            <Trash2
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <DatabaseModal
        v-model:open="databaseModalOpen"
        :server="server"
        :database="databaseToEdit"
        :is-edit="isDatabaseEdit"
    />

    <ServiceModal
        v-model:open="serviceModalOpen"
        :server="server"
        :service="serviceToEdit"
        :is-edit="isServiceEdit"
    />
</template>

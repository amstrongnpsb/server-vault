<script setup>
import { ref, computed, reactive, watch } from "vue";
import { Dialog, DialogContent, DialogTitle } from "@/Components/ui/dialog";
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
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
    RefreshCw,
} from "lucide-vue-next";
import DatabaseModal from "./DatabaseModal.vue";
import ServiceModal from "./ServiceModal.vue";
import { router } from "@inertiajs/vue3";
import { toast } from "vue-sonner";
import axios from "axios";
import { Skeleton } from "@/Components/ui/skeleton";
import { useServerDetails } from "@/composables/useServerDetails";

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

const { databases, services, isLoading, fetchDetails, invalidateCache } = useServerDetails();

const dbCount = computed(() => databases.value?.length || 0);
const svcCount = computed(() => services.value?.length || 0);

watch(() => props.open, (isOpen) => {
    if (isOpen && props.server?.id) {
        fetchDetails(props.server.id);
    }
});

// Password reveal state per row
// Password reveal state per row
const revealedPasswords = ref({});
const copiedPasswords = reactive(new Set());
const isLoadingPassword = ref({});

const togglePassword = async (type, id) => {
    if (revealedPasswords.value[id] !== undefined) {
        delete revealedPasswords.value[id];
        return;
    }

    try {
        isLoadingPassword.value[id] = true;
        const response = await axios.post(route('credentials.reveal'), { type, id });
        revealedPasswords.value[id] = response.data.password;
    } catch (e) {
        toast.error('Failed to reveal password');
    } finally {
        isLoadingPassword.value[id] = false;
    }
};

const copyPassword = async (type, id) => {
    let password = revealedPasswords.value[id];
    if (password === undefined) {
        try {
            isLoadingPassword.value[id] = true;
            const response = await axios.post(route('credentials.reveal'), { type, id });
            password = response.data.password;
        } catch (e) {
            toast.error('Failed to copy password');
            return;
        } finally {
            isLoadingPassword.value[id] = false;
        }
    }

    try {
        await navigator.clipboard.writeText(password);
        copiedPasswords.add(id);
        setTimeout(() => copiedPasswords.delete(id), 2000);
    } catch {
        toast.error('Failed to copy to clipboard');
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

const deleteDialogOpen = ref(false);
const itemToDelete = ref(null);
const deleteType = ref(null);
const isDeleting = ref(false);

const confirmDelete = (type, item) => {
    deleteType.value = type;
    itemToDelete.value = item;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogOpen.value = false;
    itemToDelete.value = null;
    deleteType.value = null;
    isDeleting.value = false;
};

const executeDelete = () => {
    if (!itemToDelete.value) return;
    
    isDeleting.value = true;
    const id = itemToDelete.value.id;
    
    if (deleteType.value === 'database') {
        router.delete(route("servers.databases.destroy", id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Database deleted successfully.");
                invalidateCache(props.server.id);
                fetchDetails(props.server.id, true);
                closeDeleteDialog();
            },
            onError: () => {
                toast.error("Failed to delete database.");
                isDeleting.value = false;
            },
        });
    } else if (deleteType.value === 'service') {
        router.delete(route("servers.services.destroy", id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Service deleted successfully.");
                invalidateCache(props.server.id);
                fetchDetails(props.server.id, true);
                closeDeleteDialog();
            },
            onError: () => {
                toast.error("Failed to delete service.");
                isDeleting.value = false;
            },
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

const handleSaved = () => {
    if (props.server?.id) {
        invalidateCache(props.server.id);
        fetchDetails(props.server.id, true);
    }
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
                            <div
                                class="text-sm text-muted-foreground font-medium mt-0.5 flex items-center gap-2 flex-wrap"
                            >
                                <span>{{ server.host || server.ip_address }} &middot; {{ server.os }}</span>
                                <span v-if="server.username">&middot; {{ server.username }}</span>
                                <div v-if="server.has_credentials" class="flex items-center gap-1.5">
                                    <code class="text-xs bg-muted/50 px-1.5 py-0.5 rounded font-mono min-w-[4rem]">
                                        <span v-if="isLoadingPassword[server.id]">...</span>
                                        <span v-else>{{ revealedPasswords[server.id] !== undefined ? revealedPasswords[server.id] : '••••••••' }}</span>
                                    </code>
                                    <button
                                        type="button"
                                        @click="togglePassword('server', server.id)"
                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                        :disabled="isLoadingPassword[server.id]"
                                        :title="revealedPasswords[server.id] !== undefined ? 'Hide' : 'Reveal'"
                                    >
                                        <EyeOff v-if="revealedPasswords[server.id] !== undefined" class="h-3.5 w-3.5" />
                                        <Eye v-else class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="copyPassword('server', server.id)"
                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                        :disabled="isLoadingPassword[server.id]"
                                        title="Copy password"
                                    >
                                        <Check v-if="copiedPasswords.has(server.id)" class="h-3.5 w-3.5 text-green-500" />
                                        <Copy v-else class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            @click="fetchDetails(server.id, true)"
                            variant="outline"
                            size="icon"
                            class="bg-background text-foreground hover:bg-muted font-medium"
                            :disabled="isLoading"
                            title="Refresh data"
                        >
                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
                        </Button>
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
                                        <template v-if="isLoading">
                                            <TableRow v-for="i in 3" :key="`db-skeleton-${i}`" class="border-border">
                                                <TableCell class="pl-6"><div class="flex items-center gap-3"><Skeleton class="h-4 w-4" /><Skeleton class="h-4 w-[120px]" /></div></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[80px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[40px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[80px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[100px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[60px]" /></TableCell>
                                                <TableCell class="pr-6"><Skeleton class="h-8 w-8 rounded-md ml-auto" /></TableCell>
                                            </TableRow>
                                        </template>
                                        <TableRow
                                            v-else-if="dbCount === 0"
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
                                            v-for="db in databases"
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
                                                <div v-if="db.has_credentials" class="flex items-center gap-1.5">
                                                    <code class="text-xs bg-muted/50 px-1.5 py-0.5 rounded font-mono min-w-[4rem]">
                                                        <span v-if="isLoadingPassword[db.id]">...</span>
                                                        <span v-else>{{ revealedPasswords[db.id] !== undefined ? revealedPasswords[db.id] : '••••••••' }}</span>
                                                    </code>
                                                    <button
                                                        type="button"
                                                        @click="togglePassword('database', db.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                                        :disabled="isLoadingPassword[db.id]"
                                                        :title="revealedPasswords[db.id] !== undefined ? 'Hide' : 'Reveal'"
                                                    >
                                                        <EyeOff v-if="revealedPasswords[db.id] !== undefined" class="h-3.5 w-3.5" />
                                                        <Eye v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyPassword('database', db.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                                        :disabled="isLoadingPassword[db.id]"
                                                        title="Copy password"
                                                    >
                                                        <Check v-if="copiedPasswords.has(db.id)" class="h-3.5 w-3.5 text-green-500" />
                                                        <Copy v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <span v-else class="text-muted-foreground px-1.5">—</span>
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
                                                                confirmDelete(
                                                                    'database',
                                                                    db,
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
                                        <template v-if="isLoading">
                                            <TableRow v-for="i in 3" :key="`svc-skeleton-${i}`" class="border-border">
                                                <TableCell class="pl-6"><div class="flex items-center gap-3"><Skeleton class="h-4 w-4" /><Skeleton class="h-4 w-[120px]" /></div></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[40px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[80px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[100px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[150px]" /></TableCell>
                                                <TableCell><Skeleton class="h-4 w-[60px]" /></TableCell>
                                                <TableCell class="pr-6"><Skeleton class="h-8 w-8 rounded-md ml-auto" /></TableCell>
                                            </TableRow>
                                        </template>
                                        <TableRow
                                            v-else-if="svcCount === 0"
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
                                            v-for="svc in services"
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
                                                <div v-if="svc.has_credentials" class="flex items-center gap-1.5">
                                                    <code class="text-xs bg-muted/50 px-1.5 py-0.5 rounded font-mono min-w-[4rem]">
                                                        <span v-if="isLoadingPassword[svc.id]">...</span>
                                                        <span v-else>{{ revealedPasswords[svc.id] !== undefined ? revealedPasswords[svc.id] : '••••••••' }}</span>
                                                    </code>
                                                    <button
                                                        type="button"
                                                        @click="togglePassword('service', svc.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                                        :disabled="isLoadingPassword[svc.id]"
                                                        :title="revealedPasswords[svc.id] !== undefined ? 'Hide' : 'Reveal'"
                                                    >
                                                        <EyeOff v-if="revealedPasswords[svc.id] !== undefined" class="h-3.5 w-3.5" />
                                                        <Eye v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="copyPassword('service', svc.id)"
                                                        class="text-muted-foreground hover:text-foreground transition-colors focus:outline-none disabled:opacity-50"
                                                        :disabled="isLoadingPassword[svc.id]"
                                                        title="Copy password"
                                                    >
                                                        <Check v-if="copiedPasswords.has(svc.id)" class="h-3.5 w-3.5 text-green-500" />
                                                        <Copy v-else class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                                <span v-else class="text-muted-foreground px-1.5">—</span>
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
                                                                confirmDelete(
                                                                    'service',
                                                                    svc,
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
        @saved="handleSaved"
    />

    <ServiceModal
        v-model:open="serviceModalOpen"
        :server="server"
        :service="serviceToEdit"
        :is-edit="isServiceEdit"
        @saved="handleSaved"
    />

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="closeDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                <AlertDialogDescription>
                    This action cannot be undone. This will permanently delete the {{ deleteType }}
                    <strong>{{ itemToDelete?.name || 'Unnamed' }}</strong> and remove its data from the system.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel
                    @click="closeDeleteDialog"
                    :disabled="isDeleting"
                >
                    Cancel
                </AlertDialogCancel>
                <Button
                    variant="destructive"
                    @click="executeDelete"
                    :disabled="isDeleting"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                    {{ isDeleting ? "Deleting..." : "Delete" }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

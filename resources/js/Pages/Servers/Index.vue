<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Skeleton } from "@/Components/ui/skeleton";
import FadeIn from "@/Components/FadeIn.vue";
import ServerModal from "@/Components/ServerModal.vue";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from "@/Components/ui/pagination";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Badge } from "@/Components/ui/badge";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/Components/ui/popover";
import OsIcon from "@/Components/OsIcon.vue";
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    Server,
    X,
    Eye,
    Monitor,
} from "lucide-vue-next";
import { ref, computed, watch } from "vue";
import { debounce } from "lodash-es";

const props = defineProps({
    servers: Object,
    osOptions: Array,
    statusOptions: Array,
    filters: Object,
});

const deleteDialogOpen = ref(false);
const serverToDelete = ref(null);
const search = ref(props.filters?.search || "");

// Modal state
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const serverToEdit = ref(null);

// Convert filters to arrays if needed
const initialOs = props.filters?.os || [];
const selectedOs = ref(
    Array.isArray(initialOs)
        ? initialOs
        : initialOs
          ? initialOs.split(",")
          : [],
);
const initialStatus = props.filters?.status || [];
const selectedStatus = ref(
    Array.isArray(initialStatus)
        ? initialStatus
        : initialStatus
          ? initialStatus.split(",")
          : [],
);

const isLoading = ref(false);

// Custom OS input for filter
const customOsInput = ref("");
const showCustomOsInput = ref(false);

const openDeleteDialog = (server) => {
    serverToDelete.value = server;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogOpen.value = false;
    serverToDelete.value = null;
};

const deleteServer = () => {
    if (!serverToDelete.value) return;

    router.delete(route("servers.destroy", serverToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteDialog();
        },
    });
};

// Modal functions
const openCreateModal = () => {
    createModalOpen.value = true;
};

const openEditModal = (server) => {
    serverToEdit.value = server;
    editModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
};

const closeEditModal = () => {
    editModalOpen.value = false;
    serverToEdit.value = null;
};

const handleServerSaved = () => {
    // Refresh the server list without flashing
    router.reload({
        only: ["servers"],
        preserveScroll: true,
        preserveState: true,
    });
};

// Search functionality
const performSearch = debounce(() => {
    isLoading.value = true;

    const osParam =
        selectedOs.value && selectedOs.value.length > 0
            ? selectedOs.value.join(",")
            : "";
    const statusParam =
        selectedStatus.value && selectedStatus.value.length > 0
            ? selectedStatus.value.join(",")
            : "";

    router.get(
        route("servers.index"),
        {
            search: search.value,
            os: osParam,
            status: statusParam,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}, 300);

watch(search, () => {
    performSearch();
});

watch(selectedOs, () => {
    performSearch();
});

watch(selectedStatus, () => {
    performSearch();
});

const clearSearch = () => {
    search.value = "";
};

const clearOsFilter = () => {
    selectedOs.value = [];
};

const clearStatusFilter = () => {
    selectedStatus.value = [];
};

const toggleOsFilter = (os) => {
    if (selectedOs.value.includes(os)) {
        selectedOs.value = selectedOs.value.filter((item) => item !== os);
    } else {
        selectedOs.value = [...selectedOs.value, os];
    }
};

const addCustomOs = () => {
    const customOs = customOsInput.value.trim();
    if (customOs && !selectedOs.value.includes(customOs)) {
        selectedOs.value = [...selectedOs.value, customOs];
        customOsInput.value = "";
    }
};

const removeOsFromFilter = (osToRemove) => {
    selectedOs.value = selectedOs.value.filter((os) => os !== osToRemove);
};

// Pagination computed properties
const currentPage = computed(() => props.servers.current_page);
const totalPages = computed(() => props.servers.last_page);
const itemsPerPage = computed(() => props.servers.per_page);
const totalItems = computed(() => props.servers.total);

const handlePageChange = (page) => {
    isLoading.value = true;

    const osParam =
        selectedOs.value && selectedOs.value.length > 0
            ? selectedOs.value.join(",")
            : "";
    const statusParam =
        selectedStatus.value && selectedStatus.value.length > 0
            ? selectedStatus.value.join(",")
            : "";

    router.get(
        route("servers.index"),
        {
            page: page,
            search: search.value,
            os: osParam,
            status: statusParam,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

// Helper functions
const getStatusBadgeClass = (status) => {
    return status === "Online"
        ? "bg-green-100 text-green-800 border-green-200"
        : "bg-red-100 text-red-800 border-red-200";
};
</script>

<template>
    <Head title="Servers" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">Server management</h1>
        </template>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <!-- Header Section with Add Button -->
                <FadeIn :delay="0.1">
                    <div
                        class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-base font-semibold">
                                    Server management
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Manage your connected servers and access
                                    credentials.
                                    <span v-if="isLoading">
                                        <Skeleton
                                            class="inline-block h-4 w-24"
                                        />
                                    </span>
                                    <span
                                        v-else-if="
                                            search ||
                                            (selectedOs &&
                                                selectedOs.length > 0) ||
                                            (selectedStatus &&
                                                selectedStatus.length > 0)
                                        "
                                        class="font-medium text-foreground"
                                    >
                                        {{ servers.total }} result{{
                                            servers.total !== 1 ? "s" : ""
                                        }}
                                        found
                                    </span>
                                    <span v-else>
                                        Total servers: {{ servers.total }}
                                    </span>
                                </p>
                            </div>
                            <Button
                                @click="openCreateModal"
                                class="transition-all duration-200 hover:scale-105"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Add server
                            </Button>
                        </div>
                    </div>
                </FadeIn>

                <!-- Search and Filters -->
                <FadeIn :delay="0.2">
                    <div class="flex items-center gap-3">
                        <!-- Search -->
                        <div class="relative flex-1 max-w-md">
                            <Search
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-colors duration-200"
                            />
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Search servers..."
                                class="pl-10 pr-10 transition-all duration-200 focus:pl-10 focus:pr-10"
                            />
                            <button
                                v-if="search"
                                @click="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-all duration-200 hover:text-foreground hover:scale-110"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- OS Filter -->
                        <div class="flex items-center gap-2">
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        class="w-48 justify-start text-left font-normal"
                                    >
                                        <Server class="mr-2 h-4 w-4" />
                                        <span class="truncate">
                                            {{
                                                selectedOs.length > 0
                                                    ? `${selectedOs.length} selected`
                                                    : "Filter by OS"
                                            }}
                                        </span>
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-64 p-0" align="start">
                                    <div class="p-2 space-y-2">
                                        <!-- Predefined OS options -->
                                        <div class="space-y-1">
                                            <div
                                                class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                                            >
                                                Select OS
                                            </div>
                                            <div
                                                v-for="os in osOptions"
                                                :key="os"
                                                class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-accent cursor-pointer transition-colors"
                                                @click="toggleOsFilter(os)"
                                            >
                                                <div
                                                    class="h-4 w-4 border rounded flex items-center justify-center"
                                                    :class="
                                                        selectedOs.includes(os)
                                                            ? 'bg-primary border-primary'
                                                            : 'border-input'
                                                    "
                                                >
                                                    <svg
                                                        v-if="
                                                            selectedOs.includes(
                                                                os,
                                                            )
                                                        "
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
                                                <OsIcon
                                                    :os="os"
                                                    size="h-4 w-4"
                                                />
                                                <span class="text-sm">{{
                                                    os
                                                }}</span>
                                            </div>
                                        </div>

                                        <!-- Divider -->
                                        <div
                                            class="border-t border-border"
                                        ></div>

                                        <!-- Custom OS input -->
                                        <div class="space-y-2">
                                            <div
                                                class="px-2 py-1.5 text-xs font-medium text-muted-foreground rounded-sm"
                                            >
                                                Add Custom OS
                                            </div>
                                            <div class="flex gap-1 px-2">
                                                <Input
                                                    v-model="customOsInput"
                                                    placeholder="Type OS name..."
                                                    class="h-8 text-sm"
                                                    @keyup.enter="addCustomOs"
                                                    @keydown.stop
                                                />
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    @click="addCustomOs"
                                                    class="h-8 px-2 rounded-sm hover:opacity-80 cursor-pointer"
                                                    :disabled="
                                                        !customOsInput.trim()
                                                    "
                                                >
                                                    <Plus class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Selected custom OS (not in predefined list) -->
                                        <div
                                            v-if="
                                                selectedOs.some(
                                                    (os) =>
                                                        !osOptions.includes(os),
                                                )
                                            "
                                            class="space-y-1"
                                        >
                                            <div
                                                class="border-t border-border"
                                            ></div>
                                            <div
                                                class="px-2 py-1.5 text-xs font-medium text-muted-foreground"
                                            >
                                                Custom OS Selected
                                            </div>
                                            <div
                                                v-for="os in selectedOs.filter(
                                                    (os) =>
                                                        !osOptions.includes(os),
                                                )"
                                                :key="os"
                                                class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md hover:bg-accent transition-colors"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <OsIcon
                                                        :os="os"
                                                        size="h-4 w-4"
                                                    />
                                                    <span class="text-sm">{{
                                                        os
                                                    }}</span>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="
                                                        removeOsFromFilter(os)
                                                    "
                                                    class="text-muted-foreground hover:text-destructive"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>

                            <!-- Selected OS badges -->
                            <div
                                v-if="selectedOs.length > 0"
                                class="flex items-center gap-1"
                            >
                                <div class="flex flex-wrap gap-1 max-w-md">
                                    <Badge
                                        v-for="os in selectedOs.slice(0, 3)"
                                        :key="os"
                                        variant="secondary"
                                        class="flex items-center gap-1 px-2 py-0.5"
                                    >
                                        <OsIcon :os="os" size="h-3 w-3" />
                                        <span class="text-xs">{{ os }}</span>
                                        <button
                                            type="button"
                                            @click="removeOsFromFilter(os)"
                                            class="ml-1 hover:text-destructive"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </Badge>
                                    <Badge
                                        v-if="selectedOs.length > 3"
                                        variant="secondary"
                                        class="px-2 py-0.5 text-xs"
                                    >
                                        +{{ selectedOs.length - 3 }} more
                                    </Badge>
                                </div>
                            </div>

                            <button
                                v-if="selectedOs && selectedOs.length > 0"
                                type="button"
                                @click="clearOsFilter"
                                class="flex items-center justify-center w-8 h-8 text-muted-foreground transition-all duration-200 hover:text-foreground hover:scale-110 hover:bg-muted/50 rounded-md"
                                title="Clear OS filter"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center gap-2">
                            <Select v-model="selectedStatus" multiple>
                                <SelectTrigger class="w-48">
                                    <SelectValue
                                        placeholder="Filter by status"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Status</SelectLabel>
                                        <SelectItem
                                            v-for="status in statusOptions"
                                            :key="status"
                                            :value="status"
                                        >
                                            {{ status }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <button
                                v-if="
                                    selectedStatus && selectedStatus.length > 0
                                "
                                type="button"
                                @click="clearStatusFilter"
                                class="flex items-center justify-center w-8 h-8 text-muted-foreground transition-all duration-200 hover:text-foreground hover:scale-110 hover:bg-muted/50 rounded-md"
                                title="Clear status filter"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </FadeIn>

                <!-- Servers Table -->
                <FadeIn :delay="0.3">
                    <div
                        class="rounded-md border border-border bg-card shadow-sm overflow-hidden transition-opacity duration-200"
                        :class="{ 'opacity-90': isLoading }"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Host</TableHead>
                                    <TableHead>OS</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Created at</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <!-- Loading State with Skeleton -->
                                <template v-if="isLoading">
                                    <TableRow
                                        v-for="i in 10"
                                        :key="`skeleton-${i}`"
                                    >
                                        <TableCell class="font-medium">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Skeleton
                                                    class="h-5 w-5 rounded-full"
                                                />
                                                <Skeleton class="h-4 w-32" />
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Skeleton class="h-4 w-32" />
                                        </TableCell>
                                        <TableCell>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Skeleton
                                                    class="h-4 w-4 rounded"
                                                />
                                                <Skeleton class="h-4 w-16" />
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Skeleton
                                                class="h-6 w-16 rounded-full"
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Skeleton class="h-4 w-24" />
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Skeleton
                                                class="h-8 w-8 rounded-md ml-auto"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <!-- No Results State -->
                                <TableRow v-else-if="servers.data.length === 0">
                                    <TableCell
                                        colspan="6"
                                        class="text-center text-muted-foreground py-12"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-2"
                                        >
                                            <Monitor
                                                class="h-12 w-12 text-muted-foreground/50"
                                            />
                                            <p class="text-base">
                                                No servers found
                                            </p>
                                            <p
                                                v-if="
                                                    search ||
                                                    (selectedOs &&
                                                        selectedOs.length >
                                                            0) ||
                                                    (selectedStatus &&
                                                        selectedStatus.length >
                                                            0)
                                                "
                                                class="text-sm"
                                            >
                                                Try adjusting your filters
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <!-- Server Data -->
                                <template v-else>
                                    <TableRow
                                        v-for="server in servers.data"
                                        :key="server.id"
                                        class="transition-colors duration-200 hover:bg-muted/50"
                                    >
                                        <TableCell class="font-medium">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Server
                                                    class="h-4 w-4 text-muted-foreground"
                                                />
                                                {{ server.name }}
                                            </div>
                                        </TableCell>
                                        <TableCell>{{ server.host }}</TableCell>
                                        <TableCell>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <OsIcon
                                                    :os="server.os"
                                                    size="h-4 w-4"
                                                />
                                                {{ server.os }}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                :class="[
                                                    'rounded-full px-2 py-1 text-xs font-medium',
                                                    getStatusBadgeClass(
                                                        server.status,
                                                    ),
                                                ]"
                                            >
                                                <div
                                                    class="flex items-center gap-1"
                                                >
                                                    <div
                                                        class="h-2 w-2 rounded-full"
                                                        :class="
                                                            server.status ===
                                                            'Online'
                                                                ? 'bg-green-500'
                                                                : 'bg-red-500'
                                                        "
                                                    />
                                                    {{ server.status }}
                                                </div>
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {{
                                                new Date(
                                                    server.created_at,
                                                ).toLocaleDateString()
                                            }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="transition-all duration-200 hover:scale-110"
                                                    >
                                                        <MoreHorizontal
                                                            class="h-4 w-4"
                                                        />
                                                        <span class="sr-only"
                                                            >Open menu</span
                                                        >
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                >
                                                    <DropdownMenuLabel
                                                        >Actions</DropdownMenuLabel
                                                    >
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem as-child>
                                                        <Link
                                                            :href="
                                                                route(
                                                                    'servers.show',
                                                                    server.id,
                                                                )
                                                            "
                                                            class="flex w-full cursor-pointer items-center"
                                                        >
                                                            <Eye
                                                                class="mr-2 h-4 w-4"
                                                            />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        @click="
                                                            openEditModal(
                                                                server,
                                                            )
                                                        "
                                                        class="flex w-full cursor-pointer items-center"
                                                    >
                                                        <Pencil
                                                            class="mr-2 h-4 w-4"
                                                        />
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        class="text-destructive focus:text-destructive"
                                                        @click="
                                                            openDeleteDialog(
                                                                server,
                                                            )
                                                        "
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
                                </template>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="totalPages > 1"
                        class="border-t border-border p-4"
                    >
                        <div
                            class="flex flex-col items-center gap-4 sm:flex-row sm:justify-between"
                        >
                            <div class="text-sm text-muted-foreground">
                                <span v-if="isLoading">
                                    <Skeleton class="inline-block h-4 w-32" />
                                </span>
                                <span v-else>
                                    Showing {{ servers.from }} to
                                    {{ servers.to }} of {{ totalItems }} results
                                </span>
                            </div>

                            <Pagination
                                :page="currentPage"
                                @update:page="handlePageChange"
                                :total="totalItems"
                                :items-per-page="itemsPerPage"
                                :sibling-count="2"
                                show-edges
                            >
                                <PaginationContent v-slot="{ items }">
                                    <PaginationFirst
                                        @click="handlePageChange(1)"
                                    />
                                    <PaginationPrevious
                                        @click="
                                            currentPage > 1 &&
                                            handlePageChange(currentPage - 1)
                                        "
                                    />

                                    <template
                                        v-for="(item, index) in items"
                                        :key="index"
                                    >
                                        <Button
                                            v-if="item.type === 'page'"
                                            :variant="
                                                item.value === currentPage
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                            size="icon"
                                            @click="
                                                handlePageChange(item.value)
                                            "
                                        >
                                            {{ item.value }}
                                        </Button>

                                        <PaginationEllipsis
                                            v-else
                                            :index="index"
                                        />
                                    </template>

                                    <PaginationNext
                                        @click="
                                            currentPage < totalPages &&
                                            handlePageChange(currentPage + 1)
                                        "
                                    />
                                    <PaginationLast
                                        @click="handlePageChange(totalPages)"
                                    />
                                </PaginationContent>
                            </Pagination>
                        </div>
                    </div>
                </FadeIn>
            </div>
        </div>

        <!-- Create Server Modal -->
        <ServerModal
            v-model:open="createModalOpen"
            :os-options="osOptions"
            :status-options="statusOptions"
            @saved="handleServerSaved"
        />

        <!-- Edit Server Modal -->
        <ServerModal
            v-model:open="editModalOpen"
            :server="serverToEdit"
            :os-options="osOptions"
            :status-options="statusOptions"
            :is-edit="true"
            @saved="handleServerSaved"
        />

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="deleteDialogOpen" @update:open="closeDeleteDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >Are you absolutely sure?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently
                        delete the server
                        <strong>{{ serverToDelete?.name }}</strong> and remove
                        its data from the system.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="closeDeleteDialog"
                        >Cancel</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="deleteServer"
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AuthenticatedLayout>
</template>

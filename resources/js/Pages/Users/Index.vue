<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import FadeIn from "@/Components/FadeIn.vue";
import MultiSelectFilter from "@/Components/MultiSelectFilter.vue";
import UserModal from "./Modals/UserModal.vue";
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
    AlertDialog,
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
import { Skeleton } from "@/Components/ui/skeleton";
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserCircle,
    X,
    Shield,
} from "lucide-vue-next";
import { ref, computed, watch } from "vue";
import { debounce } from "lodash-es";
import { toast } from "vue-sonner";

const props = defineProps({
    users: Object,
    roles: Array,
    filters: Object,
});

const deleteDialogOpen = ref(false);
const userToDelete = ref(null);
const isDeleting = ref(false);
const search = ref(props.filters?.search || "");
// Convert string to array if needed (when coming from URL)
const initialRole = props.filters?.role || [];
const selectedRole = ref(
    Array.isArray(initialRole)
        ? initialRole
        : initialRole
          ? initialRole.split(",")
          : [],
);
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const userToEdit = ref(null);

const isLoading = ref(false);

// Convert roles array to simple string array for MultiSelectFilter
const roleOptions = computed(() => {
    return props.roles.map((role) => role.name);
});

// Modal functions
const openCreateModal = () => {
    createModalOpen.value = true;
};

const openEditModal = (user) => {
    userToEdit.value = user;
    editModalOpen.value = true;
};

watch(
    () => props.users,
    (newUsers) => {
        if (newUsers && newUsers.data) {
            if (userToEdit.value) {
                const updatedUser = newUsers.data.find(
                    (u) => u.id === userToEdit.value.id,
                );
                if (updatedUser) {
                    userToEdit.value = updatedUser;
                }
            }
        }
    },
    { deep: true },
);

const handleUserSaved = () => {
    router.reload({
        only: ["users"],
        preserveScroll: true,
        preserveState: true,
    });
};

const openDeleteDialog = (user) => {
    userToDelete.value = user;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogOpen.value = false;
    userToDelete.value = null;
    isDeleting.value = false;
};

const deleteUser = () => {
    if (!userToDelete.value) return;

    if (isDeleting.value) return;

    isDeleting.value = true;

    router.delete(route("users.destroy", userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("User deleted successfully!");
            closeDeleteDialog();
        },
        onError: () => {
            toast.error("Failed to delete user");
            isDeleting.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const getRoleBadgeClass = (roleName) => {
    const classes = {
        admin: "border-cyan-500 ",
        superadmin: "border-red-500",
        user: "border-green-500",
    };
    return classes[roleName?.toLowerCase()] || "border-border text-foreground";
};

// Search functionality
const performSearch = debounce(() => {
    isLoading.value = true;

    // Convert array to comma-separated string for URL compatibility
    const roleParam =
        selectedRole.value && selectedRole.value.length > 0
            ? selectedRole.value.join(",")
            : "";

    router.get(
        route("users.index"),
        {
            search: search.value,
            role: roleParam,
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

watch(selectedRole, () => {
    performSearch();
});

const clearSearch = () => {
    search.value = "";
};

// Pagination computed properties
const currentPage = computed(() => props.users.current_page);
const totalPages = computed(() => props.users.last_page);
const itemsPerPage = computed(() => props.users.per_page);
const totalItems = computed(() => props.users.total);

// Debug pagination values
console.log("Pagination Debug:", {
    currentPage: currentPage.value,
    totalPages: totalPages.value,
    itemsPerPage: itemsPerPage.value,
    totalItems: totalItems.value,
    usersObject: props.users,
});

const handlePageChange = (page) => {
    console.log("Navigating to page:", page);
    isLoading.value = true;

    // Convert array to comma-separated string for URL compatibility
    const roleParam =
        selectedRole.value && selectedRole.value.length > 0
            ? selectedRole.value.join(",")
            : "";

    router.get(
        route("users.index"),
        {
            page: page,
            search: search.value,
            role: roleParam,
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
</script>

<template>
    <Head title="User" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">User</h1>
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
                                    User Management
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Manage application users, roles, and access.
                                    <span v-if="isLoading">
                                        <Skeleton
                                            class="inline-block h-4 w-24"
                                        />
                                    </span>
                                    <span
                                        v-else-if="search"
                                        class="font-medium text-foreground"
                                    >
                                        {{ users.total }} result{{
                                            users.total !== 1 ? "s" : ""
                                        }}
                                        found
                                    </span>
                                    <span v-else>
                                        Total users: {{ users.total }}
                                    </span>
                                </p>
                            </div>
                            <Button
                                @click="openCreateModal"
                                class="transition-all duration-200 hover:scale-105"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Add User
                            </Button>
                        </div>
                    </div>
                </FadeIn>

                <!-- Search Filter - Minimalist & Elegant -->
                <FadeIn :delay="0.2">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <Search
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-colors duration-200"
                            />
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Search users..."
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

                        <!-- Role Filter -->
                        <MultiSelectFilter
                            v-model="selectedRole"
                            :options="roleOptions"
                            :icon="Shield"
                            placeholder="Filter by Role"
                            label="Select Role"
                            custom-label="Add Custom Role"
                            custom-placeholder="Type role name..."
                            :allow-custom="true"
                            :max-badges="3"
                        />
                    </div>
                </FadeIn>

                <!-- Users Table -->
                <FadeIn :delay="0.3">
                    <div
                        class="rounded-md border border-border bg-card shadow-sm overflow-hidden transition-opacity duration-200"
                        :class="{ 'opacity-90': isLoading }"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead>Created At</TableHead>
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
                                            <Skeleton class="h-4 w-48" />
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-1">
                                                <Skeleton
                                                    class="h-6 w-16 rounded-sm"
                                                />
                                            </div>
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
                                <TableRow v-else-if="users.data.length === 0">
                                    <TableCell
                                        colspan="5"
                                        class="text-center text-muted-foreground py-12"
                                    >
                                        <div
                                            class="flex flex-col items-center gap-2"
                                        >
                                            <Search
                                                class="h-12 w-12 text-muted-foreground/50"
                                            />
                                            <p class="text-base">
                                                No users found
                                            </p>
                                            <p
                                                v-if="search || selectedRole"
                                                class="text-sm"
                                            >
                                                Try adjusting your filters
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <!-- User Data -->
                                <TableRow
                                    v-else
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="transition-colors duration-200 hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            <UserCircle
                                                class="h-5 w-5 text-muted-foreground"
                                            />
                                            {{ user.name }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ user.email }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                :variant="'outline'"
                                                :class="[
                                                    'rounded-sm px-2 py-1 text-xs font-medium transition-all duration-200 hover:scale-105',
                                                    getRoleBadgeClass(
                                                        role.name,
                                                    ),
                                                ]"
                                            >
                                                {{ role.name }}
                                            </Badge>
                                            <span
                                                v-if="
                                                    !user.roles ||
                                                    user.roles.length === 0
                                                "
                                                class="text-sm text-muted-foreground"
                                            >
                                                No roles
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{
                                            new Date(
                                                user.created_at,
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
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel
                                                    >Actions</DropdownMenuLabel
                                                >
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="cursor-pointer"
                                                    @click="openEditModal(user)"
                                                >
                                                    <Pencil
                                                        class="mr-2 h-4 w-4"
                                                    />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive cursor-pointer"
                                                    @click="
                                                        openDeleteDialog(user)
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
                                    Showing {{ users.from }} to
                                    {{ users.to }} of {{ totalItems }} results
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

        <!-- Create User Modal -->
        <UserModal
            v-model:open="createModalOpen"
            :roles="roles"
            @saved="handleUserSaved"
        />

        <!-- Edit User Modal -->
        <UserModal
            v-model:open="editModalOpen"
            :user="userToEdit"
            :roles="roles"
            :is-edit="true"
            @saved="handleUserSaved"
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
                        delete the user
                        <strong>{{ userToDelete?.name }}</strong> and remove
                        their data from the system.
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
                        @click="deleteUser"
                        :disabled="isDeleting"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        {{ isDeleting ? "Deleting..." : "Delete" }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import FadeIn from "@/Components/FadeIn.vue";
import RoleModal from "./Modals/RoleModal.vue";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
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
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
    Shield,
} from "lucide-vue-next";
import { ref } from "vue";
import { toast } from "vue-sonner";
import { usePermission } from "@/composables/usePermission";

const props = defineProps({
    roles: Array,
    permissions: Array,
});

const { hasPermission } = usePermission();

const deleteDialogOpen = ref(false);
const roleToDelete = ref(null);
const isDeleting = ref(false);
const createModalOpen = ref(false);
const editModalOpen = ref(false);
const roleToEdit = ref(null);

const openCreateModal = () => {
    createModalOpen.value = true;
};

const openEditModal = (role) => {
    roleToEdit.value = role;
    editModalOpen.value = true;
};

const handleRoleSaved = () => {
    router.reload({
        only: ["roles"],
        preserveScroll: true,
        preserveState: true,
    });
};

const openDeleteDialog = (role) => {
    roleToDelete.value = role;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogOpen.value = false;
    roleToDelete.value = null;
    isDeleting.value = false;
};

const deleteRole = () => {
    if (!roleToDelete.value || isDeleting.value) return;

    isDeleting.value = true;

    router.delete(route("roles.destroy", roleToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("Role deleted successfully!");
            closeDeleteDialog();
        },
        onError: (errors) => {
            toast.error(errors?.message || "Failed to delete role");
            isDeleting.value = false;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const getBadgeClass = (roleName) => {
    const classes = {
        superadmin: "border-red-500 text-red-500",
        admin: "border-cyan-500 text-cyan-500",
        user: "border-green-500 text-green-500",
    };
    return classes[roleName?.toLowerCase()] || "";
};
</script>

<template>
    <Head title="Roles" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">Roles</h1>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <FadeIn :delay="0.1">
                    <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-base font-semibold">
                                    Role Management
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Manage user roles and assign permissions.
                                    Total roles: {{ roles.length }}
                                </p>
                            </div>
                            <Button
                                v-if="hasPermission('manage roles')"
                                @click="openCreateModal"
                                class="transition-all duration-200 hover:scale-105"
                            >
                                <Plus class="mr-2 h-4 w-4" />
                                Add Role
                            </Button>
                        </div>
                    </div>
                </FadeIn>

                <FadeIn :delay="0.3">
                    <div class="rounded-md border border-border bg-card shadow-sm overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Display Name</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Permissions</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="roles.length === 0">
                                    <TableCell colspan="5" class="text-center text-muted-foreground py-12">
                                        <div class="flex flex-col items-center gap-2">
                                            <Shield class="h-12 w-12 text-muted-foreground/50" />
                                            <p class="text-base">No roles found</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="transition-colors duration-200 hover:bg-muted/50"
                                >
                                    <TableCell class="font-medium">
                                        <Badge :variant="'outline'" :class="['rounded-sm px-2 py-1 text-xs font-medium', getBadgeClass(role.name)]">
                                            {{ role.name }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{ role.display_name || "—" }}</TableCell>
                                    <TableCell class="max-w-xs truncate">
                                        {{ role.description || "—" }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="perm in role.permissions"
                                                :key="perm.id"
                                                variant="secondary"
                                                class="rounded-sm text-xs"
                                            >
                                                {{ perm.name }}
                                            </Badge>
                                            <span v-if="!role.permissions?.length" class="text-sm text-muted-foreground">None</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="transition-all duration-200 hover:scale-110">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                    <span class="sr-only">Open menu</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    v-if="hasPermission('manage roles')"
                                                    class="cursor-pointer"
                                                    @click="openEditModal(role)"
                                                >
                                                    <Pencil class="mr-2 h-4 w-4" />
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="hasPermission('manage roles')"
                                                    class="text-destructive focus:text-destructive cursor-pointer"
                                                    @click="openDeleteDialog(role)"
                                                >
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </FadeIn>
            </div>
        </div>

        <RoleModal
            v-model:open="createModalOpen"
            :role="null"
            :permissions="permissions"
            @saved="handleRoleSaved"
        />

        <RoleModal
            v-model:open="editModalOpen"
            :role="roleToEdit"
            :permissions="permissions"
            @saved="handleRoleSaved"
        />

        <AlertDialog :open="deleteDialogOpen" @update:open="closeDeleteDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently
                        delete the role
                        <strong>{{ roleToDelete?.display_name || roleToDelete?.name }}</strong>.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="closeDeleteDialog" :disabled="isDeleting">
                        Cancel
                    </AlertDialogCancel>
                    <Button
                        variant="destructive"
                        @click="deleteRole"
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

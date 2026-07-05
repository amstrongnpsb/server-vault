<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Badge } from '@/Components/ui/badge';
import { MoreHorizontal, Pencil, Plus, Trash2, UserCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps({
    users: Object,
    roles: Array,
});

const page = usePage();
const deleteDialogOpen = ref(false);
const userToDelete = ref(null);

// Show success/error messages
if (page.props.flash?.success) {
    toast.success(page.props.flash.success);
}

if (page.props.flash?.error) {
    toast.error(page.props.flash.error);
}

const openDeleteDialog = (user) => {
    userToDelete.value = user;
    deleteDialogOpen.value = true;
};

const closeDeleteDialog = () => {
    deleteDialogOpen.value = false;
    userToDelete.value = null;
};

const deleteUser = () => {
    if (!userToDelete.value) return;

    router.delete(route('users.destroy', userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteDialog();
        },
    });
};

const getRoleBadgeVariant = (roleName) => {
    const variants = {
        'admin': 'destructive',
        'moderator': 'default',
        'user': 'secondary',
    };
    return variants[roleName?.toLowerCase()] || 'outline';
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
                <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-base font-semibold">User Management</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Manage application users, roles, and access. Total users: {{ users.total }}
                            </p>
                        </div>
                        <Button as-child>
                            <Link :href="route('users.create')">
                                <Plus class="mr-2 h-4 w-4" />
                                Add User
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="rounded-md border border-border bg-card shadow-sm">
                    <div class="overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead>Created At</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="users.data.length === 0">
                                    <TableCell colspan="5" class="text-center text-muted-foreground">
                                        No users found.
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="user in users.data" :key="user.id">
                                    <TableCell class="font-medium">
                                        <div class="flex items-center gap-2">
                                            <UserCircle class="h-5 w-5 text-muted-foreground" />
                                            {{ user.name }}
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ user.email }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="role in user.roles"
                                                :key="role.id"
                                                :variant="getRoleBadgeVariant(role.name)"
                                            >
                                                {{ role.name }}
                                            </Badge>
                                            <span
                                                v-if="!user.roles || user.roles.length === 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                No roles
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {{ new Date(user.created_at).toLocaleDateString() }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon">
                                                    <MoreHorizontal class="h-4 w-4" />
                                                    <span class="sr-only">Open menu</span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem as-child>
                                                    <Link
                                                        :href="route('users.edit', user.id)"
                                                        class="flex w-full cursor-pointer items-center"
                                                    >
                                                        <Pencil class="mr-2 h-4 w-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="openDeleteDialog(user)"
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

                    <!-- Pagination -->
                    <div
                        v-if="users.last_page > 1"
                        class="border-t border-border p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-muted-foreground">
                                Showing {{ users.from }} to {{ users.to }} of {{ users.total }} results
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    v-for="link in users.links"
                                    :key="link.label"
                                    :variant="link.active ? 'default' : 'outline'"
                                    size="sm"
                                    :disabled="!link.url"
                                    as-child
                                >
                                    <Link
                                        v-if="link.url"
                                        :href="link.url"
                                        preserve-scroll
                                        v-html="link.label"
                                    />
                                    <span v-else v-html="link.label" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="deleteDialogOpen" @update:open="closeDeleteDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This action cannot be undone. This will permanently delete the user
                        <strong>{{ userToDelete?.name }}</strong> and remove their data from the
                        system.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="deleteUser"
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AuthenticatedLayout>
</template>

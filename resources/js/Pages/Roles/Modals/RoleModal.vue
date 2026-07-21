<script setup>
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import Checkbox from "@/Components/Checkbox.vue";
import { Label } from "@/Components/ui/label";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    open: Boolean,
    role: Object,
    permissions: Array,
});

const emit = defineEmits(["update:open", "saved"]);

const form = useForm({
    name: "",
    display_name: "",
    description: "",
    permissions: [],
});

const permissionGroups = computed(() => {
    const groups = {};
    const groupMap = {
        dashboard: "Dashboard",
        view: "General",
        manage: "Management",
        create: "Servers",
        edit: "Servers",
        delete: "Servers",
        check: "Servers",
        connect: "Servers",
        duplicate: "Servers",
        export: "Data",
    };

    for (const perm of props.permissions) {
        const prefix = perm.split(" ")[0];
        const group = groupMap[prefix] || "Other";
        if (!groups[group]) groups[group] = [];
        groups[group].push(perm);
    }

    const order = ["Dashboard", "General", "Management", "Servers", "Users", "Data", "Other"];
    return Object.keys(groups)
        .sort((a, b) => {
            const ia = order.indexOf(a);
            const ib = order.indexOf(b);
            return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
        })
        .map((key) => ({ group: key, permissions: groups[key] }));
});

watch(
    () => props.role,
    (role) => {
        if (role) {
            form.name = role.name;
            form.display_name = role.display_name || "";
            form.description = role.description || "";
            form.permissions = role.permissions?.map((p) => (typeof p === "string" ? p : p.name)) || [];
            form.clearErrors();
        } else {
            form.reset();
        }
    },
);

const submit = () => {
    const routeName = props.role ? "roles.update" : "roles.store";
    const routeParams = props.role ? { role: props.role.id } : {};

    form[props.role ? "put" : "post"](route(routeName, routeParams), {
        preserveScroll: true,
        onSuccess: () => {
            emit("saved");
            emit("update:open", false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <form @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ role ? "Edit role" : "Create role" }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ role ? "Update role name and permissions." : "Define a new role and assign permissions." }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g., editor"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="display_name">Display name</Label>
                        <Input
                            id="display_name"
                            v-model="form.display_name"
                            placeholder="e.g., Editor"
                        />
                        <InputError :message="form.errors.display_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="What can this role do?"
                            rows="2"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-3">
                        <Label>Permissions</Label>
                        <div
                            v-for="group in permissionGroups"
                            :key="group.group"
                            class="space-y-1.5"
                        >
                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                {{ group.group }}
                            </p>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div
                                    v-for="perm in group.permissions"
                                    :key="perm"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted/50 transition-colors"
                                >
                                    <Checkbox
                                        :id="perm"
                                        v-model:checked="form.permissions"
                                        :value="perm"
                                    />
                                    <Label
                                        :for="perm"
                                        class="text-sm cursor-pointer"
                                    >
                                        {{ perm }}
                                    </Label>
                                </div>
                            </div>
                        </div>
                        <InputError :message="form.errors.permissions" />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="$emit('update:open', false)"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ role ? "Update" : "Create" }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

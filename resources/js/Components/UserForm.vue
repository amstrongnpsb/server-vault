<script setup>
import { useForm } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        required: true,
    },
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["submit", "cancel"]);

// roles lives directly inside useForm's data now, so it's always
// included whenever form.post()/form.put() is called — no transform needed.
//
// NOTE: this assumes props.user.roles is an array of role NAME strings
// (e.g. ['admin', 'editor']). If your controller instead passes an array
// of role OBJECTS (e.g. [{ id: 1, name: 'admin' }]), change the mapping to:
//   props.user.roles.map((r) => (typeof r === "string" ? r : r.name))
const form = useForm({
    name: props.user?.name || "",
    email: props.user?.email || "",
    password: "",
    password_confirmation: "",
    roles: Array.isArray(props.user?.roles) ? [...props.user.roles] : [],
});

const submit = () => {
    emit("submit", form);
};

const cancel = () => {
    emit("cancel");
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div
            class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm"
        >
            <div class="space-y-4">
                <!-- Name Field -->
                <div class="space-y-2">
                    <Label for="name">
                        Name <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Enter user's full name"
                        :class="{ 'border-destructive': form.errors.name }"
                        required
                    />
                    <p v-if="form.errors.name" class="text-sm text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email">
                        Email <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        placeholder="user@example.com"
                        :class="{ 'border-destructive': form.errors.email }"
                        required
                    />
                    <p
                        v-if="form.errors.email"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password">
                        Password
                        <span v-if="!isEdit" class="text-destructive">*</span>
                        <span v-else class="text-sm text-muted-foreground">
                            (leave blank to keep current)
                        </span>
                    </Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="Enter password"
                        :class="{ 'border-destructive': form.errors.password }"
                        :required="!isEdit"
                    />
                    <p
                        v-if="form.errors.password"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <!-- Password Confirmation Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation">
                        Confirm Password
                        <span v-if="!isEdit" class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Confirm password"
                        :class="{
                            'border-destructive':
                                form.errors.password_confirmation,
                        }"
                        :required="!isEdit && form.password"
                    />
                    <p
                        v-if="form.errors.password_confirmation"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <!-- Roles Field -->
                <div class="space-y-2">
                    <Label>Roles</Label>
                    <Select v-model="form.roles" multiple>
                        <SelectTrigger
                            :class="[
                                'w-full',
                                { 'border-destructive': form.errors.roles },
                            ]"
                        >
                            <SelectValue placeholder="Select roles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Role</SelectLabel>
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="form.errors.roles"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.roles }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button
                type="button"
                variant="outline"
                @click="cancel"
                :disabled="form.processing"
            >
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                <span v-if="form.processing">{{
                    isEdit ? "Updating..." : "Creating..."
                }}</span>
                <span v-else>{{ isEdit ? "Update User" : "Create User" }}</span>
            </Button>
        </div>
    </form>
</template>

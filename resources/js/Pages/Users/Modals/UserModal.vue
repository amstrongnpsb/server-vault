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
import {
    Dialog,
    DialogContent,
} from "@/Components/ui/dialog";
import { Eye, EyeOff } from "lucide-vue-next";
import { ref, watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
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

const emit = defineEmits(["update:open", "saved"]);

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    roles: [],
});

const showPassword = ref(false);

const initializeForm = (user = null) => {
    if (user) {
        form.name = user.name || "";
        form.email = user.email || "";
        form.password = "";
        form.password_confirmation = "";
        form.roles = Array.isArray(user.roles)
            ? user.roles.map((r) => (typeof r === "string" ? r : r.name))
            : [];
    } else {
        form.name = "";
        form.email = "";
        form.password = "";
        form.password_confirmation = "";
        form.roles = [];
    }
    form.clearErrors();
};

watch(
    () => props.user,
    (newUser) => {
        if (props.open && props.isEdit && newUser) {
            initializeForm(newUser);
        }
    },
    { immediate: true }
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.isEdit && props.user) {
                initializeForm(props.user);
            } else {
                initializeForm();
            }
        } else {
            form.reset();
            form.clearErrors();
        }
    }
);

const closeModal = () => {
    emit("update:open", false);
};

const submit = () => {
    const url = props.isEdit
        ? route("users.update", props.user.id)
        : route("users.store");

    const method = props.isEdit ? "put" : "post";

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                props.isEdit
                    ? "User updated successfully!"
                    : "User created successfully!",
            );
            emit("saved");
            closeModal();
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="closeModal">
        <DialogContent
            class="sm:max-w-[500px] duration-200 data-closed:slide-out-to-bottom-2 data-open:slide-in-from-bottom-2"
        >
            <div class="mb-6">
                <h2 class="text-xl font-semibold">
                    {{ isEdit ? "Edit user" : "Add user" }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    {{
                        isEdit
                            ? "Update user information and role assignments."
                            : "Create a new user account and assign roles."
                    }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name" class="text-sm font-medium">
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
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <Label for="email" class="text-sm font-medium">
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
                    <p v-if="form.errors.email" class="text-xs text-destructive">
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Password -->
                <div class="space-y-2">
                    <Label for="password" class="text-sm font-medium">
                        Password
                        <span v-if="!isEdit" class="text-destructive">*</span>
                        <span v-else class="text-xs text-muted-foreground">
                            (leave blank to keep current)
                        </span>
                    </Label>
                    <div class="relative">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Enter password"
                            :class="{ 'border-destructive': form.errors.password }"
                            :required="!isEdit"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                            tabindex="-1"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="text-xs text-destructive">
                        {{ form.errors.password }}
                    </p>
                </div>

                <!-- Confirm Password -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-sm font-medium">
                        Confirm Password
                        <span v-if="!isEdit" class="text-destructive">*</span>
                    </Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Confirm password"
                            :class="{
                                'border-destructive': form.errors.password_confirmation,
                            }"
                            :required="!isEdit && !!form.password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                            tabindex="-1"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <p
                        v-if="form.errors.password_confirmation"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>

                <!-- Roles -->
                <div class="space-y-2">
                    <Label class="text-sm font-medium">Roles</Label>
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
                    <p v-if="form.errors.roles" class="text-xs text-destructive">
                        {{ form.errors.roles }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeModal"
                        :disabled="form.processing"
                        class="transition-all duration-200 hover:scale-105"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 transition-all duration-200 hover:scale-105"
                    >
                        <span v-if="form.processing">
                            {{ isEdit ? "Updating..." : "Creating..." }}
                        </span>
                        <span v-else class="flex items-center gap-2">
                            <svg
                                v-if="!isEdit"
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9.25"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                />
                                <path
                                    d="M12 8v8M8 12h8"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                />
                            </svg>
                            <svg
                                v-else
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M12.5 6.5l5 5L8 21H3v-5l9.5-9.5z"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M11 8l5 5"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                />
                            </svg>
                            {{ isEdit ? "Update" : "Create" }}
                        </span>
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

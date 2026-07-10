<script setup>
import { useForm } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { X, Key, Lock } from "lucide-vue-next";
import { ref, watch, computed } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    server: {
        type: Object,
        default: null,
    },
    osOptions: {
        type: Array,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:open", "saved"]);

// Authentication method state
const authMethod = ref("password");

// Custom OS name for "Other" option
const customOsName = ref("");
const showCustomOsInput = computed(() => form.os === "Other");

// Form setup
const form = useForm({
    name: "",
    host: "",
    port: 22,
    os: "",
    status: "Offline",
    username: "",
    credentials: "",
    description: "",
});

// Initialize form data
const initializeForm = (server = null) => {
    if (server) {
        // Edit mode - populate with server data
        form.name = server.name || "";
        form.host = server.host || "";
        form.port = server.port || 22;
        form.os = server.os || "";
        form.status = server.status || "Offline";
        form.username = server.username || "";
        form.credentials = server.credentials || "";
        form.description = server.description || "";
        
        // Check if OS is a custom one (not in standard options)
        const standardOs = ['Ubuntu', 'Debian', 'CentOS', 'Windows', 'Other'];
        if (server.os && !standardOs.includes(server.os)) {
            customOsName.value = server.os;
            form.os = "Other";
        } else {
            customOsName.value = "";
        }
        
        // Set auth method based on credentials type
        if (server.credentials && server.credentials.includes('-----BEGIN')) {
            authMethod.value = "private_key";
        } else {
            authMethod.value = "password";
        }
    } else {
        // Create mode - reset to defaults
        form.name = "";
        form.host = "";
        form.port = 22;
        form.os = "";
        form.status = "Offline";
        form.username = "";
        form.credentials = "";
        form.description = "";
        authMethod.value = "password";
        customOsName.value = "";
    }
    
    // Clear any existing errors
    form.clearErrors();
};

// Watch for server prop changes (when editing)
watch(
    () => props.server,
    (newServer) => {
        if (props.open && props.isEdit && newServer) {
            initializeForm(newServer);
        }
    },
    { immediate: true }
);

// Reset form when modal opens/closes
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            // Modal opening - initialize based on mode
            if (props.isEdit && props.server) {
                initializeForm(props.server);
            } else {
                initializeForm(); // Create mode
            }
        } else {
            // Modal closing - reset form
            form.reset();
            form.clearErrors();
            authMethod.value = "password";
        }
    }
);

const closeModal = () => {
    emit("update:open", false);
};

const testAndSave = () => {
    // If "Other" is selected and custom OS name is provided, use it
    if (form.os === "Other" && customOsName.value.trim()) {
        form.os = customOsName.value.trim();
    }

    // Debug: Log form data before submitting
    console.log('Form data before submit:', {
        name: form.name,
        host: form.host,
        port: form.port,
        os: form.os,
        status: form.status,
        username: form.username,
        description: form.description,
    });

    const url = props.isEdit
        ? route("servers.update", props.server.id)
        : route("servers.store");

    const method = props.isEdit ? "put" : "post";

    form[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                props.isEdit
                    ? "Server updated successfully!"
                    : "Server created successfully!",
            );
            emit("saved");
            closeModal();
        },
        onError: (errors) => {
            console.error("Form errors:", errors);
            // Reset OS back to "Other" if validation fails
            if (customOsName.value.trim()) {
                form.os = "Other";
            }
        },
    });
};

const handleAuthMethodChange = (method) => {
    authMethod.value = method;
    // Clear credentials when switching methods
    form.credentials = "";
};
</script>

<template>
    <Dialog :open="open" @update:open="closeModal">
        <DialogContent
            class="sm:max-w-[500px] duration-200 data-closed:slide-out-to-bottom-2 data-open:slide-in-from-bottom-2"
        >
            <!-- Header -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold">
                    {{ isEdit ? "Edit server" : "Add server" }}
                </h2>
                <p class="text-sm text-muted-foreground mt-1">
                    {{
                        isEdit
                            ? "Update server connection details."
                            : "Connect a new server to your vault."
                    }}
                </p>
            </div>

            <form @submit.prevent="testAndSave" class="space-y-4">
                <!-- Server Name -->
                <div class="space-y-2">
                    <Label for="name" class="text-sm font-medium">
                        Server name
                    </Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="prod-web-01"
                        :class="{ 'border-destructive': form.errors.name }"
                        required
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Host/IP and Port -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label for="host" class="text-sm font-medium">
                            Host / IP address
                        </Label>
                        <Input
                            id="host"
                            v-model="form.host"
                            type="text"
                            placeholder="192.168.1.10"
                            :class="{ 'border-destructive': form.errors.host }"
                            required
                        />
                        <p
                            v-if="form.errors.host"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.host }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="port" class="text-sm font-medium">
                            Port
                        </Label>
                        <Input
                            id="port"
                            v-model="form.port"
                            type="number"
                            placeholder="22"
                            min="1"
                            max="65535"
                            :class="{ 'border-destructive': form.errors.port }"
                            required
                        />
                        <p
                            v-if="form.errors.port"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.port }}
                        </p>
                    </div>
                </div>

                <!-- Operating System and Username -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">
                            Operating system
                        </Label>
                        <Select 
                            :model-value="form.os" 
                            @update:model-value="(value) => form.os = value"
                        >
                            <SelectTrigger
                                :class="[
                                    'w-full',
                                    { 'border-destructive': form.errors.os },
                                ]"
                            >
                                <SelectValue placeholder="Select OS" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Operating System</SelectLabel>
                                    <SelectItem
                                        v-for="os in osOptions"
                                        :key="os"
                                        :value="os"
                                    >
                                        {{ os }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="form.errors.os"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.os }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="username" class="text-sm font-medium"
                            >Username</Label
                        >
                        <Input
                            id="username"
                            v-model="form.username"
                            type="text"
                            placeholder="root"
                            :class="{
                                'border-destructive': form.errors.username,
                            }"
                        />
                        <p
                            v-if="form.errors.username"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.username }}
                        </p>
                    </div>
                </div>

                <!-- Custom OS Name (shown when Other is selected) -->
                <div v-if="showCustomOsInput" class="space-y-2">
                    <Label for="customOsName" class="text-sm font-medium">
                        Custom OS Name
                    </Label>
                    <Input
                        id="customOsName"
                        v-model="customOsName"
                        type="text"
                        placeholder="e.g., FreeBSD, Alpine, Arch Linux"
                        :class="{
                            'border-destructive': showCustomOsInput && !customOsName.trim(),
                        }"
                    />
                    <p class="text-xs text-muted-foreground">
                        Enter the specific operating system name
                    </p>
                </div>

                <!-- Authentication Method -->
                <div class="space-y-3">
                    <Label class="text-sm font-medium"
                        >Authentication method</Label
                    >
                    <div
                        class="flex gap-0 rounded-md overflow-hidden border border-border"
                    >
                        <button
                            type="button"
                            @click="handleAuthMethodChange('password')"
                            :class="[
                                'flex-1 px-4 py-2 text-sm font-medium flex items-center justify-center gap-2 transition-all duration-200',
                                authMethod === 'password'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-card hover:bg-accent hover:text-accent-foreground',
                            ]"
                        >
                            <Lock class="h-4 w-4" />
                            Password
                        </button>
                        <button
                            type="button"
                            @click="handleAuthMethodChange('private_key')"
                            :class="[
                                'flex-1 px-4 py-2 text-sm font-medium flex items-center justify-center gap-2 transition-all duration-200 border-l border-border',
                                authMethod === 'private_key'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-card hover:bg-accent hover:text-accent-foreground',
                            ]"
                        >
                            <Key class="h-4 w-4" />
                            Private key
                        </button>
                    </div>
                </div>

                <!-- Password/Private Key Field -->
                <div class="space-y-2">
                    <Label for="credentials" class="text-sm font-medium">
                        {{
                            authMethod === "password"
                                ? "Password"
                                : "Private key"
                        }}
                    </Label>
                    <component
                        :is="authMethod === 'password' ? Input : Textarea"
                        id="credentials"
                        v-model="form.credentials"
                        :type="authMethod === 'password' ? 'password' : 'text'"
                        :placeholder="
                            authMethod === 'password'
                                ? 'Enter password'
                                : 'Enter private key'
                        "
                        :class="{
                            'border-destructive': form.errors.credentials,
                        }"
                        :rows="authMethod === 'private_key' ? 4 : undefined"
                    />
                    <p
                        v-if="form.errors.credentials"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.credentials }}
                    </p>
                </div>

                <!-- Status (for edit mode) -->
                <div v-if="isEdit" class="space-y-2">
                    <Label class="text-sm font-medium">Status</Label>
                    <Select 
                        :model-value="form.status" 
                        @update:model-value="(value) => form.status = value"
                    >
                        <SelectTrigger
                            :class="{
                                'border-destructive': form.errors.status,
                            }"
                        >
                            <SelectValue placeholder="Select status" />
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
                    <p
                        v-if="form.errors.status"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.status }}
                    </p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description" class="text-sm font-medium"
                        >Description (optional)</Label
                    >
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Main production web server"
                        rows="3"
                        :class="{
                            'border-destructive': form.errors.description,
                        }"
                    />
                    <p
                        v-if="form.errors.description"
                        class="text-xs text-destructive"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Form Actions -->
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
                            <!-- Create icon -->
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
                            <!-- Update icon -->
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

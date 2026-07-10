<script setup>
import { computed, watch, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { toast } from 'vue-sonner';
import { Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps({
    open: Boolean,
    server: Object,
    database: Object,
    isEdit: Boolean,
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({
    type: 'PostgreSQL',
    name: '',
    port: '',
    username: '',
    credentials: '',
});

const databaseTypes = ['PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'Other'];
const customTypeName = ref("");
const showCustomTypeInput = computed(() => form.type === "Other");
const showPassword = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.isEdit && props.database) {
                if (props.database.type && !databaseTypes.includes(props.database.type)) {
                    customTypeName.value = props.database.type;
                    form.type = 'Other';
                } else {
                    form.type = props.database.type || 'PostgreSQL';
                    customTypeName.value = '';
                }
                form.name = props.database.name;
                form.port = props.database.port;
                form.username = props.database.username;
                form.credentials = ''; // Leave password empty for edit
            } else {
                form.defaults({
                    type: '',
                    name: '',
                    port: '',
                    username: '',
                    credentials: '',
                });
                form.reset();
                customTypeName.value = '';
                showPassword.value = false;
            }
        }
    }
);

const onSubmit = () => {
    if (form.type === "Other" && customTypeName.value.trim()) {
        form.type = customTypeName.value.trim();
    }

    if (!form.username) {
        form.username = 'root';
    }

    if (!form.port) {
        const defaultPorts = {
            'PostgreSQL': 5432,
            'MySQL': 3306,
            'MongoDB': 27017,
            'Redis': 6379,
        };
        form.port = defaultPorts[form.type] || '';
    }

    if (props.isEdit) {
        form.put(route('servers.databases.update', props.database.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved');
            },
            onError: (err) => {
                console.error(err);
                toast.error('Failed to update database.');
                if (customTypeName.value.trim()) {
                    form.type = 'Other';
                }
            },
        });
    } else {
        form.post(route('servers.databases.store', props.server.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('saved');
            },
            onError: (err) => {
                console.error(err);
                toast.error('Failed to create database.');
                if (customTypeName.value.trim()) {
                    form.type = 'Other';
                }
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ isEdit ? 'Edit Database' : 'Add Database' }}</DialogTitle>
                <DialogDescription>
                    {{ isEdit ? 'Make changes to your database connection here.' : 'Add a new database connection to this server.' }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="onSubmit" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="type">Database Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger>
                            <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in databaseTypes" :key="type" :value="type">
                                {{ type }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.type" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.type }}</p>
                </div>

                <div v-if="showCustomTypeInput" class="grid gap-2">
                    <Label for="customTypeName">Custom Database Type</Label>
                    <Input
                        id="customTypeName"
                        v-model="customTypeName"
                        type="text"
                        placeholder="e.g., MariaDB, SQLite, Oracle"
                        :class="{
                            'border-destructive': showCustomTypeInput && !customTypeName.trim(),
                        }"
                    />
                    <p class="text-[0.8rem] text-muted-foreground">
                        Enter the specific database type
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="name">Database Name</Label>
                    <Input id="name" v-model="form.name" placeholder="e.g. main_db" required />
                    <p v-if="form.errors.name" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="port">Port</Label>
                    <Input id="port" v-model="form.port" type="number" placeholder="e.g. 5432" />
                    <p v-if="form.errors.port" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.port }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="username">Username</Label>
                    <Input id="username" v-model="form.username" placeholder="e.g. root" />
                    <p v-if="form.errors.username" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.username }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="credentials">Password / Credentials</Label>
                    <div class="relative">
                        <Input 
                            id="credentials" 
                            v-model="form.credentials" 
                            :type="showPassword ? 'text' : 'password'" 
                            placeholder="Leave blank to keep existing" 
                        />
                        <button 
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground focus:outline-none"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <p v-if="form.errors.credentials" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.credentials }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="$emit('update:open', false)" :disabled="form.processing">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

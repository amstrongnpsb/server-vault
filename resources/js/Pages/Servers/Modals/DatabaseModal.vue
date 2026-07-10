<script setup>
import { computed, watch } from 'vue';
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

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.isEdit && props.database) {
                form.type = props.database.type;
                form.name = props.database.name;
                form.port = props.database.port;
                form.username = props.database.username;
                form.credentials = ''; // Leave password empty for edit
            } else {
                form.reset();
            }
        }
    }
);

const databaseTypes = ['PostgreSQL', 'MySQL', 'MongoDB', 'Redis', 'Other'];

const onSubmit = () => {
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

                <div class="grid gap-2">
                    <Label for="name">Database Name</Label>
                    <Input id="name" v-model="form.name" placeholder="e.g. main_db" />
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
                    <Input id="credentials" v-model="form.credentials" type="password" placeholder="Leave blank to keep existing" />
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

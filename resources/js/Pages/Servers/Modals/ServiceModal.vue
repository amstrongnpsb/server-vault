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
import { Textarea } from '@/Components/ui/textarea';
import { toast } from 'vue-sonner';

const props = defineProps({
    open: Boolean,
    server: Object,
    service: Object,
    isEdit: Boolean,
});

const emit = defineEmits(['update:open', 'saved']);

const form = useForm({
    name: '',
    port: '',
    username: '',
    credentials: '',
    description: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            if (props.isEdit && props.service) {
                form.name = props.service.name;
                form.port = props.service.port;
                form.username = props.service.username;
                form.credentials = ''; // Leave password empty for edit
                form.description = props.service.description || '';
            } else {
                form.defaults({
                    name: '',
                    port: '',
                    username: '',
                    credentials: '',
                    description: '',
                });
                form.reset();
            }
        }
    }
);

const onSubmit = () => {
    if (props.isEdit) {
        form.put(route('servers.services.update', props.service.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Service updated successfully.');
                emit('update:open', false);
                emit('saved');
            },
            onError: (err) => {
                console.error(err);
                toast.error('Failed to update service.');
            },
        });
    } else {
        form.post(route('servers.services.store', props.server.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Service created successfully.');
                emit('update:open', false);
                emit('saved');
            },
            onError: (err) => {
                console.error(err);
                toast.error('Failed to create service.');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ isEdit ? 'Edit Service' : 'Add Service' }}</DialogTitle>
                <DialogDescription>
                    {{ isEdit ? 'Make changes to your service here.' : 'Add a new service running on this server.' }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="onSubmit" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name">Service Name</Label>
                    <Input id="name" v-model="form.name" placeholder="e.g. CCTV, Web App" />
                    <p v-if="form.errors.name" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="port">Port</Label>
                    <Input id="port" v-model="form.port" type="number" placeholder="e.g. 8080" />
                    <p v-if="form.errors.port" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.port }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="username">Username</Label>
                    <Input id="username" v-model="form.username" placeholder="Optional" />
                    <p v-if="form.errors.username" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.username }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="credentials">Password / Credentials</Label>
                    <Input id="credentials" v-model="form.credentials" type="password" placeholder="Leave blank to keep existing" />
                    <p v-if="form.errors.credentials" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.credentials }}</p>
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="form.description" placeholder="Notes about this service" />
                    <p v-if="form.errors.description" class="text-[0.8rem] font-medium text-destructive">{{ form.errors.description }}</p>
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

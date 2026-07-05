<script setup>
import { useForm } from '@inertiajs/vue3';
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
import { Badge } from '@/Components/ui/badge';
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

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

const emit = defineEmits(['submit', 'cancel']);

const form = useForm({
    name: props.user?.name || '',
    email: props.user?.email || '',
    password: '',
    password_confirmation: '',
    roles: props.user?.roles || [],
});

const selectedRole = ref('');

const addRole = () => {
    if (selectedRole.value && !form.roles.includes(selectedRole.value)) {
        form.roles.push(selectedRole.value);
        selectedRole.value = '';
    }
};

const removeRole = (role) => {
    form.roles = form.roles.filter((r) => r !== role);
};

const submit = () => {
    emit('submit', form);
};

const cancel = () => {
    emit('cancel');
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
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
                        <p v-if="form.errors.email" class="text-sm text-destructive">
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
                        <p v-if="form.errors.password" class="text-sm text-destructive">
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
                            :class="{ 'border-destructive': form.errors.password_confirmation }"
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
                        <Label for="roles">Roles</Label>
                        <div class="flex gap-2">
                            <Select v-model="selectedRole">
                                <SelectTrigger class="flex-1">
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.name"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                variant="secondary"
                                @click="addRole"
                                :disabled="!selectedRole"
                            >
                                Add
                            </Button>
                        </div>

                        <!-- Selected Roles -->
                        <div v-if="form.roles.length > 0" class="flex flex-wrap gap-2 mt-2">
                            <Badge
                                v-for="role in form.roles"
                                :key="role"
                                variant="secondary"
                                class="flex items-center gap-1 pr-1"
                            >
                                {{ role }}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-4 w-4 p-0 hover:bg-transparent"
                                    @click="removeRole(role)"
                                >
                                    <X class="h-3 w-3" />
                                </Button>
                            </Badge>
                        </div>
                        <p v-if="form.errors.roles" class="text-sm text-destructive">
                            {{ form.errors.roles }}
                        </p>
                    </div>
                </div>
            </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button type="button" variant="outline" @click="cancel" :disabled="form.processing">
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving...' : isEdit ? 'Update User' : 'Create User' }}
            </Button>
        </div>
    </form>
</template>

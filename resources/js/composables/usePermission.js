import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function usePermission() {
    const can = computed(() => usePage().props.auth.can ?? []);

    const hasPermission = (permission) => can.value.includes(permission);

    return { can, hasPermission };
}

<script setup>
import { inject } from 'vue';
import { Menu } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

const sidebar = inject('sidebar');
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon"
        aria-label="Open sidebar"
        class="md:hidden"
        @click="sidebar.mobileOpen.value = true"
    >
        <Menu class="h-5 w-5" aria-hidden="true" />
        <span class="sr-only">Open sidebar</span>
    </Button>
</template>

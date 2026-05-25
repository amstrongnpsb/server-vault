<script setup>
import { inject } from 'vue';
import { PanelLeft } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

const sidebar = inject('sidebar');
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon"
        aria-label="Toggle sidebar"
        @click="sidebar.toggleSidebar"
    >
        <PanelLeft class="h-5 w-5" aria-hidden="true" />
        <span class="sr-only">Toggle sidebar</span>
    </Button>
</template>

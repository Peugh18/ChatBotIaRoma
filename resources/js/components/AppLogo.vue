<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground shadow-sm">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-tight">
        <span class="truncate text-sm font-semibold text-foreground">Roma CRM</span>
        <span class="truncate text-[10px] font-medium text-muted-foreground">Vestidos Roma</span>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    noPadding?: boolean;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <div
            v-if="title || $slots.header"
            class="flex items-center justify-between border-b border-border bg-muted/30 px-5 py-4"
        >
            <div v-if="title">
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
            </div>
            <slot name="header" />
        </div>
        <div :class="noPadding ? '' : 'p-5'">
            <slot />
        </div>
    </div>
</template>

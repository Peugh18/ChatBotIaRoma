<script setup lang="ts">
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon?: Component;
        tone?: 'default' | 'success' | 'warning' | 'danger' | 'primary';
    }>(),
    { tone: 'default' }
);

const iconToneClass: Record<string, string> = {
    default: 'bg-muted text-muted-foreground',
    primary: 'bg-primary/10 text-primary',
    success: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    danger: 'bg-destructive/10 text-destructive',
};
</script>

<template>
    <div
        class="flex items-center justify-between rounded-xl border border-border bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
    >
        <div class="min-w-0">
            <span class="block text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ label }}</span>
            <span class="mt-1 block truncate text-2xl font-semibold tracking-tight text-foreground">{{ value }}</span>
        </div>
        <div v-if="icon" :class="cn('rounded-lg p-3', iconToneClass[tone])">
            <component :is="icon" class="h-5 w-5" />
        </div>
    </div>
</template>

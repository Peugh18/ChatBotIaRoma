<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
        fill="none"
        stroke="currentColor"
        stroke-width="3.5"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <!-- Speech Bubble (WhatsApp style) -->
        <path d="M38.5 21.5C38.5 30.9 30.9 38.5 21.5 38.5C17.8 38.5 14.4 37.3 11.6 35.3L5 37.5L7.2 31.7C5.2 28.9 4 25.4 4 21.5C4 12.1 11.6 4.5 21.5 4.5C30.9 4.5 38.5 12.1 38.5 21.5Z" />
        
        <!-- Elegant letter 'R' monogram inside -->
        <path d="M16 13.5H23.5C25.7 13.5 27.5 15.3 27.5 17.5C27.5 19.7 25.7 21.5 23.5 21.5H19.5V28.5" />
        <path d="M16 13.5V28.5" />
        <path d="M23.5 21.5L28.5 28.5" />
    </svg>
</template>

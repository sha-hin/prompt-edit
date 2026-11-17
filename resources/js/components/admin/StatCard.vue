<script setup lang="ts">
defineProps<{
    label: string;
    value: string;
    title?: string;
    hint?: string;
    tone?: 'default' | 'profit';
}>();
</script>

<template>
    <div class="rounded-xl border bg-card p-4 shadow-xs">
        <p class="studio-label text-muted-foreground">{{ label }}</p>
        <p
            class="mt-3 font-mono text-2xl font-medium"
            :class="{ 'text-accent-ink': tone === 'profit' }"
            :title="title"
        >
            {{ value }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{ start: string; end: string }>();

const emit = defineEmits<{ change: [range: { start: string; end: string }] }>();

const errors = computed(() => usePage().props.errors);

const from = ref(props.start);
const to = ref(props.end);

watch(
    () => [props.start, props.end],
    ([start, end]) => {
        from.value = start;
        to.value = end;
    },
);

// Dates are UTC days, the same as Pixelforge's usage report.
const isoDay = (date: Date): string => date.toISOString().slice(0, 10);

function daysBack(days: number): [string, string] {
    const today = new Date();
    const first = new Date(today);
    first.setUTCDate(today.getUTCDate() - (days - 1));

    return [isoDay(first), isoDay(today)];
}

function thisMonth(): [string, string] {
    const today = new Date();

    return [
        isoDay(
            new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1)),
        ),
        isoDay(today),
    ];
}

const presets: { label: string; range: () => [string, string] }[] = [
    { label: '7 days', range: () => daysBack(7) },
    { label: '30 days', range: () => daysBack(30) },
    { label: '90 days', range: () => daysBack(90) },
    { label: 'This month', range: thisMonth },
];

function apply(): void {
    emit('change', { start: from.value, end: to.value });
}

function applyPreset(range: () => [string, string]): void {
    [from.value, to.value] = range();
    apply();
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
            <div class="grid gap-1.5">
                <Label for="start" class="studio-label text-muted-foreground"
                    >From</Label
                >
                <Input
                    id="start"
                    v-model="from"
                    type="date"
                    :max="to"
                    class="w-40 bg-card"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="end" class="studio-label text-muted-foreground"
                    >To</Label
                >
                <Input
                    id="end"
                    v-model="to"
                    type="date"
                    :min="from"
                    class="w-40 bg-card"
                />
            </div>
            <Button type="submit">Show</Button>
        </form>
        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-for="preset in presets"
                :key="preset.label"
                variant="outline"
                size="sm"
                @click="applyPreset(preset.range)"
            >
                {{ preset.label }}
            </Button>
            <InputError :message="errors.start ?? errors.end" />
        </div>
    </div>
</template>

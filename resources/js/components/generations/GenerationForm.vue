<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { Plus, Sparkles, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onMounted, ref } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/Generations/GenerationController';
import GenerationEstimateController from '@/actions/App/Http/Controllers/Generations/GenerationEstimateController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { CostEstimate, ImageQuality, ImageSize } from '@/types';

const MAX_PROMPT = 4000;
const MAX_REFERENCE_IMAGES = 4;
// Bound rather than static: vue-tsc misreads a literal "//" in a component attribute inside v-for.
const REFERENCE_PLACEHOLDER = 'https://example.com/reference.png';

const sizes: { value: ImageSize; label: string }[] = [
    { value: '1024x1024', label: 'Square · 1024×1024' },
    { value: '1536x1024', label: 'Landscape · 1536×1024' },
    { value: '1024x1536', label: 'Portrait · 1024×1536' },
];

const qualities: { value: ImageQuality; label: string }[] = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

const form = useForm({
    prompt: '',
    size: '1024x1024' as ImageSize, // Pixelforge's defaults
    quality: 'medium' as ImageQuality,
    reference_images: [] as string[],
});

type EstimatePayload = {
    prompt: string;
    size: ImageSize;
    quality: ImageQuality;
    reference_images: string[];
};

// Only complete URLs count towards the estimate; half-typed ones would just fail validation.
const estimatePayload = (): EstimatePayload => ({
    prompt: form.prompt,
    size: form.size,
    quality: form.quality,
    reference_images: form.reference_images.filter((url) =>
        /^https?:\/\/\S+$/.test(url),
    ),
});

const estimate = ref<CostEstimate | null>(null);
const estimateRequest = useHttp<EstimatePayload, { data: CostEstimate }>(
    estimatePayload(),
);

async function refreshEstimate(): Promise<void> {
    estimateRequest.cancel(); // a newer estimate replaces any still in flight

    try {
        const response = await estimateRequest
            .transform(estimatePayload)
            .submit(GenerationEstimateController());
        estimate.value = response.data;
    } catch {
        // cancelled, rate limited or invalid input: keep showing the last estimate
    }
}

watchDebounced(() => estimatePayload(), refreshEstimate, {
    debounce: 300,
    deep: true,
});
onMounted(refreshEstimate);

const promptLength = computed(() => form.prompt.length);

function addReferenceImage(): void {
    if (form.reference_images.length < MAX_REFERENCE_IMAGES) {
        form.reference_images.push('');
    }
}

function removeReferenceImage(index: number): void {
    form.reference_images.splice(index, 1);
}

function submit(): void {
    form.submit(GenerationController.store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('prompt'), // keep size, quality and references for the next one
    });
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="prompt" class="studio-label text-muted-foreground">
                    Prompt
                </Label>
                <span
                    class="font-mono text-xs text-muted-foreground"
                    :class="{ 'text-destructive': promptLength > MAX_PROMPT }"
                >
                    {{ promptLength.toLocaleString() }} /
                    {{ MAX_PROMPT.toLocaleString() }}
                </span>
            </div>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="5"
                required
                :maxlength="MAX_PROMPT"
                placeholder="A lighthouse on a cliff at dusk, painted in watercolour"
                :aria-invalid="!!form.errors.prompt"
                class="w-full min-w-0 resize-y rounded-md border border-input bg-card px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
            />
            <InputError :message="form.errors.prompt" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="size" class="studio-label text-muted-foreground">
                    Size
                </Label>
                <Select v-model="form.size">
                    <SelectTrigger id="size" class="w-full bg-card">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="size in sizes"
                            :key="size.value"
                            :value="size.value"
                        >
                            {{ size.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.size" />
            </div>

            <div class="grid gap-2">
                <Label for="quality" class="studio-label text-muted-foreground">
                    Quality
                </Label>
                <Select v-model="form.quality">
                    <SelectTrigger id="quality" class="w-full bg-card">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="quality in qualities"
                            :key="quality.value"
                            :value="quality.value"
                        >
                            {{ quality.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.quality" />
            </div>
        </div>

        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <span class="studio-label text-muted-foreground">
                    Reference images
                </span>
                <span class="text-xs text-muted-foreground">
                    Optional · up to {{ MAX_REFERENCE_IMAGES }}
                </span>
            </div>
            <div
                v-for="(_, index) in form.reference_images"
                :key="index"
                class="grid gap-1"
            >
                <div class="flex gap-2">
                    <Input
                        v-model="form.reference_images[index]"
                        type="url"
                        inputmode="url"
                        :placeholder="REFERENCE_PLACEHOLDER"
                        :aria-label="`Reference image ${index + 1}`"
                        :aria-invalid="
                            !!form.errors[`reference_images.${index}`]
                        "
                        class="bg-card"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Remove reference image ${index + 1}`"
                        @click="removeReferenceImage(index)"
                    >
                        <X />
                    </Button>
                </div>
                <InputError
                    :message="form.errors[`reference_images.${index}`]"
                />
            </div>
            <Button
                v-if="form.reference_images.length < MAX_REFERENCE_IMAGES"
                type="button"
                variant="outline"
                size="sm"
                class="w-fit"
                @click="addReferenceImage"
            >
                <Plus /> Add reference image
            </Button>
            <InputError :message="form.errors.reference_images" />
        </div>

        <div class="rounded-lg border bg-secondary/60 p-4">
            <div class="flex items-center justify-between gap-4">
                <span class="studio-label text-muted-foreground">
                    Estimated cost
                </span>
                <span
                    class="flex items-center gap-2 font-mono text-lg font-medium"
                    aria-live="polite"
                >
                    <Spinner
                        v-if="estimateRequest.processing"
                        class="size-3.5 text-muted-foreground"
                    />
                    {{ estimate?.charge.display ?? '—' }}
                </span>
            </div>
            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                A range, because the exact prompt tokens and the model's
                reasoning are only known once the image is made. You pay what it
                actually cost, and nothing if it fails.
            </p>
        </div>

        <Button type="submit" size="lg" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            <Sparkles v-else />
            Generate image
        </Button>
    </form>
</template>

<script setup lang="ts">
import { CircleAlert, ImageOff } from '@lucide/vue';
import { ref } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import type { Generation } from '@/types';

defineProps<{ generation: Generation }>();

const imageFailed = ref(false);
</script>

<template>
    <div class="flex size-full items-center justify-center">
        <img
            v-if="generation.image.status === 'ready' && !imageFailed"
            :src="generation.image.url ?? undefined"
            :alt="generation.prompt"
            loading="lazy"
            class="size-full object-cover"
            @error="imageFailed = true"
        />
        <div
            v-else-if="generation.status === 'succeeded'"
            class="flex flex-col items-center gap-2 px-6 text-center text-muted-foreground"
        >
            <ImageOff class="size-6" />
            <span class="text-xs">Image expired</span>
            <span class="text-[0.6875rem]"
                >Pixelforge keeps images for 24 hours</span
            >
        </div>
        <div
            v-else-if="generation.status === 'failed'"
            class="flex flex-col items-center gap-2 px-6 text-center text-destructive"
        >
            <CircleAlert class="size-6" />
            <span class="text-xs">{{
                generation.error_message ?? 'This generation failed.'
            }}</span>
        </div>
        <div
            v-else
            class="flex flex-col items-center gap-2 text-muted-foreground"
        >
            <Spinner class="size-6" />
            <span class="text-xs">Making your image…</span>
        </div>
    </div>
</template>

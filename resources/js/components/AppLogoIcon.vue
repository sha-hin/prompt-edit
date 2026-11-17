<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A four-point spark: a neutral PromptEdit mark in place of the starter kit's Laravel logo. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M12 1.5l2.9 7.6 7.6 2.9-7.6 2.9-2.9 7.6-2.9-7.6L1.5 12l7.6-2.9z"
        />
    </svg>
</template>

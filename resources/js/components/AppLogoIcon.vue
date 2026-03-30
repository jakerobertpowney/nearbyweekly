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
    <div
        class="flex items-center justify-center rounded-xl bg-brand-surface border border-brand-border"
        :class="className"
        v-bind="$attrs"
        style="width: 40px; height: 40px;"
    >
        <img src="/images/logo-icon.svg" alt="Eventaroo" class="h-6 w-auto" />
    </div>
</template>

<script setup lang="ts">
import AppTopBar from '@/components/AppTopBar.vue';
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white">
        <AppTopBar />
        <div class="flex flex-1 flex-col pt-14">
            <slot />
        </div>
    </div>
</template>

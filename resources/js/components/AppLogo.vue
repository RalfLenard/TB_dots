<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center">
        <img
            src="/image/logo.png"
            alt="RHU-I Logo"
            class="size-8 object-contain"
        />
    </div>

    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">RHU-I</span>
    </div>
</template>
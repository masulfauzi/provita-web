<script setup>
import Toast from 'primevue/toast';
</script>

<template>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <slot />
        </div>
        <Toast position="top-right" />
    </div>
</template>

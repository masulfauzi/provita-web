<script setup>
import Message from 'primevue/message';

defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <Message v-if="message" severity="error" size="small" variant="simple">{{ message }}</Message>
</template>

<script setup>
import { tanggal } from '@/utils/format';

defineProps({
    value: { type: [String, Date, null], default: null },
    hari: { type: Boolean, default: false },
    jam: { type: Boolean, default: false },
});
</script>

<template>
    <span class="whitespace-nowrap">{{ tanggal(value, { hari, jam }) }}</span>
</template>

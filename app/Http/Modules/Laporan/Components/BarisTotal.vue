<script setup>
import MoneyText from '@/Components/MoneyText.vue';

defineProps({
    label: { type: String, required: true },
    nilai: { type: [String, Number], default: 0 },
    utama: { type: Boolean, default: false },
});
</script>

<template>
    <tr
        class="font-semibold"
        :class="utama ? 'border-y-2 border-surface-400 text-base' : 'border-t border-surface-300'"
    >
        <td />
        <td class="py-2 pr-3">{{ label }}</td>
        <td class="py-2 text-right"><MoneyText :value="nilai" colored /></td>
    </tr>
</template>

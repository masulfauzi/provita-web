<script setup>
defineProps({
    label: { type: String, required: true },
    kolom: { type: Number, default: 3 },
});
</script>

<template>
    <tr>
        <td :colspan="kolom" class="pt-4 pb-1 text-xs font-bold tracking-wide uppercase text-primary print:text-black">
            {{ label }}
        </td>
    </tr>
</template>

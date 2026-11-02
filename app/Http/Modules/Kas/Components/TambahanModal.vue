<script setup>
import ConfirmDelete from '@/Components/ConfirmDelete.vue';
import MoneyText from '@/Components/MoneyText.vue';

/**
 * Rincian tambahan modal sesi kas (jam · keterangan · petugas · nominal).
 * Tombol hapus hanya tampil bila sesi masih terbuka.
 */
defineProps({
    items: { type: Array, required: true },
    sesiId: { type: String, default: null },
    bisaHapus: { type: Boolean, default: false },
});
</script>

<template>
    <ul class="flex flex-col gap-1 text-sm">
        <li v-for="item in items" :key="item.id" class="flex items-center justify-between gap-2">
            <span class="min-w-0">
                <span class="text-muted-color">{{ item.waktu }}</span> · {{ item.keterangan }}
                <span v-if="item.petugas" class="text-xs text-muted-color">({{ item.petugas }})</span>
            </span>
            <span class="flex shrink-0 items-center gap-1">
                <MoneyText :value="item.nominal" />
                <ConfirmDelete
                    v-if="bisaHapus"
                    :url="`/kas/sesi/${sesiId}/tambahan/${item.id}`"
                    message="Hapus tambahan modal yang salah catat ini?"
                />
            </span>
        </li>
    </ul>
</template>

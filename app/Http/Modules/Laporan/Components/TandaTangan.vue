<script setup>
defineProps({
    kop: { type: Object, required: true },
});
</script>

<!-- Blok tanda tangan; hanya tampil saat dicetak. -->
<template>
    <div class="mt-12 hidden grid-cols-2 gap-8 text-center print:grid">
        <div>
            <div>Mengetahui,</div>
            <div>Kepala Sekolah</div>
            <div class="mt-20 font-semibold underline">
                {{ kop.nama_kepala_sekolah || '........................................' }}
            </div>
        </div>
        <div>
            <div>Semarang, {{ kop.tanggal_cetak }}</div>
            <div>Bendahara</div>
            <div class="mt-20 font-semibold">........................................</div>
        </div>
    </div>
</template>

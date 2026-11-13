<script setup>
import { computed } from 'vue';
import ProgressBar from 'primevue/progressbar';
import MoneyText from '@/Components/MoneyText.vue';

const props = defineProps({
    saldo: { type: [String, Number], default: 0 },
    target: { type: [String, Number], default: null },
    terkumpul: { type: [String, Number], default: 0 },
});

const persen = computed(() =>
    props.target ? Math.min(100, Math.floor((Number(props.saldo) / Number(props.target)) * 100)) : null,
);
</script>

<!-- Saldo rekening tujuan terhadap target saldo; terkumpul = total yang sudah dipindahkan autodebet. -->
<template>
    <div class="flex flex-col gap-1">
        <template v-if="target">
            <ProgressBar :value="persen" :show-value="false" style="height: 0.4rem" />
            <div class="text-xs">
                <MoneyText :value="saldo" /> / <MoneyText :value="target" />
                <span class="text-muted-color">({{ persen }}%)</span>
            </div>
        </template>
        <div v-else class="text-xs">
            <MoneyText :value="saldo" /> <span class="text-muted-color">saldo · tanpa target</span>
        </div>
        <div v-if="Number(terkumpul) > 0" class="text-xs text-muted-color">
            dari autodebet <MoneyText :value="terkumpul" />
        </div>
    </div>
</template>

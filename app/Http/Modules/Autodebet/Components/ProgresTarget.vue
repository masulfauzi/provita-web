<script setup>
import { computed } from 'vue';
import ProgressBar from 'primevue/progressbar';
import MoneyText from '@/Components/MoneyText.vue';

const props = defineProps({
    terkumpul: { type: [String, Number], default: 0 },
    target: { type: [String, Number], default: null },
});

const persen = computed(() =>
    props.target ? Math.min(100, Math.floor((Number(props.terkumpul) / Number(props.target)) * 100)) : null,
);
</script>

<!-- Terkumpul dari autodebet, dengan progres bila ada target. -->
<template>
    <div v-if="target" class="flex flex-col gap-1">
        <ProgressBar :value="persen" :show-value="false" style="height: 0.4rem" />
        <div class="text-xs">
            <MoneyText :value="terkumpul" /> / <MoneyText :value="target" />
            <span class="text-muted-color">({{ persen }}%)</span>
        </div>
    </div>
    <div v-else class="text-xs">
        <MoneyText :value="terkumpul" /> <span class="text-muted-color">terkumpul · tanpa target</span>
    </div>
</template>

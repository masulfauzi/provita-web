<script setup>
import { Head } from '@inertiajs/vue3';
import MoneyText from '@/Components/MoneyText.vue';
import PageHeader from '@/Components/PageHeader.vue';

defineProps({
    ringkasan: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Dashboard" />

    <PageHeader title="Dashboard" subtitle="Ringkasan aktivitas Bank Provita" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="kartu in ringkasan" :key="kartu.label" class="card flex items-start justify-between">
            <div>
                <div class="text-muted-color">{{ kartu.label }}</div>
                <div class="mt-2 text-2xl font-semibold">
                    <MoneyText v-if="kartu.tipe === 'uang'" :value="kartu.nilai" />
                    <span v-else>{{ kartu.nilai }}</span>
                </div>
            </div>
            <span
                class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary dark:bg-primary-900/40"
            >
                <i :class="kartu.icon" />
            </span>
        </div>
    </div>
</template>

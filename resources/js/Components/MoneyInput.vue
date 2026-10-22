<script setup>
import InputNumber from 'primevue/inputnumber';

/**
 * Input nominal rupiah: tampil "Rp 1.250.000", v-model berisi angka 1250000.
 */
defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [Number, String, null], default: null });

defineProps({
    min: { type: Number, default: 0 },
    max: { type: Number, default: 9999999999999 },
    allowDecimal: { type: Boolean, default: false },
});

function onUpdate(value) {
    model.value = value;
}
</script>

<template>
    <InputNumber
        v-bind="$attrs"
        :model-value="model === null || model === '' ? null : Number(model)"
        mode="currency"
        currency="IDR"
        locale="id-ID"
        :min-fraction-digits="0"
        :max-fraction-digits="allowDecimal ? 2 : 0"
        :min="min"
        :max="max"
        @update:model-value="onUpdate"
    />
</template>

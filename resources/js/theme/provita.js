import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

/**
 * Tema Bank Provita: Aura dengan warna primer biru tua.
 */
export default definePreset(Aura, {
    semantic: {
        primary: {
            50: '{blue.50}',
            100: '{blue.100}',
            200: '{blue.200}',
            300: '{blue.300}',
            400: '{blue.400}',
            500: '{blue.600}',
            600: '{blue.700}',
            700: '{blue.800}',
            800: '{blue.900}',
            900: '{blue.950}',
            950: '{blue.950}',
        },
        colorScheme: {
            light: {
                surface: {
                    0: '#ffffff',
                    50: '{slate.50}',
                    100: '{slate.100}',
                    200: '{slate.200}',
                    300: '{slate.300}',
                    400: '{slate.400}',
                    500: '{slate.500}',
                    600: '{slate.600}',
                    700: '{slate.700}',
                    800: '{slate.800}',
                    900: '{slate.900}',
                    950: '{slate.950}',
                },
            },
            dark: {
                primary: {
                    color: '{blue.400}',
                    inverseColor: '{slate.950}',
                    hoverColor: '{blue.300}',
                    activeColor: '{blue.200}',
                },
            },
        },
    },
});

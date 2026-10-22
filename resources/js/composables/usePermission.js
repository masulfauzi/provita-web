import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Cek permission/peran user dari shared props Inertia (auth.permissions & auth.roles).
 * Hanya untuk menyembunyikan tombol/menu; otorisasi sebenarnya tetap di server.
 */
export function usePermission() {
    const page = usePage();
    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const roles = computed(() => page.props.auth?.roles ?? []);

    const can = (...names) => names.every((name) => permissions.value.includes(name));
    const canAny = (...names) => names.some((name) => permissions.value.includes(name));
    const hasRole = (...names) => names.some((name) => roles.value.includes(name));

    return { can, canAny, hasRole, permissions, roles };
}

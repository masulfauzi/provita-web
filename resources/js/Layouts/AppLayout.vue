<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useDark, useMediaQuery } from '@vueuse/core';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Drawer from 'primevue/drawer';
import Menu from 'primevue/menu';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DateText from '@/Components/DateText.vue';

const page = usePage();
const toast = useToast();

const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles ?? []);
const menu = computed(() => page.props.menu ?? []);
const appName = computed(() => page.props.app?.name ?? 'Bank Provita');

const isDark = useDark({ selector: 'html', valueDark: 'app-dark', valueLight: '', storageKey: 'provita-dark' });
const isDesktop = useMediaQuery('(min-width: 1024px)');
const mobileOpen = ref(false);

const currentPath = computed(() => page.url.split('?')[0]);
const isActive = (url) => currentPath.value === url || currentPath.value.startsWith(`${url}/`);

const userMenu = ref();
const userMenuItems = [
    { label: 'Ganti kata sandi', icon: 'pi pi-key', command: () => router.visit('/profil/password') },
    { separator: true },
    { label: 'Keluar', icon: 'pi pi-sign-out', command: () => router.post('/logout') },
];

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.add({ severity: 'success', summary: 'Berhasil', detail: flash.success, life: 3000 });
        if (flash?.error) toast.add({ severity: 'error', summary: 'Gagal', detail: flash.error, life: 5000 });
    },
    { immediate: true, deep: true },
);

router.on('navigate', () => (mobileOpen.value = false));
</script>

<template>
    <div class="min-h-screen lg:flex">
        <aside
            v-if="isDesktop"
            class="sticky top-0 flex h-screen w-64 shrink-0 flex-col border-r border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="flex h-16 items-center gap-2 px-5 text-lg font-semibold text-primary">
                <i class="pi pi-building-columns text-xl" />
                {{ appName }}
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-4">
                <Link href="/dashboard" class="nav-link" :class="{ 'nav-link-active': isActive('/dashboard') }">
                    <i class="pi pi-home" /> Dashboard
                </Link>
                <div v-for="group in menu" :key="group.label" class="mt-4">
                    <div class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-muted-color">
                        {{ group.label }}
                    </div>
                    <Link
                        v-for="item in group.items"
                        :key="item.route"
                        :href="item.url"
                        class="nav-link"
                        :class="{ 'nav-link-active': isActive(item.url) }"
                    >
                        <i :class="item.icon" /> {{ item.label }}
                    </Link>
                </div>
            </nav>
        </aside>

        <Drawer v-else v-model:visible="mobileOpen" :header="appName" class="!w-72">
            <nav>
                <Link href="/dashboard" class="nav-link" :class="{ 'nav-link-active': isActive('/dashboard') }">
                    <i class="pi pi-home" /> Dashboard
                </Link>
                <div v-for="group in menu" :key="group.label" class="mt-4">
                    <div class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-muted-color">
                        {{ group.label }}
                    </div>
                    <Link
                        v-for="item in group.items"
                        :key="item.route"
                        :href="item.url"
                        class="nav-link"
                        :class="{ 'nav-link-active': isActive(item.url) }"
                    >
                        <i :class="item.icon" /> {{ item.label }}
                    </Link>
                </div>
            </nav>
        </Drawer>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-surface-200 bg-surface-0/90 px-4 backdrop-blur dark:border-surface-800 dark:bg-surface-900/90 lg:px-6"
            >
                <Button v-if="!isDesktop" icon="pi pi-bars" text rounded aria-label="Menu" @click="mobileOpen = true" />

                <div class="flex items-center gap-2 text-sm">
                    <i class="pi pi-calendar text-muted-color" />
                    <span class="hidden text-muted-color sm:inline">Tanggal transaksi:</span>
                    <DateText class="font-semibold" :value="page.props.app?.tanggal_transaksi" hari />
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <Button
                        :icon="isDark ? 'pi pi-sun' : 'pi pi-moon'"
                        text
                        rounded
                        severity="secondary"
                        :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
                        @click="isDark = !isDark"
                    />
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-haspopup="true"
                        @click="userMenu.toggle($event)"
                    >
                        <Avatar :label="user?.name?.charAt(0)?.toUpperCase()" shape="circle" />
                        <span class="hidden text-left leading-tight sm:block">
                            <span class="block font-medium">{{ user?.name }}</span>
                            <span class="block text-xs capitalize text-muted-color">{{ roles.join(', ') }}</span>
                        </span>
                        <i class="pi pi-angle-down text-xs text-muted-color" />
                    </button>
                    <Menu ref="userMenu" :model="userMenuItems" popup>
                        <template #start>
                            <div class="px-3 pt-2 pb-1 text-sm">
                                <div class="font-medium">{{ user?.username }}</div>
                                <Tag v-for="role in roles" :key="role" :value="role" class="mt-1 mr-1 capitalize" />
                            </div>
                        </template>
                    </Menu>
                </div>
            </header>

            <main class="flex-1 p-4 lg:p-6">
                <slot />
            </main>
        </div>

        <Toast position="top-right" />
        <ConfirmDialog />
    </div>
</template>

<style scoped>
@reference '../../css/app.css';

.nav-link {
    @apply flex items-center gap-3 rounded-lg px-3 py-2 text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-800;
}

.nav-link-active {
    @apply bg-primary-50 font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-900/40 dark:text-primary-200;
}
</style>

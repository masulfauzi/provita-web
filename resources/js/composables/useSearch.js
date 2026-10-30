import { ref } from 'vue';

/**
 * Sumber saran untuk AutoComplete dari endpoint JSON, mis. useSearch('/nasabah/cari').
 */
export function useSearch(url) {
    const suggestions = ref([]);
    let controller = null;

    async function search(event) {
        controller?.abort();
        controller = new AbortController();

        try {
            const pemisah = url.includes('?') ? '&' : '?';
            const response = await fetch(`${url}${pemisah}q=${encodeURIComponent(event.query ?? '')}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            suggestions.value = response.ok ? await response.json() : [];
        } catch (error) {
            if (error.name !== 'AbortError') suggestions.value = [];
        }
    }

    return { suggestions, search };
}

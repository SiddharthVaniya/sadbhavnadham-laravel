import { computed, ref } from 'vue';

const compareValues = (a, b) => {
    if (a === b) {
        return 0;
    }

    if (a === null || a === undefined || a === '') {
        return 1;
    }

    if (b === null || b === undefined || b === '') {
        return -1;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
};

export function useClientSort(source, defaultSort = null) {
    const sortKey = ref(defaultSort?.key ?? null);
    const sortDir = ref(defaultSort?.dir ?? 'asc');

    const sortedRows = computed(() => {
        const items = [...(source.value ?? source ?? [])];

        if (! sortKey.value) {
            return items;
        }

        const key = sortKey.value;
        const direction = sortDir.value === 'desc' ? -1 : 1;

        return items.sort((left, right) => compareValues(left[key], right[key]) * direction);
    });

    const toggleSort = (key) => {
        if (sortKey.value === key) {
            sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';

            return;
        }

        sortKey.value = key;
        sortDir.value = 'asc';
    };

    const sortIndicator = (key) => {
        if (sortKey.value !== key) {
            return 'none';
        }

        return sortDir.value;
    };

    return {
        sortKey,
        sortDir,
        sortedRows,
        toggleSort,
        sortIndicator,
    };
}

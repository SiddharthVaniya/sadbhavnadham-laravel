<script setup>
import { ChevronDownIcon, ChevronUpIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import { Card, CardContent, CardFooter } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    sortKey: {
        type: String,
        default: null,
    },
    sortDir: {
        type: String,
        default: 'asc',
    },
    emptyMessage: {
        type: String,
        default: 'No records found.',
    },
});

const emit = defineEmits(['sort']);

const onSort = (column) => {
    if (! column.sortable) {
        return;
    }

    emit('sort', column.key);
};
</script>

<template>
    <Card class="shadow-none">
        <CardContent class="p-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead
                            v-for="column in columns"
                            :key="column.key"
                            :class="[
                                column.align === 'right' ? 'text-right' : 'text-left',
                                column.sortable ? 'cursor-pointer select-none hover:text-foreground' : '',
                            ]"
                            @click="onSort(column)"
                        >
                            <span class="inline-flex items-center gap-1" :class="column.align === 'right' ? 'float-right' : ''">
                                <slot :name="`head-${column.key}`" :column="column">
                                    {{ column.label }}
                                </slot>
                                <template v-if="column.sortable">
                                    <ChevronUpIcon v-if="sortKey === column.key && sortDir === 'asc'" class="size-4 text-foreground" />
                                    <ChevronDownIcon v-else-if="sortKey === column.key && sortDir === 'desc'" class="size-4 text-foreground" />
                                    <ChevronUpDownIcon v-else class="size-4 text-muted-foreground" />
                                </template>
                            </span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(row, index) in rows" :key="row.id ?? index">
                        <TableCell
                            v-for="column in columns"
                            :key="column.key"
                            :class="column.align === 'right' ? 'text-right' : 'text-left'"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" :index="index">
                                {{ row[column.key] }}
                            </slot>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="! rows.length">
                        <TableCell :colspan="columns.length" class="py-12 text-center text-muted-foreground">
                            {{ emptyMessage }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
        <CardFooter v-if="$slots.footer" class="w-full px-4 py-3">
            <div class="w-full">
                <slot name="footer" />
            </div>
        </CardFooter>
    </Card>
</template>

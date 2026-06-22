<template>
	<div>
		<div v-if="title" class="mb-3 text-sm font-medium text-gray-700">{{ title }}</div>
		<div class="flex h-40 items-end gap-1">
			<div
				v-for="item in items"
				:key="item.date || item.label"
				class="group flex min-w-0 flex-1 flex-col items-center justify-end"
			>
				<div
					class="w-full rounded-t bg-primary-500 transition-colors group-hover:bg-primary-600"
					:style="{ height: barHeight(item.count) + '%', minHeight: item.count > 0 ? '4px' : '0' }"
					:title="tooltip(item)"
				/>
				<div class="mt-1 w-full truncate text-center text-[10px] text-gray-500">
					{{ formatLabel(item.date || item.label) }}
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	title: { type: String, default: '' },
	items: { type: Array, default: () => [] },
	labelFormat: { type: String, default: 'short-date' },
});

const maxCount = computed(() => {
	const counts = props.items.map((i) => i.count || 0);
	return Math.max(...counts, 1);
});

function barHeight(count) {
	return Math.round(((count || 0) / maxCount.value) * 100);
}

function formatLabel(value) {
	if (!value) return '';
	if (props.labelFormat === 'short-date' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
		const d = new Date(value + 'T00:00:00');
		return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
	}
	return value;
}

function tooltip(item) {
	const label = item.date || item.label;
	return `${label}: ${item.count}`;
}
</script>

<template>
	<div v-if="!total" class="text-sm text-gray-500">No sentiment data in this period.</div>
	<ul v-else class="space-y-3">
		<li v-for="item in items" :key="item.label">
			<div class="mb-1 flex justify-between text-sm">
				<span class="font-medium text-gray-700">{{ item.label }}</span>
				<span class="text-gray-500">{{ item.count }} ({{ item.pct }}%)</span>
			</div>
			<div class="h-2 overflow-hidden rounded-full bg-gray-100">
				<div class="h-full rounded-full" :class="item.colorClass" :style="{ width: item.pct + '%' }" />
			</div>
		</li>
	</ul>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	sentiment: { type: Object, default: () => ({}) },
});

const colorMap = {
	positive: 'bg-green-500',
	neutral: 'bg-gray-400',
	negative: 'bg-red-500',
	unknown: 'bg-yellow-400',
};

const total = computed(() => Object.values(props.sentiment).reduce((sum, n) => sum + n, 0));

const items = computed(() =>
	Object.entries(props.sentiment).map(([label, count]) => ({
		label: label.charAt(0).toUpperCase() + label.slice(1),
		count,
		pct: total.value ? Math.round((count / total.value) * 100) : 0,
		colorClass: colorMap[label] || colorMap.unknown,
	}))
);
</script>

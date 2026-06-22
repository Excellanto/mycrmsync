<template>
	<div v-if="!total" class="text-sm text-gray-500">No calls in this period.</div>
	<ul v-else class="space-y-3">
		<li v-for="item in items" :key="item.label">
			<div class="mb-1 flex justify-between text-sm">
				<span class="font-medium text-gray-700">{{ item.label }}</span>
				<span class="text-gray-500">{{ item.count }} ({{ item.pct }}%)</span>
			</div>
			<div class="h-2 overflow-hidden rounded-full bg-gray-100">
				<div class="h-full rounded-full bg-primary-500" :style="{ width: item.pct + '%' }" />
			</div>
		</li>
	</ul>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	directions: { type: Object, default: () => ({}) },
});

const total = computed(() => Object.values(props.directions).reduce((sum, n) => sum + n, 0));

const items = computed(() =>
	Object.entries(props.directions).map(([label, count]) => ({
		label,
		count,
		pct: total.value ? Math.round((count / total.value) * 100) : 0,
	}))
);
</script>

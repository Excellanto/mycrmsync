<template>
	<div v-if="!items.length" class="text-sm text-gray-500">{{ emptyText }}</div>
	<ul v-else class="space-y-2">
		<li v-for="item in items" :key="item.label" class="flex items-center justify-between text-sm">
			<span class="truncate pr-3 text-gray-700">{{ item.label }}</span>
			<span class="font-medium text-gray-900">{{ item.count }}</span>
		</li>
	</ul>
</template>

<script setup>
defineProps({
	items: { type: Array, default: () => [] },
	emptyText: { type: String, default: 'No data.' },
});
</script>

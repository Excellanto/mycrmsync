<template>
	<span :class="classes">
		<slot />
	</span>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
	color: { type: String, default: 'gray' }
});
const classes = computed(() => {
	const map = {
		gray: 'bg-gray-100 text-gray-800',
		green: 'bg-green-100 text-green-800',
		red: 'bg-red-100 text-red-800',
		blue: 'bg-blue-100 text-blue-800',
		yellow: 'bg-yellow-100 text-yellow-800',
	};
	return `inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${map[props.color] || map.gray}`;
});
</script>






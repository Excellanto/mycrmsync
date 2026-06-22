<template>
	<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
		<h3 v-if="title" class="mb-4 text-lg font-semibold text-gray-900">{{ title }}</h3>
		<slot />
	</div>
</template>

<script setup>
defineProps({
	title: { type: String, default: '' },
});
</script>

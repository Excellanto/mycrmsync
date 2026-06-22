<template>
	<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
		<div class="flex items-start gap-4">
			<div
				v-if="icon"
				class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
				:class="toneClasses.icon"
			>
				<i :class="[icon, 'text-lg']" aria-hidden="true" />
			</div>
			<div class="min-w-0 flex-1">
				<div class="text-sm font-medium text-gray-500">{{ label }}</div>
				<div class="mt-1 text-2xl font-semibold text-gray-900">{{ value }}</div>
				<div v-if="hint" class="mt-1 text-xs text-gray-500">{{ hint }}</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	label: { type: String, required: true },
	value: { type: [String, Number], required: true },
	hint: { type: String, default: '' },
	icon: { type: String, default: '' },
	tone: {
		type: String,
		default: 'primary',
		validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral', 'purple'].includes(value),
	},
});

const toneClasses = computed(() => {
	const map = {
		primary: 'bg-primary-50 text-primary-600',
		success: 'bg-green-50 text-green-600',
		warning: 'bg-amber-50 text-amber-600',
		danger: 'bg-red-50 text-red-600',
		info: 'bg-sky-50 text-sky-600',
		neutral: 'bg-gray-100 text-gray-600',
		purple: 'bg-violet-50 text-violet-600',
	};

	return { icon: map[props.tone] || map.primary };
});
</script>

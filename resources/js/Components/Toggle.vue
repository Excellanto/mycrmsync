<template>
	<button
		type="button"
		:aria-pressed="modelValue"
		@click="$emit('update:modelValue', !modelValue)"
		:class="[
			'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none',
			modelValue ? 'bg-green-600' : 'bg-gray-300'
		]"
	>
		<span
			:class="[
				'inline-block h-5 w-5 transform rounded-full bg-white transition-transform',
				modelValue ? 'translate-x-6' : 'translate-x-1'
			]"
		/>
	</button>
	<span v-if="label" class="ml-2 text-sm text-gray-700">{{ label }}</span>
</template>

<script setup>
defineProps({
	modelValue: { type: Boolean, default: false },
	label: { type: String, default: '' }
});
defineEmits(['update:modelValue']);
</script>






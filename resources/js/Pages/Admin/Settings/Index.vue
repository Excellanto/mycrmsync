<template>
	<div>
		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-4">
				<div v-for="(group, gname) in grouped" :key="gname" class="rounded-xl border border-gray-200">
					<div class="border-b px-4 py-2 font-medium text-gray-800">{{ gname }}</div>
					<div class="p-4 space-y-3">
						<div v-for="item in group" :key="item.key">
							<label class="mb-1 block text-sm font-medium text-gray-700">{{ item.key }}</label>
							<template v-if="item.type === 'boolean'">
								<Toggle v-model="formMap[item.key]" />
							</template>
							<template v-else-if="item.type === 'json'">
								<textarea v-model="formMap[item.key]" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"></textarea>
							</template>
							<template v-else>
								<input v-model="formMap[item.key]" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
							</template>
						</div>
					</div>
				</div>
				<div class="flex items-center justify-end">
					<button :disabled="processing" class="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 disabled:opacity-50">
						Save Settings
					</button>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import Toggle from '@/Components/Toggle.vue';
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
	settings: Array
});

const grouped = computed(() => {
	const out = {};
	for (const s of props.settings) {
		const group = s.key.split('.')[0] || 'general';
		if (!out[group]) out[group] = [];
		out[group].push(s);
	}
	return out;
});

const formMap = reactive(Object.fromEntries(props.settings.map(s => [s.key, s.type === 'boolean' ? s.value === '1' : s.value])));
const processing = ref(false);
function submit() {
	processing.value = true;
	const payload = {
		settings: props.settings.map(s => ({
			key: s.key,
			type: s.type,
			value: s.type === 'boolean' ? (formMap[s.key] ? '1' : '0') : formMap[s.key]
		}))
	};
	router.put('/admin/settings', payload, { onFinish: () => (processing.value = false) });
}
</script>



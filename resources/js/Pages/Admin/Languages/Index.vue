<template>
	<div>
		<div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
			<div class="flex items-center gap-2">
				<select v-model="state.lang" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
					<option v-for="l in langs" :key="l" :value="l">{{ l }}</option>
				</select>
				<select v-model="state.file" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
					<option value="">All files</option>
					<option v-for="f in files" :key="f" :value="f">{{ f }}</option>
				</select>
				<button class="rounded-lg bg-gray-100 px-3 py-2 text-sm" @click="applyFilter">Filter</button>
			</div>
			<div class="flex items-center gap-2">
				<button class="rounded-lg border px-3 py-2 text-sm" @click="sync" :disabled="syncing">Sync Files</button>
				<button class="rounded-lg bg-primary-600 px-3 py-2 text-sm text-white hover:bg-primary-700" @click="save" :disabled="saving">Save</button>
			</div>
		</div>

		<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-600">File</th>
						<th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-600">Key</th>
						<th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-600">Value</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100 bg-white">
					<tr v-for="row in rows.data" :key="row.id">
						<td class="px-4 py-2 text-sm text-gray-700">{{ row.file }}</td>
						<td class="px-4 py-2 text-sm text-gray-700">{{ row.key }}</td>
						<td class="px-4 py-2">
							<input v-model="formMap[row.id]" type="text" class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="mt-4">
			<Pagination :links="rows.links" />
		</div>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
	lang: String,
	file: String,
	files: Array,
	rows: Object
});

const langs = ['en', 'es', 'fr', 'hi'];
const state = reactive({
	lang: props.lang || 'en',
	file: props.file || ''
});

const formMap = reactive(Object.fromEntries(props.rows.data.map(r => [r.id, r.value || ''])));
const saving = ref(false);
const syncing = ref(false);

function applyFilter() {
	router.get('/admin/languages', { lang: state.lang, file: state.file }, { preserveState: true, preserveScroll: true });
}
function save() {
	saving.value = true;
	const items = props.rows.data.map(r => ({ file: r.file, key: r.key, value: formMap[r.id] }));
	router.put('/admin/languages', { lang: state.lang, items }, { onFinish: () => (saving.value = false) });
}
function sync() {
	syncing.value = true;
	router.post('/admin/languages/sync', {}, { onFinish: () => (syncing.value = false) });
}
</script>



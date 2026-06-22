<template>
	<div>
		<div class="mb-4 flex items-center justify-between">
			<h3 class="text-lg font-semibold">All Permissions</h3>
			<form @submit.prevent="create" class="flex items-center gap-2">
				<input v-model="form.name" type="text" placeholder="module.action" class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required />
				<button :disabled="form.processing" class="rounded-lg bg-primary-600 px-3 py-2 text-sm text-white hover:bg-primary-700">Add</button>
			</form>
		</div>
		<div class="space-y-4">
			<div v-for="(perms, module) in grouped" :key="module" class="rounded border border-gray-200 bg-white">
				<div class="border-b px-4 py-2 font-medium text-gray-800">{{ module }}</div>
				<div class="p-4">
					<div class="grid grid-cols-1 gap-2 md:grid-cols-3">
						<div v-for="p in perms" :key="p.id" class="flex items-center justify-between rounded-lg border px-3 py-2">
							<span class="text-sm text-gray-800">{{ p.name }}</span>
							<button @click="destroy(p)" class="rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700">Delete</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
const props = defineProps({
	permissions: Object,
	grouped: Object
});
const form = useForm({ name: '' });
function create() {
	form.post('/admin/permissions', { onSuccess: () => form.reset() });
}
function destroy(p) {
	if (confirm('Delete permission?')) {
		router.delete(`/admin/permissions/${p.id}`);
	}
}
</script>



<template>
	<div>
		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-4">
				<div class="grid gap-4 md:grid-cols-2">
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
						<input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required />
					</div>
				</div>
				<div>
					<h3 class="mb-2 text-sm font-medium text-gray-700">Permissions</h3>
					<RolePermissionMatrix v-model="form.permissions" :matrix="permissionMatrix" />
				</div>
				<div class="flex items-center gap-3">
					<button :disabled="form.processing" class="rounded-lg bg-primary-600 px-4 py-2 text-white hover:bg-primary-700 disabled:opacity-50">Save</button>
					<Link href="/admin/roles" class="text-sm text-gray-700 hover:text-gray-900">Back</Link>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import RolePermissionMatrix from '@/Components/RolePermissionMatrix.vue';
const props = defineProps({
	role: Object,
	permissionMatrix: Object
});
const form = useForm({
	name: props.role.name,
	permissions: props.role.permissions || []
});
function submit() {
	form.put(`/admin/roles/${props.role.id}`);
}
</script>



<template>
	<div class="space-y-4">
		<div class="flex items-center justify-between">
			<h1 class="text-xl font-semibold">Roles</h1>
			<Link :href="route('admin.roles.create')">
				<PButton label="Add New Role" icon="pi pi-plus" />
			</Link>
		</div>

		<PConfirmDialog />

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
		<PDataTable :value="roles.data" dataKey="id" tableStyle="min-width: 40rem">
			<PColumn header="Role">
				<template #body="slotProps">
					{{ slotProps.data.name }}
				</template>
			</PColumn>

			<PColumn header="Actions" style="width: 140px">
				<template #body="slotProps">
					<div class="flex items-center gap-1">
						<Link :href="route('admin.roles.edit', slotProps.data.id)" title="Edit">
							<PButton icon="pi pi-pencil" rounded text />
						</Link>
						<PButton
							icon="pi pi-trash"
							rounded
							text
							severity="danger"
							title="Delete"
							@click="destroy(slotProps.data)"
						/>
					</div>
				</template>
			</PColumn>
		</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="roles.links || []" />
		</div>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import { Link, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';

defineProps({
	roles: Object
});

const confirm = useConfirm();

function destroy(role) {
	confirm.require({
		message: `Delete role "${role.name}"?`,
		header: 'Confirm Deletion',
		icon: 'pi pi-exclamation-triangle',
		acceptLabel: 'Delete',
		rejectLabel: 'Cancel',
		acceptClass: 'p-button-danger',
		accept: () => router.delete(`/admin/roles/${role.id}`),
	});
}
</script>


<template>
	<div>
		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Users</h1>
			<Link href="/admin/users/create">
				<PButton label="Add New User" icon="pi pi-plus" />
			</Link>
		</div>
		<div class="mb-4 flex flex-wrap items-center gap-2">
			<input v-model="query.search" type="text" placeholder="Search..." class="w-64 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" @keyup.enter="applyFilter" />
			<select
				v-if="isMaster && tenants"
				v-model="query.tenant_id"
				class="rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
			>
				<option value="">All Companies</option>
				<option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
					{{ tenant.company_name }}
				</option>
			</select>
			<button class="rounded-lg bg-gray-100 px-3 py-2 text-sm hover:bg-gray-200" @click="applyFilter">Filter</button>
		</div>

		<PConfirmDialog />

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
		<PDataTable :value="users.data" dataKey="id" stripedRows tableStyle="min-width: 40rem">
			<PColumn header="Image" style="width: 80px">
				<template #body="slotProps">
					<Avatar :label="initials(slotProps.data.name)" class="bg-gray-200 text-gray-700" shape="circle" />
				</template>
			</PColumn>

			<PColumn header="Name" field="name">
				<template #body="slotProps">
					<Link :href="`/admin/users/${slotProps.data.id}/edit#profile`" class="text-primary-700 hover:underline">
						{{ slotProps.data.name }}
					</Link>
				</template>
			</PColumn>

			<PColumn header="Email" field="email" />

			<PColumn header="Mapped User">
				<template #body="slotProps">
					<span>{{ slotProps.data.mapped_user || '—' }}</span>
				</template>
			</PColumn>

			<PColumn header="Tenant">
				<template #body="slotProps">
					<span>{{ slotProps.data.tenant?.company_name || '' }}</span>
				</template>
			</PColumn>

			<PColumn v-if="isMaster" header="System">
				<template #body="slotProps">
					<Badge v-if="slotProps.data.tenant?.integrated_system_name" class="bg-gray-100 text-gray-700">
						{{ slotProps.data.tenant.integrated_system_name }}
					</Badge>
					<span v-else class="text-gray-500">—</span>
				</template>
			</PColumn>

			<PColumn header="Role">
				<template #body="slotProps">
					<Badge v-if="slotProps.data.roles && slotProps.data.roles.length" class="mr-1">
						{{ slotProps.data.roles[0] }}
					</Badge>
					<span v-else class="text-gray-500">-</span>
				</template>
			</PColumn>

			<PColumn header="Actions" style="width: 160px">
				<template #body="slotProps">
					<div class="flex items-center gap-1">
						<Link :href="`/admin/users/${slotProps.data.id}/edit#profile`" title="Edit User Profile">
							<PButton icon="pi pi-user-edit" rounded text />
						</Link>
						<Link :href="`/admin/users/${slotProps.data.id}/edit#permissions`" title="Edit Permissions">
							<PButton icon="pi pi-key" rounded text />
						</Link>
						<PButton
							v-if="canDeleteUser(slotProps.data)"
							icon="pi pi-trash"
							rounded
							text
							severity="danger"
							title="Delete User"
							@click="destroyUser(slotProps.data)"
						/>
					</div>
				</template>
			</PColumn>
		</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="users.meta.links" />
		</div>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import Avatar from 'primevue/avatar';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
	users: Object,
	tenants: Array
});

const page = usePage();
const confirm = useConfirm();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);
const canDelete = computed(() => (page.props.auth?.permissions || []).includes('users.delete'));

const query = reactive({
	search: new URLSearchParams(location.search).get('search') || '',
	tenant_id: new URLSearchParams(location.search).get('tenant_id') || ''
});

function applyFilter() {
	router.get('/admin/users', query, { preserveState: true, preserveScroll: true });
}

function canDeleteUser(user) {
	if (!canDelete.value) {
		return false;
	}

	return user.id !== page.props.auth?.user?.id;
}

function destroyUser(user) {
	confirm.require({
		message: `Delete user "${user.name}"? This cannot be undone.`,
		header: 'Confirm Deletion',
		icon: 'pi pi-exclamation-triangle',
		acceptLabel: 'Delete',
		rejectLabel: 'Cancel',
		acceptClass: 'p-button-danger',
		accept: () => router.delete(`/admin/users/${user.id}`, { preserveScroll: true }),
	});
}

function initials(name) {
	if (!name) return 'U';
	return name
		.split(' ')
		.map((p) => p.charAt(0))
		.join('')
		.slice(0, 2)
		.toUpperCase();
}
</script>



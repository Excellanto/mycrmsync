<template>
	<div>
		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Tenants</h1>
			<Link
				v-if="canCreate"
				:href="route('admin.tenants.create')"
				class="rounded-lg bg-primary-600 px-3 py-2 text-sm text-white hover:bg-primary-700"
			>
				Add Tenant
			</Link>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
		<PDataTable :value="tenants.data" dataKey="id" stripedRows tableStyle="min-width: 50rem">
			<PColumn header="Company Name" field="company_name">
				<template #body="slotProps">
					<Link
						:href="route('admin.tenants.edit', slotProps.data.id)"
						class="text-primary-700 hover:underline"
					>
						{{ slotProps.data.company_name }}
					</Link>
				</template>
			</PColumn>

			<PColumn header="Account Type" field="account_type">
				<template #body="slotProps">
					{{ slotProps.data.account_type || '-' }}
				</template>
			</PColumn>

			<PColumn header="Email" field="email" />

			<PColumn header="PAN Card" field="pan_card">
				<template #body="slotProps">
					{{ slotProps.data.pan_card || '-' }}
				</template>
			</PColumn>

			<PColumn header="GST Number" field="gst_number">
				<template #body="slotProps">
					{{ slotProps.data.gst_number || '-' }}
				</template>
			</PColumn>

			<PColumn header="Status">
				<template #body="slotProps">
					<Badge
						:class="{
							'bg-green-100 text-green-800': slotProps.data.status === 'active',
							'bg-yellow-100 text-yellow-800': slotProps.data.status === 'inactive',
							'bg-red-100 text-red-800': slotProps.data.status === 'suspended'
						}"
					>
						{{ slotProps.data.status }}
					</Badge>
				</template>
			</PColumn>

			<PColumn header="Users">
				<template #body="slotProps">
					{{ slotProps.data.users_count || 0 }}
				</template>
			</PColumn>

			<PColumn header="Integration">
				<template #body="slotProps">
					{{ integrationLabel(slotProps.data.integration) }}
				</template>
			</PColumn>

			<PColumn header="Created At" field="created_at">
				<template #body="slotProps">
					{{ formatDate(slotProps.data.created_at) }}
				</template>
			</PColumn>

			<PColumn header="Actions" style="width: 100px">
				<template #body="slotProps">
					<Link
						:href="route('admin.tenants.edit', slotProps.data.id)"
						class="rounded px-2 py-1 text-sm text-primary-600 hover:bg-primary-50"
					>
						Edit
					</Link>
				</template>
			</PColumn>
		</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="tenants.meta?.links" />
		</div>
	</div>
</template>

<script setup>
import Badge from '@/Components/Badge.vue';
import Pagination from '@/Components/Pagination.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
	tenants: Object,
	canCreate: Boolean,
	integrationLabels: {
		type: Object,
		default: () => ({}),
	},
});

function integrationLabel(integration) {
	const slug = integration && typeof integration === 'object' ? integration.slug : null;
	if (!slug) {
		return '-';
	}
	return props.integrationLabels[slug] ?? slug;
}

function formatDate(dateStr) {
	if (!dateStr) return '-';
	const d = new Date(dateStr);
	return d.toLocaleDateString();
}
</script>

<template>
	<div>
		<Head title="Url Management" />

		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Url Management</h1>
			<p class="mt-1 text-sm text-gray-500">Short URLs mapped to long storage links.</p>
		</div>

		<div class="mb-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
					<input
						v-model="filters.search"
						type="text"
						placeholder="Code, long URL, source..."
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>

				<div v-if="isMaster && tenants?.length">
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<select
						v-model="filters.tenant_id"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All companies</option>
						<option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
							{{ tenant.company_name }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">User</label>
					<select
						v-model="filters.user_id"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All users</option>
						<option v-for="user in users" :key="user.id" :value="user.id">
							{{ user.name }}
						</option>
					</select>
				</div>

				<div class="flex items-end gap-2">
					<button
						type="button"
						class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
						@click="applyFilters"
					>
						Apply
					</button>
					<button
						type="button"
						class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
						@click="clearFilters"
					>
						Clear
					</button>
				</div>
			</div>
		</div>

		<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code</th>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Short URL</th>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Long URL</th>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
							<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 bg-white">
						<tr v-for="row in shortUrls.data" :key="row.id">
							<td class="px-4 py-3 text-sm font-mono text-gray-900">{{ row.code }}</td>
							<td class="px-4 py-3 text-sm">
								<div class="flex max-w-xs items-center gap-2">
									<a :href="row.short_url" target="_blank" rel="noopener noreferrer" class="truncate text-primary-600 hover:underline">
										{{ row.short_url }}
									</a>
									<button type="button" class="shrink-0 text-xs text-gray-500 hover:text-gray-800" @click="copyText(row.short_url)">
										Copy
									</button>
								</div>
							</td>
							<td class="px-4 py-3 text-sm">
								<div class="flex max-w-md items-center gap-2">
									<a :href="row.long_url" target="_blank" rel="noopener noreferrer" class="truncate text-gray-700 hover:underline">
										{{ row.long_url }}
									</a>
									<button type="button" class="shrink-0 text-xs text-gray-500 hover:text-gray-800" @click="copyText(row.long_url)">
										Copy
									</button>
								</div>
							</td>
							<td class="px-4 py-3 text-sm text-gray-600">
								<span v-if="row.source_type">{{ row.source_type }}</span>
								<span v-if="row.source_id" class="block truncate font-mono text-xs text-gray-500">{{ row.source_id }}</span>
								<span v-if="!row.source_type && !row.source_id">—</span>
							</td>
							<td class="px-4 py-3 text-sm text-gray-600">
								<div v-if="row.user">{{ row.user.name }}</div>
								<div v-else>—</div>
							</td>
							<td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(row.created_at) }}</td>
						</tr>
						<tr v-if="!shortUrls.data.length">
							<td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No short URLs found.</td>
						</tr>
					</tbody>
				</table>
			</div>

			<Pagination v-if="shortUrls.links?.length" :links="shortUrls.links" />
		</div>
	</div>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
	shortUrls: { type: Object, required: true },
	filters: { type: Object, default: () => ({}) },
	users: { type: Array, default: () => [] },
	tenants: { type: Array, default: null },
	isMaster: { type: Boolean, default: false },
});

const filters = reactive({
	search: props.filters.search ?? '',
	tenant_id: props.filters.tenant_id ?? '',
	user_id: props.filters.user_id ?? '',
});

function applyFilters() {
	router.get(route('admin.url-management.index'), filters, {
		preserveState: true,
		replace: true,
	});
}

function clearFilters() {
	filters.search = '';
	filters.tenant_id = '';
	filters.user_id = '';
	applyFilters();
}

async function copyText(value) {
	try {
		await navigator.clipboard.writeText(value);
	} catch {
		// ignore clipboard failures
	}
}

function formatDate(value) {
	if (!value) return '—';
	return new Date(value).toLocaleString();
}
</script>

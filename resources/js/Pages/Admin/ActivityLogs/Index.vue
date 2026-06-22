<template>
	<div>
		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Activity Logs</h1>
			<div class="flex gap-2">
				<button v-if="canExport" @click="exportLogs" class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700">
					<i class="pi pi-download mr-2"></i>Export
				</button>
			</div>
		</div>

		<!-- Filters -->
		<div class="mb-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
					<input 
						v-model="filters.search" 
						type="text" 
						placeholder="Search logs..." 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>
				
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Module</label>
					<select 
						v-model="filters.module" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All Modules</option>
						<option v-for="module in modules" :key="module" :value="module">
							{{ formatModuleName(module) }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Action</label>
					<select 
						v-model="filters.action" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All Actions</option>
						<option v-for="action in actions" :key="action" :value="action">
							{{ formatActionName(action) }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">User</label>
					<select 
						v-model="filters.user_id" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All Users</option>
						<option v-for="user in users" :key="user.id" :value="user.id">
							{{ user.name }}
						</option>
					</select>
				</div>

				<div v-if="isMaster && tenants">
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<select 
						v-model="filters.tenant_id" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All Companies</option>
						<option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
							{{ tenant.company_name }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Start Date</label>
					<input 
						v-model="filters.start_date" 
						type="date" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">End Date</label>
					<input 
						v-model="filters.end_date" 
						type="date" 
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>

				<div class="flex items-end gap-2">
					<button @click="applyFilters" class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700">
						Apply Filters
					</button>
					<button @click="resetFilters" class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">
						Reset
					</button>
				</div>
			</div>
		</div>

		<!-- Activity Logs Table -->
		<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
		<PDataTable :value="logs.data" dataKey="id" stripedRows tableStyle="min-width: 50rem">
			<PColumn header="Date & Time" style="width: 180px">
				<template #body="slotProps">
					<div class="text-sm">
						<div class="font-medium">{{ formatDate(slotProps.data.created_at) }}</div>
						<div class="text-gray-500">{{ formatTime(slotProps.data.created_at) }}</div>
					</div>
				</template>
			</PColumn>

			<PColumn header="User" style="width: 200px">
				<template #body="slotProps">
					<div class="flex items-center gap-2">
						<Avatar 
							:label="getInitials(slotProps.data.user?.name || slotProps.data.user_name || 'System')" 
							class="bg-gray-200 text-gray-700" 
							shape="circle" 
							size="small"
						/>
						<span class="text-sm">{{ slotProps.data.user?.name || slotProps.data.user_name || 'System' }}</span>
					</div>
				</template>
			</PColumn>

			<PColumn header="Module" style="width: 120px">
				<template #body="slotProps">
					<Badge :severity="getModuleBadgeColor(slotProps.data.module)">
						{{ formatModuleName(slotProps.data.module) }}
					</Badge>
				</template>
			</PColumn>

			<PColumn v-if="isMaster" header="Company" style="width: 150px">
				<template #body="slotProps">
					{{ slotProps.data.tenant?.company_name || '-' }}
				</template>
			</PColumn>

			<PColumn header="Action" style="width: 120px">
				<template #body="slotProps">
					<Badge :severity="getActionBadgeColor(slotProps.data.action)">
						{{ formatActionName(slotProps.data.action) }}
					</Badge>
				</template>
			</PColumn>

			<PColumn header="Description">
				<template #body="slotProps">
					<div class="text-sm">{{ slotProps.data.description }}</div>
				</template>
			</PColumn>

			<PColumn header="Actions" style="width: 100px">
				<template #body="slotProps">
					<button 
						@click="showDetails(slotProps.data)" 
						class="text-primary-600 hover:text-primary-800"
						title="View Details"
					>
						<i class="pi pi-eye"></i>
					</button>
				</template>
			</PColumn>
		</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="logs.links" />
		</div>

		<!-- Details Modal -->
		<PDialog v-model:visible="detailsModalVisible" modal header="Activity Log Details" :style="{ width: '50vw' }">
			<div v-if="selectedLog" class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<div class="text-sm font-medium text-gray-500">User</div>
						<div class="mt-1">{{ selectedLog.user?.name || selectedLog.user_name || 'System' }}</div>
					</div>
					<div>
						<div class="text-sm font-medium text-gray-500">Date & Time</div>
						<div class="mt-1">{{ formatDateTime(selectedLog.created_at) }}</div>
					</div>
					<div>
						<div class="text-sm font-medium text-gray-500">Module</div>
						<div class="mt-1">
							<Badge :severity="getModuleBadgeColor(selectedLog.module)">
								{{ formatModuleName(selectedLog.module) }}
							</Badge>
						</div>
					</div>
					<div>
						<div class="text-sm font-medium text-gray-500">Action</div>
						<div class="mt-1">
							<Badge :severity="getActionBadgeColor(selectedLog.action)">
								{{ formatActionName(selectedLog.action) }}
							</Badge>
						</div>
					</div>
					<div>
						<div class="text-sm font-medium text-gray-500">IP Address</div>
						<div class="mt-1">{{ selectedLog.ip_address || '-' }}</div>
					</div>
					<div>
						<div class="text-sm font-medium text-gray-500">User Agent</div>
						<div class="mt-1 text-sm">{{ selectedLog.user_agent ? truncate(selectedLog.user_agent, 50) : '-' }}</div>
					</div>
				</div>
				
				<div>
					<div class="text-sm font-medium text-gray-500">Description</div>
					<div class="mt-1">{{ selectedLog.description }}</div>
				</div>

				<div v-if="selectedLog.properties">
					<div class="text-sm font-medium text-gray-500 mb-2">Properties</div>
					<div class="rounded-lg bg-gray-50 p-3">
						<pre class="text-xs">{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
					</div>
				</div>
			</div>
		</PDialog>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';
import Avatar from 'primevue/avatar';
import PDialog from 'primevue/dialog';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	logs: Object,
	filters: Object,
	modules: Array,
	actions: Array,
	users: Array,
});

const canExport = ref(true); // You can make this dynamic based on permissions

const filters = reactive({
	search: props.filters?.search || '',
	module: props.filters?.module || '',
	action: props.filters?.action || '',
	user_id: props.filters?.user_id || '',
	tenant_id: props.filters?.tenant_id || '',
	start_date: props.filters?.start_date || '',
	end_date: props.filters?.end_date || '',
});

const detailsModalVisible = ref(false);
const selectedLog = ref(null);

function applyFilters() {
	router.get('/admin/activity-logs', filters, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
	filters.search = '';
	filters.module = '';
	filters.action = '';
	filters.user_id = '';
	filters.tenant_id = '';
	filters.start_date = '';
	filters.end_date = '';
	applyFilters();
}

function exportLogs() {
	const params = new URLSearchParams(filters);
	window.location.href = `/admin/activity-logs/export?${params.toString()}`;
}

function showDetails(log) {
	selectedLog.value = log;
	detailsModalVisible.value = true;
}

function formatDate(dateString) {
	const date = new Date(dateString);
	return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatTime(dateString) {
	const date = new Date(dateString);
	return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function formatDateTime(dateString) {
	return new Date(dateString).toLocaleString('en-US', { 
		year: 'numeric', 
		month: 'short', 
		day: 'numeric',
		hour: '2-digit', 
		minute: '2-digit',
		second: '2-digit'
	});
}

function formatModuleName(module) {
	if (!module) return '';
	return module.charAt(0).toUpperCase() + module.slice(1);
}

function formatActionName(action) {
	if (!action) return '';
	return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function getInitials(name) {
	if (!name) return 'S';
	return name
		.split(' ')
		.map((p) => p.charAt(0))
		.join('')
		.slice(0, 2)
		.toUpperCase();
}

function getModuleBadgeColor(module) {
	const colors = {
		users: 'info',
		roles: 'warning',
		permissions: 'danger',
		settings: 'secondary',
		languages: 'success',
		auth: 'primary',
	};
	return colors[module] || 'secondary';
}

function getActionBadgeColor(action) {
	const colors = {
		created: 'success',
		updated: 'info',
		deleted: 'danger',
		login: 'success',
		logout: 'warning',
		failed_login: 'danger',
		restored: 'info',
	};
	return colors[action] || 'secondary';
}

function truncate(str, length) {
	return str.length > length ? str.substring(0, length) + '...' : str;
}
</script>


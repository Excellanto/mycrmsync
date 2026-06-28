<template>
	<div>
		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Call Log Management</h1>
		</div>

		<div class="mb-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
				<div v-if="isMaster && tenants?.length">
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<AutoComplete
						v-model="tenantSelection"
						:suggestions="filteredTenants"
						option-label="company_name"
						placeholder="All companies..."
						class="w-full"
						dropdown
						force-selection
						show-clear
						@complete="searchTenants"
						@item-select="onTenantSelected"
						@clear="onTenantCleared"
					/>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Phone number</label>
					<PInputText v-model="filters.phone" type="text" placeholder="Search by number..." class="w-full" />
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Contact</label>
					<PInputText v-model="filters.contact" type="text" placeholder="Name or CRM contact id..." class="w-full" />
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

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Direction</label>
					<select
						v-model="filters.direction"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All directions</option>
						<option v-for="direction in directions" :key="direction" :value="direction">
							{{ direction }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Start date</label>
					<input
						v-model="filters.start_date"
						type="date"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">End date</label>
					<input
						v-model="filters.end_date"
						type="date"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					/>
				</div>

				<div class="flex items-end">
					<label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
						<input v-model="filters.has_recording" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
						Has recording only
					</label>
				</div>

				<div class="flex items-end gap-2">
					<button
						type="button"
						class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700"
						@click="applyFilters"
					>
						Apply filters
					</button>
					<button
						type="button"
						class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300"
						@click="resetFilters"
					>
						Reset
					</button>
				</div>
			</div>
		</div>

		<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
			<PDataTable :value="logs.data" data-key="id" striped-rows table-style="min-width: 68rem">
				<PColumn header="Started" style="width: 200px">
					<template #body="{ data }">
						<span class="text-sm font-medium">{{ formatStartedAt(data.started_at || data.created_at) }}</span>
					</template>
				</PColumn>

				<PColumn v-if="isMaster" header="Company" style="width: 160px">
					<template #body="{ data }">
						{{ data.user?.tenant?.company_name || '—' }}
					</template>
				</PColumn>

				<PColumn header="User" style="width: 160px">
					<template #body="{ data }">
						<div class="text-sm">
							<div class="font-medium">{{ data.user?.name || '—' }}</div>
							<div v-if="data.user?.email" class="text-gray-500">{{ data.user.email }}</div>
						</div>
					</template>
				</PColumn>

				<PColumn header="Contact" style="width: 180px">
					<template #body="{ data }">
						<span class="text-sm font-medium">{{ data.contact_name || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Phone" style="width: 150px">
					<template #body="{ data }">
						<span class="text-sm">{{ data.phone_e164 || data.phone_raw || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Direction" style="width: 110px">
					<template #body="{ data }">
						<Badge :severity="directionSeverity(data.direction)">{{ data.direction }}</Badge>
					</template>
				</PColumn>

				<PColumn header="Duration" style="width: 90px">
					<template #body="{ data }">
						{{ formatDuration(data.duration_sec) }}
					</template>
				</PColumn>

				<PColumn header="Recording" style="width: 140px">
					<template #body="{ data }">
						<div v-if="data.recordings_count > 0" class="flex flex-col gap-1">
							<button
								type="button"
								class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-800"
								@click="openRecordingDrawer(data)"
							>
								<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.5A2.25 2.25 0 012.25 15v-6A2.25 2.25 0 014.5 6.75h2.25z" />
								</svg>
								Play
								<span v-if="data.recordings_count > 1" class="text-xs text-gray-500">({{ data.recordings_count }})</span>
							</button>
							<Badge v-if="recordingAiBadge(data.latest_recording)" :severity="recordingAiBadge(data.latest_recording).severity">
								{{ recordingAiBadge(data.latest_recording).label }}
							</Badge>
						</div>
						<span v-else class="text-sm text-gray-400">—</span>
					</template>
				</PColumn>

				<PColumn header="Status" style="width: 120px">
					<template #body="{ data }">
						<span class="text-sm text-gray-700">{{ data.status }}</span>
					</template>
				</PColumn>

				<PColumn v-if="canDelete" header="Actions" style="width: 80px">
					<template #body="{ data }">
						<button
							type="button"
							class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-red-600"
							title="Delete call log"
							@click="confirmDelete(data)"
						>
							<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
							</svg>
						</button>
					</template>
				</PColumn>
			</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="logs.links" />
		</div>

		<PDrawer v-model:visible="recordingDrawerVisible" position="right" :style="{ width: '32rem' }" header="Call Recording">
			<div v-if="activeCallLog" class="space-y-5">
				<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
					<div class="font-medium">{{ activeCallLog.contact_name || activeCallLog.phone_e164 || activeCallLog.phone_raw || 'Call' }}</div>
					<div class="mt-1 text-gray-600">{{ formatStartedAt(activeCallLog.started_at) }} · {{ activeCallLog.direction }}</div>
				</div>

				<div v-if="recordingsLoading" class="text-sm text-gray-500">Loading recordings...</div>
				<div v-else-if="recordingsError" class="text-sm text-red-600">{{ recordingsError }}</div>
				<div v-else-if="recordings.length === 0" class="text-sm text-gray-500">No recordings linked to this call.</div>

				<div v-for="recording in recordings" :key="recording.call_recording_id" class="space-y-4 rounded-lg border border-gray-200 p-4">
					<div class="flex flex-wrap items-center justify-between gap-2">
						<span class="text-xs text-gray-500">{{ formatRecordingDate(recording.created_at) }}</span>
						<Badge :severity="recordingStatusSeverity(recording.status)">{{ recordingStatusLabel(recording) }}</Badge>
					</div>

					<audio
						v-if="playbackUrl(recording)"
						:key="recording.call_recording_id"
						controls
						class="w-full"
						:src="playbackUrl(recording)"
						preload="metadata"
					>
						Your browser does not support audio playback.
					</audio>
					<p v-else class="text-sm text-gray-500">No playable audio URL for this recording.</p>

					<div v-if="recording.transcription_backend" class="text-xs text-gray-500">
						Engine: {{ recording.transcription_backend }}
					</div>

					<div v-if="recording.summary" class="space-y-1">
						<h3 class="text-sm font-semibold text-gray-900">Summary</h3>
						<p class="whitespace-pre-wrap text-sm text-gray-700">{{ recording.summary }}</p>
					</div>

					<div v-if="recording.sentiment?.overall" class="space-y-1">
						<h3 class="text-sm font-semibold text-gray-900">Sentiment</h3>
						<p class="text-sm capitalize text-gray-700">
							{{ recording.sentiment.overall }}
							<span v-if="recording.sentiment.score != null" class="text-gray-500">
								(score {{ Number(recording.sentiment.score).toFixed(2) }})
							</span>
						</p>
						<ul v-if="recording.sentiment.highlights?.length" class="mt-1 list-inside list-disc text-sm text-gray-600">
							<li v-for="(highlight, idx) in recording.sentiment.highlights" :key="idx">{{ highlight }}</li>
						</ul>
					</div>

					<div v-if="recording.transcription" class="space-y-1">
						<h3 class="text-sm font-semibold text-gray-900">Transcription</h3>
						<p class="max-h-64 overflow-y-auto whitespace-pre-wrap text-sm text-gray-700">{{ recording.transcription }}</p>
					</div>

					<div
						v-if="!recording.summary && !recording.transcription && recording.status === 'completed'"
						class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
					>
						Recording saved but no AI transcription or summary is available.
					</div>
				</div>
			</div>
		</PDrawer>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import Badge from '@/Components/Badge.vue';
import { axios } from '@/bootstrap';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AutoComplete from 'primevue/autocomplete';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	logs: Object,
	filters: Object,
	tenants: Array,
	users: Array,
	directions: Array,
	canDelete: Boolean,
});

const filters = reactive({
	tenant_id: props.filters?.tenant_id || '',
	user_id: props.filters?.user_id || '',
	phone: props.filters?.phone || '',
	contact: props.filters?.contact || '',
	direction: props.filters?.direction || '',
	start_date: props.filters?.start_date || '',
	end_date: props.filters?.end_date || '',
	has_recording: props.filters?.has_recording === '1' || props.filters?.has_recording === true || props.filters?.has_recording === 'true',
});

const filteredTenants = ref([]);
const tenantSelection = ref(null);

const recordingDrawerVisible = ref(false);
const activeCallLog = ref(null);
const recordings = ref([]);
const recordingsLoading = ref(false);
const recordingsError = ref('');

function syncTenantSelectionFromFilters() {
	if (!props.tenants?.length || !filters.tenant_id) {
		tenantSelection.value = null;
		return;
	}

	tenantSelection.value = props.tenants.find((tenant) => String(tenant.id) === String(filters.tenant_id)) || null;
}

syncTenantSelectionFromFilters();

onMounted(() => {
	filteredTenants.value = props.tenants || [];
});

watch(
	() => props.filters?.tenant_id,
	() => syncTenantSelectionFromFilters()
);

function searchTenants(event) {
	const query = (event.query || '').trim().toLowerCase();
	const source = props.tenants || [];

	if (!query) {
		filteredTenants.value = source;
		return;
	}

	filteredTenants.value = source.filter((tenant) => tenant.company_name.toLowerCase().includes(query));
}

function onTenantSelected(event) {
	filters.tenant_id = event.value?.id ? String(event.value.id) : '';
}

function onTenantCleared() {
	filters.tenant_id = '';
	tenantSelection.value = null;
}

function filterPayload() {
	return {
		...filters,
		has_recording: filters.has_recording ? '1' : '',
	};
}

function applyFilters() {
	router.get('/admin/call-logs', filterPayload(), { preserveState: true, preserveScroll: true });
}

function resetFilters() {
	filters.tenant_id = '';
	filters.user_id = '';
	filters.phone = '';
	filters.contact = '';
	filters.direction = '';
	filters.start_date = '';
	filters.end_date = '';
	filters.has_recording = false;
	tenantSelection.value = null;
	applyFilters();
}

function confirmDelete(callLog) {
	const label = callLog.contact_name || callLog.phone_e164 || callLog.phone_raw || 'this call log';
	if (!window.confirm(`Delete call log for "${label}"? This cannot be undone.`)) {
		return;
	}

	router.delete(route('admin.call-logs.destroy', callLog.id), {
		data: filterPayload(),
		preserveScroll: true,
	});
}

async function openRecordingDrawer(callLog) {
	activeCallLog.value = callLog;
	recordings.value = [];
	recordingsError.value = '';
	recordingDrawerVisible.value = true;
	recordingsLoading.value = true;

	try {
		const { data } = await axios.get(route('admin.call-logs.recordings', callLog.id));
		activeCallLog.value = data.call_log || callLog;
		recordings.value = Array.isArray(data.recordings) ? data.recordings : [];
	} catch (err) {
		recordingsError.value = err?.response?.data?.message || err.message || 'Could not load recordings.';
	} finally {
		recordingsLoading.value = false;
	}
}

function playbackUrl(recording) {
	return recording.recording_url_long || recording.recording_url || '';
}

function recordingAiBadge(latest) {
	if (!latest) return null;
	if (latest.has_summary || latest.has_transcription) {
		return { label: 'AI processed', severity: 'success' };
	}
	if (latest.status === 'failed') {
		return { label: 'Processing failed', severity: 'danger' };
	}
	return { label: 'Recording only', severity: 'secondary' };
}

function recordingStatusLabel(recording) {
	if (recording.status === 'completed' && (recording.summary || recording.transcription)) {
		return 'AI processed';
	}
	if (recording.status === 'completed') {
		return 'Completed';
	}
	if (recording.status === 'failed') {
		return 'Failed';
	}
	return recording.status || 'Unknown';
}

function recordingStatusSeverity(status) {
	if (status === 'completed') return 'success';
	if (status === 'failed') return 'danger';
	return 'secondary';
}

function formatRecordingDate(value) {
	if (!value) return '';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return value;
	return date.toLocaleString();
}

function getOrdinalDay(day) {
	if (day > 3 && day < 21) {
		return `${day}th`;
	}

	switch (day % 10) {
		case 1:
			return `${day}st`;
		case 2:
			return `${day}nd`;
		case 3:
			return `${day}rd`;
		default:
			return `${day}th`;
	}
}

function formatStartedAt(value) {
	if (!value) return '—';

	const date = new Date(value);
	const day = getOrdinalDay(date.getDate());
	const month = date.toLocaleDateString('en-GB', { month: 'long' });
	const year = String(date.getFullYear()).slice(-2);
	const time = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

	return `${day} ${month} ${year} ${time}`;
}

function formatDuration(seconds) {
	if (seconds === null || seconds === undefined) return '—';
	if (seconds === 0) return '0s';
	const mins = Math.floor(seconds / 60);
	const secs = seconds % 60;
	return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
}

function directionSeverity(direction) {
	switch (direction) {
		case 'INCOMING':
			return 'success';
		case 'OUTGOING':
			return 'info';
		case 'MISSED':
			return 'danger';
		default:
			return 'secondary';
	}
}
</script>

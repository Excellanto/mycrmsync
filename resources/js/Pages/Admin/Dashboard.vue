<template>
	<div>
		<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
			<div>
				<h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
				<p class="text-sm text-gray-500">{{ analytics.period_label }}</p>
			</div>
			<div class="flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm">
				<button
					v-for="option in periodOptions"
					:key="option.value"
					type="button"
					class="rounded-md px-3 py-1.5 text-sm font-medium transition"
					:class="period === option.value ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
					@click="changePeriod(option.value)"
				>
					{{ option.label }}
				</button>
			</div>
		</div>

		<!-- Master admin -->
		<template v-if="isMaster && analytics.scope === 'master'">
			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
				<StatCard label="Active tenants" :value="analytics.summary.tenants_active" icon="pi pi-building" tone="success" />
				<StatCard label="Suspended tenants" :value="analytics.summary.tenants_suspended" icon="pi pi-pause-circle" tone="danger" />
				<StatCard label="Total users" :value="analytics.summary.total_users" icon="pi pi-users" tone="info" />
				<StatCard label="Calls" :value="formatNumber(analytics.summary.total_calls)" icon="pi pi-phone" tone="primary" />
				<StatCard label="Recordings" :value="formatNumber(analytics.summary.total_recordings)" icon="pi pi-volume-up" tone="purple" />
			</div>

			<div class="mt-4 grid gap-4 lg:grid-cols-2">
				<DashboardSection title="Platform call volume">
					<SimpleBarChart :items="sampledCallsOverTime(analytics.calls_over_time)" />
				</DashboardSection>
				<DashboardSection title="Call directions">
					<DirectionBreakdown :directions="analytics.directions" />
				</DashboardSection>
			</div>

			<div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
				<StatCard
					label="Recording coverage"
					:value="formatPercent(analytics.recordings.coverage_pct)"
					hint="Calls with at least one recording"
					icon="pi pi-chart-pie"
					tone="info"
				/>
				<StatCard label="Transcriptions completed" :value="analytics.recordings.transcription_completed" icon="pi pi-check-circle" tone="success" />
				<StatCard label="Transcription failures" :value="analytics.recordings.transcription_failed" icon="pi pi-times-circle" tone="danger" />
				<StatCard
					label="Transcription failure rate"
					:value="formatPercent(analytics.recordings.transcription_failure_rate)"
					icon="pi pi-exclamation-triangle"
					tone="warning"
				/>
			</div>

			<div class="mt-4 grid gap-4 lg:grid-cols-2">
				<DashboardSection title="Sentiment (recordings)">
					<SentimentBreakdown :sentiment="analytics.sentiment" />
				</DashboardSection>
				<DashboardSection title="Tenants by CRM">
					<KeyValueList :items="crmBreakdownItems(analytics.tenants_by_crm)" empty-text="No tenants yet." />
				</DashboardSection>
			</div>

			<DashboardSection class="mt-4" title="MyCrmSync platform">
				<div class="grid gap-4 md:grid-cols-3">
					<StatCard label="MyCrmSync tenants" :value="analytics.mycrmsync_platform.tenant_count" icon="pi pi-database" tone="primary" />
					<StatCard label="Total contacts" :value="formatNumber(analytics.mycrmsync_platform.total_contacts)" icon="pi pi-address-book" tone="info" />
					<StatCard
						label="Tenants with zero contacts"
						:value="analytics.mycrmsync_platform.tenants_with_zero_contacts"
						icon="pi pi-inbox"
						tone="warning"
					/>
				</div>
			</DashboardSection>

			<DashboardSection class="mt-4" title="Tenant scorecard">
				<div class="overflow-x-auto">
					<PDataTable :value="analytics.tenant_scorecard" data-key="tenant_id" striped-rows size="small">
						<PColumn header="Company" field="company_name" />
						<PColumn header="CRM" style="width: 130px">
							<template #body="{ data }">{{ data.crm_label }}</template>
						</PColumn>
						<PColumn header="Users" field="users_count" style="width: 80px" />
						<PColumn header="Calls" field="calls_in_period" style="width: 80px" />
						<PColumn header="Recordings" field="recordings_in_period" style="width: 100px" />
						<PColumn header="Integration" style="width: 110px">
							<template #body="{ data }">
								<Badge :color="data.integration_status ? 'green' : 'red'">
									{{ data.integration_status ? 'Connected' : 'Issue' }}
								</Badge>
							</template>
						</PColumn>
						<PColumn header="Last activity" style="width: 170px">
							<template #body="{ data }">{{ formatDateTime(data.last_activity_at) }}</template>
						</PColumn>
					</PDataTable>
				</div>
			</DashboardSection>

			<DashboardSection v-if="analytics.integration_health?.length" class="mt-4" title="Integration health">
				<div class="overflow-x-auto">
					<PDataTable :value="analytics.integration_health" data-key="tenant_id" striped-rows size="small">
						<PColumn header="Company" field="company_name" />
						<PColumn header="CRM" field="crm_label" style="width: 140px" />
						<PColumn header="Status" style="width: 120px">
							<template #body="{ data }">
								<Badge :color="data.integration_status ? 'green' : 'yellow'">
									{{ data.integration_status ? 'OK' : 'Needs attention' }}
								</Badge>
							</template>
						</PColumn>
						<PColumn header="Account" style="width: 110px">
							<template #body="{ data }">
								<Badge :color="data.tenant_status === 'active' ? 'green' : 'red'">{{ data.tenant_status }}</Badge>
							</template>
						</PColumn>
					</PDataTable>
				</div>
			</DashboardSection>

			<DashboardSection v-if="analytics.activity_logs" class="mt-4" title="Activity logs (last 30 days)">
				<div class="grid gap-6 md:grid-cols-3">
					<StatCard label="Total events" :value="formatNumber(analytics.activity_logs.total)" icon="pi pi-history" tone="neutral" />
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">By module</h4>
						<KeyValueList :items="activityItems(analytics.activity_logs.by_module, 'module')" />
					</div>
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">By action</h4>
						<KeyValueList :items="activityItems(analytics.activity_logs.by_action, 'action')" />
					</div>
				</div>
			</DashboardSection>
		</template>

		<!-- Tenant admin -->
		<template v-else-if="analytics.scope === 'tenant'">
			<div v-if="analytics.integration" class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<span class="text-sm text-gray-600">CRM integration</span>
				<Badge color="blue">{{ analytics.integration.label }}</Badge>
				<Badge :color="analytics.integration.status ? 'green' : 'yellow'">
					{{ analytics.integration.status ? 'Connected' : 'Needs attention' }}
				</Badge>
			</div>

			<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
				<StatCard label="Calls" :value="formatNumber(analytics.summary.total_calls)" icon="pi pi-phone" tone="primary" />
				<StatCard label="Recordings" :value="formatNumber(analytics.recordings.total)" icon="pi pi-volume-up" tone="purple" />
				<StatCard label="Avg call duration" :value="formatDuration(analytics.summary.avg_duration_sec)" icon="pi pi-clock" tone="info" />
				<StatCard
					v-if="analytics.integration?.is_mycrmsync && analytics.contacts"
					label="Contacts"
					:value="formatNumber(analytics.contacts.total)"
					icon="pi pi-address-book"
					tone="info"
				/>
				<StatCard
					v-else
					label="Recording coverage"
					:value="formatPercent(analytics.recordings.coverage_pct)"
					icon="pi pi-chart-pie"
					tone="info"
				/>
			</div>

			<div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
				<StatCard
					label="Recording coverage"
					:value="formatPercent(analytics.recordings.coverage_pct)"
					hint="Calls with at least one recording"
					icon="pi pi-chart-pie"
					tone="info"
				/>
				<StatCard label="Calls linked to contacts" :value="formatNumber(analytics.summary.calls_linked_to_contacts)" icon="pi pi-link" tone="success" />
				<StatCard label="Unmatched calls" :value="formatNumber(analytics.summary.calls_unmatched)" icon="pi pi-question-circle" tone="warning" />
				<StatCard
					label="Missed call rate"
					:value="analytics.summary.missed_call_rate != null ? formatPercent(analytics.summary.missed_call_rate) : '—'"
					icon="pi pi-arrow-down-left"
					tone="danger"
				/>
			</div>

			<div class="mt-4 grid gap-4 lg:grid-cols-2">
				<DashboardSection title="Calls over time">
					<SimpleBarChart :items="sampledCallsOverTime(analytics.calls_over_time)" />
				</DashboardSection>
				<DashboardSection title="Call directions">
					<DirectionBreakdown :directions="analytics.directions" />
				</DashboardSection>
			</div>

			<div class="mt-4 grid gap-4 lg:grid-cols-2">
				<DashboardSection title="Top users by calls">
					<KeyValueList
						:items="analytics.top_users.map((u) => ({ label: u.name, count: u.count }))"
						empty-text="No calls in this period."
					/>
				</DashboardSection>
				<DashboardSection title="Top phone numbers">
					<KeyValueList
						:items="analytics.top_numbers.map((n) => ({ label: n.phone, count: n.count }))"
						empty-text="No phone data in this period."
					/>
				</DashboardSection>
			</div>

			<div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
				<StatCard label="Transcriptions completed" :value="analytics.recordings.transcription_completed" icon="pi pi-check-circle" tone="success" />
				<StatCard label="Transcription failures" :value="analytics.recordings.transcription_failed" icon="pi pi-times-circle" tone="danger" />
				<StatCard label="Transcription pending" :value="analytics.recordings.transcription_pending" icon="pi pi-hourglass" tone="warning" />
				<StatCard
					label="Recording duration"
					:value="formatDuration(analytics.recordings.total_duration_sec)"
					icon="pi pi-stopwatch"
					tone="neutral"
				/>
			</div>

			<div class="mt-4 grid gap-4 lg:grid-cols-2">
				<DashboardSection title="Sentiment (recordings)">
					<SentimentBreakdown :sentiment="analytics.sentiment" />
				</DashboardSection>
				<DashboardSection title="Recent recordings">
					<div v-if="!analytics.recent_recordings.length" class="text-sm text-gray-500">No recordings in this period.</div>
					<ul v-else class="space-y-3">
						<li
							v-for="recording in analytics.recent_recordings"
							:key="recording.id"
							class="rounded-lg border border-gray-100 bg-gray-50 p-3"
						>
							<div class="flex flex-wrap items-center justify-between gap-2">
								<span class="text-sm font-medium text-gray-800">{{ recording.user_name || 'Unknown user' }}</span>
								<span class="text-xs text-gray-500">{{ formatDateTime(recording.created_at) }}</span>
							</div>
							<p v-if="recording.summary" class="mt-1 text-sm text-gray-600">{{ recording.summary }}</p>
							<div class="mt-2 flex flex-wrap gap-2">
								<Badge v-if="recording.sentiment" :color="sentimentColor(recording.sentiment)">{{ recording.sentiment }}</Badge>
								<Badge :color="recording.status === 'completed' ? 'green' : 'red'">{{ recording.status }}</Badge>
								<span v-if="recording.duration_sec" class="text-xs text-gray-500">{{ formatDuration(recording.duration_sec) }}</span>
							</div>
						</li>
					</ul>
				</DashboardSection>
			</div>

			<DashboardSection v-if="analytics.contacts" class="mt-4" title="MyCrmSync contacts">
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
					<StatCard label="Total contacts" :value="formatNumber(analytics.contacts.total)" icon="pi pi-address-book" tone="info" />
					<StatCard label="New in period" :value="formatNumber(analytics.contacts.new_in_period)" icon="pi pi-user-plus" tone="success" />
					<StatCard label="With notes" :value="formatNumber(analytics.contacts.with_notes)" icon="pi pi-file-edit" tone="primary" />
					<StatCard label="Calls linked to contacts" :value="formatNumber(analytics.contacts.calls_linked)" icon="pi pi-link" tone="success" />
				</div>
				<div class="mt-4 grid gap-4 lg:grid-cols-2">
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">By source</h4>
						<KeyValueList :items="objectToItems(analytics.contacts.by_source)" />
					</div>
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">By type</h4>
						<KeyValueList :items="objectToItems(analytics.contacts.by_type)" />
					</div>
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">By assigned user</h4>
						<KeyValueList :items="objectToItems(analytics.contacts.by_assigned)" />
					</div>
					<div>
						<h4 class="mb-2 text-sm font-medium text-gray-700">Top tags</h4>
						<KeyValueList
							:items="analytics.contacts.top_tags.map((t) => ({ label: t.tag, count: t.count }))"
							empty-text="No tags yet."
						/>
					</div>
				</div>
				<div class="mt-4 grid gap-4 md:grid-cols-2">
					<StatCard label="Contacts without notes" :value="formatNumber(analytics.contacts.without_notes)" icon="pi pi-file" tone="neutral" />
					<StatCard
						label="Contacts with recordings"
						:value="formatNumber(analytics.contacts.contacts_with_recordings)"
						icon="pi pi-microphone"
						tone="purple"
					/>
				</div>
			</DashboardSection>

			<DashboardSection class="mt-4" title="User sync health">
				<div class="mb-4 flex flex-wrap gap-4">
					<StatCard label="Users never synced" :value="analytics.users_never_synced" icon="pi pi-cloud-upload" tone="danger" />
					<StatCard
						label="Active users (30d)"
						:value="analytics.user_sync_health.filter((u) => u.is_active).length"
						icon="pi pi-verified"
						tone="success"
					/>
				</div>
				<div class="overflow-x-auto">
					<PDataTable :value="analytics.user_sync_health" data-key="id" striped-rows size="small">
						<PColumn header="User" field="name" />
						<PColumn header="Calls in period" field="calls_in_period" style="width: 120px" />
						<PColumn header="Last call sync" style="width: 170px">
							<template #body="{ data }">{{ formatDateTime(data.last_call_sync_at) }}</template>
						</PColumn>
						<PColumn header="Last login" style="width: 170px">
							<template #body="{ data }">{{ formatDateTime(data.last_login_at) }}</template>
						</PColumn>
						<PColumn header="Status" style="width: 130px">
							<template #body="{ data }">
								<Badge v-if="!data.has_ever_synced" color="red">Never synced</Badge>
								<Badge v-else-if="data.is_active" color="green">Active</Badge>
								<Badge v-else color="yellow">Inactive</Badge>
							</template>
						</PColumn>
					</PDataTable>
				</div>
			</DashboardSection>
		</template>
	</div>
</template>

<script setup>
import Badge from '@/Components/Badge.vue';
import DashboardSection from '@/Components/Dashboard/DashboardSection.vue';
import DirectionBreakdown from '@/Components/Dashboard/DirectionBreakdown.vue';
import KeyValueList from '@/Components/Dashboard/KeyValueList.vue';
import SentimentBreakdown from '@/Components/Dashboard/SentimentBreakdown.vue';
import SimpleBarChart from '@/Components/Dashboard/SimpleBarChart.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	analytics: Object,
	period: { type: String, default: '30d' },
});

const periodOptions = [
	{ value: '7d', label: '7 days' },
	{ value: '30d', label: '30 days' },
	{ value: '90d', label: '90 days' },
];

function changePeriod(value) {
	router.get(route('admin.dashboard'), { period: value }, { preserveState: true, preserveScroll: true });
}

function formatNumber(value) {
	return new Intl.NumberFormat().format(value ?? 0);
}

function formatPercent(value) {
	if (value == null) return '—';
	return `${value}%`;
}

function formatDuration(seconds) {
	const total = Number(seconds) || 0;
	if (total < 60) return `${total}s`;
	const mins = Math.floor(total / 60);
	const secs = total % 60;
	if (mins < 60) return secs ? `${mins}m ${secs}s` : `${mins}m`;
	const hours = Math.floor(mins / 60);
	const remMins = mins % 60;
	return remMins ? `${hours}h ${remMins}m` : `${hours}h`;
}

function formatDateTime(value) {
	if (!value) return '—';
	return new Date(value).toLocaleString();
}

function sentimentColor(value) {
	const map = { positive: 'green', neutral: 'gray', negative: 'red' };
	return map[String(value).toLowerCase()] || 'yellow';
}

function objectToItems(obj) {
	return Object.entries(obj || {}).map(([label, count]) => ({ label, count }));
}

function crmBreakdownItems(rows) {
	return (rows || []).map((row) => ({ label: row.label, count: row.count }));
}

function activityItems(rows, key) {
	return (rows || []).map((row) => ({ label: row[key], count: row.count }));
}

function sampledCallsOverTime(items) {
	const data = items || [];
	if (data.length <= 31) return data;
	const step = Math.ceil(data.length / 31);
	return data.filter((_, index) => index % step === 0 || index === data.length - 1);
}
</script>

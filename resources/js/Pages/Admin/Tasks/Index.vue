<template>
	<div>
		<div class="mb-4 flex items-start justify-between gap-4">
			<div>
				<h1 class="text-xl font-semibold text-gray-900">My tasks</h1>
				<p class="mt-1 max-w-2xl text-sm text-gray-500">
					A task you add here is not a reminder. Reminders set on a contact note also show up in this list.
				</p>
			</div>
			<button
				v-if="canCreate"
				type="button"
				class="shrink-0 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white"
				@click="openCreate"
			>
				Add task
			</button>
		</div>

		<div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
			<div class="grid gap-4 md:grid-cols-3">
				<div v-if="isMaster">
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<select v-model="localFilters.tenant_id" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters">
						<option value="">Choose a company</option>
						<option v-for="tenant in tenants" :key="tenant.id" :value="String(tenant.id)">
							{{ tenant.company_name }}
						</option>
					</select>
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
					<select v-model="localFilters.status" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters">
						<option value="open">Open</option>
						<option value="completed">Completed</option>
						<option value="cancelled">Cancelled</option>
						<option value="all">All</option>
					</select>
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Show</label>
					<select v-model="localFilters.kind" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters">
						<option value="all">Tasks and reminders</option>
						<option value="tasks">Tasks only</option>
						<option value="reminders">Reminders only</option>
					</select>
				</div>
			</div>
		</div>

		<p v-if="isMaster && !filters.tenant_id" class="mb-4 text-sm text-gray-500">Choose a company to see its tasks.</p>

		<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
			<div v-if="!tasks.data?.length" class="p-6 text-sm text-gray-500">You have no tasks in this view.</div>
			<div v-else class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-3">Task</th>
							<th class="px-4 py-3">Type</th>
							<th class="px-4 py-3">Contact</th>
							<th class="px-4 py-3">Due</th>
							<th class="px-4 py-3">Assigned To</th>
							<th class="px-4 py-3">Status</th>
							<th class="px-4 py-3"></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="task in tasks.data" :key="task.id">
							<td class="px-4 py-3">
								<div class="font-medium text-gray-900">{{ task.title }}</div>
								<p v-if="task.body" class="mt-1 text-gray-500">{{ task.body }}</p>
								<p v-if="task.sync_error" class="mt-1 text-xs text-red-600">{{ task.sync_error }}</p>
								<p v-else-if="task.external_id" class="mt-1 text-xs text-gray-400">Synced to {{ crmLabel }}</p>
							</td>
							<td class="px-4 py-3">
								<span
									class="rounded-full px-2 py-0.5 text-xs font-medium"
									:class="task.is_reminder ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'"
								>
									{{ task.is_reminder ? 'Reminder' : 'Task' }}
								</span>
							</td>
							<td class="px-4 py-3 text-gray-700">{{ task.contact_name || '—' }}</td>
							<td class="px-4 py-3 text-gray-700">{{ formatDue(task.due_at) }}</td>
							<td class="px-4 py-3 text-gray-700">{{ assigneeLabel(task) }}</td>
							<td class="px-4 py-3">
								<select
									v-if="canUpdate"
									:value="task.status"
									class="rounded-lg border-gray-300 text-sm capitalize"
									:disabled="busyId === task.id"
									@change="changeStatus(task, $event.target.value)"
								>
									<option value="open">Open</option>
									<option value="completed">Completed</option>
									<option value="cancelled">Cancelled</option>
								</select>
								<span v-else class="capitalize text-gray-700">{{ task.status }}</span>
							</td>
							<td class="px-4 py-3 text-right whitespace-nowrap">
								<button
									v-if="!task.is_reminder && canUpdate"
									type="button"
									class="text-xs text-primary-700 hover:underline"
									@click="openEdit(task)"
								>
									Edit
								</button>
								<button
									v-if="task.can_delete"
									type="button"
									class="ml-3 text-xs text-red-600 hover:underline disabled:opacity-50"
									:disabled="busyId === task.id"
									@click="deleteTask(task)"
								>
									Delete
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div v-if="tasks.links" class="border-t border-gray-100 px-4 py-3">
				<Pagination :links="tasks.links" />
			</div>
		</div>

		<p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>

		<PDialog v-model:visible="formOpen" modal :header="editingId ? 'Edit task' : 'Add task'" :style="{ width: '32rem' }">
			<div class="space-y-3">
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
					<input v-model="form.title" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Details</label>
					<textarea v-model="form.body" rows="3" class="w-full rounded-lg border-gray-300 text-sm" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Due</label>
					<input v-model="form.due_at" type="datetime-local" class="w-full rounded-lg border-gray-300 text-sm" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Assign to</label>
					<PMultiSelect
						v-model="form.assignee_ids"
						:options="users"
						option-label="name"
						option-value="id"
						placeholder="All users"
						display="chip"
						filter
						append-to="body"
						class="w-full"
					/>
					<p class="mt-1 text-xs text-gray-500">Leave this empty to show the task to everyone in the company.</p>
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">
						Contact
						<span v-if="contactRequired" class="text-red-600">*</span>
					</label>
					<input
						v-model="contactQuery"
						type="text"
						class="w-full rounded-lg border-gray-300 text-sm"
						placeholder="Search by name or phone"
						@input="onContactQuery"
					/>
					<p v-if="form.contact_name" class="mt-1 text-xs text-gray-500">Selected: {{ form.contact_name }}</p>
					<ul v-if="contactResults.length" class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-gray-200">
						<li v-for="contact in contactResults" :key="contact.id">
							<button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50" @click="selectContact(contact)">
								{{ contact.name }}
							</button>
						</li>
					</ul>
				</div>
				<p v-if="form.sync_error" class="text-sm text-red-600">{{ form.sync_error }}</p>
			</div>
			<template #footer>
				<button type="button" class="mr-2 rounded-lg px-3 py-2 text-sm text-gray-600" @click="formOpen = false">Cancel</button>
				<button
					type="button"
					class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white disabled:opacity-50"
					:disabled="saving"
					@click="saveTask"
				>
					Save task
				</button>
			</template>
		</PDialog>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import { axios } from '@/bootstrap';
import { router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	tasks: { type: Object, default: () => ({ data: [], links: [] }) },
	filters: { type: Object, default: () => ({}) },
	tenants: { type: Array, default: () => [] },
	contactRequired: { type: Boolean, default: false },
	crmLabel: { type: String, default: '' },
	canCreate: { type: Boolean, default: false },
	canUpdate: { type: Boolean, default: false },
	users: { type: Array, default: () => [] },
});

const localFilters = reactive({
	status: props.filters.status || 'open',
	kind: props.filters.kind || 'all',
	tenant_id: props.filters.tenant_id ? String(props.filters.tenant_id) : '',
});

watch(
	() => props.filters,
	(value) => {
		localFilters.status = value?.status || 'open';
		localFilters.kind = value?.kind || 'all';
		localFilters.tenant_id = value?.tenant_id ? String(value.tenant_id) : '';
	},
);

const formOpen = ref(false);
const saving = ref(false);
const formError = ref('');
const editingId = ref(null);
const busyId = ref(null);
const contactQuery = ref('');
const contactResults = ref([]);
let contactTimer = null;

const form = reactive({
	title: '',
	body: '',
	due_at: '',
	contact_id: '',
	contact_name: '',
	assignee_ids: [],
	sync_error: '',
});

function applyFilters() {
	router.get(
		route('admin.tasks.index'),
		{
			status: localFilters.status,
			kind: localFilters.kind,
			tenant_id: localFilters.tenant_id || undefined,
		},
		{ preserveState: true, preserveScroll: true },
	);
}

function openCreate() {
	if (isMaster.value && !localFilters.tenant_id) {
		formError.value = 'Choose a company before adding a task.';
		return;
	}

	editingId.value = null;
	form.title = '';
	form.body = '';
	form.due_at = '';
	form.contact_id = '';
	form.contact_name = '';
	form.assignee_ids = [];
	form.sync_error = '';
	contactQuery.value = '';
	contactResults.value = [];
	formError.value = '';
	formOpen.value = true;
}

function openEdit(task) {
	editingId.value = task.id;
	form.title = task.title;
	form.body = task.body || '';
	form.due_at = toDatetimeLocal(task.due_at);
	form.contact_id = task.contact_id || '';
	form.contact_name = task.contact_name || '';
	form.assignee_ids = Array.isArray(task.assignee_ids) ? [...task.assignee_ids] : [];
	form.sync_error = '';
	contactQuery.value = task.contact_name || '';
	contactResults.value = [];
	formError.value = '';
	formOpen.value = true;
}

function onContactQuery() {
	form.contact_id = '';
	window.clearTimeout(contactTimer);
	contactTimer = window.setTimeout(searchContacts, 250);
}

async function searchContacts() {
	const q = contactQuery.value.trim();
	if (q.length < 2) {
		contactResults.value = [];
		return;
	}

	try {
		const { data } = await axios.get(route('admin.tasks.contacts'), {
			params: {
				q,
				tenant_id: localFilters.tenant_id || undefined,
			},
		});
		contactResults.value = Array.isArray(data.contacts) ? data.contacts : [];
	} catch {
		contactResults.value = [];
	}
}

function selectContact(contact) {
	form.contact_id = contact.id;
	form.contact_name = contact.name;
	contactQuery.value = contact.name;
	contactResults.value = [];
}

async function saveTask() {
	formError.value = '';
	form.sync_error = '';
	if (!form.title.trim()) {
		formError.value = 'A title is required.';
		return;
	}
	const dueAt = fromDatetimeLocal(form.due_at);
	if (!dueAt) {
		formError.value = 'Pick a due date and time.';
		return;
	}
	if (props.contactRequired && !form.contact_id) {
		formError.value = 'Choose a GoHighLevel contact for this task.';
		return;
	}

	saving.value = true;
	const payload = {
		title: form.title.trim(),
		body: form.body.trim(),
		due_at: dueAt,
		contact_id: form.contact_id || null,
		contact_name: form.contact_name || '',
		assignee_ids: form.assignee_ids,
		tenant_id: localFilters.tenant_id || undefined,
	};

	try {
		const url = editingId.value ? route('admin.tasks.update', editingId.value) : route('admin.tasks.store');
		const method = editingId.value ? 'put' : 'post';
		const { data } = await axios[method](url, payload);
		if (data.task?.sync_error) {
			form.sync_error = data.task.sync_error;
			router.reload({ preserveScroll: true });
			return;
		}
		formOpen.value = false;
		router.reload({ preserveScroll: true });
	} catch (err) {
		formError.value = err?.response?.data?.message || err?.message || 'Could not save the task.';
	} finally {
		saving.value = false;
	}
}

function assigneeLabel(task) {
	if (!task.assignee_names?.length) return 'all';
	return task.assignee_names.join(', ');
}

async function deleteTask(task) {
	if (!window.confirm(`Delete "${task.title}"? This cannot be undone.`)) return;
	busyId.value = task.id;
	formError.value = '';
	try {
		await axios.delete(route('admin.tasks.destroy', task.id));
		router.reload({ preserveScroll: true });
	} catch (err) {
		formError.value = err?.response?.data?.message || err?.message || 'Could not delete the task.';
	} finally {
		busyId.value = null;
	}
}

async function changeStatus(task, status) {
	if (status === task.status) return;
	busyId.value = task.id;
	formError.value = '';
	try {
		await axios.post(route('admin.tasks.status', task.id), { status });
		router.reload({ preserveScroll: true });
	} catch (err) {
		formError.value = err?.response?.data?.message || err?.message || 'Could not change the status.';
	} finally {
		busyId.value = null;
	}
}

function formatDue(value) {
	if (!value) return '—';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return value;
	const day = date.getDate();
	const month = date.toLocaleString('en-GB', { month: 'short' }).toUpperCase();
	const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
	return `${ordinal(day)} ${month} ${date.getFullYear()}, ${time}`;
}

function ordinal(day) {
	const mod100 = day % 100;
	if (mod100 >= 11 && mod100 <= 13) return `${day}th`;
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

function toDatetimeLocal(value) {
	if (!value) return '';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return '';
	const pad = (part) => String(part).padStart(2, '0');
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function fromDatetimeLocal(value) {
	if (!value) return null;
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return null;
	return date.toISOString();
}
</script>

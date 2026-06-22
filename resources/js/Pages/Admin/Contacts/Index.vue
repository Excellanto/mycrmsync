<template>
	<div>
		<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
			<h1 class="text-xl font-semibold">Contacts</h1>
			<button
				v-if="canCreate"
				type="button"
				class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700"
				@click="openCreateModal"
			>
				Add Contact
			</button>
		</div>

		<div class="mb-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
				<div v-if="isMaster && tenants?.length">
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<AutoComplete
						v-model="tenantSelection"
						:suggestions="filteredTenants"
						option-label="company_name"
						placeholder="Select company..."
						class="w-full"
						dropdown
						force-selection
						@complete="searchTenants"
						@item-select="onTenantSelected"
					/>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
					<PInputText v-model="filters.search" type="text" placeholder="Name, email, phone..." class="w-full" />
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Assigned user</label>
					<select
						v-model="filters.assigned_to"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All users</option>
						<option v-for="user in users" :key="user.id" :value="user.id">
							{{ user.name }}
						</option>
					</select>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Tag</label>
					<select
						v-model="filters.tag"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">All tags</option>
						<option v-for="tag in tags" :key="tag.id" :value="tag.name">
							{{ tag.name }}
						</option>
					</select>
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
			<PDataTable
				:value="contacts.data"
				data-key="id"
				striped-rows
				table-style="min-width: 60rem"
				@row-click="onRowClick"
				row-hover
				class="cursor-pointer"
			>
				<PColumn header="Name" style="width: 180px">
					<template #body="{ data }">
						<span class="text-sm font-medium">{{ data.name || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Email" style="width: 200px">
					<template #body="{ data }">
						<span class="text-sm">{{ data.email || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Phone" style="width: 140px">
					<template #body="{ data }">
						<span class="text-sm">{{ data.phone || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Company" style="width: 160px">
					<template #body="{ data }">
						<span class="text-sm">{{ data.companyName || '—' }}</span>
					</template>
				</PColumn>

				<PColumn header="Assigned to" style="width: 140px">
					<template #body="{ data }">
						<span class="text-sm">{{ assignedUserName(data.assignedTo) }}</span>
					</template>
				</PColumn>

				<PColumn header="Tags" style="width: 180px">
					<template #body="{ data }">
						<div class="flex flex-wrap gap-1">
							<span
								v-for="tag in data.tags || []"
								:key="tag"
								class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700"
							>
								{{ tag }}
							</span>
							<span v-if="!(data.tags || []).length" class="text-sm text-gray-400">—</span>
						</div>
					</template>
				</PColumn>

				<PColumn header="Added" style="width: 160px">
					<template #body="{ data }">
						<span class="text-sm">{{ formatDate(data.dateAdded) }}</span>
					</template>
				</PColumn>

				<PColumn header="Actions" style="width: 120px">
					<template #body="{ data }">
						<div class="flex items-center gap-2" @click.stop>
							<button
								v-if="canUpdate"
								type="button"
								class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-primary-600"
								title="Edit contact"
								@click="openEditModal(data)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
								</svg>
							</button>
							<button
								type="button"
								class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-amber-600"
								title="View notes"
								@click="openNotesDrawer(data)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
								</svg>
							</button>
							<button
								v-if="canDelete"
								type="button"
								class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-red-600"
								title="Delete contact"
								@click="confirmDelete(data)"
							>
								<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
								</svg>
							</button>
						</div>
					</template>
				</PColumn>
			</PDataTable>
		</div>

		<div class="mt-3">
			<Pagination :links="contacts.links" />
		</div>

		<PDialog
			v-model:visible="contactModalVisible"
			:header="contactModalMode === 'create' ? 'Add Contact' : 'Edit Contact'"
			modal
			:style="{ width: '32rem' }"
		>
			<form class="space-y-4" @submit.prevent="submitContactForm">
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">First name</label>
						<PInputText v-model="contactForm.first_name" class="w-full" />
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Last name</label>
						<PInputText v-model="contactForm.last_name" class="w-full" />
					</div>
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
					<PInputText v-model="contactForm.email" type="email" class="w-full" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
					<PInputText v-model="contactForm.phone" class="w-full" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
					<PInputText v-model="contactForm.company_name" class="w-full" />
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Assigned to</label>
					<select
						v-model="contactForm.assigned_to"
						class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					>
						<option value="">Unassigned</option>
						<option v-for="user in users" :key="user.id" :value="String(user.id)">
							{{ user.name }}
						</option>
					</select>
				</div>
				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700">Tags (comma-separated)</label>
					<PInputText v-model="contactForm.tagsInput" class="w-full" placeholder="lead, vip" />
				</div>
				<div class="flex justify-end gap-2 pt-2">
					<button type="button" class="rounded-lg bg-gray-200 px-4 py-2 text-sm" @click="contactModalVisible = false">
						Cancel
					</button>
					<button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white" :disabled="contactForm.processing">
						Save
					</button>
				</div>
			</form>
		</PDialog>

		<PDrawer v-model:visible="notesDrawerVisible" position="right" :style="{ width: '28rem' }" header="Contact Notes">
			<div v-if="activeContact" class="space-y-4">
				<div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
					<div class="font-medium">{{ activeContact.name || 'Contact' }}</div>
					<div class="text-sm text-gray-600">{{ activeContact.email || activeContact.phone || '—' }}</div>
				</div>

				<div v-if="notesLoading" class="text-sm text-gray-500">Loading notes...</div>
				<div v-else-if="notesError" class="text-sm text-red-600">{{ notesError }}</div>
				<div v-else class="space-y-3">
					<div v-if="notes.length === 0" class="text-sm text-gray-500">No notes yet.</div>
					<div
						v-for="note in notes"
						:key="note.id"
						class="rounded-lg border border-gray-200 p-3"
					>
						<div class="mb-1 flex items-start justify-between gap-2">
							<div class="text-xs text-gray-500">
								{{ note.user_name }} · {{ formatDate(note.dateAdded) }}
							</div>
							<div v-if="canUpdate" class="flex gap-1">
								<button type="button" class="text-xs text-primary-600 hover:underline" @click="startEditNote(note)">
									Edit
								</button>
								<button type="button" class="text-xs text-red-600 hover:underline" @click="deleteNote(note)">
									Delete
								</button>
							</div>
						</div>
						<div v-if="editingNoteId === note.id" class="space-y-2">
							<textarea v-model="noteEditBody" rows="4" class="w-full rounded-lg border-gray-300 text-sm" />
							<div class="flex gap-2">
								<button type="button" class="rounded bg-primary-600 px-2 py-1 text-xs text-white" @click="saveNoteEdit(note)">
									Save
								</button>
								<button type="button" class="rounded bg-gray-200 px-2 py-1 text-xs" @click="cancelNoteEdit">
									Cancel
								</button>
							</div>
						</div>
						<div v-else class="whitespace-pre-wrap text-sm text-gray-800">{{ note.body }}</div>
					</div>
				</div>

				<div v-if="canUpdate" class="border-t border-gray-200 pt-4">
					<label class="mb-1 block text-sm font-medium text-gray-700">Add note</label>
					<textarea v-model="newNoteBody" rows="4" class="mb-2 w-full rounded-lg border-gray-300 text-sm" placeholder="Write a note..." />
					<button
						type="button"
						class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white disabled:opacity-50"
						:disabled="!newNoteBody.trim() || noteSaving"
						@click="addNote"
					>
						Save note
					</button>
				</div>
			</div>
		</PDrawer>
	</div>
</template>

<script setup>
import Pagination from '@/Components/Pagination.vue';
import { axios } from '@/bootstrap';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AutoComplete from 'primevue/autocomplete';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	contacts: Object,
	filters: Object,
	tenants: Array,
	users: Array,
	tags: Array,
	canCreate: Boolean,
	canUpdate: Boolean,
	canDelete: Boolean,
});

const filters = reactive({
	tenant_id: props.filters?.tenant_id || '',
	search: props.filters?.search || '',
	assigned_to: props.filters?.assigned_to || '',
	tag: props.filters?.tag || '',
});

const filteredTenants = ref([]);
const tenantSelection = ref(null);

function syncTenantSelectionFromFilters() {
	if (!props.tenants?.length || !filters.tenant_id) {
		tenantSelection.value = props.tenants?.length === 1 ? props.tenants[0] : null;
		if (tenantSelection.value && !filters.tenant_id) {
			filters.tenant_id = String(tenantSelection.value.id);
		}
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

function applyFilters() {
	router.get('/admin/contacts', filters, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
	filters.search = '';
	filters.assigned_to = '';
	filters.tag = '';
	applyFilters();
}

function assignedUserName(userId) {
	if (!userId) return '—';
	const match = props.users?.find((user) => String(user.id) === String(userId));
	return match?.name || userId;
}

function formatDate(value) {
	if (!value) return '—';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return value;
	return date.toLocaleString();
}

const contactModalVisible = ref(false);
const contactModalMode = ref('create');
const editingContactId = ref(null);

const contactForm = useForm({
	first_name: '',
	last_name: '',
	email: '',
	phone: '',
	company_name: '',
	assigned_to: '',
	tagsInput: '',
});

function emptyContactForm() {
	contactForm.first_name = '';
	contactForm.last_name = '';
	contactForm.email = '';
	contactForm.phone = '';
	contactForm.company_name = '';
	contactForm.assigned_to = '';
	contactForm.tagsInput = '';
}

function openCreateModal() {
	contactModalMode.value = 'create';
	editingContactId.value = null;
	emptyContactForm();
	contactModalVisible.value = true;
}

function openEditModal(contact) {
	contactModalMode.value = 'edit';
	editingContactId.value = contact.id;
	contactForm.first_name = contact.firstName || '';
	contactForm.last_name = contact.lastName || '';
	contactForm.email = contact.email || '';
	contactForm.phone = contact.phone || '';
	contactForm.company_name = contact.companyName || '';
	contactForm.assigned_to = contact.assignedTo ? String(contact.assignedTo) : '';
	contactForm.tagsInput = (contact.tags || []).join(', ');
	contactModalVisible.value = true;
}

function contactPayloadFromForm() {
	const tags = contactForm.tagsInput
		.split(',')
		.map((tag) => tag.trim())
		.filter(Boolean);

	return {
		first_name: contactForm.first_name,
		last_name: contactForm.last_name,
		email: contactForm.email,
		phone: contactForm.phone,
		company_name: contactForm.company_name,
		assigned_to: contactForm.assigned_to || null,
		tags,
		tenant_id: filters.tenant_id || undefined,
	};
}

function submitContactForm() {
	const payload = contactPayloadFromForm();

	if (contactModalMode.value === 'create') {
		contactForm.transform(() => payload).post(route('admin.contacts.store'), {
			preserveScroll: true,
			onSuccess: () => {
				contactModalVisible.value = false;
			},
		});
		return;
	}

	contactForm.transform(() => payload).put(route('admin.contacts.update', editingContactId.value), {
		preserveScroll: true,
		onSuccess: () => {
			contactModalVisible.value = false;
		},
	});
}

function confirmDelete(contact) {
	if (!window.confirm(`Delete contact "${contact.name || contact.email || contact.id}"?`)) {
		return;
	}

	router.delete(route('admin.contacts.destroy', contact.id), {
		data: { tenant_id: filters.tenant_id || undefined },
		preserveScroll: true,
	});
}

const notesDrawerVisible = ref(false);
const activeContact = ref(null);
const notes = ref([]);
const notesLoading = ref(false);
const notesError = ref('');
const newNoteBody = ref('');
const noteSaving = ref(false);
const editingNoteId = ref(null);
const noteEditBody = ref('');

async function openNotesDrawer(contact) {
	activeContact.value = contact;
	notesDrawerVisible.value = true;
	notes.value = [];
	notesError.value = '';
	newNoteBody.value = '';
	editingNoteId.value = null;
	await loadNotes();
}

function onRowClick(event) {
	openNotesDrawer(event.data);
}

async function loadNotes() {
	if (!activeContact.value?.id) return;

	notesLoading.value = true;
	notesError.value = '';

	try {
		const { data } = await axios.get(route('admin.contacts.notes.index', activeContact.value.id));
		notes.value = Array.isArray(data.notes) ? data.notes : [];
		if (data.contact) {
			activeContact.value = data.contact;
		}
	} catch (err) {
		notesError.value = err?.response?.data?.message || err.message || 'Could not load notes.';
	} finally {
		notesLoading.value = false;
	}
}

async function addNote() {
	if (!activeContact.value?.id || !newNoteBody.value.trim()) return;

	noteSaving.value = true;

	try {
		const { data } = await axios.post(route('admin.contacts.notes.store', activeContact.value.id), {
			body: newNoteBody.value.trim(),
		});
		if (data.note) {
			notes.value = [data.note, ...notes.value];
		} else {
			await loadNotes();
		}
		newNoteBody.value = '';
	} catch (err) {
		notesError.value = err?.response?.data?.message || err.message || 'Could not save note.';
	} finally {
		noteSaving.value = false;
	}
}

function startEditNote(note) {
	editingNoteId.value = note.id;
	noteEditBody.value = note.body;
}

function cancelNoteEdit() {
	editingNoteId.value = null;
	noteEditBody.value = '';
}

async function saveNoteEdit(note) {
	if (!activeContact.value?.id) return;

	try {
		const { data } = await axios.put(route('admin.contacts.notes.update', [activeContact.value.id, note.id]), {
			body: noteEditBody.value.trim(),
		});
		const updated = data.note;
		notes.value = notes.value.map((row) => (row.id === note.id ? updated : row));
		cancelNoteEdit();
	} catch (err) {
		notesError.value = err?.response?.data?.message || err.message || 'Could not update note.';
	}
}

async function deleteNote(note) {
	if (!activeContact.value?.id) return;
	if (!window.confirm('Delete this note?')) return;

	try {
		await axios.delete(route('admin.contacts.notes.destroy', [activeContact.value.id, note.id]));
		notes.value = notes.value.filter((row) => row.id !== note.id);
	} catch (err) {
		notesError.value = err?.response?.data?.message || err.message || 'Could not delete note.';
	}
}
</script>

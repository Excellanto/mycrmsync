<template>
	<div>
		<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
			<div>
				<h1 class="text-xl font-semibold">Tag Management</h1>
				<p v-if="tenant" class="mt-1 text-sm text-gray-500">
					{{ tenant.company_name }} · {{ tenant.integration_label }}
				</p>
			</div>
		</div>

		<div
			v-if="page.props.flash?.success"
			class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
		>
			{{ page.props.flash.success }}
		</div>
		<div
			v-if="page.props.flash?.error || loadError"
			class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
		>
			{{ page.props.flash?.error || loadError }}
		</div>

		<div v-if="!tenant" class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 shadow-sm">
			No MyCrmSync, Zoho, or GoHighLevel tenant is configured.
		</div>

		<template v-else>
			<div class="mb-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
				<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
					<div v-if="isMaster && tenants?.length">
						<label class="mb-1 block text-sm font-medium text-gray-700">Company</label>
						<select
							class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
							:value="String(tenant.id)"
							@change="changeTenant"
						>
							<option v-for="company in tenants" :key="company.id" :value="String(company.id)">
								{{ company.company_name }} ({{ company.integration_label }})
							</option>
						</select>
					</div>
					<div v-if="canCreate">
						<label class="mb-1 block text-sm font-medium text-gray-700">New tag</label>
						<form class="flex gap-2" @submit.prevent="createTag">
							<input
								v-model="newName"
								type="text"
								maxlength="100"
								placeholder="Tag name"
								class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
							/>
							<button
								type="submit"
								class="shrink-0 rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700 disabled:opacity-60"
								:disabled="createForm.processing"
							>
								Add
							</button>
						</form>
						<p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
					</div>
				</div>
				<p class="mt-4 text-sm text-gray-500">{{ scopeNote }}</p>
			</div>

			<div class="mb-3">
				<input
					v-model="search"
					type="text"
					placeholder="Search tags..."
					class="w-full max-w-sm rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
				/>
			</div>

			<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
				<table class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-left font-medium text-gray-700">Tag</th>
							<th v-if="showsContactCount" class="px-4 py-3 text-left font-medium text-gray-700">Contacts</th>
							<th class="px-4 py-3 text-right font-medium text-gray-700">Actions</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-if="!filteredTags.length">
							<td class="px-4 py-6 text-gray-500" :colspan="showsContactCount ? 3 : 2">No tags yet.</td>
						</tr>
						<tr v-for="tag in filteredTags" :key="tag.id">
							<td class="px-4 py-3">
								<form v-if="editingId === tag.id" class="flex gap-2" @submit.prevent="saveRename(tag)">
									<input
										v-model="editingName"
										type="text"
										maxlength="100"
										class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-white" :disabled="updateForm.processing">
										Save
									</button>
									<button type="button" class="rounded-lg bg-gray-200 px-3 py-1.5" @click="editingId = null">Cancel</button>
								</form>
								<span v-else>{{ tag.name }}</span>
								<p v-if="editingId === tag.id && updateForm.errors.name" class="mt-1 text-sm text-red-600">
									{{ updateForm.errors.name }}
								</p>
							</td>
							<td v-if="showsContactCount" class="px-4 py-3 text-gray-600">{{ tag.contacts }}</td>
							<td class="px-4 py-3 text-right">
								<div v-if="editingId !== tag.id" class="flex justify-end gap-2">
									<button
										v-if="canUpdate"
										type="button"
										class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50"
										@click="startRename(tag)"
									>
										Rename
									</button>
									<button
										v-if="canDelete"
										type="button"
										class="rounded-lg border border-red-200 px-3 py-1.5 text-red-700 hover:bg-red-50"
										:disabled="deleteForm.processing"
										@click="removeTag(tag)"
									>
										Remove
									</button>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>
	</div>
</template>

<script setup>
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const isMaster = computed(() => page.props.auth?.user?.is_master || false);

const props = defineProps({
	tags: { type: Array, default: () => [] },
	tenant: { type: Object, default: null },
	tenants: { type: Array, default: null },
	loadError: { type: String, default: null },
	canCreate: Boolean,
	canUpdate: Boolean,
	canDelete: Boolean,
});

const search = ref('');
const newName = ref('');
const editingId = ref(null);
const editingName = ref('');

const createForm = useForm({ name: '', tenant_id: '' });
const updateForm = useForm({ id: '', name: '', tenant_id: '' });
const deleteForm = useForm({ id: '', tenant_id: '' });

const showsContactCount = computed(() => props.tenant?.integration_slug === 'mycrmsync');

const scopeNote = computed(() => {
	if (props.tenant?.integration_slug === 'mycrmsync') {
		return 'Renaming or removing a tag updates every contact on this company that uses it.';
	}
	if (props.tenant?.integration_slug === 'zoho') {
		return 'Tags are saved in Zoho. Renaming or removing a tag updates it across Zoho contacts.';
	}
	return 'Tags are saved in GoHighLevel. Renaming or removing a tag updates it across that location.';
});

const filteredTags = computed(() => {
	const query = search.value.trim().toLowerCase();
	if (!query) return props.tags;
	return props.tags.filter((tag) => String(tag.name).toLowerCase().includes(query));
});

function changeTenant(event) {
	router.get('/admin/tags', { tenant_id: event.target.value }, { preserveState: false });
}

function createTag() {
	createForm.name = newName.value;
	createForm.tenant_id = props.tenant?.id || '';
	createForm.post('/admin/tags', {
		preserveScroll: true,
		onSuccess: () => {
			newName.value = '';
			createForm.reset('name');
		},
	});
}

function startRename(tag) {
	editingId.value = tag.id;
	editingName.value = tag.name;
	updateForm.clearErrors();
}

function saveRename(tag) {
	updateForm.id = tag.id;
	updateForm.name = editingName.value;
	updateForm.tenant_id = props.tenant?.id || '';
	updateForm.put('/admin/tags', {
		preserveScroll: true,
		onSuccess: () => {
			editingId.value = null;
		},
	});
}

function removeTag(tag) {
	const detail = showsContactCount.value
		? `Remove "${tag.name}" from this company and from ${tag.contacts} contact(s)?`
		: `Remove "${tag.name}" from ${props.tenant.integration_label}?`;
	if (!window.confirm(detail)) return;
	deleteForm.id = tag.id;
	deleteForm.tenant_id = props.tenant?.id || '';
	deleteForm.delete('/admin/tags', { preserveScroll: true });
}
</script>

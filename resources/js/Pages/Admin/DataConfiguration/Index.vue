<template>
	<div>
		<Head title="Data Configuration" />

		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Data Configuration</h1>
			<p class="mt-1 text-sm text-gray-500">
				Manage integrations, documentation links, and visibility across the system.
			</p>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="grid min-h-[520px] md:grid-cols-[16rem_1fr]">
				<aside class="border-b border-gray-200 bg-gray-50/60 md:border-b-0 md:border-r">
					<button
						type="button"
						class="flex w-full items-center gap-3 border-l-2 border-blue-600 bg-white px-4 py-4 text-left text-sm font-medium text-gray-900"
					>
						<span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-50 text-blue-600">
							<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
								<path
									stroke-linecap="round"
									stroke-linejoin="round"
									stroke-width="2"
									d="M13 10V3L4 14h7v7l9-11h-7z"
								/>
							</svg>
						</span>
						<span>Integrations</span>
					</button>
				</aside>

				<section class="p-6">
					<form @submit.prevent="submit" class="space-y-6">
						<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
							<div>
								<h2 class="text-base font-semibold text-gray-900">Integrations</h2>
								<p class="mt-1 text-sm text-gray-500">
									Set type (CRM, ATS, ERP), documentation links, and visibility. Enabled integrations appear in dropdowns across the system.
								</p>
							</div>
							<button
								type="button"
								class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
								@click="showAdd = !showAdd"
							>
								Add integration
							</button>
						</div>

						<div v-if="showAdd" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 space-y-3">
							<div class="grid gap-3 sm:grid-cols-3">
								<div class="sm:col-span-1">
									<label for="new_name" class="block text-sm font-medium text-gray-700">Name</label>
									<input
										id="new_name"
										v-model="newRow.name"
										type="text"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
										placeholder="Integration name"
										@keydown.enter.prevent="addIntegration"
									/>
								</div>
								<div>
									<label for="new_type" class="block text-sm font-medium text-gray-700">Type</label>
									<select
										id="new_type"
										v-model="newRow.type"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									>
										<option v-for="t in integrationTypes" :key="t" :value="t">{{ t }}</option>
									</select>
								</div>
								<div class="sm:col-span-1">
									<label for="new_docs" class="block text-sm font-medium text-gray-700">Documentation URL</label>
									<input
										id="new_docs"
										v-model="newRow.documentation"
										type="url"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
										placeholder="https://…"
									/>
								</div>
							</div>
							<p v-if="slugFromName(newRow.name)" class="text-xs text-gray-500">
								Slug:
								<span class="font-mono text-gray-700">{{ slugFromName(newRow.name) }}</span>
								<span class="text-gray-400"> (saved automatically)</span>
							</p>
							<div class="flex flex-wrap items-center gap-2">
								<button
									type="button"
									class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
									@click="addIntegration"
								>
									Add
								</button>
								<p v-if="addError" class="text-sm text-red-600">{{ addError }}</p>
							</div>
						</div>

						<div class="overflow-hidden rounded-lg border border-gray-200">
							<table class="min-w-full divide-y divide-gray-200">
								<thead class="bg-gray-50">
									<tr>
										<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
											Show in dropdown
										</th>
										<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
											Name
										</th>
										<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
											Slug
										</th>
										<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
											Type
										</th>
										<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
											Documentation
										</th>
										<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
											Action
										</th>
									</tr>
								</thead>
								<tbody class="divide-y divide-gray-100 bg-white">
									<tr v-for="(row, index) in form.integrations" :key="row.id ?? `tmp-${index}`">
										<td class="px-4 py-3 align-top">
											<input
												v-model="row.enabled"
												type="checkbox"
												class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
											/>
										</td>
										<td class="px-4 py-3 align-top">
											<input
												v-if="!row.is_system"
												v-model="row.name"
												type="text"
												class="block w-full min-w-[10rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
											/>
											<span v-else class="text-sm font-medium text-gray-900">{{ row.name }}</span>
										</td>
										<td class="px-4 py-3 align-top">
											<span class="block min-w-[8rem] font-mono text-sm tabular-nums text-gray-600">
												{{ integrationSlug(row) }}
											</span>
										</td>
										<td class="px-4 py-3 align-top">
											<select
												v-model="row.type"
												class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
											>
												<option v-for="t in integrationTypes" :key="t" :value="t">{{ t }}</option>
											</select>
										</td>
										<td class="px-4 py-3 align-top">
											<input
												v-model="row.documentation"
												type="url"
												class="block w-full min-w-[12rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
												placeholder="https://…"
											/>
										</td>
										<td class="px-4 py-3 text-right align-top">
											<div class="flex flex-wrap items-center justify-end gap-3">
												<button
													type="button"
													class="text-sm font-medium text-primary-600 hover:text-primary-800"
													@click="openFieldsModal(index)"
												>
													Fields
												</button>
												<button
													v-if="!row.is_system"
													type="button"
													class="inline-flex rounded-md p-1.5 text-red-600 hover:bg-red-50 hover:text-red-800"
													title="Remove integration"
													@click="removeRow(index)"
												>
													<span class="sr-only">Remove integration</span>
													<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
														<path
															stroke-linecap="round"
															stroke-linejoin="round"
															stroke-width="2"
															d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
														/>
													</svg>
												</button>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<p v-if="form.errors.integrations" class="text-sm text-red-600">{{ form.errors.integrations }}</p>

						<div class="flex justify-end">
							<button
								type="submit"
								:disabled="form.processing"
								class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
							>
								{{ form.processing ? 'Saving...' : 'Save Configuration' }}
							</button>
						</div>
					</form>
				</section>
			</div>
		</div>

		<PDialog
			:key="fieldsModalDialogKey"
			v-model:visible="fieldsModalVisible"
			modal
			:header="fieldsModalHeader"
			:style="{ width: 'min(100vw - 2rem, 28rem)' }"
			:breakpoints="{ '576px': '95vw' }"
		>
			<p class="mb-4 text-sm text-gray-600">
				Add credential or ID labels for tenants to fill in. Toggle <span class="font-medium">Optional</span> when a
				value is not needed to save the integration.
			</p>
			<ul class="mb-4 space-y-2">
				<li
					v-for="(field, fi) in draftFields"
					:key="fi"
					class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2"
				>
					<span class="min-w-0 flex-1 text-sm text-gray-900">{{ field.label }}</span>
					<label class="flex cursor-pointer items-center gap-2 text-xs text-gray-700">
						<input
							v-model="field.optional"
							type="checkbox"
							class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
						/>
						Optional
					</label>
					<button
						type="button"
						class="rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-800"
						title="Remove field"
						@click="removeDraftField(fi)"
					>
						<span class="sr-only">Remove field</span>
						<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>
				</li>
				<li v-if="draftFields.length === 0" class="rounded-lg border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-500">
					No fields yet.
				</li>
			</ul>
			<div class="flex flex-col gap-2 sm:flex-row sm:items-end">
				<div class="flex-1">
					<label for="new_field_label" class="block text-sm font-medium text-gray-700">Field label</label>
					<input
						id="new_field_label"
						v-model="newFieldLabel"
						type="text"
						class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
						placeholder="e.g. Api Key"
						@keydown.enter.prevent="addDraftField"
					/>
				</div>
				<label
					class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:h-[42px] sm:shrink-0"
				>
					<input
						v-model="newFieldOptional"
						type="checkbox"
						class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
					/>
					Optional
				</label>
				<button
					type="button"
					class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:shrink-0"
					@click="addDraftField"
				>
					Add field
				</button>
			</div>
			<p v-if="fieldsModalError" class="mt-2 text-sm text-red-600">{{ fieldsModalError }}</p>
			<template #footer>
				<div class="flex flex-wrap justify-end gap-2">
					<button
						type="button"
						class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
						:disabled="savingFields"
						@click="closeFieldsModal"
					>
						Cancel
					</button>
					<button
						type="button"
						class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
						:disabled="savingFields"
						@click="applyFieldsModal"
					>
						{{ savingFields ? 'Saving…' : 'Done' }}
					</button>
				</div>
			</template>
		</PDialog>
	</div>
</template>

<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref } from 'vue';

const props = defineProps({
	integrations: {
		type: Array,
		default: () => [],
	},
	integrationTypes: {
		type: Array,
		default: () => ['CRM', 'ATS', 'ERP'],
	},
});

/** Parse trailing "(optional)" the way the server stores legacy labels. */
function parseIntegrationFieldLabelString(text) {
	const trimmed = String(text).trim().replace(/\s+/g, ' ');
	if (!trimmed) {
		return null;
	}
	const optional = /\(optional\)\s*$/i.test(trimmed);
	const label = optional ? trimmed.replace(/\s*\(optional\)\s*$/i, '').trim() : trimmed;

	return label ? { label, optional } : null;
}

/** Normalize integration field definitions ({ label, optional } or legacy label strings). */
function normalizeFieldsValue(raw) {
	if (raw == null) {
		return [];
	}

	if (typeof raw === 'string') {
		const s = raw.trim();
		if (!s) {
			return [];
		}
		if (s.startsWith('[') || s.startsWith('{')) {
			try {
				return normalizeFieldsValue(JSON.parse(s));
			} catch {
				/**/
			}
		}

		const one = parseIntegrationFieldLabelString(s);

		return one ? [one] : [];
	}

	if (Array.isArray(raw)) {
		const merged = [];
		for (const item of raw) {
			if (typeof item === 'string') {
				const parsed = parseIntegrationFieldLabelString(item);

				if (parsed) {
					merged.push(parsed);
				}
				continue;
			}

			if (item && typeof item === 'object' && item.label != null) {
				const slug = parseIntegrationFieldLabelString(String(item.label).trim().replace(/\s+/g, ' '));

				if (!slug) {
					continue;
				}

				const optional = Boolean(item.optional) || slug.optional;
				merged.push({ label: slug.label, optional });
			}
		}

		const seen = new Set();

		return merged.filter((entry) => {
			const key = entry.label.toLowerCase();

			if (seen.has(key)) {
				return false;
			}

			seen.add(key);

			return true;
		});
	}

	if (typeof raw === 'object') {
		return normalizeFieldsValue(Object.values(raw));
	}

	return [];
}

const page = usePage();

function mapIntegrationRow(row) {
	return {
		id: row.id,
		name: row.name,
		type: row.type,
		documentation: row.documentation ?? '',
		fields: normalizeFieldsValue(row.fields),
		enabled: Boolean(row.enabled),
		is_system: Boolean(row.is_system),
	};
}

function syncIntegrationsFormFromProps() {
	form.integrations = (page.props.integrations ?? []).map(mapIntegrationRow);
}

const form = useForm({
	integrations: props.integrations.map(mapIntegrationRow),
});

const showAdd = ref(false);
const addError = ref('');
const newRow = reactive({
	name: '',
	type: 'CRM',
	documentation: '',
});

const fieldsModalVisible = ref(false);
const fieldsModalIndex = ref(null);
const fieldsModalDialogKey = ref(0);
const fieldsModalHeader = computed(() => {
	const idx = fieldsModalIndex.value;
	if (idx === null || !form.integrations[idx]) {
		return 'Required fields';
	}
	return `Required fields — ${form.integrations[idx].name}`;
});
const draftFields = ref([]);
const newFieldLabel = ref('');
const newFieldOptional = ref(false);
const fieldsModalError = ref('');
const savingFields = ref(false);

async function openFieldsModal(index) {
	fieldsModalIndex.value = index;
	const row = form.integrations[index];
	const fromServer =
		row?.id != null ? props.integrations.find((r) => Number(r.id) === Number(row.id)) : null;

	const merged =
		row?.id != null && fromServer
			? normalizeFieldsValue(fromServer.fields)
			: normalizeFieldsValue(row?.fields);

	draftFields.value = merged.map((f) => ({ label: f.label, optional: Boolean(f.optional) }));

	if (row?.id != null && fromServer) {
		form.integrations[index].fields = draftFields.value.map((f) => ({ ...f }));
	}

	newFieldLabel.value = '';
	newFieldOptional.value = false;
	fieldsModalError.value = '';

	fieldsModalDialogKey.value += 1;
	await nextTick();
	fieldsModalVisible.value = true;
}

function closeFieldsModal() {
	fieldsModalVisible.value = false;
	fieldsModalIndex.value = null;
	draftFields.value = [];
	newFieldLabel.value = '';
	newFieldOptional.value = false;
	fieldsModalError.value = '';
}

function addDraftField() {
	fieldsModalError.value = '';
	const raw = normalizeName(newFieldLabel.value);

	if (!raw) {
		fieldsModalError.value = 'Enter a field label.';
		return;
	}

	const parsed = parseIntegrationFieldLabelString(raw);

	if (!parsed) {
		fieldsModalError.value = 'Enter a field label.';
		return;
	}

	const optional = Boolean(newFieldOptional.value) || parsed.optional;

	if (draftFields.value.some((f) => f.label.toLowerCase() === parsed.label.toLowerCase())) {
		fieldsModalError.value = 'That field is already listed.';
		return;
	}

	draftFields.value.push({ label: parsed.label, optional });
	newFieldLabel.value = '';
	newFieldOptional.value = false;
}

function removeDraftField(index) {
	draftFields.value.splice(index, 1);
}

function applyFieldsModal() {
	fieldsModalError.value = '';
	const idx = fieldsModalIndex.value;

	if (idx === null || !form.integrations[idx]) {
		closeFieldsModal();
		return;
	}

	const row = form.integrations[idx];
	const fields = draftFields.value.map((f) => ({
		label: f.label,
		optional: Boolean(f.optional),
	}));

	if (row.id == null) {
		row.fields = fields;
		closeFieldsModal();
		return;
	}

	savingFields.value = true;
	router.patch(
		route('admin.data-configuration.integration-fields.update', row.id),
		{ fields },
		{
			preserveScroll: true,
			preserveState: true,
			only: ['integrations'],
			onSuccess: () => {
				const updated = (page.props.integrations ?? []).find((r) => Number(r.id) === Number(row.id));
				if (updated) {
					form.integrations[idx].fields = normalizeFieldsValue(updated.fields);
				}
				closeFieldsModal();
			},
			onError: (errors) => {
				const first =
					errors.fields ?? errors['fields.0'] ?? errors['fields.0.label'] ?? errors['fields.0.optional'];
				fieldsModalError.value =
					(Array.isArray(first) ? first[0] : first) ||
					Object.values(errors).flat()[0] ||
					'Could not save required fields.';
			},
			onFinish: () => {
				savingFields.value = false;
			},
		}
	);
}

function normalizeName(name) {
	return String(name).trim().replace(/\s+/g, ' ');
}

/** Preview slug from current name (Latin-only approximation of Laravel Str::slug). */
function slugFromName(name) {
	const squished = normalizeName(name);
	if (!squished) {
		return '';
	}
	return squished
		.toLowerCase()
		.normalize('NFKD')
		.replace(/[\u0300-\u036f]/g, '')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

/** Saved slug when name matches server; otherwise preview from edited name. */
function integrationSlug(row) {
	const preview = slugFromName(row.name);
	const serverRow =
		row?.id != null ? props.integrations.find((r) => Number(r.id) === Number(row.id)) : null;

	if (
		serverRow?.slug &&
		normalizeName(row.name).toLowerCase() === normalizeName(serverRow.name).toLowerCase()
	) {
		return serverRow.slug;
	}

	return preview || '—';
}

function addIntegration() {
	const name = normalizeName(newRow.name);
	addError.value = '';

	if (!name) {
		addError.value = 'Enter a name.';
		return;
	}

	const exists = form.integrations.some((r) => r.name.toLowerCase() === name.toLowerCase());

	if (exists) {
		addError.value = 'This name is already in the list.';
		return;
	}

	form.integrations.push({
		id: null,
		name,
		type: newRow.type || 'CRM',
		documentation: newRow.documentation ? String(newRow.documentation).trim() : '',
		fields: [],
		enabled: true,
		is_system: false,
	});

	newRow.name = '';
	newRow.type = 'CRM';
	newRow.documentation = '';
	showAdd.value = false;
}

function removeRow(index) {
	form.integrations.splice(index, 1);
}

function submit() {
	form.put(route('admin.data-configuration.update'), {
		preserveScroll: true,
		onSuccess: () => {
			syncIntegrationsFormFromProps();
		},
	});
}
</script>

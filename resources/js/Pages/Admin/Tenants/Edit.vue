<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import { axios } from '@/bootstrap';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
	tenant: {
		type: Object,
		required: true,
	},
	roles: {
		type: Array,
		default: () => [],
	},
	integrationOptions: {
		type: Array,
		default: () => [],
	},
	zohoOAuthCallbackAbsoluteUrl: {
		type: String,
		default: '',
	},
});

const ZOHO_INTEGRATION_SLUG = 'zoho';
const GOHIGHLEVEL_INTEGRATION_SLUG = 'gohighlevel';
const MYCRMSYNC_INTEGRATION_SLUG = 'mycrmsync';

/** Page reload / OAuth redirects use shared Laravel errors flash. */
const page = usePage();
const toast = useToast();

const INTEGRATION_INCOMPLETE_TOAST = 'Integration is incomplete';

const isTenantIntegrationIncomplete = computed(() => {
	const s = props.tenant?.integration_status;
	const ok = s === true || s === 1 || s === '1';
	return !ok;
});

const integrationOAuthFlashError = computed(() => {
	const e = page.props.errors ?? {};

	for (const k of ['integration.oauth', 'integration.values']) {
		const val = e[k];
		if (val == null) {
			continue;
		}

		const first = Array.isArray(val) ? val[0] : val;

		if (typeof first === 'string' && first !== '') {
			return first;
		}
	}

	return null;
});

const CRM_USERS_INTEGRATION_SLUGS = ['gohighlevel', 'zoho'];

function normalizeTenantIntegration(raw) {
	if (!raw || typeof raw !== 'object') {
		return { slug: '', values: {} };
	}
	const slug = raw.slug != null ? String(raw.slug) : '';
	const base = raw.values;
	const values =
		base && typeof base === 'object' && !Array.isArray(base)
			? Object.fromEntries(Object.entries(base).map(([k, v]) => [k, v != null ? String(v) : '']))
			: {};
	return { slug, values };
}

// Tenant profile form
const form = useForm({
	company_name: props.tenant.company_name ?? '',
	account_type: props.tenant.account_type ?? 'Business',
	pan_card: props.tenant.pan_card ?? '',
	gst_number: props.tenant.gst_number ?? '',
	email: props.tenant.email ?? '',
	status: props.tenant.status ?? 'active',
	integration: normalizeTenantIntegration(props.tenant.integration),
	edit_tab: null,
});

const selectedIntegration = computed(() =>
	props.integrationOptions.find((i) => i.slug === form.integration.slug),
);

/** Align value keys with the definition for the chosen integration slug. */
function syncIntegrationFields() {
	const slug = form.integration.slug;
	const opt = props.integrationOptions.find((i) => i.slug === slug);
	const prev = form.integration.values;
	const next = {};
	if (opt) {
		for (const f of opt.fields) {
			next[f.key] = Object.prototype.hasOwnProperty.call(prev, f.key)
				? String(prev[f.key] ?? '')
				: '';
		}
	}
	form.integration.values = next;
}

// Tabs — URL query `tab`: profile | integration | manage-users (default Profile has no query)
const tabs = ['Profile', 'Integration', 'Manage Users'];

const TAB_QUERY_BY_LABEL = {
	Profile: null,
	Integration: 'integration',
	'Manage Users': 'manage-users',
};

const LABEL_BY_TAB_QUERY = {
	profile: 'Profile',
	integration: 'Integration',
	'manage-users': 'Manage Users',
};

function tabLabelFromPageUrl(url) {
	if (!url || typeof url !== 'string') {
		return 'Profile';
	}
	try {
		const q = url.includes('?') ? url.split('?')[1].split('#')[0] : '';
		const raw = new URLSearchParams(q).get('tab');
		const key = raw == null ? '' : String(raw).trim().toLowerCase().replace(/_/g, '-');
		return LABEL_BY_TAB_QUERY[key] || 'Profile';
	} catch {
		return 'Profile';
	}
}

const activeTab = ref(tabLabelFromPageUrl(page.url));

function selectTab(t) {
	if (!tabs.includes(t)) {
		return;
	}
	if (activeTab.value === t) {
		return;
	}
	activeTab.value = t;
	const q = TAB_QUERY_BY_LABEL[t];
	const params = q ? { tab: q } : {};
	router.get(route('admin.tenants.edit', props.tenant.id), params, {
		preserveState: true,
		preserveScroll: true,
	});
}

watch(
	() => page.url,
	(url) => {
		const next = tabLabelFromPageUrl(url);
		if (next !== activeTab.value) {
			activeTab.value = next;
		}
	},
);

onMounted(() => {
	syncIntegrationFields();
});

/** Saved integration — drives CRM sidebar (persisted tenant record, not unsaved edits). */
const persistedIntegrationSlug = computed(() => {
	const raw = props.tenant.integration;
	if (!raw || typeof raw !== 'object') {
		return '';
	}
	return raw.slug != null ? String(raw.slug) : '';
});

const showCrmUsersPanel = computed(() =>
	CRM_USERS_INTEGRATION_SLUGS.includes(persistedIntegrationSlug.value),
);

const isMyCrmSyncIntegration = computed(
	() => form.integration.slug === MYCRMSYNC_INTEGRATION_SLUG
		|| persistedIntegrationSlug.value === MYCRMSYNC_INTEGRATION_SLUG,
);

const crmUsers = ref([]);
const crmUsersLoading = ref(false);
const crmUsersError = ref('');
const crmUsersBanner = ref('');

/** Same promise for overlapping calls (tab watcher + tenant.integration watcher + submit). */
let crmUsersFetchInFlight = null;

async function fetchCrmUsersFromPersistedIntegration() {
	if (crmUsersFetchInFlight) {
		return crmUsersFetchInFlight;
	}

	crmUsersFetchInFlight = (async () => {
		try {
			crmUsersError.value = '';
			crmUsersBanner.value = '';
			crmUsers.value = [];

			if (!showCrmUsersPanel.value) {
				return;
			}

			crmUsersLoading.value = true;

			try {
				const reqUrl = route('admin.tenants.integration-crm-users', props.tenant.id);
				if (import.meta.env.DEV) {
					console.info('[CRM users] GET', reqUrl);
				}
				const { data } = await axios.get(reqUrl);
				crmUsers.value = Array.isArray(data.users) ? data.users : [];
				const hint = typeof data.message === 'string' ? data.message : '';
				if (hint !== '') {
					crmUsersBanner.value = hint;
				}
				if (import.meta.env.DEV) {
					console.info('[CRM users] response', { count: crmUsers.value.length, message: hint || null });
				}
			} catch (err) {
				const d = err?.response?.data;
				const fallback = err?.message || 'Could not load CRM users.';
				const msg = typeof d?.message === 'string' ? d.message : fallback;
				crmUsersError.value = msg;
				crmUsers.value = [];
			} finally {
				crmUsersLoading.value = false;
			}
		} finally {
			crmUsersFetchInFlight = null;
		}
	})();

	return crmUsersFetchInFlight;
}

watch(activeTab, (t) => {
	if (t === 'Manage Users') {
		void refreshTenantIntegrationPickers(null);
	}
});

watch(
	[activeTab, () => props.tenant?.integration],
	() => {
		if (activeTab.value !== 'Integration') {
			return;
		}
		void fetchCrmUsersFromPersistedIntegration();
	},
	{ deep: true },
);

const submitProfile = () => {
	form.edit_tab = null;
	form.put(route('admin.tenants.update', props.tenant.id), {
		preserveScroll: true,
	});
};

/** GoHighLevel: save, stay on Integration tab, then pull CRM users. Zoho redirects to OAuth. */
const submitIntegrationTab = () => {
	const slug = form.integration.slug;
	form.edit_tab = slug === GOHIGHLEVEL_INTEGRATION_SLUG ? 'integration' : null;

	form.put(route('admin.tenants.update', props.tenant.id), {
		preserveScroll: true,
		onSuccess: () => {
			if (slug === ZOHO_INTEGRATION_SLUG) {
				window.location.assign(route('admin.tenants.integrations.zoho.oauth.start', props.tenant.id));
				return;
			}
			if (slug === GOHIGHLEVEL_INTEGRATION_SLUG) {
				void nextTick(() => {
					void fetchCrmUsersFromPersistedIntegration();
				});
			}
			if (slug === MYCRMSYNC_INTEGRATION_SLUG) {
				crmUsersBanner.value = 'MyCrmSync uses local MysimConnect users for contact management. No external credentials required.';
			}
		},
		onFinish: () => {
			form.edit_tab = null;
		},
	});
};

// Users management — always derived from Inertia props so reloads refresh the table
const users = computed(() => props.tenant.users ?? []);

const integrationApiUsers = ref([]);
const integrationUnmappedOptions = ref([]);
const integrationPickerLoading = ref(false);
const integrationPickerLoadError = ref('');

async function refreshTenantIntegrationPickers(forUserId) {
	integrationPickerLoadError.value = '';
	integrationPickerLoading.value = true;
	try {
		const params = { tenant_id: props.tenant.id };
		if (forUserId != null && forUserId !== '') {
			params.for_user_id = forUserId;
		}
		const { data } = await axios.get(route('admin.users.integration-external-options'), { params });
		integrationApiUsers.value = Array.isArray(data?.users) ? data.users : [];
		integrationUnmappedOptions.value = Array.isArray(data?.unmapped_options) ? data.unmapped_options : [];
	} catch (err) {
		const d = err?.response?.data;
		const msg =
			d && typeof d === 'object' && typeof d.message === 'string' ? d.message : err.message || 'Could not load integration users.';
		integrationPickerLoadError.value = msg;
		integrationApiUsers.value = [];
		integrationUnmappedOptions.value = [];
	} finally {
		integrationPickerLoading.value = false;
	}
}

const modalIntegratedOptions = computed(() => {
	const opts = [...integrationUnmappedOptions.value];

	if (userModalMode.value !== 'edit' || editingUserId.value == null) {
		return opts;
	}

	const tu = props.tenant.users?.find((x) => Number(x.id) === Number(editingUserId.value));
	const raw = tu?.intsysuser;
	if (raw == null || String(raw) === '') {
		return opts;
	}

	const sid = String(raw);
	if (opts.some((o) => o.id === sid)) {
		return opts;
	}

	return [...opts, { id: sid, label: `${sid} (current)` }];
});

const showUserModal = ref(false);
const userModalMode = ref('add'); // 'add' | 'edit'
const editingUserId = ref(null);

const userForm = useForm({
	name: '',
	email: '',
	intsysuser: '',
	role: '',
	roles: [],
	tenant_id: props.tenant.id,
});

const userIntegratedError = ref('');

function mappedUserLabel(u) {
	const id = u?.intsysuser;
	if (id == null || id === '') {
		return '—';
	}
	const sid = String(id);
	const hit = integrationApiUsers.value.find((o) => o.id === sid);
	return hit?.label ?? sid;
}

function resolveUserRoleSlug(u) {
	const r = u.roles?.[0];
	if (!r) return '';
	if (r.slug) return r.slug;
	const match = props.roles.find((pr) => pr.name === r.name);
	return match?.slug ?? '';
}

const refreshTenantUsers = () => {
	router.reload({
		only: ['tenant'],
		preserveScroll: true,
	});
};

const closeUserModal = () => {
	showUserModal.value = false;
	userModalMode.value = 'add';
	editingUserId.value = null;
	refreshTenantUsers();
};

const openAddUser = () => {
	if (isTenantIntegrationIncomplete.value) {
		toast.add({
			severity: 'warn',
			summary: INTEGRATION_INCOMPLETE_TOAST,
			life: 4500,
		});
		return;
	}
	userModalMode.value = 'add';
	editingUserId.value = null;
	showUserModal.value = true;
	userForm.clearErrors();
	userIntegratedError.value = '';
	userForm.name = '';
	userForm.email = '';
	userForm.intsysuser = '';
	userForm.role = '';
	userForm.roles = [];
	userForm.tenant_id = props.tenant.id;
	void refreshTenantIntegrationPickers(null);
};

const openEditUser = (u) => {
	userModalMode.value = 'edit';
	editingUserId.value = u.id;
	showUserModal.value = true;
	userForm.clearErrors();
	userIntegratedError.value = '';
	userForm.name = u.name ?? '';
	userForm.email = u.email ?? '';
	userForm.intsysuser = u.intsysuser != null ? String(u.intsysuser) : '';
	userForm.role = resolveUserRoleSlug(u);
	userForm.roles = userForm.role ? [userForm.role] : [];
	userForm.tenant_id = props.tenant.id;
	void refreshTenantIntegrationPickers(u.id);
};

const submitUserModal = () => {
	userIntegratedError.value = '';

	userForm.name = String(userForm.name).trim();
	userForm.email = String(userForm.email).trim();
	userForm.roles = userForm.role ? [userForm.role] : [];

	if (modalIntegratedOptions.value.length && !String(userForm.intsysuser ?? '').trim()) {
		userIntegratedError.value = 'Integrated system user is required.';
		return;
	}

	if (userModalMode.value === 'add') {
		if (isTenantIntegrationIncomplete.value) {
			toast.add({
				severity: 'warn',
				summary: INTEGRATION_INCOMPLETE_TOAST,
				life: 4500,
			});
			return;
		}
		userForm.tenant_id = props.tenant.id;
		userForm.post(route('admin.users.store'), {
			preserveScroll: true,
			onSuccess: () => {
				showUserModal.value = false;
				userModalMode.value = 'add';
				editingUserId.value = null;
				refreshTenantUsers();
			},
		});
		return;
	}

	userForm.put(route('admin.users.update', editingUserId.value), {
		preserveScroll: true,
		onSuccess: () => {
			showUserModal.value = false;
			userModalMode.value = 'add';
			editingUserId.value = null;
			refreshTenantUsers();
		},
	});
};

const deleteUser = (u) => {
	if (!confirm(`Delete user "${u.name}"? This cannot be undone.`)) {
		return;
	}
	router.delete(route('admin.users.destroy', u.id), {
		preserveScroll: true,
		onSuccess: () => {
			refreshTenantUsers();
		},
	});
};
</script>

<template>
	<div>
		<Head title="Edit Tenant" />

		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Edit Tenant</h1>
		</div>

		<p
			v-if="page.props.flash?.success"
			class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"
		>
			{{ page.props.flash.success }}
		</p>

		<p
			v-if="integrationOAuthFlashError"
			class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
		>
			{{ integrationOAuthFlashError }}
		</p>

		<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
			<!-- Tabs -->
			<nav class="mb-4 flex gap-2">
				<button
					v-for="t in tabs"
					:key="t"
					@click="selectTab(t)"
					:class="['px-3 py-2 rounded-md text-sm', { 'bg-primary-600 text-white': activeTab === t, 'text-gray-700': activeTab !== t }]"
				>
					{{ t }}
				</button>
			</nav>

			<!-- Profile tab -->
			<section v-show="activeTab === 'Profile'">
				<div class="p-4">
					<form @submit.prevent="submitProfile" class="space-y-4">
						<div>
							<InputLabel for="company_name" value="Company Name" />
							<TextInput
								id="company_name"
								v-model="form.company_name"
								type="text"
								class="mt-1 block w-full"
								required
							/>
							<InputError class="mt-2" :message="form.errors.company_name" />
						</div>

						<div>
							<InputLabel for="account_type" value="Account Type" />
							<select
								id="account_type"
								v-model="form.account_type"
								class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
								required
							>
								<option value="Business">Business</option>
								<option value="Recruiter">Recruiter</option>
							</select>
							<InputError class="mt-2" :message="form.errors.account_type" />
						</div>

						<div>
							<InputLabel for="pan_card" value="PAN Card (Optional)" />
							<TextInput
								id="pan_card"
								v-model="form.pan_card"
								type="text"
								class="mt-1 block w-full"
								placeholder="ABCDE1234F"
								maxlength="10"
							/>
							<InputError class="mt-2" :message="form.errors.pan_card" />
						</div>

						<div>
							<InputLabel for="gst_number" value="GST Number (Optional)" />
							<TextInput
								id="gst_number"
								v-model="form.gst_number"
								type="text"
								class="mt-1 block w-full"
								placeholder="22ABCDE1234F1Z5"
								maxlength="15"
							/>
							<InputError class="mt-2" :message="form.errors.gst_number" />
						</div>

						<div>
							<InputLabel for="email" value="Email" />
							<TextInput
								id="email"
								v-model="form.email"
								type="email"
								class="mt-1 block w-full"
								required
							/>
							<InputError class="mt-2" :message="form.errors.email" />
						</div>

						<div>
							<InputLabel for="status" value="Status" />
							<select
								id="status"
								v-model="form.status"
								class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
								required
							>
								<option value="active">active</option>
								<option value="inactive">inactive</option>
								<option value="suspended">suspended</option>
							</select>
							<InputError class="mt-2" :message="form.errors.status" />
						</div>

						<div class="flex items-center gap-3 pt-4">
							<PrimaryButton
								type="submit"
								:class="{ 'opacity-25': form.processing }"
								:disabled="form.processing"
							>
								Save Changes
							</PrimaryButton>
							<Link
								:href="route('admin.tenants.index')"
								class="text-sm text-gray-600 underline hover:text-gray-900"
							>
								Cancel
							</Link>
						</div>
					</form>
				</div>
			</section>

			<!-- Integration tab -->
			<section v-show="activeTab === 'Integration'">
				<div class="p-4">
					<div class="grid gap-8 lg:grid-cols-2 lg:items-start lg:gap-10">
						<!-- Left: credential form -->
						<div class="min-w-0">
							<form @submit.prevent="submitIntegrationTab" class="max-w-xl space-y-4">
								<p class="text-sm text-gray-600">
									Choose a CRM or other integration from Data Configuration. Required fields are defined per integration.
								</p>

								<div
									v-if="form.integration.slug === ZOHO_INTEGRATION_SLUG && zohoOAuthCallbackAbsoluteUrl"
									class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
								>
									<p class="font-medium text-amber-900">Zoho OAuth callback URL</p>
									<p class="mt-1 text-amber-900/90">
										Add this <strong class="font-semibold">Authorized Redirect URI</strong> in your Zoho API Console
										client settings (exact match):
									</p>
									<code class="mt-2 block break-all rounded bg-white/80 px-2 py-1 text-xs">{{
										zohoOAuthCallbackAbsoluteUrl
									}}</code>
									<p class="mt-2 text-xs text-amber-900/85">
										After you click &quot;Save integration&quot;, your browser opens Zoho sign-in; when you approve,
										tokens are saved on this tenant automatically.
									</p>
								</div>

								<div>
									<InputLabel for="integration_slug" value="Integration" />
									<select
										id="integration_slug"
										v-model="form.integration.slug"
										class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
										@change="syncIntegrationFields"
									>
										<option value="">No integration</option>
										<option v-for="integ in props.integrationOptions" :key="integ.id" :value="integ.slug">
											{{ integ.name }}{{ integ.type ? ` (${integ.type})` : '' }}
										</option>
									</select>
									<InputError class="mt-2" :message="form.errors['integration.slug']" />
								</div>

								<div v-if="selectedIntegration && (selectedIntegration.fields?.length ?? 0) > 0" class="space-y-4">
									<div v-for="f in selectedIntegration.fields" :key="f.key">
										<InputLabel
											:for="'integration_' + f.key"
											:value="f.optional ? `${f.label} (optional)` : f.label"
										/>
										<TextInput
											:id="'integration_' + f.key"
											v-model="form.integration.values[f.key]"
											type="text"
											class="mt-1 block w-full"
											autocomplete="off"
										/>
										<InputError class="mt-2" :message="form.errors['integration.values.' + f.key]" />
									</div>
								</div>

								<div
									v-else-if="selectedIntegration && isMyCrmSyncIntegration"
									class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
								>
									MyCrmSync stores contacts locally in MysimConnect. No external API credentials are required.
								</div>

								<div
									v-else-if="selectedIntegration"
									class="rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
								>
									This integration has no required fields configured. Saving will store the choice only.
								</div>

								<div class="flex flex-wrap items-center gap-3 pt-4">
									<PrimaryButton
										type="submit"
										:class="{ 'opacity-25': form.processing }"
										:disabled="form.processing"
									>
										Save integration
									</PrimaryButton>
									<Link
										:href="route('admin.tenants.index')"
										class="text-sm text-gray-600 underline hover:text-gray-900"
									>
										Cancel
									</Link>
								</div>
							</form>
						</div>

						<!-- Right: integrated system users (connectors with CRM user listing) -->
						<div
							class="min-w-0 border-t border-gray-200 pt-8 lg:border-l lg:border-t-0 lg:border-gray-200 lg:pl-8 lg:pt-0"
						>
							<div class="flex flex-wrap items-start justify-between gap-3">
								<div>
									<h2 class="text-sm font-semibold text-gray-900">Integrated System Users</h2>
								</div>
								<button
									v-if="showCrmUsersPanel"
									type="button"
									class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40"
									:disabled="crmUsersLoading"
									@click="fetchCrmUsersFromPersistedIntegration"
								>
									Refresh
								</button>
							</div>

							<p
								v-if="!persistedIntegrationSlug && !crmUsersLoading"
								class="mt-4 rounded-md border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
							>
								Save an integration that supports user listing (e.g. GoHighLevel or Zoho) to load external CRM
								users here.
							</p>

							<p
								v-else-if="
									persistedIntegrationSlug && !showCrmUsersPanel && !crmUsersLoading
								"
								class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
							>
								Integrated System isn&apos;t wired yet. Users cannot be loaded.
							</p>

							<p v-if="crmUsersLoading" class="mt-4 text-sm text-gray-500">Loading CRM users…</p>
							<p v-if="crmUsersError" class="mt-4 text-sm text-red-600">{{ crmUsersError }}</p>

							<p
								v-else-if="crmUsersBanner !== '' && !crmUsersError"
								class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200/60"
							>
								{{ crmUsersBanner }}
							</p>

							<div
								v-if="showCrmUsersPanel && crmUsers.length > 0"
								class="mt-4 overflow-hidden rounded-lg border border-gray-200"
							>
								<table class="w-full divide-y divide-gray-100 text-left text-sm">
									<thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
										<tr>
											<th class="px-3 py-2">Name</th>
											<th class="px-3 py-2">Email</th>
											<th class="hidden sm:table-cell px-3 py-2">Role</th>
											<th class="hidden md:table-cell px-3 py-2">Phone</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-50 bg-white">
										<tr v-for="cu in crmUsers" :key="cu.id + '-' + cu.email">
											<td class="max-w-[10rem] truncate px-3 py-2 font-medium text-gray-900">{{ cu.name }}</td>
											<td class="max-w-[14rem] truncate px-3 py-2 text-gray-700">{{ cu.email }}</td>
											<td class="hidden max-w-[12rem] truncate px-3 py-2 text-gray-600 sm:table-cell">{{ cu.role || '—' }}</td>
											<td class="hidden px-3 py-2 text-gray-600 md:table-cell">{{ cu.phone || '—' }}</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- Manage Users tab -->
			<section v-show="activeTab === 'Manage Users'">
				<div class="p-4">
					<div class="flex items-center justify-between mb-4">
						<h2 class="text-lg font-semibold">Users</h2>
						<PrimaryButton type="button" @click="openAddUser">Add User</PrimaryButton>
					</div>

					<table class="w-full text-left border-collapse">
						<thead>
							<tr class="text-sm text-gray-600">
								<th class="pb-2">Name</th>
								<th class="pb-2">Email</th>
								<th class="pb-2">Mapped User</th>
								<th class="pb-2">Roles</th>
								<th class="pb-2 w-28">Actions</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="u in users" :key="u.id" class="border-t">
								<td class="py-2">{{ u.name }}</td>
								<td class="py-2">{{ u.email }}</td>
								<td class="py-2 text-sm text-gray-600">{{ mappedUserLabel(u) }}</td>
								<td class="py-2 text-sm text-gray-600">
									{{ (u.roles || []).map(r => r.name).join(', ') }}
								</td>
								<td class="py-2">
									<div class="flex items-center gap-1">
										<PButton
											icon="pi pi-pencil"
											rounded
											text
											severity="secondary"
											title="Edit user"
											@click="openEditUser(u)"
										/>
										<PButton
											icon="pi pi-trash"
											rounded
											text
											severity="danger"
											title="Delete user"
											@click="deleteUser(u)"
										/>
									</div>
								</td>
							</tr>
							<tr v-if="users.length === 0">
								<td colspan="5" class="py-4 text-sm text-gray-500">No users for this tenant.</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<!-- Add / Edit User modal — backdrop click closes and refreshes user list -->
		<div
			v-if="showUserModal"
			class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
			@click.self="closeUserModal"
		>
			<div class="rounded-lg bg-white p-6 shadow-lg" style="width: 60vw; max-width: 900px;" @click.stop>
				<h3 class="mb-4 text-lg font-semibold">{{ userModalMode === 'add' ? 'Add User' : 'Edit User' }}</h3>
				<form @submit.prevent="submitUserModal" class="space-y-3">
					<div>
						<InputLabel for="name" value="Name" />
						<TextInput
							id="name"
							v-model="userForm.name"
							type="text"
							class="mt-1 block w-full"
							required
							minlength="3"
						/>
						<InputError class="mt-2" :message="userForm.errors.name" />
					</div>
					<div>
						<InputLabel for="email" value="Email" />
						<TextInput id="email" v-model="userForm.email" type="email" class="mt-1 block w-full" required />
						<InputError class="mt-2" :message="userForm.errors.email" />
					</div>
					<div>
						<label for="intsysuser" class="block text-sm font-medium text-gray-700">
							Integrated System User <span class="text-red-600">*</span>
						</label>
						<select
							id="intsysuser"
							v-model="userForm.intsysuser"
							:required="modalIntegratedOptions.length > 0 && !integrationPickerLoading"
							:disabled="integrationPickerLoading || modalIntegratedOptions.length === 0"
							class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-100"
						>
							<option value="" disabled>
								{{
									integrationPickerLoading
										? 'Loading integration users…'
										: modalIntegratedOptions.length
											? 'Select integrated user'
											: 'No unmapped users available'
								}}
							</option>
							<option v-for="opt in modalIntegratedOptions" :key="opt.id" :value="opt.id">
								{{ opt.label }}
							</option>
						</select>
						<InputError class="mt-2" :message="userIntegratedError" />
						<InputError class="mt-2" :message="userForm.errors.intsysuser" />
						<p v-if="integrationPickerLoadError" class="mt-1 text-xs text-red-600">{{ integrationPickerLoadError }}</p>
						<p
							v-else-if="!integrationPickerLoading && modalIntegratedOptions.length === 0"
							class="mt-1 text-xs text-gray-500"
						>
							Configure CRM integration for this tenant, or all integration users may already be linked to a local
							user.
						</p>
					</div>
					<div>
						<InputLabel for="role" value="Role" />
						<select
							id="role"
							v-model="userForm.role"
							required
							class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
						>
							<option value="" disabled>Select a role</option>
							<option v-for="r in props.roles" :key="r.id" :value="r.slug">{{ r.name }}</option>
						</select>
						<InputError class="mt-2" :message="userForm.errors.roles" />
					</div>
					<div class="flex items-center justify-end gap-2 pt-2">
						<button type="button" class="text-sm text-gray-600 underline" @click="closeUserModal">Cancel</button>
						<PrimaryButton type="submit" :disabled="userForm.processing">
							{{ userModalMode === 'add' ? 'Create' : 'Save' }}
						</PrimaryButton>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>


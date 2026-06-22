<template>
	<div>
		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-4" id="profile">
				<div class="grid gap-4 md:grid-cols-2">
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
						<input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required />
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
						<input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" required />
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Password</label>
						<input v-model="form.password" type="password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						<p class="mt-1 text-xs text-gray-500">Leave blank to keep current</p>
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password</label>
						<input v-model="form.password_confirmation" type="password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
					</div>
				</div>

				<div
					v-if="role_assignment_rules.length"
					class="rounded-lg border border-blue-100 bg-blue-50/80 px-4 py-3 text-sm text-blue-950"
				>
					<p class="mb-2 font-medium">Role assignment</p>
					<ul class="list-inside list-disc space-y-1 text-blue-900/90">
						<li v-for="(line, i) in role_assignment_rules" :key="i">{{ line }}</li>
					</ul>
				</div>

				<div id="integrated-system-user">
					<label class="mb-1 block text-sm font-medium text-gray-700"
						>Integrated System User <span class="text-red-600">*</span></label
					>
					<PDropdown
						v-model="form.intsysuser"
						:options="mergedIntegratedUsers"
						optionLabel="label"
						optionValue="id"
						placeholder="Select integrated user"
						:disabled="integrationLoading || !tenantIdForPicker || (!mergedIntegratedUsers.length && !integrationLoadError)"
						class="w-full md:w-1/2"
					/>
					<p v-if="integrationLoading" class="mt-1 text-xs text-gray-500">Loading users from integration…</p>
					<p v-else-if="integrationLoadError" class="mt-1 text-xs text-red-600">{{ integrationLoadError }}</p>
					<p v-else-if="!tenantIdForPicker" class="mt-1 text-xs text-gray-500">
						Integrated user mapping applies to tenant accounts only.
					</p>
					<p v-else-if="mergedIntegratedUsers.length === 0" class="mt-1 text-xs text-gray-500">
						No unmapped CRM users available, or integration is not configured. All integration users may already be
						linked.
					</p>
					<p v-if="errors.intsysuser" class="mt-1 text-xs text-red-600">{{ errors.intsysuser }}</p>
					<p v-if="form.errors.intsysuser" class="mt-1 text-xs text-red-600">{{ form.errors.intsysuser }}</p>
				</div>

				<div id="roles">
					<h3 class="mb-1 text-sm font-medium text-gray-700">Role</h3>
					<PDropdown
						v-model="form.role"
						:options="roles"
						optionLabel="name"
						optionValue="name"
						placeholder="Select role"
						class="w-full md:w-1/2"
					/>
				</div>

				<div class="flex items-center gap-3">
					<PButton type="submit" :disabled="form.processing" label="Save" icon="pi pi-check" />
					<Link href="/admin/users" class="text-sm text-gray-700 hover:text-gray-900">Cancel</Link>
					<button type="button" @click="destroy" class="ml-auto rounded-lg bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700">Delete</button>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { reactive, computed, ref, onMounted } from 'vue';
import { axios } from '@/bootstrap';

const props = defineProps({
	user: Object,
	roles: Array,
	integrated_system_users: {
		type: Array,
		default: () => []
	},
	role_assignment_rules: {
		type: Array,
		default: () => []
	}
});

const tenantIdForPicker = computed(() => props.user?.tenant_id ?? null);

const unmappedOptionsRef = ref([...(props.integrated_system_users ?? [])]);
const integrationLoading = ref(false);
const integrationLoadError = ref('');

async function refreshUnmappedIntegrationUsers() {
	integrationLoadError.value = '';
	const tid = tenantIdForPicker.value;
	if (!tid) {
		unmappedOptionsRef.value = [];
		return;
	}

	integrationLoading.value = true;
	try {
		const { data } = await axios.get(route('admin.users.integration-external-options'), {
			params: { tenant_id: tid, for_user_id: props.user.id }
		});
		unmappedOptionsRef.value = Array.isArray(data?.unmapped_options) ? data.unmapped_options : [];
	} catch (err) {
		const d = err?.response?.data;
		const msg =
			d && typeof d === 'object' && typeof d.message === 'string' ? d.message : err.message || 'Could not load integration users.';
		integrationLoadError.value = msg;
		unmappedOptionsRef.value = [...(props.integrated_system_users ?? [])];
	} finally {
		integrationLoading.value = false;
	}
}

onMounted(() => {
	void refreshUnmappedIntegrationUsers();
});

const mergedIntegratedUsers = computed(() => {
	const base = unmappedOptionsRef.value;
	const pid = props.user?.intsysuser;
	if (pid == null || String(pid) === '') {
		return base;
	}
	const sid = String(pid);
	if (base.some((o) => o.id === sid)) {
		return base;
	}
	return [...base, { id: sid, label: `${sid} (current)` }];
});

const form = useForm({
	name: props.user.name,
	email: props.user.email,
	password: '',
	password_confirmation: '',
	intsysuser: props.user.intsysuser ?? '',
	role: Array.isArray(props.user.roles) && props.user.roles.length ? props.user.roles[0] : ''
});
const errors = reactive({
	intsysuser: ''
});

function submit() {
	errors.intsysuser = '';

	if (tenantIdForPicker.value && mergedIntegratedUsers.value.length && !form.intsysuser) {
		errors.intsysuser = 'Integrated system user is required.';
		return;
	}

	const payload = {
		name: form.name,
		email: form.email,
		password: form.password,
		password_confirmation: form.password_confirmation,
		intsysuser: form.intsysuser ? form.intsysuser : null,
		roles: form.role ? [form.role] : [],
		permissions: []
	};
	form.transform(() => payload).put(`/admin/users/${props.user.id}`, {
		onSuccess: () => {
			router.get('/admin/users');
		}
	});
}
function destroy() {
	if (confirm('Delete this user?')) {
		router.delete(`/admin/users/${props.user.id}`);
	}
}
</script>


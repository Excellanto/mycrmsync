<template>
	<div>
		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-4">
				<div class="grid gap-4 md:grid-cols-2">
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
						<input v-model="form.name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						<p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
						<p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
						<input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						<p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
						<p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Password</label>
						<input v-model="form.password" type="password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						<p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
						<p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
					</div>
					<div>
						<label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password</label>
						<input v-model="form.password_confirmation" type="password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
						<p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ errors.password_confirmation }}</p>
						<p v-if="form.errors.password_confirmation" class="mt-1 text-xs text-red-600">{{ form.errors.password_confirmation }}</p>
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

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700"
						>Integrated System User <span class="text-red-600">*</span></label
					>
					<PDropdown
						v-model="form.intsysuser"
						:options="unmappedDropdownOptions"
						optionLabel="label"
						optionValue="id"
						placeholder="Select integrated user"
						:disabled="integrationLoading || !tenantIdForPicker || (!unmappedDropdownOptions.length && !integrationLoadError)"
						class="w-full md:w-1/2"
					/>
					<p v-if="integrationLoading" class="mt-1 text-xs text-gray-500">Loading users from integration…</p>
					<p v-else-if="integrationLoadError" class="mt-1 text-xs text-red-600">{{ integrationLoadError }}</p>
					<p v-else-if="!tenantIdForPicker" class="mt-1 text-xs text-gray-500">
						Integrated user mapping applies to tenant accounts only.
					</p>
					<p v-else-if="!unmappedDropdownOptions.length" class="mt-1 text-xs text-gray-500">
						No unmapped CRM users available. Configure CRM integration credentials for this tenant, or all integration
						users may already be linked.
					</p>
					<p v-if="errors.intsysuser" class="mt-1 text-xs text-red-600">{{ errors.intsysuser }}</p>
					<p v-if="form.errors.intsysuser" class="mt-1 text-xs text-red-600">{{ form.errors.intsysuser }}</p>
				</div>

				<div>
					<h3 class="mb-1 text-sm font-medium text-gray-700">Role</h3>
					<PDropdown
						v-model="form.role"
						:options="roles"
						optionLabel="name"
						optionValue="name"
						placeholder="Select role"
						class="w-full md:w-1/2"
					/>
					<p v-if="errors.role" class="mt-1 text-xs text-red-600">{{ errors.role }}</p>
					<p v-if="form.errors.roles" class="mt-1 text-xs text-red-600">{{ form.errors.roles }}</p>
				</div>

				<div class="flex items-center gap-3">
					<PButton type="submit" :disabled="form.processing" label="Save" icon="pi pi-check" />
					<Link href="/admin/users" class="text-sm text-gray-700 hover:text-gray-900">Cancel</Link>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed, onMounted } from 'vue';
import { axios } from '@/bootstrap';

const props = defineProps({
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

const page = usePage();
const tenantIdForPicker = computed(() => page.props.auth?.user?.tenant_id ?? null);

const unmappedDropdownOptions = ref([...(props.integrated_system_users ?? [])]);
const integrationLoading = ref(false);
const integrationLoadError = ref('');

async function refreshUnmappedIntegrationUsers() {
	integrationLoadError.value = '';
	const tid = tenantIdForPicker.value;
	if (!tid) {
		unmappedDropdownOptions.value = [];
		return;
	}

	integrationLoading.value = true;
	try {
		const { data } = await axios.get(route('admin.users.integration-external-options'), {
			params: { tenant_id: tid }
		});
		unmappedDropdownOptions.value = Array.isArray(data?.unmapped_options) ? data.unmapped_options : [];
	} catch (err) {
		const d = err?.response?.data;
		const msg =
			d && typeof d === 'object' && typeof d.message === 'string' ? d.message : err.message || 'Could not load integration users.';
		integrationLoadError.value = msg;
		unmappedDropdownOptions.value = [...(props.integrated_system_users ?? [])];
	} finally {
		integrationLoading.value = false;
	}
}

onMounted(() => {
	void refreshUnmappedIntegrationUsers();
});

const form = useForm({
	name: '',
	email: '',
	password: '',
	password_confirmation: '',
	intsysuser: '',
	role: ''
});
const errors = reactive({
	name: '',
	email: '',
	password: '',
	password_confirmation: '',
	intsysuser: '',
	role: ''
});

function validate() {
	errors.name = '';
	errors.email = '';
	errors.password = '';
	errors.password_confirmation = '';
	errors.intsysuser = '';
	errors.role = '';

	// Name required and first letter capital
	if (!form.name) {
		errors.name = 'Name is required.';
	} else if (!/^[A-Z]/.test(form.name.trim())) {
		errors.name = 'First letter must be uppercase.';
	}

	// Email format
	const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	if (!form.email) {
		errors.email = 'Email is required.';
	} else if (!emailPattern.test(form.email.trim())) {
		errors.email = 'Enter a valid email address.';
	}

	// Passwords required and must match
	if (!form.password) {
		errors.password = 'Password is required.';
	} else if (form.password.length < 8) {
		errors.password = 'Password must be at least 8 characters.';
	}
	if (!form.password_confirmation) {
		errors.password_confirmation = 'Confirm your password.';
	} else if (form.password && form.password !== form.password_confirmation) {
		errors.password_confirmation = 'Passwords do not match.';
	}

	if (tenantIdForPicker.value && unmappedDropdownOptions.value.length && !form.intsysuser) {
		errors.intsysuser = 'Integrated system user is required.';
	}

	if (!form.role) {
		errors.role = 'Please select a role.';
	}

	return (
		!errors.name &&
		!errors.email &&
		!errors.password &&
		!errors.password_confirmation &&
		!errors.intsysuser &&
		!errors.role
	);
}

function submit() {
	if (!validate()) return;

	form.name = form.name ? form.name.charAt(0).toUpperCase() + form.name.slice(1) : form.name;

	const payload = {
		name: form.name,
		email: form.email,
		password: form.password,
		password_confirmation: form.password_confirmation,
		intsysuser: form.intsysuser ? form.intsysuser : null,
		roles: form.role ? [form.role] : [],
		permissions: []
	};
	form.transform(() => payload).post('/admin/users', {
		onSuccess: () => {
			form.reset('password', 'password_confirmation');
			router.get('/admin/users');
		}
	});
}
</script>


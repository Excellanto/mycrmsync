<template>
	<div>
		<Head title="Edit Profile" />
		<div v-if="flashSuccess" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
			{{ flashSuccess }}
		</div>

		<div class="mb-6">
			<h1 class="text-xl font-semibold text-gray-900">Edit Profile</h1>
			<p class="mt-1 text-sm text-gray-600">
				Update your account and company details.
			</p>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-6">
				<!-- Company Details (Business/Recruiter) -->
				<div v-if="tenant" class="space-y-4">
					<h2 class="text-lg font-semibold text-gray-900">Company Details</h2>

					<!-- Company logo -->
					<TenantLogoUploader
						v-if="tenant"
						:company-name="form.company_name"
						:logo-url="tenant.company_logo_url"
						:store-url="route('admin.profile.company-logo.store')"
						:destroy-url="route('admin.profile.company-logo.destroy')"
					/>

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
				</div>

				<!-- Account Details -->
				<div class="space-y-4">
					<h2 class="text-lg font-semibold text-gray-900">Account Details</h2>
					<div>
						<InputLabel for="name" value="Name" />
						<TextInput
							id="name"
							v-model="form.name"
							type="text"
							class="mt-1 block w-full"
							required
							autocomplete="name"
						/>
						<InputError class="mt-2" :message="form.errors.name" />
					</div>
					<div>
						<InputLabel for="email" value="Email" />
						<TextInput
							id="email"
							v-model="form.email"
							type="email"
							class="mt-1 block w-full"
							required
							autocomplete="username"
						/>
						<InputError class="mt-2" :message="form.errors.email" />
					</div>
				</div>

				<!-- Change Password (Optional) -->
				<div class="space-y-4 border-t border-gray-200 pt-6">
					<h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
					<p class="text-sm text-gray-500">Leave blank to keep your current password.</p>
					<div>
						<InputLabel for="password" value="New Password" />
						<TextInput
							id="password"
							v-model="form.password"
							type="password"
							class="mt-1 block w-full"
							autocomplete="new-password"
						/>
						<InputError class="mt-2" :message="form.errors.password" />
					</div>
					<div>
						<InputLabel for="password_confirmation" value="Confirm New Password" />
						<TextInput
							id="password_confirmation"
							v-model="form.password_confirmation"
							type="password"
							class="mt-1 block w-full"
							autocomplete="new-password"
						/>
						<InputError class="mt-2" :message="form.errors.password_confirmation" />
					</div>
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
						:href="route('admin.dashboard')"
						class="text-sm text-gray-600 underline hover:text-gray-900"
					>
						Cancel
					</Link>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TenantLogoUploader from '@/Components/TenantLogoUploader.vue';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const props = defineProps({
	user: {
		type: Object,
		required: true,
	},
	tenant: {
		type: Object,
		default: null,
	},
});

const form = useForm({
	company_name: props.tenant?.company_name ?? '',
	account_type: props.tenant?.account_type ?? 'Business',
	pan_card: props.tenant?.pan_card ?? '',
	gst_number: props.tenant?.gst_number ?? '',
	name: props.user.name,
	email: props.user.email,
	password: '',
	password_confirmation: '',
});

const submit = () => {
	form.put(route('admin.profile.update'), {
		preserveScroll: true,
		onSuccess: () => form.reset('password', 'password_confirmation'),
	});
};
</script>

<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
	submitRoute: { type: String, required: true },
	submitLabel: { type: String, default: 'Register' },
	title: { type: String, default: 'Register' },
	showLoginLink: { type: Boolean, default: false },
	cancelHref: { type: String, default: null },
	cancelLabel: { type: String, default: 'Cancel' },
});

const form = useForm({
	company_name: '',
	account_type: 'Business',
	pan_card: '',
	gst_number: '',
	name: '',
	email: '',
	password: '',
	password_confirmation: '',
});

const submit = () => {
	form.post(props.submitRoute, {
		onFinish: () => form.reset('password', 'password_confirmation'),
	});
};
</script>

<template>
	<form @submit.prevent="submit">
		<div class="mb-6">
			<h2 class="mb-4 text-lg font-semibold text-gray-900">{{ title }}</h2>
			<div>
				<InputLabel for="company_name" value="Company Name" />
				<TextInput
					id="company_name"
					v-model="form.company_name"
					type="text"
					class="mt-1 block w-full"
					required
					autofocus
				/>
				<InputError class="mt-2" :message="form.errors.company_name" />
			</div>

			<div class="mt-4">
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

			<div class="mt-4">
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

			<div class="mt-4">
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

		<div class="mb-6">
			<h2 class="mb-4 text-lg font-semibold text-gray-900">Tenant Admin Account</h2>
		</div>

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

		<div class="mt-4">
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

		<div class="mt-4">
			<InputLabel for="password" value="Password" />
			<TextInput
				id="password"
				v-model="form.password"
				type="password"
				class="mt-1 block w-full"
				required
				autocomplete="new-password"
			/>
			<InputError class="mt-2" :message="form.errors.password" />
		</div>

		<div class="mt-4">
			<InputLabel for="password_confirmation" value="Confirm Password" />
			<TextInput
				id="password_confirmation"
				v-model="form.password_confirmation"
				type="password"
				class="mt-1 block w-full"
				required
				autocomplete="new-password"
			/>
			<InputError class="mt-2" :message="form.errors.password_confirmation" />
		</div>

		<div class="mt-4 flex items-center justify-end">
			<Link
				v-if="showLoginLink"
				:href="route('login')"
				class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
			>
				Already registered?
			</Link>
			<Link
				v-else-if="cancelHref"
				:href="cancelHref"
				class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
			>
				{{ cancelLabel }}
			</Link>

			<PrimaryButton
				class="ms-4"
				:class="{ 'opacity-25': form.processing }"
				:disabled="form.processing"
			>
				{{ submitLabel }}
			</PrimaryButton>
		</div>
	</form>
</template>

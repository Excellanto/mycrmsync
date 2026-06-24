<script setup>
import TenantRegistrationForm from '@/Components/TenantRegistrationForm.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
	<GuestLayout gradient-background>
		<Head title="Register" />
		<TenantRegistrationForm
			:submit-route="route('register')"
			title="Company Details"
			submit-label="Register"
			:show-login-link="true"
		/>
	</GuestLayout>
</template>

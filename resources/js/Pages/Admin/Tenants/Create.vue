<script setup>
import TenantRegistrationForm from '@/Components/TenantRegistrationForm.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
	<div>
		<Head title="Add Tenant" />

		<div class="mb-4 flex items-center justify-between">
			<h1 class="text-xl font-semibold">Add Tenant</h1>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<TenantRegistrationForm
				:submit-route="route('admin.tenants.store')"
				title="Company Details"
				submit-label="Add Tenant"
				:show-login-link="false"
				:cancel-href="route('admin.tenants.index')"
			/>
		</div>
	</div>
</template>

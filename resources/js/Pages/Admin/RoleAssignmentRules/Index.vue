<template>
	<div>
		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Role assignment rules</h1>
			<p class="mt-1 text-sm text-gray-600">
				Read-only reference for who can see and assign roles on Create User and Edit User. Behavior is enforced in
				application code as well as this summary.
			</p>
		</div>

		<div
			class="mb-6 rounded-lg border border-blue-100 bg-blue-50/80 px-4 py-3 text-sm text-blue-950"
		>
			<p class="font-medium">Note</p>
			<p class="mt-1 text-blue-900/90">
				To change which roles are platform-wide, update the role in the database (for example the
				<code class="rounded bg-blue-100/80 px-1 text-xs">is_platform_scope</code> flag) or seed data—this page
				does not edit settings.
			</p>
		</div>

		<div class="space-y-4">
			<div
				v-for="(section, i) in sections"
				:key="i"
				class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
			>
				<h2 class="text-base font-semibold text-gray-900">{{ section.title }}</h2>
				<ul class="mt-3 list-inside list-disc space-y-2 text-sm text-gray-700">
					<li v-for="(line, j) in section.bullets" :key="j" class="leading-relaxed">
						{{ line }}
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script setup>
defineProps({
	sections: {
		type: Array,
		default: () => []
	}
});
</script>

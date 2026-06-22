<template>
	<div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
		<table class="min-w-full divide-y divide-gray-200">
			<thead class="bg-gray-50">
				<tr>
					<th v-for="(h, i) in headers" :key="i" scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-600">
						{{ h }}
					</th>
					<th v-if="$slots.actions" class="px-4 py-3" />
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100 bg-white">
				<slot />
			</tbody>
		</table>
	</div>
	<div v-if="$slots.footer" class="mt-3">
		<slot name="footer" />
	</div>
	<div v-if="paginator" class="mt-4">
		<Pagination :links="paginator.links" />
	</div>
	<div v-if="!$slots.default && !loading" class="p-4 text-center text-gray-500">No records.</div>
	<div v-if="loading" class="p-4 text-center text-gray-500">Loading...</div>
	</template>
	
	<script setup>
	import Pagination from './Pagination.vue';
	defineProps({
		headers: { type: Array, default: () => [] },
		paginator: { type: Object, default: null },
		loading: { type: Boolean, default: false }
	});
	</script>
	





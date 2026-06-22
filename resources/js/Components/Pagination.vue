<template>
	<nav v-if="links && links.length > 1" class="flex items-center justify-between">
		<ul class="inline-flex -space-x-px rounded-md border border-gray-200 bg-white">
			<li v-for="(link, i) in links" :key="i">
				<Link
					:href="link.url || '#'"
					:class="[
						'px-3 py-2 text-sm border-r border-gray-200 hover:bg-gray-50',
						link.active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-gray-700',
						!link.url ? 'opacity-50 cursor-not-allowed' : ''
					]"
					:preserve-scroll="true"
					:preserve-state="true"
					v-html="link.label"
				/>
			</li>
		</ul>
	</nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
	links: { type: Array, default: () => [] }
});
</script>






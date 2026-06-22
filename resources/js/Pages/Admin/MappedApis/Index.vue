<template>
	<div>
		<Head title="Mapped APIs" />

		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Mapped APIs</h1>
			<p class="mt-1 text-sm text-gray-500">
				Each integration pairs
				<strong class="font-medium text-gray-700">Application API</strong>
				routes (this product, e.g.
				<code class="rounded bg-gray-100 px-1 text-xs text-gray-700">/api/crm/*</code>
				with API JWT auth) beside the
				<strong class="font-medium text-gray-700">integrated system API</strong>
				(vendor CRM endpoints and auth). Request and response shapes match where parity is implemented.
			</p>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="grid min-h-[520px] md:grid-cols-[16rem_1fr]">
				<aside
					class="flex max-h-[min(70vh,560px)] flex-col border-b border-gray-200 bg-gray-50/60 md:max-h-none md:border-b-0 md:border-r"
				>
					<div class="shrink-0 border-b border-gray-200/80 px-3 py-3">
						<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Integrations</p>
						<p class="mt-0.5 text-xs text-gray-500">{{ sortedIntegrations.length }} configured</p>
					</div>
					<nav class="min-h-0 flex-1 overflow-y-auto py-2">
						<button
							v-for="integration in sortedIntegrations"
							:key="integration.id"
							type="button"
							class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm transition-colors"
							:class="sidebarRowClass(integration.id)"
							@click="selectedId = integration.id"
						>
							<span
								class="inline-flex h-8 min-w-[2rem] shrink-0 items-center justify-center rounded-md text-xs font-semibold uppercase"
								:class="iconWrapClass(integration.id)"
							>
								{{ initials(integration.name) }}
							</span>
							<span class="min-w-0 flex-1 truncate font-medium">{{ integration.name }}</span>
						</button>
					</nav>
				</aside>

				<section class="p-6">
					<template v-if="selectedIntegration">
						<div class="rounded-lg border border-gray-100 bg-gray-50/80 px-5 py-6">
							<dl class="space-y-6">
								<div>
									<dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Heading</dt>
									<dd class="mt-1 text-lg font-semibold text-gray-900">{{ selectedIntegration.name }}</dd>
								</div>
								<div>
									<dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Type</dt>
									<dd class="mt-1 text-sm font-medium text-gray-900">{{ selectedIntegration.type }}</dd>
								</div>
								<div>
									<dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Documentation Link</dt>
									<dd class="mt-1">
										<a
											v-if="selectedIntegration.documentation"
											:href="selectedIntegration.documentation"
											target="_blank"
											rel="noreferrer"
											class="inline-flex max-w-full items-center gap-1 break-all text-sm font-medium text-primary-700 hover:text-primary-900 hover:underline"
										>
											{{ selectedIntegration.documentation }}
											<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
												<path
													stroke-linecap="round"
													stroke-linejoin="round"
													stroke-width="2"
													d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
												/>
											</svg>
										</a>
										<span v-else class="text-sm text-gray-500">No documentation link configured.</span>
									</dd>
								</div>
							</dl>
							<p class="mt-6 text-xs text-gray-500">
								Status:
								<span :class="selectedIntegration.enabled ? 'text-emerald-700' : 'text-gray-600'">
									{{ selectedIntegration.enabled ? 'Enabled in dropdowns' : 'Hidden from dropdowns' }}
								</span>
							</p>
						</div>

						<div class="mt-8">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Application API vs integrated system API</h2>
							<p class="mt-1 text-sm text-gray-600">
								Expand a row to compare the route on this application with the same capability on the external system.
							</p>

							<div v-if="selectedEndpointMappings.length" class="mt-4 space-y-2">
								<details
									v-for="row in selectedEndpointMappings"
									:key="row.key"
									class="group rounded-lg border border-gray-200 bg-white transition-colors open:border-gray-300 open:shadow-sm"
								>
									<summary
										class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 text-left marker:content-none [&::-webkit-details-marker]:hidden"
									>
										<svg
											class="h-5 w-5 shrink-0 text-gray-400 transition-transform group-open:rotate-90"
											fill="none"
											viewBox="0 0 24 24"
											stroke="currentColor"
											aria-hidden="true"
										>
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
										</svg>
										<div class="min-w-0 flex-1 space-y-1">
											<div class="text-sm font-semibold text-gray-900">{{ row.name }}</div>
											<div class="font-mono text-xs leading-relaxed text-gray-700">
												<div class="flex flex-wrap items-baseline gap-x-1">
													<span class="shrink-0 font-semibold text-blue-900">Application API</span>
													<span class="text-slate-600">·</span>
													<span class="text-slate-800">{{ row.systemMethod }}</span>
													<span class="break-all text-gray-900">{{ row.systemPath }}</span>
												</div>
												<div class="mt-1 flex flex-wrap items-baseline gap-x-1">
													<span class="shrink-0 font-semibold text-slate-800">Integrated system API</span>
													<span class="text-slate-600">·</span>
													<span class="text-slate-800">{{ row.crmMethod }}</span>
													<span class="break-all text-gray-900">{{ row.crmPath }}</span>
													<span class="text-gray-500">({{ crmHostLabel(row) }})</span>
												</div>
											</div>
										</div>
									</summary>
									<div class="space-y-4 border-t border-gray-100 px-4 pb-4 pt-3">
										<div class="grid gap-4 sm:grid-cols-2">
											<div class="rounded-lg border border-blue-200/80 bg-blue-50/50 px-4 py-3">
												<div class="text-xs font-semibold uppercase tracking-wide text-blue-950">Application API</div>
												<p class="mt-2 break-all font-mono text-sm text-gray-900">
													<span class="font-semibold text-blue-950">{{ row.systemMethod }}</span>
													{{ ' ' }}
													{{ row.systemPath }}
												</p>
												<p v-if="row.routeName" class="mt-2 text-xs text-gray-600">
													Route name:
													<code class="rounded bg-white/80 px-1 py-0.5 text-gray-800">{{ row.routeName }}</code>
												</p>
												<p class="mt-2 text-xs text-gray-600">Calls this application · authenticate with API JWT (<code class="text-xs">auth:api</code>).</p>
											</div>
											<div class="rounded-lg border border-slate-200 bg-slate-50/80 px-4 py-3">
												<div class="text-xs font-semibold uppercase tracking-wide text-slate-800">
													Integrated system API
													<span v-if="selectedIntegration" class="font-normal text-gray-600">
														({{ selectedIntegration.name }})
													</span>
												</div>
												<p class="mt-2 break-all font-mono text-sm text-gray-900">
													<span class="font-semibold text-slate-900">{{ row.crmMethod }}</span>
													{{ ' ' }}
													{{ crmFullUrl(row) }}
												</p>
												<p class="mt-2 text-xs text-gray-600">
													Mirrors the application route against the vendor host
													<span class="font-mono text-gray-800">{{ crmHostLabel(row) }}</span>
													— use the integrated system’s OAuth/token flow and required headers (see docs link above).
												</p>
											</div>
										</div>

										<p
											v-if="row.headersNote"
											class="rounded-md border border-amber-100 bg-amber-50/80 px-3 py-2 text-xs text-amber-950"
										>
											<strong class="font-semibold">Auth comparison:</strong>
											{{ ' ' }}
											{{ row.headersNote }}
										</p>

										<div
											v-if="row.request || row.responseBody !== undefined"
											class="space-y-6 border-t border-gray-100 pt-4"
										>
											<div>
												<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Request comparison</div>
												<p class="mt-1 text-xs text-gray-600">
													Parity layer: query, path, and body are the same for both sides (only base URL and auth headers differ).
												</p>
												<div class="mt-3 grid gap-3 lg:grid-cols-2">
													<div class="min-w-0 rounded-md border border-blue-100 bg-white px-3 py-2">
														<div class="text-xs font-semibold text-blue-950">Application API</div>
														<pre
															v-if="requestParityPayload(row.request)"
															class="mt-2 max-h-48 overflow-auto rounded border border-gray-200 bg-gray-900/95 p-2.5 font-mono text-xs leading-relaxed text-gray-100"
														>{{ formatJson(requestParityPayload(row.request)) }}</pre>
														<p v-else-if="row.request" class="mt-2 text-xs italic text-gray-500">
															No query, path, or JSON body (headers only).
														</p>
														<p v-else class="mt-2 text-xs italic text-gray-500">Request shape not documented.</p>
													</div>
													<div class="min-w-0 rounded-md border border-slate-200 bg-white px-3 py-2">
														<div class="text-xs font-semibold text-slate-900">
															Integrated system API
															<span v-if="selectedIntegration" class="font-normal text-gray-600">
																({{ selectedIntegration.name }})
															</span>
														</div>
														<pre
															v-if="requestParityPayload(row.request)"
															class="mt-2 max-h-48 overflow-auto rounded border border-gray-200 bg-gray-900/95 p-2.5 font-mono text-xs leading-relaxed text-gray-100"
														>{{ formatJson(requestParityPayload(row.request)) }}</pre>
														<p v-else-if="row.request" class="mt-2 text-xs italic text-gray-500">
															No query, path, or JSON body (headers only).
														</p>
														<p v-else class="mt-2 text-xs italic text-gray-500">Request shape not documented.</p>
													</div>
												</div>
											</div>

											<div>
												<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
													Response comparison
													<span v-if="row.responseStatus" class="font-normal text-gray-600">
														(HTTP {{ row.responseStatus }})
													</span>
												</div>
												<p class="mt-1 text-xs text-gray-600">
													Example JSON: parity responses match this shape on both sides (stub lists/IDs in this app today).
												</p>
												<div class="mt-3 grid gap-3 lg:grid-cols-2">
													<div class="min-w-0 rounded-md border border-blue-100 bg-white px-3 py-2">
														<div class="text-xs font-semibold text-blue-950">Application API</div>
														<pre
															v-if="row.responseBody !== undefined"
															class="mt-2 max-h-64 overflow-auto rounded border border-gray-200 bg-gray-900/95 p-2.5 font-mono text-xs leading-relaxed text-gray-100"
														>{{ formatJson(row.responseBody) }}</pre>
														<p v-else class="mt-2 text-xs italic text-gray-500">Response example not documented.</p>
													</div>
													<div class="min-w-0 rounded-md border border-slate-200 bg-white px-3 py-2">
														<div class="text-xs font-semibold text-slate-900">
															Integrated system API
															<span v-if="selectedIntegration" class="font-normal text-gray-600">
																({{ selectedIntegration.name }})
															</span>
														</div>
														<pre
															v-if="row.responseBody !== undefined"
															class="mt-2 max-h-64 overflow-auto rounded border border-gray-200 bg-gray-900/95 p-2.5 font-mono text-xs leading-relaxed text-gray-100"
														>{{ formatJson(row.responseBody) }}</pre>
														<p v-else class="mt-2 text-xs italic text-gray-500">Response example not documented.</p>
													</div>
												</div>
											</div>
										</div>
									</div>
								</details>
							</div>
							<div
								v-else
								class="mt-4 rounded-lg border border-dashed border-gray-200 bg-gray-50/50 px-4 py-8 text-center text-sm text-gray-600"
							>
								No documented parity mappings for this integration yet. GoHighLevel is listed first when
								configured; other CRMs can be added as system routes are implemented.
							</div>
						</div>
					</template>

					<div
						v-else
						class="rounded-lg border border-dashed border-gray-200 px-4 py-16 text-center text-sm text-gray-500"
					>
						No integrations configured yet. Add them under Settings → Data Configuration.
					</div>
				</section>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { getCrmSystemApiMappingsForSlug } from '@/data/crmSystemApiMappings';

const props = defineProps({
	integrations: {
		type: Array,
		default: () => [],
	},
});

/** Sidebar order: GoHighLevel, Zoho, then everyone else A–Z (matches common workflow). */
const preferredSidebarOrder = ['gohighlevel', 'zoho'];

function normalizedIntegrationKey(name) {
	return String(name || '')
		.toLowerCase()
		.replace(/\s+/g, '');
}

function preferredOrderRank(integration) {
	const slug = String(integration?.slug || '').toLowerCase();
	const bySlug = preferredSidebarOrder.indexOf(slug);
	if (bySlug !== -1) return bySlug;
	const byName = preferredSidebarOrder.indexOf(normalizedIntegrationKey(integration?.name));
	return byName === -1 ? 1000 : byName;
}

const sortedIntegrations = computed(() => {
	const list = [...props.integrations];
	return list.sort((a, b) => {
		const ra = preferredOrderRank(a);
		const rb = preferredOrderRank(b);
		if (ra !== rb) return ra - rb;
		return String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' });
	});
});

const selectedId = ref(null);

watch(
	sortedIntegrations,
	(list) => {
		if (!list.length) {
			selectedId.value = null;
			return;
		}
		const ids = new Set(list.map((i) => i.id));
		if (selectedId.value == null || !ids.has(selectedId.value)) {
			selectedId.value = list[0].id;
		}
	},
	{ immediate: true }
);

const selectedIntegration = computed(() => {
	if (selectedId.value == null) return null;
	return sortedIntegrations.value.find((i) => i.id === selectedId.value) || null;
});

const selectedEndpointMappings = computed(() =>
	getCrmSystemApiMappingsForSlug(selectedIntegration.value?.slug)
);

function crmFullUrl(row) {
	const base = row.crmBaseUrl ? String(row.crmBaseUrl).replace(/\/$/, '') : '';
	const path = row.crmPath.startsWith('/') ? row.crmPath : `/${row.crmPath}`;
	return base ? `${base}${path}` : path;
}

function crmHostLabel(row) {
	const base = row.crmBaseUrl;
	if (!base) return 'external host';
	try {
		return new URL(String(base)).host;
	} catch {
		return 'external host';
	}
}

/** Merge query/path/body for side-by-side request comparison (same shape on app vs integrated system). */
function requestParityPayload(req) {
	if (!req) return null;
	const out = {};
	if (req.query && typeof req.query === 'object' && Object.keys(req.query).length > 0) {
		out.query = req.query;
	}
	if (req.path && typeof req.path === 'object' && Object.keys(req.path).length > 0) {
		out.path = req.path;
	}
	if (req.body != null) {
		out.body = req.body;
	}
	return Object.keys(out).length > 0 ? out : null;
}

function formatJson(value) {
	if (value === undefined) return '';
	try {
		return JSON.stringify(value, null, 2);
	} catch {
		return String(value);
	}
}

function initials(name) {
	const parts = String(name || '')
		.trim()
		.split(/\s+/)
		.filter(Boolean);
	if (!parts.length) return '?';
	if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
	return (parts[0][0] + parts[1][0]).toUpperCase();
}

function sidebarRowClass(id) {
	const active = selectedId.value === id;
	return active
		? 'border-l-[3px] border-blue-600 bg-white font-medium text-gray-900 shadow-sm'
		: 'border-l-[3px] border-transparent text-gray-700 hover:bg-white/80';
}

function iconWrapClass(id) {
	const active = selectedId.value === id;
	return active ? 'bg-blue-100 text-blue-700' : 'bg-gray-200/80 text-gray-700';
}
</script>

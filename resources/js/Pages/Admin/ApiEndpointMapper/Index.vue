<template>
	<div class="mx-auto w-full max-w-[1480px] space-y-8 px-1 sm:px-2">
		<!-- Top header -->
		<div class="rounded-xl border border-gray-200 bg-white p-6 mb-2 shadow-sm">
			<div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
				<div class="min-w-0 ">
					<h1 class="text-xl font-semibold text-gray-900">API Endpoint Mapper</h1>
					<p class="mt-1 text-sm text-gray-600">Map Integrated System endpoints to internal system APIs</p>
				</div>

		
			</div>
		</div>

		<!-- Main mapping section -->
		<div class="grid gap-8 lg:grid-cols-2 mb-2">
			<!-- LEFT: System APIs -->
			<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
				<div class="border-b border-gray-100 p-6">
					<div class="flex items-center justify-between gap-3">
						<div>
							<h2 class="text-base font-semibold text-gray-900">System APIs</h2>
							<p class="mt-1 text-sm text-gray-600">Choose an internal endpoint to map.</p>
						</div>
						<button
							type="button"
							class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
							@click="refreshSystemEndpoints"
							:disabled="loadingSystemEndpoints"
						>
							{{ loadingSystemEndpoints ? 'Loading…' : 'Refresh' }}
						</button>
					</div>

					<div class="mt-4">
						<label class="block text-sm font-medium text-gray-700">Select System API</label>
						<select
							v-model="selectedSystemEndpointId"
							class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
						>
							<option value="" disabled>Select an endpoint…</option>
							<option v-for="e in filteredSystemEndpoints" :key="e.id" :value="e.id">
								{{ e.method }} {{ e.uri }}
							</option>
						</select>
						<p v-if="systemEndpointsError" class="mt-2 text-sm text-red-600">
							{{ systemEndpointsError }}
						</p>
					</div>
				</div>

				<div class="p-6">
					<div v-if="!selectedSystemEndpoint" class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-600">
						Select a System API to see details.
					</div>

					<div v-else class="space-y-5">
						<div class="flex flex-wrap items-center justify-between gap-2">
							<div class="min-w-0">
								<div class="flex flex-wrap items-center gap-2">
									<span class="inline-flex items-center rounded-md bg-slate-900 px-2 py-0.5 text-xs font-semibold text-white">
										{{ selectedSystemEndpoint.method }}
									</span>
									<span class="truncate font-mono text-sm text-gray-900">{{ selectedSystemEndpoint.uri }}</span>
								</div>
								<p class="mt-1 text-sm text-gray-600">
									{{ selectedSystemEndpoint.description }}
								</p>
							</div>
							<button
								type="button"
								class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
								@click="onViewFullSchema('system')"
							>
								View Full Schema
							</button>
						</div>

						<div class="grid gap-4 sm:grid-cols-2">
							<div class="rounded-lg border border-gray-200 p-4">
								<div class="text-xs font-semibold text-gray-700">Endpoint URL</div>
								<div class="mt-1 truncate font-mono text-xs text-gray-900">{{ selectedSystemEndpoint.url }}</div>
							</div>
							<div class="rounded-lg border border-gray-200 p-4">
								<div class="text-xs font-semibold text-gray-700">Authentication</div>
								<div class="mt-1 text-sm text-gray-900">{{ systemAuthType }}</div>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="flex items-center justify-between gap-2">
								<div class="text-xs font-semibold text-gray-700">Headers</div>
								<button
									type="button"
									class="text-xs font-medium text-primary-700 hover:underline"
									@click="copyJson(systemHeaders)"
								>
									Copy
								</button>
							</div>
							<pre class="mt-2 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ pretty(systemHeaders) }}</pre>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="text-xs font-semibold text-gray-700">Query params</div>
							<div class="mt-2 flex flex-wrap gap-2">
								<span
									v-for="p in systemQueryParams"
									:key="p.name"
									class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-700"
								>
									<span class="font-mono">{{ p.name }}</span>
									<span v-if="p.required" class="rounded bg-red-100 px-1 text-[10px] font-semibold text-red-700">REQ</span>
								</span>
								<span v-if="systemQueryParams.length === 0" class="text-sm text-gray-600">None</span>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="text-xs font-semibold text-gray-700">Request body schema</div>
							<div class="mt-2 flex flex-wrap gap-2">
								<span
									v-for="f in systemFields"
									:key="f.key"
									class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-800"
								>
									<span class="font-mono">{{ f.key }}</span>
									<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ f.type }}</span>
									<span v-if="f.required" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">REQ</span>
								</span>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="flex items-center justify-between gap-2">
								<div class="text-xs font-semibold text-gray-700">Sample JSON payload</div>
								<button
									type="button"
									class="text-xs font-medium text-primary-700 hover:underline"
									@click="copyJson(systemSamplePayload)"
								>
									Copy
								</button>
							</div>
							<pre class="mt-2 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ pretty(systemSamplePayload) }}</pre>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="text-xs font-semibold text-gray-700">Expected response</div>
							<pre class="mt-2 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ pretty(systemExpectedResponse) }}</pre>
						</div>
					</div>
				</div>
			</div>

			<!-- RIGHT: Integrated System APIs -->
			<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
				<div class="border-b border-gray-100 p-6">
					<h2 class="text-base font-semibold text-gray-900">Integrated System APIs</h2>
					<p class="mt-1 text-sm text-gray-600">Choose an Integrated System and an external endpoint.</p>

					<div class="mt-4 grid gap-3 sm:grid-cols-2">
						<div>
							<label class="block text-sm font-medium text-gray-700">Select Integrated System</label>
							<select
								v-model="selectedCrm"
								:disabled="crmIntegrations.length === 0"
								class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
							>
								<option value="" disabled>{{ crmIntegrations.length ? 'Select an Integrated System…' : 'No enabled Integrated System configured…' }}</option>
								<option v-for="c in crmIntegrations" :key="c.slug" :value="c.slug">{{ c.name }}</option>
							</select>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700">Select Integrated System Endpoint</label>
							<select
								v-model="selectedCrmEndpointKey"
								:disabled="!selectedCrm"
								class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm outline-none disabled:bg-gray-50 disabled:text-gray-500 focus:border-primary-500 focus:ring-2 focus:ring-primary-200"
							>
								<option value="" disabled>{{ crmEndpointPlaceholder }}</option>
								<option v-for="e in filteredCrmEndpoints" :key="e.key" :value="e.key">{{ e.name }}</option>
							</select>
						</div>
					</div>
				</div>

				<div class="p-6">
					<div v-if="!selectedCrmEndpoint" class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-600">
						Select an Integrated System endpoint to see details.
					</div>

					<div v-else class="space-y-5">
						<div class="flex flex-wrap items-center justify-between gap-2">
							<div class="min-w-0">
								<div class="flex flex-wrap items-center gap-2">
									<span class="inline-flex items-center rounded-md bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">
										{{ selectedCrmEndpoint.method }}
									</span>
									<span class="truncate font-mono text-sm text-gray-900">{{ selectedCrmEndpoint.url }}</span>
								</div>
								<p class="mt-1 text-sm text-gray-600">{{ selectedCrmEndpoint.description }}</p>
							</div>
							<a
								:href="selectedCrmEndpoint.docsUrl"
								target="_blank"
								rel="noreferrer"
								class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
							>
								API documentation link
							</a>
						</div>

						<div class="grid gap-4 sm:grid-cols-2">
							<div class="rounded-lg border border-gray-200 p-4">
								<div class="text-xs font-semibold text-gray-700">OAuth/Auth requirements</div>
								<div class="mt-1 text-sm text-gray-900">{{ selectedCrmEndpoint.auth }}</div>
							</div>
							<div class="rounded-lg border border-gray-200 p-4">
								<div class="text-xs font-semibold text-gray-700">Required fields</div>
								<div class="mt-2 flex flex-wrap gap-2">
									<span
										v-for="f in crmFields.filter((x) => x.required)"
										:key="f.key"
										class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700"
									>
										{{ f.key }}
									</span>
									<span v-if="crmFields.filter((x) => x.required).length === 0" class="text-sm text-gray-600">None</span>
								</div>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="text-xs font-semibold text-gray-700">Request schema</div>
							<div class="mt-2 flex flex-wrap gap-2">
								<span
									v-for="f in crmRequestFields"
									:key="f.key"
									class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-800"
								>
									<span class="font-mono">{{ f.key }}</span>
									<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ f.type }}</span>
									<span v-if="f.required" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">REQ</span>
								</span>
								<span v-if="crmRequestFields.length === 0" class="text-sm text-gray-600">None</span>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="text-xs font-semibold text-gray-700">Response schema</div>
							<div class="mt-2 flex flex-wrap gap-2">
								<span
									v-for="f in crmResponseFields"
									:key="f.key"
									class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-800"
								>
									<span class="font-mono">{{ f.key }}</span>
									<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ f.type }}</span>
								</span>
								<span v-if="crmResponseFields.length === 0" class="text-sm text-gray-600">Response schema not documented.</span>
							</div>
						</div>

						<div class="rounded-lg border border-gray-200 p-4">
							<div class="flex items-center justify-between gap-2">
								<div class="text-xs font-semibold text-gray-700">Example payload</div>
								<button
									type="button"
									class="text-xs font-medium text-primary-700 hover:underline"
									@click="copyJson(selectedCrmEndpoint.samplePayload ?? {})"
								>
									Copy
								</button>
							</div>
							<pre class="mt-2 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ pretty(selectedCrmEndpoint.samplePayload ?? {}) }}</pre>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Field & logic mapping -->
		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="border-b border-gray-100 p-6">
				<div>
					<h2 class="text-base font-semibold text-gray-900">Field Mapping &amp; API Transformation</h2>
					<p class="mt-1 text-sm text-gray-600">
						Pair fields, validate requirements, and add transformation logic.
					</p>
				</div>
			</div>

			<div class="p-6">
				<div class="grid gap-8 lg:grid-cols-2">
					<!-- Left list -->
					<div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4">
						<div class="flex items-center justify-between border-b border-gray-200/70 pb-3">
							<div class="text-sm font-semibold text-gray-900 px-2">System Fields</div>
							<div class="text-xs text-gray-600 px-2">{{ systemFields.length }} fields</div>
						</div>
						<div class="mt-4 space-y-2">
							<div
								v-for="f in systemFields"
								:key="f.key"
								class="flex cursor-grab items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
								draggable="true"
								@dragstart="onDragStartSystemField(f)"
							>
								<div class="min-w-0">
									<div class="truncate font-mono text-xs text-gray-900">{{ f.key }}</div>
									<div class="mt-0.5 flex items-center gap-2">
										<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ f.type }}</span>
										<span v-if="f.required" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">REQ</span>
									</div>
								</div>
								<svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 15h8" />
								</svg>
							</div>
						</div>
					</div>

					<!-- Right list -->
					<div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4">
						<div class="flex items-center justify-between border-b border-gray-200/70 pb-3">
							<div class="text-sm font-semibold text-gray-900 px-2">Integrated System Fields</div>
							<div class="text-xs text-gray-600 px-2">{{ crmFields.length }} fields</div>
						</div>
						<div class="mt-4 space-y-2">
							<div
								v-for="f in crmFields"
								:key="f.key"
								class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
								@dragover.prevent
								@drop="onDropOnCrmField(f)"
							>
								<div class="min-w-0">
									<div class="truncate font-mono text-xs text-gray-900">{{ f.key }}</div>
									<div class="mt-0.5 flex items-center gap-2">
										<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ f.type }}</span>
										<span v-if="f.required" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">REQ</span>
									</div>
								</div>
								<div class="text-xs text-gray-500">Drop a system field</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Mapping rows -->
				<div class="mt-6 rounded-xl border border-gray-200">
					<div class="flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3">
						<div class="text-sm font-semibold text-gray-900">Mappings</div>
						<div class="flex items-center gap-2">
							<button
								type="button"
								class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
								@click="addMappingRow"
							>
								Add mapping row
							</button>
							<button
								type="button"
								class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
								@click="validateMappings"
							>
								Validate
							</button>
							<button
								type="button"
								class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
								:disabled="saveMappingLoading || !selectedSystemEndpoint || !selectedCrmEndpoint"
								@click="onSaveMapping"
							>
								{{ saveMappingLoading ? 'Saving…' : 'Save mapping' }}
							</button>
						</div>
					</div>
					<div v-if="selectedSavedMapping || saveMappingError" class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs">
						<span v-if="saveMappingError" class="text-red-600">{{ saveMappingError }}</span>
						<span v-else class="text-gray-600">
							Saved mapping loaded. Last updated {{ new Date(selectedSavedMapping.updated_at).toLocaleString() }}.
						</span>
					</div>

					<div class="overflow-x-auto bg-white">
						<table class="min-w-full text-left text-sm">
							<thead class="bg-gray-50 text-xs font-semibold text-gray-700">
								<tr>
									<th class="px-4 py-3">Source field</th>
									<th class="px-4 py-3">Destination field</th>
									<th class="px-4 py-3">Transformation rule</th>
									<th class="px-4 py-3">Required</th>
									<th class="px-4 py-3 text-right">Remove</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<tr v-for="(row, idx) in mappingRows" :key="row.id">
									<td class="px-4 py-3">
										<select v-model="row.source" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm">
											<option value="">—</option>
											<option v-for="f in systemFields" :key="f.key" :value="f.key">{{ f.key }}</option>
										</select>
										<div v-if="row.errors.source" class="mt-1 text-xs text-red-600">{{ row.errors.source }}</div>
									</td>
									<td class="px-4 py-3">
										<select v-model="row.dest" class="w-full rounded-lg border border-gray-300 bg-white px-2 py-2 text-sm">
											<option value="">—</option>
											<option v-for="f in crmFields" :key="f.key" :value="f.key">{{ f.key }}</option>
										</select>
										<div v-if="row.errors.dest" class="mt-1 text-xs text-red-600">{{ row.errors.dest }}</div>
									</td>
									<td class="px-4 py-3">
										<input
											v-model="row.transform"
											type="text"
											placeholder="e.g. trim(), lowercase(), mapStatus()…"
											class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm"
										/>
									</td>
									<td class="px-4 py-3">
										<label class="inline-flex items-center gap-2">
											<input v-model="row.required" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-200" />
											<span class="text-sm text-gray-700">Required</span>
										</label>
									</td>
									<td class="px-4 py-3 text-right">
										<button
											type="button"
											class="rounded-lg px-2 py-1 text-sm font-medium text-red-700 hover:bg-red-50"
											@click="removeMappingRow(idx)"
										>
											Remove
										</button>
									</td>
								</tr>
								<tr v-if="mappingRows.length === 0">
									<td class="px-4 py-6 text-center text-sm text-gray-600" colspan="5">
										No mappings yet. Drag a system field onto an Integrated System field, or click “Add mapping row”.
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Bottom tabs -->
		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="border-b border-gray-100 px-6 py-4">
				<div class="flex flex-wrap items-center gap-2">
					<button v-for="t in tabs" :key="t" type="button" class="tabBtn" :class="activeTab === t ? 'tabBtnActive' : ''" @click="activeTab = t">
						{{ t }}
					</button>
				</div>
			</div>
			<div class="p-6">
				<div v-if="activeTab === 'API Tester'" class="space-y-3">
					<div class="text-sm text-gray-700">Run a dry-run request with the current mapping (UI scaffolding).</div>
					<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 font-mono text-xs text-gray-700">
						Select endpoints, build mappings, then use “Test Integration”.
					</div>
				</div>
				<div v-else-if="activeTab === 'Logs'" class="text-sm text-gray-700">Integration logs will appear here (placeholder).</div>
				<div v-else-if="activeTab === 'Webhook Events'" class="text-sm text-gray-700">Webhook events (incoming/outgoing) (placeholder).</div>
				<div v-else-if="activeTab === 'Transformation Preview'" class="text-sm text-gray-700">Preview transformed payloads (placeholder).</div>
				<div v-else-if="activeTab === 'Error Handling'" class="text-sm text-gray-700">Configure retries, fallbacks, and error mapping (placeholder).</div>
				<div v-else class="text-sm text-gray-700">Mapping versions and change history (placeholder).</div>
			</div>
		</div>

		<!-- Lightweight notification -->
		<div v-if="toast" class="fixed bottom-6 right-6 z-50">
			<div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-lg ring-1 ring-black/5">
				<div class="text-sm font-semibold text-gray-900">{{ toast.title }}</div>
				<div class="mt-0.5 text-sm text-gray-600">{{ toast.message }}</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { axios } from '@/bootstrap';
import { getCrmSystemApiMappingsForSlug } from '@/data/crmSystemApiMappings';

const props = defineProps({
	savedMappings: {
		type: Array,
		default: () => [],
	},
});

const page = usePage();
const globalSearch = ref('');

const loadingSystemEndpoints = ref(false);
const systemEndpointsError = ref('');
const systemEndpoints = ref([]);
const selectedSystemEndpointId = ref('');

const defaultCrmIntegrations = [
	{ name: 'Salesforce', slug: 'salesforce' },
	{ name: 'HubSpot', slug: 'hubspot' },
	{ name: 'Zoho', slug: 'zoho' },
	{ name: 'GoHighLevel', slug: 'gohighlevel' },
	{ name: 'Pipedrive', slug: 'pipedrive' },
	{ name: 'Freshsales', slug: 'freshsales' },
];

/** Normalized contact fields returned by `/api/crm/contacts` and `/api/crm/contacts/search`. */
const NORMALIZED_CONTACT_RESPONSE_FIELDS = [
	{ key: 'id', type: 'string', required: false },
	{ key: 'firstName', type: 'string', required: false },
	{ key: 'lastName', type: 'string', required: false },
	{ key: 'companyName', type: 'string', required: false },
	{ key: 'businessInfo', type: 'string', required: false },
	{ key: 'email', type: 'string', required: false },
	{ key: 'phone', type: 'string', required: false },
	{ key: 'source', type: 'string', required: false },
	{ key: 'type', type: 'string', required: false },
	{ key: 'assignedTo', type: 'string', required: false },
	{ key: 'city', type: 'string', required: false },
	{ key: 'state', type: 'string', required: false },
	{ key: 'postalCode', type: 'string', required: false },
	{ key: 'address', type: 'string', required: false },
	{ key: 'dateAdded', type: 'string', required: false },
	{ key: 'dateUpdated', type: 'string', required: false },
	{ key: 'dateOfBirth', type: 'string', required: false },
	{ key: 'tags', type: 'string[]', required: false },
	{ key: 'country', type: 'string', required: false },
	{ key: 'website', type: 'string', required: false },
	{ key: 'timezone', type: 'string', required: false },
	{ key: 'profilePhoto', type: 'string', required: false },
];

const NORMALIZED_CONTACT_SAMPLE = {
	id: 'crm_contact_123',
	firstName: 'Jane',
	lastName: 'Doe',
	companyName: 'Acme Inc',
	businessInfo: '',
	email: 'jane@example.com',
	phone: '+1 555 0100',
	source: 'web',
	type: 'lead',
	assignedTo: 'user_123',
	city: 'Austin',
	state: 'TX',
	postalCode: '78701',
	address: '123 Main St',
	dateAdded: '2026-01-01T00:00:00Z',
	dateUpdated: '2026-01-02T00:00:00Z',
	dateOfBirth: '1990-05-15',
	tags: ['vip', 'newsletter'],
	country: 'US',
	website: 'https://example.com',
	timezone: 'America/Chicago',
	profilePhoto: '',
};
const crmIntegrations = computed(() => {
	const integrations = page.props.crm?.integrations;
	if (Array.isArray(integrations) && integrations.length > 0) {
		return integrations.filter((row) => row?.slug);
	}

	return defaultCrmIntegrations;
});
const selectedCrm = ref('');
const selectedCrmEndpointKey = ref('');

const tabs = ['API Tester', 'Logs', 'Webhook Events', 'Transformation Preview', 'Error Handling', 'Version History'];
const activeTab = ref('API Tester');

const toast = ref(null);
let toastTimer = null;
const savedMappings = ref([...props.savedMappings]);
const saveMappingLoading = ref(false);
const saveMappingError = ref('');

function notify(title, message) {
	toast.value = { title, message };
	if (toastTimer) window.clearTimeout(toastTimer);
	toastTimer = window.setTimeout(() => (toast.value = null), 2400);
}

async function refreshSystemEndpoints() {
	loadingSystemEndpoints.value = true;
	systemEndpointsError.value = '';
	try {
		const { data } = await axios.get(route('admin.api-endpoint-mapper.system-endpoints'));
		systemEndpoints.value = data?.data || [];
	} catch (e) {
		systemEndpointsError.value = 'Failed to load system endpoints. Check permissions or server logs.';
	} finally {
		loadingSystemEndpoints.value = false;
	}
}

onMounted(async () => {
	await refreshSystemEndpoints();
});

const filteredSystemEndpoints = computed(() => {
	const q = globalSearch.value.trim().toLowerCase();
	if (!q) return systemEndpoints.value;
	return systemEndpoints.value.filter((e) => {
		const hay = `${e.method} ${e.uri} ${e.name || ''} ${e.description || ''}`.toLowerCase();
		return hay.includes(q);
	});
});

const selectedSystemEndpoint = computed(() => {
	if (!selectedSystemEndpointId.value) return null;
	return systemEndpoints.value.find((e) => e.id === selectedSystemEndpointId.value) || null;
});

const crmCatalog = computed(() => {
	const bySlug = {
		salesforce: [
			{
				key: 'sf.createLead',
				name: 'Create Lead',
				method: 'POST',
				url: '/services/data/vXX.X/sobjects/Lead',
				description: 'Create a lead record in Salesforce.',
				auth: 'OAuth 2.0 (Bearer token)',
				docsUrl: 'https://developer.salesforce.com/docs',
				schema: [
					{ key: 'FirstName', type: 'string', required: false },
					{ key: 'LastName', type: 'string', required: true },
					{ key: 'Email', type: 'string', required: false },
					{ key: 'Phone', type: 'string', required: false },
				],
				samplePayload: { FirstName: 'Jane', LastName: 'Doe', Email: 'jane@example.com', Phone: '+1 555 0100' },
			},
		],
		hubspot: [
			{
				key: 'hs.createContact',
				name: 'Create Contact',
				method: 'POST',
				url: '/crm/v3/objects/contacts',
				description: 'Create a contact in HubSpot.',
				auth: 'Private app token / OAuth',
				docsUrl: 'https://developers.hubspot.com/docs/api/crm/contacts',
				schema: [
					{ key: 'properties.email', type: 'string', required: true },
					{ key: 'properties.firstname', type: 'string', required: false },
					{ key: 'properties.lastname', type: 'string', required: false },
					{ key: 'properties.phone', type: 'string', required: false },
				],
				samplePayload: { properties: { email: 'jane@example.com', firstname: 'Jane', lastname: 'Doe', phone: '+1 555 0100' } },
			},
		],
		zoho: [
			{
				key: 'zoho.listContacts',
				name: 'List contacts',
				method: 'GET',
				url: '/api/crm/contacts',
				description:
					'MysimConnect list contacts (maps Zoho CRM Contacts to normalized fields). Upstream: GET /crm/v8/Contacts.',
				auth: 'OAuth 2.0 (Zoho-oauthtoken)',
				docsUrl: 'https://www.zoho.com/crm/developer/docs/api/v8/get-records.html',
				schema: [
					{ key: 'query.user_id', type: 'number', required: true },
					{ key: 'query.limit', type: 'number', required: false },
					...NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
						...f,
						key: `response.contacts[].${f.key}`,
					})),
				],
				samplePayload: { user_id: 1, limit: 20 },
				sampleResponse: { contacts: [NORMALIZED_CONTACT_SAMPLE], meta: {} },
			},
			{
				key: 'zoho.searchContacts',
				name: 'Search contacts',
				method: 'POST',
				url: '/api/crm/contacts/search',
				description:
					'MysimConnect search contacts (maps Zoho CRM Contacts search to normalized fields). Upstream: GET /crm/v8/Contacts/search.',
				auth: 'OAuth 2.0 (Zoho-oauthtoken)',
				docsUrl: 'https://www.zoho.com/crm/developer/docs/api/v8/search-records.html',
				schema: [
					{ key: 'body.user_id', type: 'number', required: true },
					{ key: 'body.query', type: 'string', required: false },
					{ key: 'body.pageLimit', type: 'number', required: false },
					...NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
						...f,
						key: `response.contacts[].${f.key}`,
					})),
				],
				samplePayload: { user_id: 1, query: 'jane@example.com', pageLimit: 20 },
				sampleResponse: { contacts: [NORMALIZED_CONTACT_SAMPLE], meta: {} },
			},
			{
				key: 'zoho.createLead',
				name: 'Create Lead',
				method: 'POST',
				url: '/crm/v2/Leads',
				description: 'Create a lead in Zoho CRM.',
				auth: 'OAuth 2.0',
				docsUrl: 'https://www.zoho.com/crm/developer/docs/api/v2/',
				schema: [
					{ key: 'data[0].Last_Name', type: 'string', required: true },
					{ key: 'data[0].Email', type: 'string', required: false },
					{ key: 'data[0].Phone', type: 'string', required: false },
				],
				samplePayload: { data: [{ Last_Name: 'Doe', Email: 'jane@example.com', Phone: '+1 555 0100' }] },
			},
		],
		gohighlevel: [
			{
				key: 'ghl.getContacts',
				name: 'List contacts',
				method: 'GET',
				url: '/api/crm/contacts',
				description:
					'MysimConnect list contacts (maps Lead Connector contacts to normalized fields). Upstream: GET /contacts/.',
				auth: 'Bearer OAuth token; Version header (e.g. 2021-07-28)',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'query.user_id', type: 'number', required: true },
					{ key: 'query.limit', type: 'number', required: false },
					...NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
						...f,
						key: `response.contacts[].${f.key}`,
					})),
				],
				samplePayload: { user_id: 1, limit: 20 },
				sampleResponse: { contacts: [NORMALIZED_CONTACT_SAMPLE], meta: {} },
			},
			{
				key: 'ghl.searchContacts',
				name: 'Search contacts',
				method: 'POST',
				url: '/api/crm/contacts/search',
				description:
					'MysimConnect search contacts (maps Lead Connector search to normalized fields). Upstream: POST /contacts/search.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'body.user_id', type: 'number', required: true },
					{ key: 'body.query', type: 'string', required: false },
					{ key: 'body.pageLimit', type: 'number', required: false },
					...NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
						...f,
						key: `response.contacts[].${f.key}`,
					})),
				],
				samplePayload: { user_id: 1, query: 'jane@example.com', pageLimit: 20 },
				sampleResponse: { contacts: [NORMALIZED_CONTACT_SAMPLE], meta: {} },
			},
			{
				key: 'ghl.getUsers',
				name: 'List users',
				method: 'GET',
				url: '/api/crm/users',
				description: 'MysimConnect list users. Upstream: GET /users/.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'query.user_id', type: 'number', required: true },
				],
				samplePayload: { user_id: 1 },
			},
			{
				key: 'ghl.getLocationTags',
				name: 'List location tags',
				method: 'GET',
				url: '/api/crm/tags',
				description: 'MysimConnect list location tags. Upstream: GET /locations/{locationId}/tags.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'query.user_id', type: 'number', required: true },
				],
				samplePayload: { user_id: 1 },
			},
			{
				key: 'ghl.addContactTags',
				name: 'Add, update, or remove contact tags',
				method: 'POST',
				url: '/api/crm/contacts/add/tags',
				description: 'Use this endpoint for contact tag add, update, and remove actions. Send contactid in the request body.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'contactid', type: 'string', required: true },
					{ key: 'tags', type: 'array', required: true },
				],
				samplePayload: { contactid: '550e8400-e29b-41d4-a716-446655440000', tags: ['tag_id_1', 'tag_id_2'] },
				sampleResponse: { tags: ['sent whatsapp', 'friendly', 'hni'], message: 'tags updated', status: true },
			},
			{
				key: 'ghl.getContactNotes',
				name: 'List contact notes',
				method: 'GET',
				url: '/api/crm/contacts/notes/list',
				description: 'List notes for a contact. Send contactId as a query parameter.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'query.contactId', type: 'string', required: true },
					{ key: 'query.contact', type: 'string', required: false },
					{ key: 'query.user_id', type: 'number', required: true },
				],
				samplePayload: { user_id: 1, contactId: '550e8400-e29b-41d4-a716-446655440000' },
				sampleResponse: {
					success: true,
					status: true,
					notes: [
						{
							id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
							body: 'Call: INCOMING\nNumber: +919910023290\nContact: Ankur Wadhawan\nDuration: 18s\nAt: 2026-05-06 02:46:31',
							attachments: [
								{
									filetype: 'mp3',
									fileshorturl: 'http://127.0.0.1:8000/surl/6Q7IMA8Y',
									fileslongurl: 'https://your-storage.example/uploads/recording.mp3',
								},
							],
							title: '',
							user_name: 'Excellanto Developers',
							userId: 'TlWn93srwc6WyxUYy98a',
							contactId: '550e8400-e29b-41d4-a716-446655440000',
							dateAdded: '2026-05-05T21:16:56.211Z',
							dateUpdated: '',
						},
					],
					meta: {},
				},
			},
			{
				key: 'ghl.createContactNote',
				name: 'Create contact note',
				method: 'POST',
				url: '/api/crm/contacts/notes/add',
				description: 'Add a note to a contact. Returns only the latest added note.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'body.user_id', type: 'number', required: true },
					{ key: 'body.contactId', type: 'string', required: true },
					{ key: 'body.body', type: 'string', required: true },
					{ key: 'body.urls', type: 'array', required: false },
				],
				samplePayload: {
					user_id: 10,
					contactId: '1303041000000523005',
					body: 'Prospect wanted to schedule call on Friday at 11:30 AM',
				},
				sampleResponse: {
					success: true,
					status: true,
					notes: [
						{
							id: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
							body: 'Prospect wanted to schedule call on Friday at 11:30 AM',
							attachments: ['https://your-s3-bucket.com/uploads/audio_brief.mp3'],
							title: '',
							user_name: 'Excellanto Developers',
							userId: '1303041000000453001',
							contactId: '1303041000000523005',
							dateAdded: '2026-05-05T21:16:56.211Z',
							dateUpdated: '',
						},
					],
				},
			},
			{
				key: 'ghl.updateContactNote',
				name: 'Update contact note',
				method: 'POST',
				url: '/api/crm/contacts/notes/update',
				description: 'Update a note using the same payload as add note, plus noteid.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'body.noteid', type: 'string', required: true },
					{ key: 'body.user_id', type: 'number', required: true },
					{ key: 'body.contactId', type: 'string', required: true },
					{ key: 'body.body', type: 'string', required: true },
					{ key: 'body.urls', type: 'array', required: true },
				],
				samplePayload: {
					noteid: 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
					user_id: 10,
					contactId: '1303041000000523005',
					body: '2 NOW Prospect wanted to schedule call on Friday at 11:30 AM',
					urls: ['https://your-s3-bucket.com/uploads/audio_brief.mp3'],
				},
			},
			{
				key: 'ghl.deleteContactNote',
				name: 'Delete contact note',
				method: 'POST',
				url: '/api/crm/contacts/notes/delete',
				description: 'Delete a note using noteid, user_id, and contactId in the request body.',
				auth: 'Bearer OAuth token; Version header',
				docsUrl: 'https://marketplace.gohighlevel.com/docs/ghl/',
				schema: [
					{ key: 'body.noteid', type: 'string', required: true },
					{ key: 'body.user_id', type: 'number', required: true },
					{ key: 'body.contactId', type: 'string', required: true },
				],
				samplePayload: {
					noteid: 'jyukCGNByCVyoOfXMOjm',
					user_id: 6,
					contactId: 'o4gTsYqevQpfwrjzfMFN',
				},
			},
		],
		pipedrive: [
			{
				key: 'pd.createPerson',
				name: 'Create Person',
				method: 'POST',
				url: '/v1/persons',
				description: 'Create a person record in Pipedrive.',
				auth: 'API token / OAuth',
				docsUrl: 'https://developers.pipedrive.com/docs/api/v1/',
				schema: [
					{ key: 'name', type: 'string', required: true },
					{ key: 'email', type: 'string', required: false },
					{ key: 'phone', type: 'string', required: false },
				],
				samplePayload: { name: 'Jane Doe', email: 'jane@example.com', phone: '+1 555 0100' },
			},
		],
		freshsales: [
			{
				key: 'fs.createContact',
				name: 'Create Contact',
				method: 'POST',
				url: '/api/contacts',
				description: 'Create a contact in Freshsales.',
				auth: 'API key',
				docsUrl: 'https://developers.freshworks.com/crm/api/',
				schema: [
					{ key: 'contact.first_name', type: 'string', required: false },
					{ key: 'contact.last_name', type: 'string', required: true },
					{ key: 'contact.email', type: 'string', required: false },
					{ key: 'contact.mobile_number', type: 'string', required: false },
				],
				samplePayload: { contact: { first_name: 'Jane', last_name: 'Doe', email: 'jane@example.com', mobile_number: '+1 555 0100' } },
			},
		],
	};

	return bySlug;
});

function requestObjectToFields(prefix, value) {
	if (!value || typeof value !== 'object' || Array.isArray(value)) return [];

	return Object.keys(value).map((key) => ({
		key: `${prefix}.${key}`,
		type: Array.isArray(value[key]) ? 'array' : typeof value[key],
		required: false,
		direction: 'request',
	}));
}

function responseFieldsForDocumentedEndpoint(row) {
	if (row.key?.includes('contacts')) {
		return NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
			...f,
			key: `response.contacts[].${f.key}`,
			direction: 'response',
		}));
	}

	if (row.key?.includes('users')) {
		return ['id', 'name', 'email', 'phone', 'role'].map((key) => ({
			key: `response.users[].${key}`,
			type: 'string',
			required: false,
			direction: 'response',
		}));
	}

	if (row.key === 'ghl.tags' || row.key?.includes('contacts.tags')) {
		return ['id', 'name'].map((key) => ({
			key: `response.tags[].${key}`,
			type: 'string',
			required: false,
			direction: 'response',
		}));
	}

	if (row.key?.includes('notes')) {
		return [
			{ key: 'response.notes[].id', type: 'string', required: false, direction: 'response' },
			{ key: 'response.notes[].body', type: 'string', required: false, direction: 'response' },
			{ key: 'response.note.id', type: 'string', required: false, direction: 'response' },
			{ key: 'response.note.body', type: 'string', required: false, direction: 'response' },
		];
	}

	return [];
}

function documentedEndpointToCatalogEndpoint(row, integration) {
	const request = row.request || {};
	const requestSchema = [
		...requestObjectToFields('query', request.query),
		...requestObjectToFields('path', request.path),
		...requestObjectToFields('body', request.body),
	];
	const responseSchema = responseFieldsForDocumentedEndpoint(row);

	return {
		key: row.key,
		name: row.name,
		method: row.crmMethod,
		url: row.crmPath,
		description: `Documented mapping for ${row.systemMethod} ${row.systemPath}.`,
		auth: row.headersNote || 'Use the integrated system documentation auth requirements.',
		docsUrl: integration?.documentation || '#',
		schema: [...requestSchema, ...responseSchema],
		requestSchema,
		responseSchema,
		samplePayload: {
			...(request.query ? { query: request.query } : {}),
			...(request.path ? { path: request.path } : {}),
			...(request.body ? { body: request.body } : {}),
		},
		systemPath: row.systemPath,
		systemMethod: row.systemMethod,
		routeName: row.routeName,
	};
}

const documentedCrmCatalog = computed(() => {
	const bySlug = {};
	for (const integration of crmIntegrations.value) {
		const rows = getCrmSystemApiMappingsForSlug(integration.slug);
		if (rows.length > 0) {
			bySlug[integration.slug] = rows.map((row) => documentedEndpointToCatalogEndpoint(row, integration));
		}
	}

	return bySlug;
});

function endpointsForSelectedCrm() {
	if (!selectedCrm.value) return [];
	const documented = documentedCrmCatalog.value[selectedCrm.value] || [];
	return documented.length > 0 ? documented : crmCatalog.value[selectedCrm.value] || [];
}

const filteredCrmEndpoints = computed(() => {
	if (!selectedCrm.value) return [];
	const endpoints = endpointsForSelectedCrm();
	const q = globalSearch.value.trim().toLowerCase();
	if (!q) return endpoints;
	return endpoints.filter((e) => `${e.name} ${e.method} ${e.url} ${e.description}`.toLowerCase().includes(q));
});

const crmEndpointPlaceholder = computed(() => {
	if (!selectedCrm.value) return 'Select an Integrated System first…';
	if (endpointsForSelectedCrm().length === 0) return 'No endpoints configured for this Integrated System…';
	return 'Select an endpoint…';
});

const selectedCrmEndpoint = computed(() => {
	if (!selectedCrm.value || !selectedCrmEndpointKey.value) return null;
	return endpointsForSelectedCrm().find((e) => e.key === selectedCrmEndpointKey.value) || null;
});

watch(selectedCrm, () => {
	selectedCrmEndpointKey.value = '';
});

watch(crmIntegrations, (available) => {
	const slugs = available.map((row) => row.slug);
	if (selectedCrm.value && !slugs.includes(selectedCrm.value)) {
		selectedCrm.value = '';
	}
});

const systemAuthType = computed(() => {
	// Best-effort: infer from middleware array.
	const mw = selectedSystemEndpoint.value?.middleware || [];
	if (mw.some((m) => String(m).includes('auth'))) return 'Session auth (Laravel auth middleware)';
	if (mw.some((m) => String(m).includes('sanctum'))) return 'Token auth (Sanctum)';
	return 'Varies by route (see middleware)';
});

function guessSystemFieldsFromUri(uri) {
	const base = [
		{ key: 'customer_name', type: 'string', required: true },
		{ key: 'phone', type: 'string', required: false },
		{ key: 'email', type: 'string', required: false },
		{ key: 'call_notes', type: 'string', required: false },
	];

	if (!uri) return base;
	if (uri.includes('users')) {
		return [
			{ key: 'name', type: 'string', required: true },
			{ key: 'email', type: 'string', required: true },
			{ key: 'phone', type: 'string', required: false },
			{ key: 'role', type: 'string', required: false },
		];
	}
	if (uri.includes('leads')) {
		return [
			{ key: 'lead.name', type: 'string', required: true },
			{ key: 'lead.email', type: 'string', required: false },
			{ key: 'lead.phone', type: 'string', required: false },
			{ key: 'lead.source', type: 'string', required: false },
		];
	}
	if (uri.includes('contacts')) {
		return NORMALIZED_CONTACT_RESPONSE_FIELDS.map((f) => ({
			...f,
			key: `contacts[].${f.key}`,
		}));
	}
	if (uri.includes('opportunities')) {
		return [
			{ key: 'opportunity.id', type: 'string', required: false },
			{ key: 'opportunity.name', type: 'string', required: true },
			{ key: 'opportunity.value', type: 'number', required: false },
			{ key: 'opportunity.status', type: 'string', required: false },
		];
	}
	return base;
}

const systemFields = computed(() => guessSystemFieldsFromUri(selectedSystemEndpoint.value?.uri || ''));
const crmFields = computed(() => selectedCrmEndpoint.value?.schema || []);
const crmRequestFields = computed(() =>
	selectedCrmEndpoint.value?.requestSchema || crmFields.value.filter((f) => f.direction !== 'response'),
);
const crmResponseFields = computed(() =>
	selectedCrmEndpoint.value?.responseSchema || crmFields.value.filter((f) => f.direction === 'response'),
);

const systemHeaders = computed(() => ({
	Accept: 'application/json',
	'Content-Type': 'application/json',
}));

const systemQueryParams = computed(() => {
	const uri = selectedSystemEndpoint.value?.uri || '';
	const hasId = uri.includes('{') || uri.includes('}') || uri.includes(':id');
	return hasId ? [{ name: 'id', required: true }] : [];
});

const systemSamplePayload = computed(() => {
	const payload = {};
	for (const f of systemFields.value) {
		if (f.type === 'number') payload[f.key] = 123;
		else payload[f.key] = f.key.includes('email') ? 'jane@example.com' : f.key.includes('phone') ? '+1 555 0100' : 'sample';
	}
	return payload;
});

const systemExpectedResponse = computed(() => {
	const uri = selectedSystemEndpoint.value?.uri || '';
	if (uri.includes('contacts')) {
		return { contacts: [NORMALIZED_CONTACT_SAMPLE], meta: {} };
	}
	return {
		success: true,
		data: { id: 'res_123', status: 'ok' },
	};
});

function pretty(obj) {
	return JSON.stringify(obj, null, 2);
}

async function copyJson(obj) {
	try {
		await navigator.clipboard.writeText(pretty(obj));
		notify('Copied', 'JSON copied to clipboard.');
	} catch (e) {
		notify('Copy failed', 'Your browser blocked clipboard access.');
	}
}

function onViewFullSchema(kind) {
	// For now, link to the internal API docs page if available.
	if (kind === 'system') {
		window.open('/docs/mysimconnect-api', '_blank', 'noopener,noreferrer');
		return;
	}
	if (kind === 'crm' && selectedCrmEndpoint.value?.docsUrl) {
		window.open(selectedCrmEndpoint.value.docsUrl, '_blank', 'noopener,noreferrer');
	}
}

async function onSaveMapping() {
	saveMappingError.value = '';
	if (!selectedSystemEndpoint.value || !selectedCrmEndpoint.value) {
		saveMappingError.value = 'Select both endpoints before saving.';
		return;
	}
	if (!validateMappings(false)) {
		return;
	}

	saveMappingLoading.value = true;
	try {
		const payload = {
			integration_slug: selectedCrm.value,
			system_endpoint_id: selectedSystemEndpoint.value.id,
			system_method: selectedSystemEndpoint.value.method,
			system_uri: selectedSystemEndpoint.value.uri,
			system_name: selectedSystemEndpoint.value.name,
			crm_endpoint_key: selectedCrmEndpoint.value.key,
			crm_method: selectedCrmEndpoint.value.method,
			crm_uri: selectedCrmEndpoint.value.url,
			crm_name: selectedCrmEndpoint.value.name,
			field_mappings: mappingRows.value.map((row) => ({
				source: row.source,
				dest: row.dest,
				transform: row.transform || '',
				required: Boolean(row.required),
			})),
		};
		const { data } = await axios.post(route('admin.api-endpoint-mapper.mappings.store'), payload);
		const saved = data?.data;
		if (saved) {
			const idx = savedMappings.value.findIndex((row) => row.id === saved.id);
			if (idx === -1) {
				savedMappings.value.unshift(saved);
			} else {
				savedMappings.value.splice(idx, 1, saved);
			}
		}
		notify('Mapping saved', data?.message || 'API endpoint mapping saved.');
	} catch (e) {
		const d = e?.response?.data;
		saveMappingError.value = d?.message || e.message || 'Could not save the mapping.';
		notify('Save failed', saveMappingError.value);
	} finally {
		saveMappingLoading.value = false;
	}
}
const draggedSystemField = ref(null);
function onDragStartSystemField(f) {
	draggedSystemField.value = f;
}

const mappingRows = ref([]);

const selectedSavedMapping = computed(() => {
	if (!selectedSystemEndpoint.value || !selectedCrmEndpoint.value || !selectedCrm.value) return null;
	return (
		savedMappings.value.find(
			(row) =>
				row.integration_slug === selectedCrm.value &&
				row.system_uri === selectedSystemEndpoint.value.uri &&
				row.system_method === selectedSystemEndpoint.value.method &&
				row.crm_endpoint_key === selectedCrmEndpoint.value.key,
		) || null
	);
});

watch(
	[selectedSystemEndpointId, selectedCrm, selectedCrmEndpointKey],
	() => {
		saveMappingError.value = '';
		const saved = selectedSavedMapping.value;
		mappingRows.value = (saved?.field_mappings || []).map((row) => ({
			id: crypto?.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
			source: row.source || '',
			dest: row.dest || '',
			transform: row.transform || '',
			required: Boolean(row.required),
			errors: {},
		}));
	},
);

function addMappingRow(seed = {}) {
	mappingRows.value.push({
		id: crypto?.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()),
		source: seed.source || '',
		dest: seed.dest || '',
		transform: seed.transform || '',
		required: Boolean(seed.required),
		errors: {},
	});
}

function removeMappingRow(idx) {
	mappingRows.value.splice(idx, 1);
}

function onDropOnCrmField(crmField) {
	if (!draggedSystemField.value) return;
	const src = draggedSystemField.value.key;
	const dest = crmField.key;
	addMappingRow({ source: src, dest });
	draggedSystemField.value = null;
	notify('Mapped', `${src} → ${dest}`);
}

function validateMappings(notifySuccess = true) {
	const systemKeys = new Set(systemFields.value.map((f) => f.key));
	const crmKeys = new Set(crmFields.value.map((f) => f.key));
	const requiredCrm = new Set(crmFields.value.filter((f) => f.required).map((f) => f.key));

	let ok = true;
	for (const row of mappingRows.value) {
		row.errors = {};
		if (!row.source) {
			row.errors.source = 'Select a source field.';
			ok = false;
		} else if (!systemKeys.has(row.source)) {
			row.errors.source = 'Unknown source field.';
			ok = false;
		}
		if (!row.dest) {
			row.errors.dest = 'Select a destination field.';
			ok = false;
		} else if (!crmKeys.has(row.dest)) {
			row.errors.dest = 'Unknown destination field.';
			ok = false;
		}
	}

	// Required field validation: ensure every required Integrated System field is mapped.
	const mappedDest = new Set(mappingRows.value.map((r) => r.dest).filter(Boolean));
	const missing = [...requiredCrm].filter((k) => !mappedDest.has(k));
	if (missing.length) {
		ok = false;
		notify('Missing required fields', `Map required Integrated System fields: ${missing.slice(0, 6).join(', ')}${missing.length > 6 ? '…' : ''}`);
	}

	if (ok && notifySuccess) notify('Validation passed', 'All mappings look consistent.');
	return ok;
}
</script>

<style scoped>
.tabBtn {
	@apply rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50;
}
.tabBtnActive {
	@apply border-primary-200 bg-primary-50 text-primary-800;
}
</style>


<template>
	<div>
		<Head title="Integrations" />

		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Integrations</h1>
			<p class="mt-1 text-sm text-gray-500">
				Configure third-party services
				<span v-if="isMaster">for a tenant company</span>
				<span v-else>for your organization</span>.
			</p>
		</div>

		<div v-if="isMaster" class="mb-4 max-w-md">
			<label for="tenant_id" class="block text-sm font-medium text-gray-700">Company</label>
			<select
				id="tenant_id"
				v-model="tenantSelection"
				class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
				@change="onTenantChange"
			>
				<option value="">Select a company…</option>
				<option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
					{{ tenant.company_name }}
				</option>
			</select>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="grid min-h-[520px] md:grid-cols-[16rem_1fr]">
				<aside class="border-b border-gray-200 bg-gray-50/60 md:border-b-0 md:border-r">
					<div class="shrink-0 border-b border-gray-200/80 px-3 py-3">
						<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Providers</p>
					</div>
					<nav class="py-2">
						<button
							v-for="panel in panels"
							:key="panel.id"
							type="button"
							class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm transition-colors"
							:class="sidebarRowClass(panel.id)"
							@click="selectedPanel = panel.id"
						>
							<span
								class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-xs font-semibold uppercase"
								:class="iconWrapClass(panel.id)"
							>
								{{ panel.initials }}
							</span>
							<span class="min-w-0 flex-1 font-medium">{{ panel.label }}</span>
						</button>
					</nav>
				</aside>

				<section class="p-6">
					<div
						v-if="!selectedTenantId"
						class="flex min-h-[320px] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 text-center"
					>
						<p class="text-sm text-gray-600">
							<span v-if="isMaster">Select a company above to manage its integration settings.</span>
							<span v-else>Your account is not linked to a tenant. Contact an administrator.</span>
						</p>
					</div>

					<template v-else-if="selectedPanel === 'openai' && openai">
						<form @submit.prevent="submitOpenAi" class="space-y-6">
							<div>
								<h2 class="text-base font-semibold text-gray-900">OpenAI</h2>
								<p class="mt-1 text-sm text-gray-500">
									API credentials and models used for voice note and call transcription.
								</p>
							</div>

							<div
								v-if="openai.using_system_api_key"
								class="max-w-xl rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
							>
								Using the system OpenAI API key. Enter a key below to override for this tenant.
							</div>
							<div
								v-else-if="!openai.is_configured"
								class="max-w-xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
							>
								No tenant or system OpenAI API key is configured. Transcription will not work until one is set.
							</div>

							<div class="max-w-xl space-y-5">
								<div>
									<label for="openai_api_key" class="block text-sm font-medium text-gray-700">OpenAI API Key</label>
									<input
										id="openai_api_key"
										v-model="openAiForm.openai_api_key"
										type="password"
										autocomplete="new-password"
										:placeholder="openai.has_tenant_api_key ? '••••••••' : 'sk-…'"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">
										<span v-if="openai.has_tenant_api_key">Leave blank to keep the existing tenant key.</span>
										<span v-else-if="openai.system_has_api_key">Leave blank to continue using the system key.</span>
										<span v-else>Enter a key to enable OpenAI for this tenant.</span>
										Enter a new value to replace the current key.
									</p>
									<p v-if="openAiForm.errors.openai_api_key" class="mt-1 text-sm text-red-600">
										{{ openAiForm.errors.openai_api_key }}
									</p>
								</div>

								<div>
									<label for="gpt_model" class="block text-sm font-medium text-gray-700">GPT Model</label>
									<select
										id="gpt_model"
										v-model="openAiForm.gpt_model"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									>
										<option v-for="model in gptModels" :key="model" :value="model">{{ model }}</option>
									</select>
									<p v-if="!openai.has_tenant_gpt_model && openai.system_has_api_key" class="mt-1 text-xs text-gray-500">
										Showing system default ({{ openai.system_gpt_model }}). Save to set a tenant-specific model.
									</p>
									<p v-if="openAiForm.errors.gpt_model" class="mt-1 text-sm text-red-600">
										{{ openAiForm.errors.gpt_model }}
									</p>
								</div>

								<div>
									<label for="whisper_model" class="block text-sm font-medium text-gray-700">Whisper Model</label>
									<input
										id="whisper_model"
										:value="openai.whisper_model"
										type="text"
										readonly
										class="mt-1 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600 shadow-sm"
									/>
								</div>
							</div>

							<div class="flex items-center justify-end border-t border-gray-100 pt-6">
								<button
									type="submit"
									:disabled="openAiForm.processing"
									class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
								>
									Save OpenAI settings
								</button>
							</div>
						</form>
					</template>

					<template v-else-if="selectedPanel === 'storage' && selectedTenantId && storage">
						<div class="space-y-6">
							<div>
								<h2 class="text-base font-semibold text-gray-900">Storage</h2>
								<p class="mt-1 text-sm text-gray-500">
									Connect a storage provider for file uploads and media. When multiple providers are configured,
									mark one as default.
								</p>
								<p v-if="storage.active_provider" class="mt-2 text-xs text-gray-500">
									Active storage:
									<span class="font-medium text-gray-700">{{ providerLabel(storage.active_provider) }}</span>
								</p>
							</div>

							<div class="border-b border-gray-200">
								<nav class="-mb-px flex flex-wrap gap-2">
									<button
										v-for="tab in storageTabs"
										:key="tab.id"
										type="button"
										class="rounded-t-lg px-3 py-2 text-sm font-medium transition-colors"
										:class="
											activeStorageTab === tab.id
												? 'border border-b-0 border-gray-200 bg-white text-primary-700'
												: 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
										"
										@click="activeStorageTab = tab.id"
									>
										{{ tab.label }}
										<span
											v-if="storage.providers[tab.id]?.is_default"
											class="ml-1.5 rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-primary-700"
										>
											Default
										</span>
									</button>
								</nav>
							</div>

							<form
								v-if="activeStorageTab === 'supabase'"
								@submit.prevent="submitStorage('supabase')"
								class="max-w-xl space-y-5"
							>
								<div
									v-if="storage.providers.supabase.using_system_fallback"
									class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
								>
									Using system Supabase settings from .env. Enter values below to override for this tenant.
								</div>

								<div>
									<label for="supabase_url" class="block text-sm font-medium text-gray-700">Project URL</label>
									<input
										id="supabase_url"
										v-model="supabaseForm.url"
										type="url"
										placeholder="https://your-project.supabase.co"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p v-if="supabaseForm.errors.url" class="mt-1 text-sm text-red-600">{{ supabaseForm.errors.url }}</p>
								</div>

								<div>
									<label for="supabase_key" class="block text-sm font-medium text-gray-700">Service Role Key</label>
									<input
										id="supabase_key"
										v-model="supabaseForm.key"
										type="password"
										autocomplete="new-password"
										:placeholder="storage.providers.supabase.has_key ? '••••••••' : 'eyJ…'"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">Leave blank to keep the existing key.</p>
									<p v-if="supabaseForm.errors.key" class="mt-1 text-sm text-red-600">{{ supabaseForm.errors.key }}</p>
								</div>

								<div>
									<label for="supabase_bucket" class="block text-sm font-medium text-gray-700">Bucket</label>
									<input
										id="supabase_bucket"
										v-model="supabaseForm.bucket"
										type="text"
										placeholder="uploads"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p v-if="supabaseForm.errors.bucket" class="mt-1 text-sm text-red-600">{{ supabaseForm.errors.bucket }}</p>
								</div>

								<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
									<label class="flex cursor-pointer items-start gap-3">
										<input
											v-model="supabaseForm.is_default"
											type="checkbox"
											:disabled="!storage.providers.supabase.is_configured && !supabaseHasInput"
											class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
										/>
										<span>
											<span class="block text-sm font-medium text-gray-900">Use as default storage</span>
											<span class="mt-0.5 block text-xs text-gray-500">
												When multiple providers are configured, the default is used for uploads.
											</span>
										</span>
									</label>
									<p v-if="supabaseForm.errors.is_default" class="mt-2 text-sm text-red-600">{{ supabaseForm.errors.is_default }}</p>
								</div>

								<div class="flex justify-end border-t border-gray-100 pt-6">
									<button
										type="submit"
										:disabled="supabaseForm.processing"
										class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
									>
										Save Supabase settings
									</button>
								</div>
							</form>

							<form
								v-else-if="activeStorageTab === 'google_drive'"
								@submit.prevent="submitStorage('google_drive')"
								class="max-w-xl space-y-5"
							>
								<div>
									<label for="google_client_id" class="block text-sm font-medium text-gray-700">Client ID</label>
									<input
										id="google_client_id"
										v-model="googleDriveForm.client_id"
										type="text"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p v-if="googleDriveForm.errors.client_id" class="mt-1 text-sm text-red-600">{{ googleDriveForm.errors.client_id }}</p>
								</div>

								<div>
									<label for="google_client_secret" class="block text-sm font-medium text-gray-700">Client Secret</label>
									<input
										id="google_client_secret"
										v-model="googleDriveForm.client_secret"
										type="password"
										autocomplete="new-password"
										:placeholder="storage.providers.google_drive.has_client_secret ? '••••••••' : ''"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">Leave blank to keep the existing secret.</p>
									<p v-if="googleDriveForm.errors.client_secret" class="mt-1 text-sm text-red-600">{{ googleDriveForm.errors.client_secret }}</p>
								</div>

								<div>
									<label for="google_folder_id" class="block text-sm font-medium text-gray-700">Folder ID</label>
									<input
										id="google_folder_id"
										v-model="googleDriveForm.folder_id"
										type="text"
										placeholder="Optional root folder"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p v-if="googleDriveForm.errors.folder_id" class="mt-1 text-sm text-red-600">{{ googleDriveForm.errors.folder_id }}</p>
								</div>

								<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
									<label class="flex cursor-pointer items-start gap-3">
										<input
											v-model="googleDriveForm.is_default"
											type="checkbox"
											:disabled="!storage.providers.google_drive.is_configured && !googleDriveHasInput"
											class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
										/>
										<span>
											<span class="block text-sm font-medium text-gray-900">Use as default storage</span>
											<span class="mt-0.5 block text-xs text-gray-500">
												When multiple providers are configured, the default is used for uploads.
											</span>
										</span>
									</label>
									<p v-if="googleDriveForm.errors.is_default" class="mt-2 text-sm text-red-600">{{ googleDriveForm.errors.is_default }}</p>
								</div>

								<div class="flex justify-end border-t border-gray-100 pt-6">
									<button
										type="submit"
										:disabled="googleDriveForm.processing"
										class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
									>
										Save Google Drive settings
									</button>
								</div>
							</form>

							<form
								v-else-if="activeStorageTab === 'r2'"
								@submit.prevent="submitStorage('r2')"
								class="max-w-xl space-y-5"
							>
								<p class="text-sm text-gray-600">
									R2 API credentials are configured in <code class="text-xs">.env</code>
									(<code class="text-xs">AWS_ACCESS_KEY_ID</code>, <code class="text-xs">AWS_SECRET_ACCESS_KEY</code>,
									<code class="text-xs">AWS_BUCKET</code>, <code class="text-xs">AWS_ENDPOINT</code>).
								</p>

								<div
									v-if="!storage.providers.r2.system_has_disk_config"
									class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
								>
									System R2 credentials are missing from .env. Add them before uploads will work.
								</div>

								<div
									v-if="storage.providers.r2.using_system_public_url"
									class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
								>
									Using the system R2 Public Development URL from <code class="text-xs">R2_PUBLIC_URL</code> in .env.
									Enter a URL below to override for this tenant.
								</div>

								<div>
									<label for="r2_public_url" class="block text-sm font-medium text-gray-700">Public Development URL</label>
									<input
										id="r2_public_url"
										v-model="r2Form.public_url"
										type="url"
										placeholder="https://pub-xxxx.r2.dev"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">
										From Cloudflare R2 → bucket → Settings → Public Development URL.
										<span v-if="storage.providers.r2.has_public_url">Leave blank to keep the existing tenant URL.</span>
										<span v-else-if="storage.providers.r2.system_has_public_url">Leave blank to use the system URL from .env.</span>
									</p>
									<p v-if="r2Form.errors.public_url" class="mt-1 text-sm text-red-600">{{ r2Form.errors.public_url }}</p>
								</div>

								<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
									<label class="flex cursor-pointer items-start gap-3">
										<input
											v-model="r2Form.is_default"
											type="checkbox"
											:disabled="!storage.providers.r2.is_configured && !r2HasInput"
											class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
										/>
										<span>
											<span class="block text-sm font-medium text-gray-900">Use as default storage</span>
											<span class="mt-0.5 block text-xs text-gray-500">
												When multiple providers are configured, the default is used for uploads.
											</span>
										</span>
									</label>
									<p v-if="r2Form.errors.is_default" class="mt-2 text-sm text-red-600">{{ r2Form.errors.is_default }}</p>
								</div>

								<div class="flex justify-end border-t border-gray-100 pt-6">
									<button
										type="submit"
										:disabled="r2Form.processing"
										class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
									>
										Save Cloudflare R2 settings
									</button>
								</div>
							</form>

							<form
								v-else-if="activeStorageTab === 'dropbox'"
								@submit.prevent="submitStorage('dropbox')"
								class="max-w-xl space-y-5"
							>
								<div>
									<label for="dropbox_app_key" class="block text-sm font-medium text-gray-700">App Key</label>
									<input
										id="dropbox_app_key"
										v-model="dropboxForm.app_key"
										type="text"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p v-if="dropboxForm.errors.app_key" class="mt-1 text-sm text-red-600">{{ dropboxForm.errors.app_key }}</p>
								</div>

								<div>
									<label for="dropbox_app_secret" class="block text-sm font-medium text-gray-700">App Secret</label>
									<input
										id="dropbox_app_secret"
										v-model="dropboxForm.app_secret"
										type="password"
										autocomplete="new-password"
										:placeholder="storage.providers.dropbox.has_app_secret ? '••••••••' : ''"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">Leave blank to keep the existing secret.</p>
									<p v-if="dropboxForm.errors.app_secret" class="mt-1 text-sm text-red-600">{{ dropboxForm.errors.app_secret }}</p>
								</div>

								<div>
									<label for="dropbox_refresh_token" class="block text-sm font-medium text-gray-700">Refresh Token</label>
									<input
										id="dropbox_refresh_token"
										v-model="dropboxForm.refresh_token"
										type="password"
										autocomplete="new-password"
										:placeholder="storage.providers.dropbox.has_refresh_token ? '••••••••' : ''"
										class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
									/>
									<p class="mt-1 text-xs text-gray-500">Optional. Leave blank to keep the existing token.</p>
									<p v-if="dropboxForm.errors.refresh_token" class="mt-1 text-sm text-red-600">{{ dropboxForm.errors.refresh_token }}</p>
								</div>

								<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
									<label class="flex cursor-pointer items-start gap-3">
										<input
											v-model="dropboxForm.is_default"
											type="checkbox"
											:disabled="!storage.providers.dropbox.is_configured && !dropboxHasInput"
											class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
										/>
										<span>
											<span class="block text-sm font-medium text-gray-900">Use as default storage</span>
											<span class="mt-0.5 block text-xs text-gray-500">
												When multiple providers are configured, the default is used for uploads.
											</span>
										</span>
									</label>
									<p v-if="dropboxForm.errors.is_default" class="mt-2 text-sm text-red-600">{{ dropboxForm.errors.is_default }}</p>
								</div>

								<div class="flex justify-end border-t border-gray-100 pt-6">
									<button
										type="submit"
										:disabled="dropboxForm.processing"
										class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
									>
										Save Dropbox settings
									</button>
								</div>
							</form>
						</div>
					</template>
				</section>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
	openai: {
		type: Object,
		default: null,
	},
	storage: {
		type: Object,
		default: null,
	},
	gptModels: {
		type: Array,
		default: () => ['gpt-4', 'gpt-4o', 'gpt-4o-mini'],
	},
	isMaster: {
		type: Boolean,
		default: false,
	},
	tenants: {
		type: Array,
		default: () => [],
	},
	selectedTenantId: {
		type: Number,
		default: null,
	},
});

const panels = [
	{ id: 'openai', label: 'OpenAI', initials: 'AI' },
	{ id: 'storage', label: 'Storage', initials: 'ST' },
];

const storageTabs = [
	{ id: 'supabase', label: 'Supabase' },
	{ id: 'r2', label: 'Cloudflare R2' },
	{ id: 'google_drive', label: 'Google Drive' },
	{ id: 'dropbox', label: 'Dropbox' },
];

const selectedPanel = ref('openai');
const activeStorageTab = ref('supabase');

const tenantSelection = ref(props.selectedTenantId ? String(props.selectedTenantId) : '');

const openAiForm = useForm({
	openai_api_key: '',
	gpt_model: props.openai?.gpt_model || 'gpt-4',
	tenant_id: props.selectedTenantId || null,
});

const supabaseForm = useForm({
	url: props.storage?.providers?.supabase?.url || '',
	key: '',
	bucket: props.storage?.providers?.supabase?.bucket || '',
	is_default: props.storage?.providers?.supabase?.is_default || false,
	tenant_id: props.selectedTenantId || null,
});

const googleDriveForm = useForm({
	client_id: props.storage?.providers?.google_drive?.client_id || '',
	client_secret: '',
	folder_id: props.storage?.providers?.google_drive?.folder_id || '',
	is_default: props.storage?.providers?.google_drive?.is_default || false,
	tenant_id: props.selectedTenantId || null,
});

const dropboxForm = useForm({
	app_key: props.storage?.providers?.dropbox?.app_key || '',
	app_secret: '',
	refresh_token: '',
	is_default: props.storage?.providers?.dropbox?.is_default || false,
	tenant_id: props.selectedTenantId || null,
});

const r2Form = useForm({
	public_url: props.storage?.providers?.r2?.public_url || '',
	is_default: props.storage?.providers?.r2?.is_default || false,
	tenant_id: props.selectedTenantId || null,
});

const supabaseHasInput = computed(
	() => Boolean(supabaseForm.url?.trim() || supabaseForm.key?.trim() || supabaseForm.bucket?.trim())
);
const googleDriveHasInput = computed(
	() => Boolean(googleDriveForm.client_id?.trim() || googleDriveForm.client_secret?.trim())
);
const dropboxHasInput = computed(
	() => Boolean(dropboxForm.app_key?.trim() || dropboxForm.app_secret?.trim())
);
const r2HasInput = computed(() => Boolean(r2Form.public_url?.trim()));

watch(
	() => props.openai,
	(openai) => {
		if (openai?.gpt_model) {
			openAiForm.gpt_model = openai.gpt_model;
		}
	},
	{ immediate: true }
);

watch(
	() => props.selectedTenantId,
	(tenantId) => {
		tenantSelection.value = tenantId ? String(tenantId) : '';
		openAiForm.tenant_id = tenantId || null;
		supabaseForm.tenant_id = tenantId || null;
		googleDriveForm.tenant_id = tenantId || null;
		dropboxForm.tenant_id = tenantId || null;
		r2Form.tenant_id = tenantId || null;
	}
);

watch(
	() => props.storage,
	(storage) => {
		if (!storage?.providers) return;

		supabaseForm.url = storage.providers.supabase?.url || '';
		supabaseForm.bucket = storage.providers.supabase?.bucket || '';
		supabaseForm.is_default = storage.providers.supabase?.is_default || false;
		supabaseForm.key = '';

		googleDriveForm.client_id = storage.providers.google_drive?.client_id || '';
		googleDriveForm.folder_id = storage.providers.google_drive?.folder_id || '';
		googleDriveForm.is_default = storage.providers.google_drive?.is_default || false;
		googleDriveForm.client_secret = '';

		dropboxForm.app_key = storage.providers.dropbox?.app_key || '';
		dropboxForm.is_default = storage.providers.dropbox?.is_default || false;
		dropboxForm.app_secret = '';
		dropboxForm.refresh_token = '';

		r2Form.public_url = storage.providers.r2?.public_url || '';
		r2Form.is_default = storage.providers.r2?.is_default || false;
	},
	{ immediate: true }
);

function providerLabel(id) {
	return storageTabs.find((tab) => tab.id === id)?.label ?? id;
}

function sidebarRowClass(panelId) {
	return selectedPanel.value === panelId
		? 'border-l-2 border-blue-600 bg-white font-medium text-gray-900'
		: 'border-l-2 border-transparent text-gray-600 hover:bg-white/80';
}

function iconWrapClass(panelId) {
	return selectedPanel.value === panelId ? 'bg-blue-50 text-blue-600' : 'bg-gray-200/70 text-gray-700';
}

function onTenantChange() {
	router.get(
		route('admin.integrations.index'),
		tenantSelection.value ? { tenant_id: tenantSelection.value } : {},
		{ preserveState: false, preserveScroll: true }
	);
}

function submitOpenAi() {
	openAiForm.put(route('admin.integrations.openai.update'), {
		preserveScroll: true,
		onSuccess: () => {
			openAiForm.openai_api_key = '';
		},
	});
}

function submitStorage(provider) {
	const forms = {
		supabase: supabaseForm,
		google_drive: googleDriveForm,
		dropbox: dropboxForm,
		r2: r2Form,
	};
	const form = forms[provider];
	if (!form) return;

	form.put(route('admin.integrations.storage.update', provider), {
		preserveScroll: true,
		onSuccess: () => {
			if (provider === 'supabase') form.key = '';
			if (provider === 'google_drive') form.client_secret = '';
			if (provider === 'dropbox') {
				form.app_secret = '';
				form.refresh_token = '';
			}
		},
	});
}
</script>

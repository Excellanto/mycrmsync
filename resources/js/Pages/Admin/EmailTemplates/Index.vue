<template>
	<div>
		<Head title="Email Templates" />

		<div class="mb-4">
			<h1 class="text-xl font-semibold text-gray-900">Email Templates</h1>
			<p class="mt-1 text-sm text-gray-500">
				Edit HTML email content for OTP login and password recovery. Use placeholders such as
				<code class="rounded bg-gray-100 px-1 py-0.5 text-xs" v-pre>{{otp_code}}</code>
				in the body and subject.
			</p>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
			<div class="border-b border-gray-200 px-6 py-4">
				<label for="template_slug" class="mb-1 block text-sm font-medium text-gray-700">Template</label>
				<select
					id="template_slug"
					v-model="selectedSlug"
					class="block w-full max-w-md rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
					@change="loadSelectedTemplate"
				>
					<option v-for="template in templates" :key="template.slug" :value="template.slug">
						{{ template.name }}
					</option>
				</select>
			</div>

			<form v-if="currentTemplate" class="space-y-6 p-6" @submit.prevent="submit">
				<div class="flex flex-wrap items-center justify-between gap-3">
					<div class="flex items-center gap-3">
						<label class="text-sm font-medium text-gray-700" for="is_active">Active</label>
						<Toggle id="is_active" v-model="form.is_active" />
						<span class="text-sm text-gray-500">
							{{ form.is_active ? 'Uses this template when sending email' : 'Falls back to built-in default' }}
						</span>
					</div>
					<div class="flex items-center gap-2">
						<button
							type="button"
							class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
							:disabled="previewing"
							@click="preview"
						>
							{{ previewing ? 'Opening…' : 'Preview' }}
						</button>
						<button
							type="submit"
							class="rounded-lg bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700 disabled:opacity-50"
							:disabled="processing"
						>
							{{ processing ? 'Saving…' : 'Save template' }}
						</button>
					</div>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700" for="subject">Subject</label>
					<input
						id="subject"
						v-model="form.subject"
						type="text"
						class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
						required
					/>
				</div>

				<div>
					<label class="mb-1 block text-sm font-medium text-gray-700" for="html_body">HTML body</label>
					<textarea
						id="html_body"
						v-model="form.html_body"
						rows="18"
						class="block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
						required
					></textarea>
				</div>

				<div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
					<p class="text-sm font-medium text-gray-800">Available placeholders</p>
					<div class="mt-2 flex flex-wrap gap-2">
						<code
							v-for="variable in currentVariables"
							:key="variable"
							class="rounded bg-white px-2 py-1 text-xs text-gray-700 ring-1 ring-gray-200"
						>
							{{ formatPlaceholder(variable) }}
						</code>
					</div>
				</div>
			</form>
		</div>
	</div>
</template>

<script setup>
import Toggle from '@/Components/Toggle.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
	templates: {
		type: Array,
		default: () => [],
	},
	selectedSlug: {
		type: String,
		default: 'login_otp',
	},
	sampleVariables: {
		type: Object,
		default: () => ({}),
	},
});

const selectedSlug = ref(props.selectedSlug);
const processing = ref(false);
const previewing = ref(false);

const form = reactive({
	subject: '',
	html_body: '',
	is_active: true,
});

const currentTemplate = computed(() => props.templates.find((t) => t.slug === selectedSlug.value) ?? null);

const currentVariables = computed(() => {
	const fromTemplate = currentTemplate.value?.variables;
	if (Array.isArray(fromTemplate) && fromTemplate.length > 0) {
		return fromTemplate;
	}
	return Object.keys(props.sampleVariables[selectedSlug.value] ?? {});
});

function formatPlaceholder(variable) {
	return `{{${variable}}}`;
}

function loadFormFromTemplate(template) {
	if (!template) return;
	form.subject = template.subject ?? '';
	form.html_body = template.html_body ?? '';
	form.is_active = Boolean(template.is_active);
}

function loadSelectedTemplate() {
	const template = props.templates.find((t) => t.slug === selectedSlug.value);
	loadFormFromTemplate(template);
}

watch(
	() => props.selectedSlug,
	(slug) => {
		selectedSlug.value = slug;
		loadSelectedTemplate();
	},
	{ immediate: true }
);

watch(
	() => props.templates,
	() => loadSelectedTemplate(),
	{ deep: true }
);

function submit() {
	if (!currentTemplate.value) return;
	processing.value = true;
	router.put(
		route('admin.email-templates.update', currentTemplate.value.slug),
		{
			subject: form.subject,
			html_body: form.html_body,
			is_active: form.is_active,
		},
		{
			preserveScroll: true,
			onFinish: () => {
				processing.value = false;
			},
		}
	);
}

async function preview() {
	if (!currentTemplate.value) return;
	previewing.value = true;
	try {
		const response = await fetch(route('admin.email-templates.preview', currentTemplate.value.slug), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'text/html',
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: JSON.stringify({
				subject: form.subject,
				html_body: form.html_body,
			}),
		});
		if (!response.ok) {
			throw new Error('Preview failed');
		}
		const html = await response.text();
		const previewWindow = window.open('', '_blank');
		if (previewWindow) {
			previewWindow.document.open();
			previewWindow.document.write(html);
			previewWindow.document.close();
		}
	} finally {
		previewing.value = false;
	}
}
</script>

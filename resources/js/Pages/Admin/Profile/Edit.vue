<template>
	<div>
		<Head title="Edit Profile" />
		<div v-if="flashSuccess" class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
			{{ flashSuccess }}
		</div>

		<div class="mb-6">
			<h1 class="text-xl font-semibold text-gray-900">Edit Profile</h1>
			<p class="mt-1 text-sm text-gray-600">
				Update your account and company details.
			</p>
		</div>

		<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
			<form @submit.prevent="submit" class="space-y-6">
				<!-- Company Details (Business/Recruiter) -->
				<div v-if="tenant" class="space-y-4">
					<h2 class="text-lg font-semibold text-gray-900">Company Details</h2>

					<!-- Company logo -->
					<div>
						<InputLabel value="Company Logo" />
						<p class="mt-1 text-xs text-gray-500">
							Recommended size 250 × 100 px. Click to upload and crop.
						</p>
						<div class="mt-2 flex flex-wrap items-start gap-3">
							<button
								type="button"
								class="group relative flex h-[100px] w-[250px] max-w-full shrink-0 cursor-pointer overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-100 transition hover:border-indigo-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
								@click="openFilePicker"
							>
								<img
									v-if="tenant.company_logo_url"
									:src="tenant.company_logo_url"
									alt="Company logo"
									class="h-full w-full object-cover"
								/>
								<div
									v-else
									class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-200 to-gray-300 text-3xl font-bold tracking-tight text-gray-600"
								>
									{{ companyInitials(form.company_name) }}
								</div>
								<span
									class="absolute inset-0 flex items-center justify-center bg-black/0 text-sm font-medium text-white opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100"
								>
									Change logo
								</span>
							</button>
							<div v-if="tenant.company_logo_url" class="flex flex-col gap-2">
								<button
									type="button"
									class="text-sm text-red-600 underline hover:text-red-800"
									@click="removeLogo"
								>
									Remove logo
								</button>
							</div>
						</div>
						<input
							ref="fileInputRef"
							type="file"
							class="hidden"
							accept="image/jpeg,image/png,image/webp"
							@change="onFileSelected"
						/>
					</div>

					<div>
						<InputLabel for="company_name" value="Company Name" />
						<TextInput
							id="company_name"
							v-model="form.company_name"
							type="text"
							class="mt-1 block w-full"
							required
						/>
						<InputError class="mt-2" :message="form.errors.company_name" />
					</div>
					<div>
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
					<div>
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
					<div>
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

				<!-- Account Details -->
				<div class="space-y-4">
					<h2 class="text-lg font-semibold text-gray-900">Account Details</h2>
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
					<div>
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
				</div>

				<!-- Change Password (Optional) -->
				<div class="space-y-4 border-t border-gray-200 pt-6">
					<h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
					<p class="text-sm text-gray-500">Leave blank to keep your current password.</p>
					<div>
						<InputLabel for="password" value="New Password" />
						<TextInput
							id="password"
							v-model="form.password"
							type="password"
							class="mt-1 block w-full"
							autocomplete="new-password"
						/>
						<InputError class="mt-2" :message="form.errors.password" />
					</div>
					<div>
						<InputLabel for="password_confirmation" value="Confirm New Password" />
						<TextInput
							id="password_confirmation"
							v-model="form.password_confirmation"
							type="password"
							class="mt-1 block w-full"
							autocomplete="new-password"
						/>
						<InputError class="mt-2" :message="form.errors.password_confirmation" />
					</div>
				</div>

				<div class="flex items-center gap-3 pt-4">
					<PrimaryButton
						type="submit"
						:class="{ 'opacity-25': form.processing }"
						:disabled="form.processing"
					>
						Save Changes
					</PrimaryButton>
					<Link
						:href="route('admin.dashboard')"
						class="text-sm text-gray-600 underline hover:text-gray-900"
					>
						Cancel
					</Link>
				</div>
			</form>
		</div>

		<!-- Crop dialog -->
		<PDialog
			v-model:visible="cropDialogVisible"
			modal
			header="Crop company logo"
			:style="{ width: 'min(95vw, 520px)' }"
			:dismissable-mask="true"
			@hide="onCropDialogHide"
		>
			<div class="space-y-4">
				<p class="text-sm text-gray-600">
					Drag to position. Output is fixed at <strong>250 × 100</strong> pixels (5:2).
				</p>
				<div class="max-h-[min(50vh,360px)] min-h-[200px] overflow-hidden rounded-lg bg-gray-900/5">
					<Cropper
						v-if="cropImageSrc"
						ref="cropperRef"
						class="cropper h-[min(50vh,360px)]"
						:src="cropImageSrc"
						:stencil-props="{
							aspectRatio: LOGO_ASPECT,
						}"
					/>
				</div>
				<div class="flex justify-end gap-2 pt-2">
					<PButton label="Cancel" severity="secondary" text @click="cropDialogVisible = false" />
					<PButton
						label="Save logo"
						icon="pi pi-check"
						:loading="logoUploading"
						@click="applyCropAndUpload"
					/>
				</div>
			</div>
		</PDialog>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const LOGO_W = 250;
const LOGO_H = 100;
const LOGO_ASPECT = LOGO_W / LOGO_H;

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const props = defineProps({
	user: {
		type: Object,
		required: true,
	},
	tenant: {
		type: Object,
		default: null,
	},
});

const form = useForm({
	company_name: props.tenant?.company_name ?? '',
	account_type: props.tenant?.account_type ?? 'Business',
	pan_card: props.tenant?.pan_card ?? '',
	gst_number: props.tenant?.gst_number ?? '',
	name: props.user.name,
	email: props.user.email,
	password: '',
	password_confirmation: '',
});

const fileInputRef = ref(null);
const cropperRef = ref(null);
const cropDialogVisible = ref(false);
const cropImageSrc = ref('');
const logoUploading = ref(false);

function companyInitials(name) {
	if (!name?.trim()) return 'CO';
	const parts = name.trim().split(/\s+/).filter(Boolean);
	if (parts.length >= 2) {
		return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase().slice(0, 2);
	}
	return name.trim().slice(0, 2).toUpperCase();
}

function openFilePicker() {
	fileInputRef.value?.click();
}

function onFileSelected(e) {
	const file = e.target.files?.[0];
	if (!file) return;
	if (!file.type.startsWith('image/')) return;
	if (cropImageSrc.value) {
		URL.revokeObjectURL(cropImageSrc.value);
	}
	cropImageSrc.value = URL.createObjectURL(file);
	cropDialogVisible.value = true;
	e.target.value = '';
}

function onCropDialogHide() {
	if (cropImageSrc.value) {
		URL.revokeObjectURL(cropImageSrc.value);
		cropImageSrc.value = '';
	}
}

function canvasToJpegBlob(canvas) {
	return new Promise((resolve, reject) => {
		canvas.toBlob(
			(blob) => {
				if (blob) resolve(blob);
				else reject(new Error('Could not create image'));
			},
			'image/jpeg',
			0.92
		);
	});
}

async function applyCropAndUpload() {
	const cropper = cropperRef.value;
	if (!cropper) return;

	const result = cropper.getResult();
	if (!result?.canvas) return;

	const src = result.canvas;
	const out = document.createElement('canvas');
	out.width = LOGO_W;
	out.height = LOGO_H;
	const ctx = out.getContext('2d');
	if (!ctx) return;
	ctx.imageSmoothingEnabled = true;
	ctx.imageSmoothingQuality = 'high';
	ctx.drawImage(src, 0, 0, LOGO_W, LOGO_H);

	logoUploading.value = true;
	try {
		const blob = await canvasToJpegBlob(out);
		const file = new File([blob], 'company-logo.jpg', { type: 'image/jpeg' });
		router.post(route('admin.profile.company-logo.store'), { logo: file }, {
			forceFormData: true,
			preserveScroll: true,
			onFinish: () => {
				logoUploading.value = false;
				cropDialogVisible.value = false;
				onCropDialogHide();
			},
		});
	} catch {
		logoUploading.value = false;
	}
}

function removeLogo() {
	if (!confirm('Remove the company logo?')) return;
	router.delete(route('admin.profile.company-logo.destroy'), {
		preserveScroll: true,
	});
}

const submit = () => {
	form.put(route('admin.profile.update'), {
		preserveScroll: true,
		onSuccess: () => form.reset('password', 'password_confirmation'),
	});
};
</script>

<style scoped>
.cropper :deep(.vue-simple-handler) {
	width: 12px;
	height: 12px;
}
</style>

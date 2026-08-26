<template>
	<div>
		<InputLabel value="Profile Logo" />
		<p class="mt-1 text-xs text-gray-500">
			Upload an image and crop to a fixed <strong>4:1</strong> aspect ratio ({{ LOGO_W }} × {{ LOGO_H }} px).
		</p>
		<div class="mt-2 flex flex-wrap items-start gap-3">
			<button
				type="button"
				class="group relative flex aspect-[4/1] w-full max-w-md shrink-0 cursor-pointer overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-100 transition hover:border-indigo-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
				@click="openFilePicker"
			>
				<img
					v-if="logoUrl"
					:src="logoUrl"
					alt="Profile logo"
					class="h-full w-full object-cover"
				/>
				<div
					v-else
					class="flex h-full w-full items-center justify-center bg-gradient-to-br from-slate-200 to-slate-300 text-3xl font-bold tracking-tight text-slate-600"
				>
					{{ initials }}
				</div>
				<span
					class="absolute inset-0 flex items-center justify-center bg-black/0 text-sm font-medium text-white opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100"
				>
					{{ logoUrl ? 'Change logo' : 'Upload logo' }}
				</span>
			</button>
			<div v-if="logoUrl" class="flex flex-col gap-2">
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

		<PDialog
			v-model:visible="cropDialogVisible"
			modal
			header="Crop profile logo"
			:style="{ width: 'min(95vw, 640px)' }"
			:dismissable-mask="true"
			@hide="onCropDialogHide"
		>
			<div class="space-y-4">
				<p class="text-sm text-gray-600">
					Drag the overlay to position and use the handles to resize. The crop is locked to
					<strong>4:1</strong> ({{ LOGO_W }} × {{ LOGO_H }} px).
				</p>
				<div class="max-h-[min(55vh,420px)] min-h-[220px] overflow-hidden rounded-lg bg-gray-900/5">
					<Cropper
						v-if="cropImageSrc"
						ref="cropperRef"
						class="cropper h-[min(55vh,420px)]"
						:src="cropImageSrc"
						image-restriction="stencil"
						:stencil-props="{
							aspectRatio: LOGO_ASPECT,
							movable: true,
							resizable: true,
						}"
						:default-size="defaultCropSize"
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
import { router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';

const LOGO_W = 400;
const LOGO_H = 100;
const LOGO_ASPECT = LOGO_W / LOGO_H;

const props = defineProps({
	companyName: {
		type: String,
		default: '',
	},
	logoUrl: {
		type: String,
		default: null,
	},
	storeUrl: {
		type: String,
		required: true,
	},
	destroyUrl: {
		type: String,
		required: true,
	},
});

const fileInputRef = ref(null);
const cropperRef = ref(null);
const cropDialogVisible = ref(false);
const cropImageSrc = ref('');
const logoUploading = ref(false);

const initials = computed(() => companyInitials(props.companyName));

function companyInitials(name) {
	if (!name?.trim()) {
		return 'TN';
	}
	const parts = name.trim().split(/\s+/).filter(Boolean);
	if (parts.length >= 2) {
		return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
	}
	return name.trim().slice(0, 2).toUpperCase();
}

function defaultCropSize({ imageSize }) {
	if (!imageSize?.width || !imageSize?.height) {
		return { width: LOGO_W, height: LOGO_H };
	}
	let width = imageSize.width;
	let height = width / LOGO_ASPECT;
	if (height > imageSize.height) {
		height = imageSize.height;
		width = height * LOGO_ASPECT;
	}
	return { width, height };
}

function openFilePicker() {
	fileInputRef.value?.click();
}

function onFileSelected(e) {
	const file = e.target.files?.[0];
	if (!file) {
		return;
	}
	if (!file.type.startsWith('image/')) {
		return;
	}
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
				if (blob) {
					resolve(blob);
				} else {
					reject(new Error('Could not create image'));
				}
			},
			'image/jpeg',
			0.92
		);
	});
}

async function applyCropAndUpload() {
	const cropper = cropperRef.value;
	if (!cropper) {
		return;
	}

	const result = cropper.getResult();
	if (!result?.canvas) {
		return;
	}

	const src = result.canvas;
	const out = document.createElement('canvas');
	out.width = LOGO_W;
	out.height = LOGO_H;
	const ctx = out.getContext('2d');
	if (!ctx) {
		return;
	}
	ctx.imageSmoothingEnabled = true;
	ctx.imageSmoothingQuality = 'high';
	ctx.drawImage(src, 0, 0, LOGO_W, LOGO_H);

	logoUploading.value = true;
	try {
		const blob = await canvasToJpegBlob(out);
		const file = new File([blob], 'company-logo.jpg', { type: 'image/jpeg' });
		router.post(
			props.storeUrl,
			{ logo: file },
			{
				forceFormData: true,
				preserveScroll: true,
				onFinish: () => {
					logoUploading.value = false;
					cropDialogVisible.value = false;
					onCropDialogHide();
				},
			}
		);
	} catch {
		logoUploading.value = false;
	}
}

function removeLogo() {
	if (!confirm('Remove the profile logo?')) {
		return;
	}
	router.delete(props.destroyUrl, {
		preserveScroll: true,
	});
}
</script>

<style scoped>
.cropper :deep(.vue-simple-handler) {
	width: 12px;
	height: 12px;
}
</style>

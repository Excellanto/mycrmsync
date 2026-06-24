<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    gradientBackground: {
        type: Boolean,
        default: false,
    },
});

const pageStyle = computed(() =>
    props.gradientBackground
        ? { background: 'linear-gradient(to right, #c12dd1, #6d8cf8)' }
        : undefined,
);
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
        :class="{ 'bg-app': !gradientBackground }"
        :style="pageStyle"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>

        <div v-if="$slots.footer" class="mt-4 w-full sm:max-w-md text-center">
            <slot name="footer" />
        </div>
    </div>
</template>

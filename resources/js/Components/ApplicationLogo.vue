<template>
	<div v-bind="$attrs" class="w-full flex justify-center">
		<img :src="logo" alt="MyCrmSync" class="w-100 mx-auto" />
	</div>
</template>
<script setup>
import logo from '../../images/mycrmsync.png';
</script>

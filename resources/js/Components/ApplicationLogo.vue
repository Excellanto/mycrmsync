<template>
	<div v-bind="$attrs" class="w-full flex justify-center">
		<img :src="logo" alt="Sibrama Newage" class="w-[70%] mx-auto" />
	</div>
</template>
<script setup>
import logo from '../../images/sibrama-newage.png';
</script>

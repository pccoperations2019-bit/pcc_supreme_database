<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
  title: {
    type: String,
    default: 'Authentication',
  },
})
</script>

<template>
  <div class="flex max-h-screen flex-col overflow-hidden">
    <Head :title="title" />
    <div class="h-32 sm:h-40 md:h-48 lg:h-48">
      <div class="h-2.5 w-full bg-linear-to-r from-yellow-200 to-orange-500">&nbsp;</div>
      <header class="flex flex-col bg-indigo-900 sm:flex-row">
        <div class="flex items-center justify-center gap-1 px-4 py-2 sm:px-6 sm:py-4">
          <img
            src="/images/DA-PCC-logo-full-white-text.png"
            alt="Philippine Carabao Center"
            class="h-12 w-auto object-contain sm:h-16 md:h-20 lg:h-24"
          />
          <img
            src="/images/bagong-pilipinas-logo.png"
            alt="Bagong Pilipinas"
            class="h-12 w-auto object-contain sm:h-16 md:h-20 lg:h-24"
          />
        </div>
        <h1
          class="flex items-center justify-center px-4 py-2 text-center font-serif text-xs font-bold text-white sm:ml-auto sm:px-4 sm:text-sm md:text-base lg:text-lg xl:text-xl"
        >
          SUPREME - CBED DATABASE
        </h1>
      </header>
    </div>
    <div class="flex flex-1 flex-col lg:flex-row">
      <div class="hidden lg:block lg:w-2/3">
        <div class="relative h-full w-full">
          <img
            src="/images/login-bg.png"
            alt="Login background"
            class="h-full w-full object-cover"
          />
        </div>
      </div>

      <div
        class="flex w-full items-center justify-center bg-white px-4 py-6 sm:px-6 sm:py-8 md:px-8 md:py-10 lg:w-1/3 lg:px-8 lg:py-12 xl:px-12"
      >
        <div class="w-full max-w-sm space-y-4 sm:max-w-md sm:space-y-6 md:space-y-8">
          <div class="flex flex-col space-y-2 sm:space-y-3 md:space-y-4">
            <slot name="header" />
          </div>

          <div class="bg-white">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

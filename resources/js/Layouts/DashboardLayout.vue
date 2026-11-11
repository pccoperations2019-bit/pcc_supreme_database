<script setup lang="ts">
import { computed, useSlots } from 'vue'
import ExportQueueCard from '@/components/ExportQueueCard.vue'
import ImportQueueCard from '@/components/ImportQueueCard.vue'
import MainNav from '@/components/MainNav.vue'
import UserNav from '@/components/UserNav.vue'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { cn } from '@/lib/utils'

const props = defineProps({
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
})

const url = window.location.href

const isNumericSegment = segment => /^\d+$/.test(String(segment))

const constructBreadcrumbs = () => {
  const pathSegments = new window.URL(url).pathname.split('/').filter(Boolean)
  const breadcrumbs = pathSegments.reduce((acc, segment, index) => {
    if (isNumericSegment(segment)) return acc
    const href =
      segment === 'reports'
        ? '#'
        : segment === 'dashboard'
          ? '/dashboard?program=cbed'
          : '/' + pathSegments.slice(0, index + 1).join('/')
    acc.push({
      name: segment.charAt(0).toUpperCase() + segment.slice(1),
      href,
    })
    return acc
  }, [])
  return breadcrumbs
}
const BREADCRUMB_HOME = { name: 'Home', href: '/dashboard?program=cbed' }
const defaultBreadcrumbs = [BREADCRUMB_HOME, ...constructBreadcrumbs()]
const breadcrumbsItems = computed(() => {
  const items =
    props.breadcrumbs.length > 0 ? [BREADCRUMB_HOME, ...props.breadcrumbs] : defaultBreadcrumbs
  return items.filter((item, index, self) => index === self.findIndex(t => t.href === item.href))
})
defineEmits(['nav-click'])

const slots = useSlots()
const hasFilters = !!slots.filters
</script>

<template>
  <div class="to-steel-blue from-deep-navy-blue min-h-screen flex-col bg-linear-to-b md:flex">
    <div class="bg-warm-yellow border-deep-navy-blue relative border-b shadow-sm">
      <div class="text-deep-navy-blue flex h-16 items-center space-x-4 px-4 lg:px-6">
        <MainNav @nav-click="$emit('nav-click', $event)" />
        <div class="ml-auto flex items-center space-x-4">
          <UserNav />
        </div>
      </div>
    </div>
    <!-- Breadcrumb -->
    <Breadcrumb :class="cn('bg-deep-navy-blue/50 flex items-center shadow-md')">
      <BreadcrumbList class="mx-6 my-1">
        <BreadcrumbItem v-for="(breadcrumb, index) in breadcrumbsItems" :key="index">
          <BreadcrumbLink :href="breadcrumb.href">
            <BreadcrumbPage class="text-gray-300 hover:text-white">
              {{ breadcrumb.name }}
            </BreadcrumbPage>
          </BreadcrumbLink>
          <BreadcrumbSeparator v-if="index < breadcrumbsItems.length - 1">/</BreadcrumbSeparator>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb>

    <!-- Page Content -->
    <div class="flex flex-1 p-6 text-white">
      <template v-if="hasFilters">
        <div class="flex w-full flex-col gap-4 lg:flex-row lg:gap-6">
          <div class="w-full shrink-0 lg:w-58">
            <div class="mb-4">
              <slot name="toggle" />
            </div>
            <div class="hidden lg:block">
              <slot name="filters" />
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <slot />
          </div>
        </div>
      </template>
      <template v-else>
        <div class="min-w-0 flex-1">
          <slot />
        </div>
      </template>
    </div>
    <div class="fixed right-4 bottom-4 z-50 flex w-full max-w-md flex-col gap-4">
      <ImportQueueCard />
      <ExportQueueCard />
    </div>
  </div>
</template>

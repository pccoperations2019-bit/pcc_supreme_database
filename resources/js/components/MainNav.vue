<script setup lang="ts">
import { computed, ref, reactive } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import { ChevronDown, Menu, X } from 'lucide-vue-next'
import { Icon } from '@iconify/vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import ApplicationLogo from '@/components/ApplicationLogo.vue'
import navLinks from '@/lib/navLinks'
import { useAuth } from '@/composable/useAuth'

type NavLink = {
  label: string
  route: string
  icon: string | null
  params?: Record<string, any>
  /** Key from the shared `permissions` prop; the link is hidden without it. */
  permission?: string
  children?: {
    label: string
    route: string
    params?: Record<string, any>
    /** Key from the shared `permissions` prop; the child is hidden without it. */
    permission?: string
  }[]
}

type Program = { label: string; value: string; image: string }

const page = usePage()
const open = ref(false)
const dropdownStates = ref<Record<string, boolean>>({})
const mobileMenuOpen = ref(false)
const mobileExpandedItems = ref<Record<string, boolean>>({})

const { user, isCenterAdmin, isCenterScoped, isAgencyWideDataEntry, hasPermission } = useAuth()

const leftLinks = computed(() => {
  const centerId = isCenterAdmin.value ? user.value?.center_id : null

  return navLinks.left
    .filter(link => !link.permission || hasPermission(link.permission))
    .map(link => {
      const updated = { ...link }

      if (link.label === 'Profiles' && centerId) {
        updated.route = 'centers.show'
        updated.params = { center: centerId }
        updated.children = link.children?.map(child =>
          child.label === 'Centers'
            ? {
                ...child,
                label: 'My Center',
                route: 'centers.show',
                params: { center: centerId },
              }
            : child
        )
      }
      if (
        link.label === 'Profiles' &&
        !centerId &&
        isCenterScoped.value &&
        !isAgencyWideDataEntry.value
      ) {
        updated.children = link.children?.filter(child => child.label !== 'Centers')
      }

      if (link.label === 'Reports' && isCenterAdmin.value) {
        updated.children = link.children?.filter(
          child => child.route !== 'summary-buffalo-derived-products'
        )
      }
      if (updated.children) {
        updated.children = updated.children.filter(
          child => !child.permission || hasPermission(child.permission)
        )
      }

      return updated
    })
})

const rightLinks = computed(() => navLinks.right || [])

const program = reactive<Program>({
  label: 'CBED',
  value: 'cbed',
  image: '/images/carabao.png',
})

function toggleMobileItem(label: string) {
  mobileExpandedItems.value[label] = !mobileExpandedItems.value[label]
}

function closeMobileMenu() {
  mobileMenuOpen.value = false
}
</script>

<template>
  <div class="flex w-full items-center">
    <!-- Logo -->
    <div class="flex h-12 w-12 shrink-0 items-center">
      <Link href="/dashboard?program=cbed">
        <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
      </Link>
    </div>

    <nav
      :class="cn('hidden w-full items-center space-x-6 md:flex lg:space-x-8', $attrs.class ?? '')"
    >
      <div class="ml-6 flex items-center gap-6 lg:gap-8">
        <!-- Add space after logo -->
        <template v-for="link in leftLinks" :key="link.label">
          <!-- Dropdown Links -->
          <Popover
            v-if="link.children"
            v-model:open="dropdownStates[link.label]"
            @update:open="val => (dropdownStates[link.label] = val)"
          >
            <PopoverTrigger as-child>
              <div
                class="hover:text-primary inline-flex cursor-pointer items-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
              >
                <Link
                  v-if="link.route !== '#'"
                  :href="route(link.route, link.params)"
                  class="hover:text-primary flex items-center gap-1"
                >
                  <Icon v-if="link.icon" :icon="link.icon" class="text-gray-600" />
                  {{ link.label }}
                </Link>
                <span v-else>
                  <div class="flex items-center gap-1">
                    <Icon v-if="link.icon" :icon="link.icon" class="text-gray-600" />
                    {{ link.label }}
                  </div>
                </span>
                <ChevronDown class="h-4 w-4" />
              </div>
            </PopoverTrigger>

            <PopoverContent class="z-50 w-[200px] p-2">
              <div class="flex flex-col space-y-1">
                <component
                  v-for="child in link.children"
                  :key="child.label"
                  :is="child.route === '#' ? 'button' : Link"
                  :href="child.route !== '#' ? route(child.route, child.params) : undefined"
                  class="hover:bg-accent rounded-md px-3 py-2 text-left text-sm transition-colors"
                  @click="dropdownStates[link.label] = false"
                >
                  {{ child.label }}
                </component>
              </div>
            </PopoverContent>
          </Popover>

          <!-- Regular Links -->
          <component
            v-else
            :is="link.route === '#' ? 'button' : Link"
            :href="link.route !== '#' ? route(link.route, link.params) : undefined"
            class="hover:text-primary flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
          >
            <Icon v-if="link.icon" :icon="link.icon" class="text-gray-600" />
            {{ link.label }}
          </component>
        </template>
      </div>

      <div class="flex grow"></div>

      <div class="flex space-x-4 lg:space-x-6">
        <Link
          v-for="link in rightLinks"
          :key="link.label"
          :href="link.route !== '#' ? route(link.route) : undefined"
          class="hover:text-primary flex items-center gap-2 px-3 py-2 text-sm font-medium transition-colors"
        >
          <Icon v-if="link.icon" :icon="link.icon" class="text-gray-600" />
          {{ link.label }}
        </Link>
      </div>
    </nav>

    <div class="ml-auto md:hidden">
      <button
        @click="mobileMenuOpen = !mobileMenuOpen"
        class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none"
        :aria-expanded="mobileMenuOpen"
        aria-label="Toggle menu"
      >
        <X v-if="mobileMenuOpen" class="h-5 w-5" />
        <Menu v-else class="h-5 w-5" />
      </button>
    </div>
  </div>

  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-show="mobileMenuOpen"
      class="absolute top-full right-0 left-0 z-50 border-b border-gray-200 bg-white shadow-lg md:hidden"
    >
      <nav class="flex flex-col space-y-1 px-4 py-3">
        <!-- Left nav links -->
        <template v-for="link in leftLinks" :key="link.label">
          <!-- Mobile Dropdown -->
          <div v-if="link.children">
            <div class="flex items-center">
              <component
                :is="link.route !== '#' ? Link : 'div'"
                :href="link.route !== '#' ? route(link.route, link.params) : undefined"
                @click="link.route !== '#' ? closeMobileMenu() : null"
                class="flex flex-1 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
              >
                <Icon v-if="link.icon" :icon="link.icon" class="text-gray-500" />
                {{ link.label }}
              </component>
              <button
                @click="toggleMobileItem(link.label)"
                class="rounded-md p-2 text-gray-500 transition-colors hover:bg-gray-100"
              >
                <ChevronDown
                  class="h-4 w-4 transition-transform duration-200"
                  :class="{ 'rotate-180': mobileExpandedItems[link.label] }"
                />
              </button>
            </div>

            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1"
            >
              <div
                v-show="mobileExpandedItems[link.label]"
                class="mt-1 ml-4 flex flex-col space-y-1"
              >
                <component
                  v-for="child in link.children"
                  :key="child.label"
                  :is="child.route === '#' ? 'button' : Link"
                  :href="child.route !== '#' ? route(child.route, child.params) : undefined"
                  class="rounded-md px-3 py-2 text-left text-sm text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
                  @click="closeMobileMenu"
                >
                  {{ child.label }}
                </component>
              </div>
            </Transition>
          </div>

          <!-- Mobile Regular Link -->
          <component
            v-else
            :is="link.route === '#' ? 'button' : Link"
            :href="link.route !== '#' ? route(link.route, link.params) : undefined"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
            @click="closeMobileMenu"
          >
            <Icon v-if="link.icon" :icon="link.icon" class="text-gray-500" />
            {{ link.label }}
          </component>
        </template>

        <div v-if="rightLinks?.length" class="my-1 border-t border-gray-100 pt-1">
          <Link
            v-for="link in rightLinks"
            :key="link.label"
            :href="link.route !== '#' ? route(link.route) : undefined"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
            @click="closeMobileMenu"
          >
            <Icon v-if="link.icon" :icon="link.icon" class="text-gray-500" />
            {{ link.label }}
          </Link>
        </div>
      </nav>
    </div>
  </Transition>
</template>

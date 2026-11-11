<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import AccountSettingsDialog from '@/Pages/Profile/Partials/AccountSettingsDialog.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

const accountOpen = ref(false)
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" class="relative h-8 w-8 rounded-full">
        <Avatar class="h-8 w-8">
          <AvatarImage src="/avatars/01.png" alt="@shadcn" />
          <AvatarFallback>SC</AvatarFallback>
        </Avatar>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent class="z-[9999] w-56" align="end">
      <DropdownMenuLabel class="flex font-normal">
        <div class="flex flex-col space-y-1">
          <p class="text-sm leading-none font-medium">
            {{ $page.props.auth.user.name }}
          </p>
          <p class="text-muted-foreground text-xs leading-none">
            {{ $page.props.auth.user.email }}
          </p>
        </div>
      </DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuItem class="cursor-pointer" @select="accountOpen = true">
        Account Settings
      </DropdownMenuItem>
      <DropdownMenuItem as-child>
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="flex w-full items-center justify-between"
        >
          Logout
          <DropdownMenuShortcut>⇧⌘Q</DropdownMenuShortcut>
        </Link>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <AccountSettingsDialog v-model:open="accountOpen" />
</template>

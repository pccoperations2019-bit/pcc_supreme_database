<script setup>
import { computed, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { displayCenterLabel } from '@/lib/center'
import { roleLabel, roleBadgeColor } from '@/enums/UserRoleEnum'
import { statusLabel } from '@/enums/UserStatusEnum'
import { formatDate } from '@/lib/utils'
import { useAuth } from '@/composable/useAuth'

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
  user: { type: Object, default: () => ({}) },
  centers: { type: Array, default: () => [] },
  enums: { type: Object, required: true },
})

/**
 * Mirrors App\Enums\UserRoleEnum::isPccLevel() and isCenterScoped(). The server
 * re-validates both, so this only decides which fields to show — never what is
 * submitted.
 */
const PCC_LEVEL_ROLES = ['agency-admin', 'executive']
const CENTER_SCOPED_ROLES = ['center-admin', 'encoder', 'reviewer']

const { user: authUser, hasPermission, isAgencyAdmin, isCenterScoped } = useAuth()

const isCreateMode = computed(() => props.mode === 'create')
const isEditMode = computed(() => props.mode === 'edit')
const isViewMode = computed(() => props.mode === 'view')

const isSelf = computed(() => authUser.value?.id === props.user.id)
const isDeactivated = computed(() => props.user.status === 'deactivated')
const awaitsApproval = computed(() => ['pending', 'rejected'].includes(props.user.status))

/** Self-approval is refused by the backend, so never offer it on your own row. */
const canDecide = computed(
  () => hasPermission('approve-users') && !isSelf.value && awaitsApproval.value
)

const canReactivate = computed(
  () => hasPermission('deactivate-users') && !isSelf.value && isDeactivated.value
)

const deciding = ref(false)

/**
 * A center-scoped actor can only ever create users inside its own center — the
 * server forces that — so it is the only option offered, and the default.
 */
const defaultCenterId = () => {
  const own = authUser.value?.center_id
  const existing = props.user.center_id

  return String(existing ?? own ?? '') || null
}

const form = useForm({
  name: props.user.name || '',
  email: props.user.email || '',
  username: props.user.username || '',
  password: '',
  password_confirmation: '',
  role: props.user.roles?.[0]?.name || '',
  center_id: defaultCenterId(),
})

/**
 * Roles never offered as a choice. The agency holds a single PCC Administrator,
 * which is seeded rather than assigned.
 */
const ROLE_OPTION_BLOCKLIST = ['agency-admin']

/**
 * Center Admin and Executive are provisioned by the PCC Administrator, so they are
 * only offered when signed in as one. Any other actor sees the data-entry roles
 * alone. An account holding a role that is not on offer keeps it, rendered
 * read-only. Purely a UI filter — the server still re-validates the role.
 */
const PCC_ADMIN_ONLY_ROLES = ['center-admin', 'executive']

const currentRole = computed(() => props.user.roles?.[0]?.name || '')

const visibleRoleOptions = computed(() =>
  Object.entries(props.enums.role || {})
    .filter(([value]) => {
      if (ROLE_OPTION_BLOCKLIST.includes(value)) return false
      if (PCC_ADMIN_ONLY_ROLES.includes(value)) return isAgencyAdmin.value
      return true
    })
    .map(([value, label]) => ({ value, label }))
)

/** True when the account holds a role the dropdown does not offer right now. */
const roleIsFixed = computed(
  () =>
    !isCreateMode.value &&
    !visibleRoleOptions.value.some(({ value }) => value === currentRole.value)
)

/**
 * Center-scoped roles that may also be provisioned agency-wide, mirroring
 * UserRoleEnum::agencyWideCapableValues(). Such an account gets a "Whole agency"
 * choice in the Center select, which clears `center_id`; the server treats no
 * center on these two roles as nationwide reach rather than as a broken account.
 */
const AGENCY_WIDE_CAPABLE_ROLES = ['encoder', 'reviewer']
const WHOLE_AGENCY = 'whole-agency'

/** Whether the chosen role may sit outside a center. */
const roleCanBeAgencyWide = computed(() => AGENCY_WIDE_CAPABLE_ROLES.includes(form.role))

const centerOptions = computed(() => {
  const own = props.centers.filter(center => center.id === authUser.value?.center_id)

  // Falls back to the full list if the actor's own center is missing from the
  // options, so a preselected value always has a matching item to display.
  const centers = isCenterScoped.value && own.length ? own : props.centers

  const options = centers.map(center => ({
    value: String(center.id),
    label: displayCenterLabel(center.code, center.name),
  }))

  // Only a PCC-level actor may widen an account beyond its own center, and only
  // for a role that allows it.
  if (roleCanBeAgencyWide.value && !isCenterScoped.value) {
    return [{ value: WHOLE_AGENCY, label: 'Whole agency' }, ...options]
  }

  return options
})

const requiresCenter = computed(
  () => CENTER_SCOPED_ROLES.includes(form.role) && !roleCanBeAgencyWide.value
)

/**
 * The Center select stays visible before a role is chosen, so a center-scoped
 * actor sees its own center preselected immediately instead of having to pick a
 * role first. It disappears only once the role is known to be PCC-wide.
 */
const showsCenterSelect = computed(
  () => !form.role || requiresCenter.value || roleCanBeAgencyWide.value
)

/** The center the form currently points at, for the read-only rendering. */
const selectedCenter = computed(
  () => props.centers.find(center => String(center.id) === form.center_id) ?? null
)

// The select speaks "whole-agency"; `center_id` stays null underneath, which is
// what the server reads.
const selectedCenterValue = computed({
  get: () => form.center_id ?? (roleCanBeAgencyWide.value ? WHOLE_AGENCY : null),
  set: value => {
    form.center_id = value === WHOLE_AGENCY ? null : value
  },
})

// Switching role has to settle the center: a PCC-wide role covers the whole
// agency, a center-only role needs exactly one, and an agency-wide-capable role
// starts from the default and may then be widened to "Whole agency".
watch(
  () => form.role,
  role => {
    if (PCC_LEVEL_ROLES.includes(role)) {
      form.center_id = null

      return
    }

    if (CENTER_SCOPED_ROLES.includes(role) && !form.center_id) {
      form.center_id = defaultCenterId()
    }
  }
)

const goToEdit = () => router.visit(route('users.edit', props.user.id))
const goToView = () => router.visit(route('users.show', props.user.id))
const goToIndex = () => router.visit(route('users.index'))

const decide = action => {
  deciding.value = true

  router.patch(
    `/users/${props.user.id}/${action}`,
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        deciding.value = false
      },
      onFinish: () => {
        deciding.value = false
      },
    }
  )
}

const reactivate = () => {
  deciding.value = true

  router.patch(
    `/users/${props.user.id}/reactivate`,
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        deciding.value = false
      },
    }
  )
}

const submit = () => {
  // An empty password on edit means "leave the credentials alone", so it is
  // omitted entirely rather than sent as ''.
  const payload = {
    name: form.name,
    email: form.email,
    username: form.username,
    role: form.role,
    // A PCC-wide role is agency-wide by definition; every other role submits the
    // center it was given, which is null for an agency-wide encoder or reviewer.
    center_id: PCC_LEVEL_ROLES.includes(form.role) ? null : form.center_id,
  }

  if (form.password) {
    payload.password = form.password
    payload.password_confirmation = form.password_confirmation
  }

  form
    .transform(() => payload)
    .post(isCreateMode.value ? route('users.store') : route('users.update', props.user.id))
}

const statusBadgeClass = computed(() => {
  switch (props.user.status) {
    case 'active':
      return 'bg-green-100 text-green-800'
    case 'pending':
      return 'bg-amber-100 text-amber-800'
    case 'rejected':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-slate-100 text-slate-800'
  }
})
</script>

<template>
  <Card class="h-full w-full rounded-lg">
    <CardHeader class="rounded-t-lg">
      <div class="flex items-center justify-between gap-3">
        <div>
          <CardTitle class="text-xl font-bold">{{ user.name || 'New User' }}</CardTitle>
          <CardDescription class="mt-1">
            <span v-if="isCreateMode">Create a new user account.</span>
            <span v-else-if="isEditMode">Update the user account below.</span>
            <span v-else>View the user account.</span>
          </CardDescription>
        </div>
        <div class="flex items-center gap-2">
          <Button v-if="isViewMode" variant="outline" @click="goToEdit">Edit</Button>
          <Button v-if="isEditMode" variant="outline" @click="goToView">View</Button>
        </div>
      </div>
    </CardHeader>

    <CardContent class="p-6">
      <form class="space-y-6" @submit.prevent="submit">
        <div
          v-if="awaitsApproval && !isCreateMode && hasPermission('approve-users') && !isSelf"
          class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
        >
          This account is awaiting a decision and cannot sign in until it is approved.
        </div>

        <div
          v-else-if="isDeactivated"
          class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"
        >
          This account has been deactivated and cannot sign in.
        </div>

        <div
          v-if="isSelf && !isCreateMode"
          class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"
        >
          This is your own account. Edit your profile and password from your profile page.
        </div>

        <!-- Account Card -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Account</CardTitle>
            <CardDescription>Sign-in credentials for this account</CardDescription>
          </CardHeader>
          <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2 md:col-span-2">
              <Label for="user-name" class="text-sm font-medium text-gray-700">Name *</Label>
              <Input
                v-if="!isViewMode"
                id="user-name"
                v-model="form.name"
                placeholder="Enter full name"
                required
                class="h-10"
                :class="{ 'border-red-500': form.errors.name }"
              />
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                {{ user.name || 'N/A' }}
              </div>
              <div v-if="form.errors.name" class="text-xs text-red-600">
                {{ form.errors.name }}
              </div>
            </div>

            <div class="space-y-2">
              <Label for="user-email" class="text-sm font-medium text-gray-700">Email *</Label>
              <Input
                v-if="!isViewMode"
                id="user-email"
                v-model="form.email"
                type="email"
                placeholder="name@pcc.gov.ph"
                required
                class="h-10"
                :class="{ 'border-red-500': form.errors.email }"
              />
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                {{ user.email || 'N/A' }}
              </div>
              <div v-if="form.errors.email" class="text-xs text-red-600">
                {{ form.errors.email }}
              </div>
            </div>

            <div class="space-y-2">
              <Label for="user-username" class="text-sm font-medium text-gray-700"
                >Username *</Label
              >
              <Input
                v-if="!isViewMode"
                id="user-username"
                v-model="form.username"
                placeholder="Enter username"
                required
                class="h-10"
                :class="{ 'border-red-500': form.errors.username }"
              />
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                {{ user.username || 'N/A' }}
              </div>
              <div v-if="form.errors.username" class="text-xs text-red-600">
                {{ form.errors.username }}
              </div>
            </div>

            <div v-if="!isViewMode" class="space-y-2">
              <Label for="user-password" class="text-sm font-medium text-gray-700">
                Password {{ isCreateMode ? '*' : '(leave blank to keep)' }}
              </Label>
              <Input
                id="user-password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :required="isCreateMode"
                class="h-10"
                :class="{ 'border-red-500': form.errors.password }"
              />
              <div v-if="form.errors.password" class="text-xs text-red-600">
                {{ form.errors.password }}
              </div>
            </div>

            <div v-if="!isViewMode" class="space-y-2">
              <Label for="user-password-confirm" class="text-sm font-medium text-gray-700">
                Confirm Password {{ isCreateMode ? '*' : '' }}
              </Label>
              <Input
                id="user-password-confirm"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :required="isCreateMode"
                class="h-10"
              />
            </div>
          </CardContent>
        </Card>

        <!-- Role and Scope Card -->
        <Card>
          <CardHeader>
            <CardTitle class="text-base">Role and Scope</CardTitle>
            <CardDescription>
              A center-level role is pinned to one center. A PCC-wide role covers the whole agency.
            </CardDescription>
          </CardHeader>
          <CardContent class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label class="text-sm font-medium text-gray-700">Role *</Label>
              <div v-if="roleIsFixed" class="rounded bg-gray-50 p-2 text-sm">
                <Badge variant="secondary" :class="roleBadgeColor(user.roles?.[0]?.name)">
                  {{ roleLabel(user.roles?.[0]?.name) }}
                </Badge>
                <span class="ml-1 text-xs text-gray-500">(fixed for this account)</span>
              </div>
              <Select v-else-if="!isViewMode" v-model="form.role">
                <SelectTrigger class="h-10 w-full">
                  <SelectValue placeholder="Select role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="role in visibleRoleOptions"
                    :key="role.value"
                    :value="role.value"
                  >
                    {{ role.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                <Badge variant="secondary" :class="roleBadgeColor(user.roles?.[0]?.name)">
                  {{ roleLabel(user.roles?.[0]?.name) }}
                </Badge>
              </div>
              <div v-if="form.errors.role" class="text-xs text-red-600">
                {{ form.errors.role }}
              </div>
            </div>

            <div class="space-y-2">
              <Label class="text-sm font-medium text-gray-700">Center</Label>
              <Select v-if="!isViewMode && showsCenterSelect" v-model="selectedCenterValue">
                <SelectTrigger class="h-10 w-full">
                  <SelectValue placeholder="Select center" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="center in centerOptions"
                    :key="center.value"
                    :value="center.value"
                  >
                    {{ center.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <div v-else class="rounded bg-gray-50 p-2 text-sm">
                {{
                  (user.center ?? selectedCenter)
                    ? displayCenterLabel(
                        (user.center ?? selectedCenter).code,
                        (user.center ?? selectedCenter).name
                      )
                    : 'Whole agency'
                }}
              </div>
              <div v-if="form.errors.center_id" class="text-xs text-red-600">
                {{ form.errors.center_id }}
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between gap-4 border-t pt-6">
          <div class="text-sm text-gray-600">
            <span v-if="isCreateMode">Fill in the required fields and click Create.</span>
            <span v-else-if="isEditMode"
              >Make your changes and click Save to update the account.</span
            >
            <span v-else
              >{{ statusLabel(user.status) }} · created {{ formatDate(user.created_at) }}</span
            >
          </div>

          <div class="flex items-center gap-3">
            <template v-if="canDecide">
              <Button
                type="button"
                variant="outline"
                :disabled="deciding"
                @click="decide('reject')"
              >
                Reject
              </Button>
              <Button
                type="button"
                :disabled="deciding"
                class="min-w-32"
                @click="decide('approve')"
              >
                <span v-if="deciding"> Processing... </span>
                <span v-else>Approve</span>
              </Button>
            </template>

            <Button v-if="canReactivate" type="button" :disabled="deciding" @click="reactivate">
              Reactivate
            </Button>

            <div v-if="!isViewMode" class="flex items-center gap-3">
              <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="goToIndex"
              >
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing" class="min-w-32">
                <span v-if="form.processing"> Processing... </span>
                <span v-else>{{ isCreateMode ? 'Create User' : 'Save Changes' }}</span>
              </Button>
            </div>
          </div>
        </div>
      </form>
    </CardContent>
  </Card>
</template>

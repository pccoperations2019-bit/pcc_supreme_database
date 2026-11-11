<script setup>
import { computed } from 'vue'
import {
  BookOpen,
  UserPlus,
  KeyRound,
  ShieldCheck,
  MapPin,
  CircleCheck,
  Info,
  Lightbulb,
  TriangleAlert,
  PencilLine,
  Eye,
  Clock,
} from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { roleLabel, roleBadgeColor } from '@/enums/UserRoleEnum'

/**
 * Plain-language reference shown beside the user form. Every rule here mirrors
 * something the server already enforces (StoreUserRequest / UpdateUserRequest
 * plus UserRoleEnum and UserStatusEnum), so the guide never promises something
 * validation will refuse.
 *
 * Shared by the create, edit and view screens, so the copy is mode-aware: a blank
 * password keeps the current credentials on an edit, an edit does not send the
 * account back to Pending, and the view screen submits nothing at all. Rendering
 * the create wording unchanged on those pages would state rules that are not true
 * there.
 */
const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: value => ['create', 'edit', 'view'].includes(value),
  },
})

const isCreateMode = computed(() => props.mode === 'create')
const isViewMode = computed(() => props.mode === 'view')

const heading = computed(
  () =>
    ({
      create: 'How to create a user',
      edit: 'How to edit a user',
      view: 'About this account',
    })[props.mode]
)

const subheading = computed(
  () =>
    ({
      create: 'What each field needs and what happens next.',
      edit: 'What you can change, and what stays as it is.',
      view: 'What the fields and role mean.',
    })[props.mode]
)

/** The steps differ per screen, so they are chosen rather than filtered. */
const steps = computed(() => {
  if (props.mode === 'edit') {
    return [
      {
        icon: PencilLine,
        title: 'Change what needs changing',
        body: 'Name, email and username can be edited freely. Leave the password blank to keep the current credentials.',
      },
      {
        icon: ShieldCheck,
        title: 'Change the role carefully',
        body: 'Only the PCC Administrator may change a role. Changing one resets nothing else about the account.',
      },
      {
        icon: MapPin,
        title: 'Keep the center in step with the role',
        body: 'A center-level role takes one center. A PCC-wide role takes none, and the center is cleared when you switch to one.',
      },
      {
        icon: CircleCheck,
        title: 'Save and review',
        body: 'The account keeps its current status. An approved account is not sent back to Pending by an edit.',
      },
    ]
  }

  if (props.mode === 'view') {
    return [
      {
        icon: Eye,
        title: 'Read-only record',
        body: 'Nothing on this screen can be changed here. Use Edit to change the account, or Deactivate to retire it.',
      },
      {
        icon: Clock,
        title: 'Pending needs a decision',
        body: 'A new account starts as Pending and cannot sign in until someone with approval rights approves it.',
      },
      {
        icon: TriangleAlert,
        title: 'Deactivated, not deleted',
        body: 'Deactivating keeps everything this account authored. Its audit trail stays readable and its rows keep a valid author.',
      },
      {
        icon: ShieldCheck,
        title: 'No self-approval',
        body: 'You cannot approve or reject your own account. Another approver has to review it.',
      },
    ]
  }

  return [
    {
      icon: UserPlus,
      title: 'Fill in the account details',
      body: 'Name, email, username and a password. Email and username must not already exist in the system.',
    },
    {
      icon: ShieldCheck,
      title: 'Pick a role',
      body: 'The role decides what the new account may see and do. It cannot be changed to a wider role later without your approval.',
    },
    {
      icon: MapPin,
      title: 'Choose a center',
      body: 'Center-level roles are pinned to one center. PCC-wide roles cover the whole agency and take no center.',
    },
    {
      icon: CircleCheck,
      title: 'Submit and wait',
      body: 'The account is created as Pending. Someone with approval rights must approve it before the user can sign in.',
    },
  ]
})

/** The password rule is the one field rule that differs between the screens. */
const fieldRules = computed(() => [
  { field: 'Email', rule: 'A valid, unique address' },
  { field: 'Username', rule: '3+ characters, letters, numbers, dashes, underscores only' },
  {
    field: 'Password',
    rule: isCreateMode.value
      ? 'At least 8 characters, confirmed in the field below'
      : isViewMode.value
        ? 'Not shown here. Only an admin can change it.'
        : 'Leave blank to keep the current one. Otherwise at least 8 characters.',
  },
  { field: 'Name', rule: 'Up to 255 characters' },
])

/** Role meanings, condensed from the PHPDoc on App\Enums\UserRoleEnum. */
const roleGuide = [
  {
    value: 'center-admin',
    scope: 'One center',
    body: 'Manages the center record and its farmers, partners and accounts.',
  },
  {
    value: 'encoder',
    scope: 'One center or whole agency',
    body: 'Enters production, farmer and partner records and submits documents. Cannot approve anything.',
  },
  {
    value: 'reviewer',
    scope: 'Own center or whole agency',
    body: 'Approves/rejects pending documents. No data entry.',
  },
  {
    value: 'executive',
    scope: 'Whole agency',
    body: 'Read-only oversight across the agency. Can view and export reports, but cannot enter data or create accounts.',
  },
]

const tips = computed(() => {
  if (props.mode === 'edit') {
    return [
      'A center-scoped account can only ever change users inside its own center — the Center list is limited for them automatically.',
      'Only the PCC Administrator may change a role. Editing a name or email does not need that permission.',
      'An account cannot be approved by the person who created it. Another approver has to review it.',
    ]
  }

  if (isViewMode.value) {
    return [
      'Deactivating is not deleting. The records this account authored keep a valid author and the audit trail stays readable.',
      'A center-scoped account only ever reaches its own center, so what it can see here matches what it can act on.',
      'Roles decide what an account may see and do. Scope is decided by its center, not by its role.',
    ]
  }

  return [
    'A center-scoped account can only ever create users inside its own center — the Center list is limited for them automatically.',
    'Encoder and Reviewer may be set to "Whole agency" when the role is meant to cover every center. Center Admin cannot.',
    'You cannot approve your own account. Another approver has to review it.',
  ]
})
</script>

<template>
  <Card class="flex h-full w-full flex-col border-slate-200 bg-white/95 shadow-sm backdrop-blur">
    <CardHeader class="rounded-t-lg border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
      <div class="flex items-center gap-2">
        <span class="flex size-8 items-center justify-center rounded-full bg-sky-100 text-sky-700">
          <BookOpen class="size-4" />
        </span>
        <div>
          <CardTitle class="text-base text-slate-900">{{ heading }}</CardTitle>
          <CardDescription class="mt-0.5 text-xs text-slate-600">
            {{ subheading }}
          </CardDescription>
        </div>
      </div>
    </CardHeader>

    <CardContent class="flex-1 space-y-3 p-4 sm:p-5">
      <!-- Steps -->
      <section>
        <h3
          class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase"
        >
          <Info class="size-3.5" />
          {{ isViewMode ? 'What this screen means' : 'Steps' }}
        </h3>
        <ol class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
          <li
            v-for="(step, index) in steps"
            :key="step.title"
            class="flex items-start gap-2 rounded-md border border-slate-100 bg-slate-50/70 px-2.5 py-1.5"
          >
            <span
              class="flex size-6 shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-700"
            >
              <component :is="step.icon" class="size-3.5" />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-medium text-slate-900">
                <span class="mr-1 text-slate-400">{{ index + 1 }}.</span>{{ step.title }}
              </p>
              <p class="mt-0.5 text-xs leading-relaxed text-slate-600">{{ step.body }}</p>
            </div>
          </li>
        </ol>
      </section>

      <hr class="border-slate-100" />

      <!-- Field rules -->
      <section>
        <h3
          class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase"
        >
          <KeyRound class="size-3.5" />
          Field requirements
        </h3>
        <ul class="grid grid-cols-2 gap-1.5 lg:grid-cols-4">
          <li
            v-for="rule in fieldRules"
            :key="rule.field"
            class="flex items-start gap-1.5 rounded-md border border-slate-100 bg-slate-50/70 px-2 py-1.5"
          >
            <CircleCheck class="mt-0.5 size-3 shrink-0 text-emerald-600" />
            <div class="min-w-0">
              <span class="block text-xs font-medium text-slate-900">{{ rule.field }}</span>
              <span class="block text-xs leading-relaxed text-slate-600">{{ rule.rule }}</span>
            </div>
          </li>
        </ul>
      </section>

      <hr class="border-slate-100" />

      <!-- Role reference -->
      <section>
        <h3
          class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase"
        >
          <ShieldCheck class="size-3.5" />
          {{ isViewMode ? 'What each role can do' : 'Roles you can assign' }}
        </h3>
        <ul class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 2xl:grid-cols-4">
          <li
            v-for="role in roleGuide"
            :key="role.value"
            class="rounded-md border border-slate-100 bg-slate-50/70 p-2"
          >
            <div class="flex flex-wrap items-center gap-1.5">
              <Badge variant="secondary" :class="roleBadgeColor(role.value)">
                {{ roleLabel(role.value) }}
              </Badge>
              <span class="text-[11px] text-slate-500">{{ role.scope }}</span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ role.body }}</p>
          </li>
        </ul>
      </section>

      <hr class="border-slate-100" />

      <!-- Tips -->
      <section>
        <h3
          class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase"
        >
          <Lightbulb class="size-3.5" />
          Good to know
        </h3>
        <ul class="grid grid-cols-1 gap-1.5 md:grid-cols-3">
          <li
            v-for="tip in tips"
            :key="tip"
            class="flex items-start gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5"
          >
            <TriangleAlert class="mt-0.5 size-3 shrink-0 text-amber-600" />
            <span class="text-xs leading-relaxed text-amber-900">{{ tip }}</span>
          </li>
        </ul>
      </section>
    </CardContent>
  </Card>
</template>

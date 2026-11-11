import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  UserRoleEnum,
  agencyWideCapableRoles,
  centerScopedRoles,
  pccLevelRoles,
} from '@/enums/UserRoleEnum'

export function useAuth() {
  const page = usePage()

  const user = computed(() => page.props.auth?.user)

  // Roles are eager-loaded by HandleInertiaRequests. Before that they arrived as
  // null here, which made every hasRole() check silently false.
  const roles = computed(() => (user.value?.roles || []).map(r => r.name))

  // Flat list of permission names, also shared by HandleInertiaRequests.
  const permissions = computed(() => page.props.permissions || [])

  const hasRole = role => roles.value.includes(role)

  const hasAnyRole = roleList => roleList.some(hasRole)

  const hasPermission = permission =>
    Array.isArray(permissions.value)
      ? permissions.value.includes(permission)
      : Boolean(permissions.value[permission])

  const isCenterAdmin = computed(() => hasRole(UserRoleEnum.CENTER_ADMIN))
  const isAgencyAdmin = computed(() => hasRole(UserRoleEnum.AGENCY_ADMIN))
  const isExecutive = computed(() => hasRole(UserRoleEnum.EXECUTIVE))
  const isEncoder = computed(() => hasRole(UserRoleEnum.ENCODER))
  const isReviewer = computed(() => hasRole(UserRoleEnum.REVIEWER))

  const isAdmin = computed(
    () => hasRole(UserRoleEnum.CENTER_ADMIN) || hasRole(UserRoleEnum.AGENCY_ADMIN)
  )

  /**
   * Whether the account is confined to a single center. Drives the nav and the
   * filters, so it must cover every center-level role and not only the encoder —
   * a reviewer is pinned to a center too.
   */
  const isCenterScoped = computed(() => hasAnyRole(centerScopedRoles))

  /** PCC-wide: reaches every center in its agency. */
  const isPccLevel = computed(() => hasAnyRole(pccLevelRoles))

  /**
   * A data-entry or review officer for the whole agency rather than one center:
   * an encoder or reviewer provisioned with no center. Kept distinct from
   * isCenterScoped, which answers "is this a center-level role" and so is true of
   * these accounts too — the difference is the center, not the role.
   */
  const isAgencyWideDataEntry = computed(
    () => !user.value?.center_id && hasAnyRole(agencyWideCapableRoles)
  )

  /** May approve or reject documents: the PCC administrator, or a center's own reviewer. */
  const canReview = computed(() => hasPermission('review-documents'))

  /** May enter records and submit documents. */
  const canEnterData = computed(() => hasPermission('enter-data'))

  /** May bulk-export across every center. */
  const canExportData = computed(() => hasPermission('export-data'))

  /** May export only its own center's data. */
  const canExportOwnCenter = computed(() => hasPermission('export-own-center'))

  return {
    user,
    roles,
    permissions,
    hasRole,
    hasAnyRole,
    hasPermission,
    isAdmin,
    isAgencyAdmin,
    isCenterAdmin,
    isCenterScoped,
    isPccLevel,
    isAgencyWideDataEntry,
    isExecutive,
    isEncoder,
    isReviewer,
    canReview,
    canEnterData,
    canExportData,
    canExportOwnCenter,
  }
}

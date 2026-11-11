export const UserRoleEnum = Object.freeze({
  AGENCY_ADMIN: 'agency-admin',
  EXECUTIVE: 'executive',
  CENTER_ADMIN: 'center-admin',
  ENCODER: 'encoder',
  REVIEWER: 'reviewer',
})

export const UserRoleLabels = Object.freeze({
  'agency-admin': 'PCC Administrator',
  executive: 'Executive',
  'center-admin': 'Center Admin',
  encoder: 'Encoder',
  reviewer: 'Reviewer',
})

/** Roles that must be pinned to exactly one center. */
export const centerScopedRoles = Object.freeze([
  UserRoleEnum.CENTER_ADMIN,
  UserRoleEnum.ENCODER,
  UserRoleEnum.REVIEWER,
])

/** Roles that operate agency-wide and must have no center. */
export const pccLevelRoles = Object.freeze([UserRoleEnum.AGENCY_ADMIN, UserRoleEnum.EXECUTIVE])

/**
 * Roles that may be provisioned agency-wide: an encoder or a reviewer with no
 * center is a PCC officer for the whole agency, not a broken account. Center Admin
 * is deliberately absent — the center is the thing that role administers.
 */
export const agencyWideCapableRoles = Object.freeze([UserRoleEnum.ENCODER, UserRoleEnum.REVIEWER])

export function isCenterScoped(role) {
  return centerScopedRoles.includes(role)
}

export function isPccLevel(role) {
  return pccLevelRoles.includes(role)
}

/**
 * Badge colours per role, so the same role reads the same way in the user list,
 * the view panel and anywhere else it shows. Keyed by stored value, not label.
 */
export const UserRoleBadgeColors = Object.freeze({
  'agency-admin': 'bg-purple-100 text-purple-800 hover:bg-purple-200',
  executive: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200',
  'center-admin': 'bg-sky-100 text-sky-800 hover:bg-sky-200',
  encoder: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  reviewer: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
})

export function roleBadgeColor(role) {
  return UserRoleBadgeColors[role] || 'bg-slate-100 text-slate-800 hover:bg-slate-200'
}

export function roleLabel(role) {
  return UserRoleLabels[role] || role
}

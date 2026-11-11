export const UserStatusEnum = Object.freeze({
  PENDING: 'pending',
  ACTIVE: 'active',
  REJECTED: 'rejected',
  DEACTIVATED: 'deactivated',
})

export const UserStatusLabels = Object.freeze({
  pending: 'Pending',
  active: 'Active',
  rejected: 'Rejected',
  deactivated: 'Deactivated',
})

/** Only an active account may sign in. */
export const authenticatingStatuses = Object.freeze([UserStatusEnum.ACTIVE])

/** Still waiting on an approval decision, so an approver may act. */
export const awaitingApprovalStatuses = Object.freeze([
  UserStatusEnum.PENDING,
  UserStatusEnum.REJECTED,
])

export function canAuthenticate(status) {
  return authenticatingStatuses.includes(status)
}

export function statusLabel(status) {
  return UserStatusLabels[status] || status
}

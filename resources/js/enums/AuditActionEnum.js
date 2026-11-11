export const AuditActionEnum = Object.freeze({
  CREATED: 'created',
  UPDATED: 'updated',
  IMPORTED: 'imported',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  DELETED: 'deleted',
  PROCESSED: 'processed',
  FAILED: 'failed',
  EXPORTED: 'exported',
})

export const auditActionLabels = {
  [AuditActionEnum.CREATED]: 'Created',
  [AuditActionEnum.UPDATED]: 'Updated',
  [AuditActionEnum.IMPORTED]: 'Imported',
  [AuditActionEnum.APPROVED]: 'Approved',
  [AuditActionEnum.REJECTED]: 'Rejected',
  [AuditActionEnum.DELETED]: 'Deleted',
  [AuditActionEnum.PROCESSED]: 'Processed',
  [AuditActionEnum.FAILED]: 'Failed',
  [AuditActionEnum.EXPORTED]: 'Exported',
}

/**
 * Tints for the action badge. Deliberately reuses the document status palette
 * so an approval reads green here exactly as it does on the Documents page.
 */
export const auditActionBadgeClass = action => {
  const classes = {
    [AuditActionEnum.CREATED]: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    [AuditActionEnum.IMPORTED]: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
    [AuditActionEnum.APPROVED]: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
    [AuditActionEnum.PROCESSED]: 'bg-green-100 text-green-800 hover:bg-green-200',
    [AuditActionEnum.REJECTED]: 'bg-red-100 text-red-800 hover:bg-red-200',
    [AuditActionEnum.FAILED]: 'bg-red-100 text-red-800 hover:bg-red-200',
    [AuditActionEnum.DELETED]: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    [AuditActionEnum.UPDATED]: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    [AuditActionEnum.EXPORTED]: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200',
  }

  return classes[action] || 'bg-gray-100 text-gray-800'
}

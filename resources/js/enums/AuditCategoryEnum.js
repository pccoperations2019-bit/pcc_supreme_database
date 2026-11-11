export const AuditCategoryEnum = Object.freeze({
  DOCUMENTS: 'documents',
  PROFILES: 'profiles',
  GOALS: 'goals',
  PRODUCTS: 'products',
  PROJECTS: 'projects',
  SERVICES: 'services',
  DATA: 'data',
  USERS: 'users',
  REPORTS: 'reports',
  SYSTEM: 'system',
})

export const auditCategoryLabels = {
  [AuditCategoryEnum.DOCUMENTS]: 'Documents',
  [AuditCategoryEnum.PROFILES]: 'Profiles',
  [AuditCategoryEnum.GOALS]: 'Goals',
  [AuditCategoryEnum.PRODUCTS]: 'Products',
  [AuditCategoryEnum.PROJECTS]: 'Projects',
  [AuditCategoryEnum.SERVICES]: 'Services',
  [AuditCategoryEnum.DATA]: 'Production & Sales Data',
  [AuditCategoryEnum.USERS]: 'Users',
  [AuditCategoryEnum.REPORTS]: 'Reports',
  [AuditCategoryEnum.SYSTEM]: 'System',
}

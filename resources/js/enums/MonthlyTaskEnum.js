export const MonthlyTaskProductEnum = Object.freeze({
  MILK: 'milk',
  MEAT: 'meat',
  MANURE: 'manure',
})

export const MonthlyTaskProductLabels = Object.freeze({
  milk: 'Milk',
  meat: 'Meat',
  manure: 'Manure',
})

export const MonthlyTaskProductIcons = Object.freeze({
  milk: 'lucide:milk',
  meat: 'lucide:beef',
  manure: 'lucide:layers',
})

export const MonthlyTaskTypeEnum = Object.freeze({
  PRODUCTION: 'production',
  SALES: 'sales',
})

export const MonthlyTaskTypeLabels = Object.freeze({
  production: 'Production',
  sales: 'Sales',
})

export const MonthlyTaskProgramLabels = Object.freeze({
  ih: 'IH',
  cbed: 'CBED',
})

export const MonthlyTaskStatusLabels = Object.freeze({
  completed: 'Completed',
  under_review: 'Awaiting review',
  returned: 'Returned for correction',
  pending: 'Pending',
  overdue: 'Overdue',
  no_data: 'No data reported',
})

export const MonthlyTaskStatusBadgeClasses = Object.freeze({
  completed: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  under_review: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
  returned: 'bg-rose-100 text-rose-800 hover:bg-rose-200',
  pending: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
  overdue: 'bg-red-100 text-red-800 hover:bg-red-200',
  no_data: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
})

export const MonthlyTaskStatusIcons = Object.freeze({
  completed: 'lucide:circle-check',
  under_review: 'lucide:clipboard-check',
  returned: 'lucide:undo-2',
  pending: 'lucide:clock',
  overdue: 'lucide:circle-alert',
  no_data: 'lucide:circle-minus',
})

export const MonthlyTaskReviewLabels = Object.freeze({
  submitted: 'Awaiting review',
  approved: 'Approved',
  returned: 'Returned for correction',
})

export const MonthlyTaskReviewBadgeClasses = Object.freeze({
  submitted: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
  approved: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
  returned: 'bg-rose-100 text-rose-800 hover:bg-rose-200',
})

export const MonthlyTaskReviewIcons = Object.freeze({
  submitted: 'lucide:hourglass',
  approved: 'lucide:badge-check',
  returned: 'lucide:undo-2',
})

export function monthlyTaskReviewStatus(task) {
  return task?.review_status || null
}

export function monthlyTaskReviewLabel(task) {
  const status = monthlyTaskReviewStatus(task)

  return status ? MonthlyTaskReviewLabels[status] || status : null
}

export function monthlyTaskReviewBadgeClass(task) {
  const status = monthlyTaskReviewStatus(task)

  return status ? MonthlyTaskReviewBadgeClasses[status] || 'bg-slate-100 text-slate-800' : ''
}

export function monthlyTaskReviewIcon(task) {
  const status = monthlyTaskReviewStatus(task)

  return status ? MonthlyTaskReviewIcons[status] || 'lucide:circle-help' : 'lucide:circle-help'
}

export function monthlyTaskProductLabel(product) {
  return MonthlyTaskProductLabels[product] || product
}

export function monthlyTaskProductIcon(product) {
  return MonthlyTaskProductIcons[product] || 'lucide:package'
}

export function monthlyTaskTypeLabel(taskType) {
  return MonthlyTaskTypeLabels[taskType] || taskType
}

export function monthlyTaskProgramLabel(program) {
  return MonthlyTaskProgramLabels[program] || (program || '').toUpperCase()
}

export function monthlyTaskStatus(task) {
  if (monthlyTaskReviewStatus(task) === 'returned') {
    return 'returned'
  }

  return task?.status || 'pending'
}

export function monthlyTaskStatusLabel(task) {
  const status = monthlyTaskStatus(task)

  return MonthlyTaskStatusLabels[status] || status
}

export function monthlyTaskStatusBadgeClass(task) {
  return MonthlyTaskStatusBadgeClasses[monthlyTaskStatus(task)] || 'bg-slate-100 text-slate-800'
}

export function monthlyTaskStatusIcon(task) {
  return MonthlyTaskStatusIcons[monthlyTaskStatus(task)] || 'lucide:circle-help'
}

export function monthlyTaskDueLabel(task) {
  const days = Number(task.days_until_due ?? 0)

  if (task.is_resolved) return `Due ${task.due_date || ''}`.trim()

  if (days < 0) return `${Math.abs(days)} day${Math.abs(days) === 1 ? '' : 's'} overdue`
  if (days === 0) return 'Due today'

  return `Due in ${days} day${days === 1 ? '' : 's'}`
}

export const documentStatusLabels = {
  draft: 'Draft',
  pending_review: 'Pending Review',
  pending_center_review: 'Pending Center Review',
  pending_agency_review: 'Pending Agency Review',
  approved: 'Approved',
  rejected: 'Rejected',
  rejected_by_center: 'Rejected by Center',
  rejected_by_agency: 'Rejected by Agency',
  processing: 'Processing',
  processed: 'Processed',
  failed: 'Processing Failed',
}

export const documentStatusBadgeClass = status => {
  const classes = {
    draft: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    pending_review: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
    pending_center_review: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
    pending_agency_review: 'bg-orange-100 text-orange-800 hover:bg-orange-200',
    approved: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200',
    rejected: 'bg-red-100 text-red-800 hover:bg-red-200',
    rejected_by_center: 'bg-red-100 text-red-800 hover:bg-red-200',
    rejected_by_agency: 'bg-rose-100 text-rose-800 hover:bg-rose-200',
    processing: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    processed: 'bg-green-100 text-green-800 hover:bg-green-200',
    failed: 'bg-red-100 text-red-800 hover:bg-red-200',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

/** Statuses that are still waiting on a reviewer decision. */
export const isAwaitingReview = status =>
  [
    'draft',
    'pending_review',
    'pending_center_review',
    'pending_agency_review',
    'rejected',
    'rejected_by_center',
    'rejected_by_agency',
  ].includes(status)

/** Statuses whose document may be corrected by its uploader. */
export const isEditableByUploader = status =>
  ['draft', 'rejected', 'rejected_by_center', 'rejected_by_agency'].includes(status)

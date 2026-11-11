export const programBadgeClass = program => {
  const classes = {
    ih: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
    cbed: 'bg-green-100 text-green-800 hover:bg-green-200',
  }
  return classes[program] || 'border-slate-200 bg-slate-50 text-slate-700'
}

export const programLabel = program => {
  const labels = {
    ih: 'IH',
    cbed: 'CBED',
  }
  return labels[program] || (program || 'N/A').toUpperCase()
}

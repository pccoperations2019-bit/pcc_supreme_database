import { computed } from 'vue'

export function useProgramColumns(programTabValue, dataColumns) {
  const IH_HIDE = ['partner.name', 'farmer', 'program', 'year']
  const CBED_HIDE = ['program', 'year']

  return computed(() => {
    const program = programTabValue.value
    if (program === 'ih') {
      return dataColumns.filter(col => !IH_HIDE.includes(col.accessorKey))
    }
    if (program === 'cbed') {
      return dataColumns.filter(col => !CBED_HIDE.includes(col.accessorKey))
    }
    if (program === 'total') {
      return dataColumns.filter(col => !['program', 'year'].includes(col.accessorKey))
    }
    return dataColumns
  })
}

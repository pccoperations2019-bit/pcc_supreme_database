import { ref, reactive, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import axios from 'axios'

export function usePivotBuilder(initialSource = 'center') {
  const source = ref(initialSource)

  const fields = ref([])

  const zones = reactive({
    rows: [],
    columns: [],
    values: [],
    filters: [],
  })

  const result = ref(null)

  const loading = ref(false)

  const error = ref(null)

  async function loadFields() {
    if (!source.value) {
      fields.value = []
      return
    }

    try {
      const { data } = await axios.get('/reports/pivot/fields', {
        params: {
          source: source.value,
        },
      })

      fields.value = data
    } catch (err) {
      error.value = err
      fields.value = []
    }
  }

  async function runPivot() {
    if (!source.value) {
      return
    }

    loading.value = true

    error.value = null

    try {
      const { data } = await axios.post('/reports/pivot/build', {
        source: source.value,

        rows: zones.rows.map(field => ({
          field: field.field,
          order: field.order ?? 'asc',
          showTotals: field.showTotals ?? false,
          repeatRowLabels: field.repeatRowLabels ?? true,
        })),

        columns: zones.columns.map(field => ({
          field: field.field,
          order: field.order ?? 'asc',
          showTotals: field.showTotals ?? false,
        })),

        values: zones.values.map(field => ({
          field: field.field,
          agg: field.agg ?? 'count',
          showAs: field.showAs ?? 'default',
        })),

        filters: zones.filters
          .filter(f => f.filterConfig)
          .map(f => ({
            field: f.field,
            values: f.filterConfig.values,
            includeBlanks: f.filterConfig.includeBlanks,
          })),
      })

      result.value = data
    } catch (err) {
      error.value = err
      result.value = null
    } finally {
      loading.value = false
    }
  }

  const debouncedRunPivot = useDebounceFn(runPivot, 500)

  function resetZones() {
    zones.rows = []
    zones.columns = []
    zones.values = []
    zones.filters = []
    result.value = null
  }

  watch(source, async () => {
    resetZones()
    await loadFields()
  })

  watch(
    () => JSON.stringify(zones),
    () => {
      debouncedRunPivot()
    }
  )

  loadFields()

  return {
    source,
    fields,
    zones,
    result,
    loading,
    error,
    runPivot,
    loadFields,
  }
}

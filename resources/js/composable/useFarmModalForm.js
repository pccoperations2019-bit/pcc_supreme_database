import { ref } from 'vue'

export function useFarmModalForm() {
  const ensureGroupDetail = (form, key) => {
    if (!form.additional_details) form.additional_details = {}
    if (!form.additional_details[key] || typeof form.additional_details[key] !== 'object') {
      form.additional_details = { ...form.additional_details, [key]: {} }
    }
    return form.additional_details[key]
  }

  const setGroupDetail = (form, key, field, value) => {
    const group = ensureGroupDetail(form, key)
    group[field] = value
    form.additional_details = { ...(form.additional_details || {}), [key]: { ...group } }
  }

  const toggleGroupValue = (form, key, field, value, checked) => {
    const group = ensureGroupDetail(form, key)
    const current = Array.isArray(group[field]) ? group[field] : []
    group[field] = checked ? [...new Set([...current, value])] : current.filter(v => v !== value)
    form.additional_details = { ...(form.additional_details || {}), [key]: { ...group } }
  }

  const hasGroupValue = (form, key, field, value) => {
    const group = form.additional_details?.[key]
    if (!group || !Array.isArray(group[field])) return false
    return group[field].includes(value)
  }

  const toBooleanLike = value => {
    if (value === null || value === undefined || value === '') return false
    const normalized = String(value).toLowerCase().trim()
    if (['true', 'yes', 'y', '1'].includes(normalized)) return true
    if (['false', 'no', 'n', '0'].includes(normalized)) return false
    return Boolean(value)
  }

  return {
    ensureGroupDetail,
    setGroupDetail,
    toggleGroupValue,
    hasGroupValue,
    toBooleanLike,
  }
}

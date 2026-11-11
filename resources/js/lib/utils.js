import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}

export function valueUpdater(updaterOrValue, ref) {
  ref.value = typeof updaterOrValue === 'function' ? updaterOrValue(ref.value) : updaterOrValue
}

export function formatCurrency(amount, currency = 'USD') {
  if (!amount) return 'Not set'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
  }).format(amount)
}

export function formatDate(date, options = {}) {
  if (!date) return 'Not set'
  const defaultOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }
  return new Date(date).toLocaleDateString('en-US', {
    ...defaultOptions,
    ...options,
  })
}

export function formatDateTime(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

export function isoToDate(isoString) {
  if (!isoString) return ''
  if (typeof isoString !== 'string') return ''
  const match = isoString.match(/^\d{4}-\d{2}-\d{2}/)
  if (match) return match[0]
  try {
    return new Date(isoString).toISOString().slice(0, 10)
  } catch {
    return ''
  }
}

export function getInitials(firstName, lastName) {
  return (firstName?.charAt(0) || '') + (lastName?.charAt(0) || '')
}

export function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      globalThis.clearTimeout(timeout)
      func(...args)
    }
    globalThis.clearTimeout(timeout)
    timeout = globalThis.setTimeout(later, wait)
  }
}

export function label(enumMap, value) {
  return enumMap?.[value] ?? value
}

/**
 * A product's stored `name` is the raw product-type value (`fresh_milk`), and the
 * label for it lives in the enum belonging to the product's category - the same
 * string means something different for a milk product than for a hide product.
 *
 * Shared by every table that lists products (the products index and the product
 * tables inside the center and partner forms) so they cannot drift apart. A name
 * outside the map - a product typed free-hand under "Others" - is shown as typed
 * rather than blanked out.
 */
export function productNameLabel(product, enums = {}) {
  if (!product?.name) return 'N/A'

  const enumMap = {
    milk: enums.milkProductType,
    meat: enums.meatProductType,
    manure: enums.manureProductType,
    hide: enums.hideProductType,
  }[product.category]

  return label(enumMap, product.name)
}

export function normalizeProjectInvolvement(value) {
  if (!value) return ''

  return String(value).trim().replace(/\s+/g, '-').replace(/-+/g, '-')
}

export function resolveProjectInvolvement(value, availableInvolvements = []) {
  const input = String(value || '').trim()
  if (!input) return ''

  const normalizedInput = normalizeProjectInvolvement(input).toLowerCase()

  const match = availableInvolvements.find(item => {
    return normalizeProjectInvolvement(item).toLowerCase() === normalizedInput
  })

  return normalizeProjectInvolvement(match || input)
}

export function normalizeProjectInvolvementOptions(availableInvolvements = []) {
  return [
    ...new Set(
      availableInvolvements.map(value => resolveProjectInvolvement(value)).filter(Boolean)
    ),
  ]
}

/**
 * Ensure a currently stored fund source value is selectable.
 *
 * A stored value can be absent from the project involvement options (e.g. a
 * "CBIN-2019" fund source when no project currently lists that involvement).
 * Reka UI's Select falls back to the placeholder whenever the bound value has
 * no matching item, which hides the saved value and invites the user to
 * overwrite it, so append the value when it is missing.
 */
export function ensureProjectInvolvementOption(options = [], value = '') {
  const current = resolveProjectInvolvement(value)

  if (!current) return options

  const exists = options.some(
    option => normalizeProjectInvolvement(option).toLowerCase() === current.toLowerCase()
  )

  return exists ? options : [...options, current]
}

export function formatCustomDateToISO(dateObj) {
  if (!dateObj) return null
  if (dateObj.year && dateObj.month && dateObj.day) {
    const year = dateObj.year
    const month = String(dateObj.month).padStart(2, '0')
    const day = String(dateObj.day).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  if (dateObj instanceof Date) return dateObj.toISOString().split('T')[0]
  if (dateObj.toString && typeof dateObj.toString === 'function') {
    try {
      const dateStr = dateObj.toString()
      if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) return dateStr
    } catch {
      return null
    }
  }
  return null
}

export function formatMetricValue(value, unit = '', currencySymbols = {}) {
  if (value == null) return '--'
  const formattedNumber = Number(value).toLocaleString()
  if (unit === 'PHP') {
    const phpSymbol = currencySymbols?.php || currencySymbols?.PHP || currencySymbols?.Php || '₱'
    return `${phpSymbol} ${formattedNumber}`
  }
  return unit ? `${formattedNumber} ${unit}` : formattedNumber
}

export function formatBool(value) {
  if (value === null || value === undefined || value === '') return 'N/A'
  if (value === true) return 'TRUE'
  if (value === false) return 'FALSE'

  const text = String(value).trim()
  if (text === '') return 'N/A'

  const lower = text.toLowerCase()
  if (['true', 'yes', 'y', '1'].includes(lower)) return 'TRUE'
  if (['false', 'no', 'n', '0'].includes(lower)) return 'FALSE'

  return text
}

export function formatBoolYesNo(value) {
  if (value === null || value === undefined || value === '') return 'N/A'
  if (value === true) return 'Yes'
  if (value === false) return 'No'

  const text = String(value).trim()
  if (text === '') return 'N/A'

  const lower = text.toLowerCase()
  if (['true', 'yes', 'y', '1'].includes(lower)) return 'Yes'
  if (['false', 'no', 'n', '0'].includes(lower)) return 'No'

  return text
}

export function displayCenterLabel(code, fallback = 'N/A') {
  if (!code) return fallback
  const c = String(code).toUpperCase()
  return c === 'GP' || c === 'NIZ' ? c : `PCC at ${code}`
}

import { toast } from 'vue-sonner'

function normalizeFlash(flash) {
  if (!flash || typeof flash !== 'object') return {}
  return {
    success: flash.success ?? null,
    error: flash.error ?? null,
    warning: flash.warning ?? null,
    info: flash.info ?? null,
  }
}

export function toastFlash(flash) {
  const { success, error, warning, info } = normalizeFlash(flash)

  if (success) toast.success(success)
  if (error) toast.error(error)
  if (warning) toast.warning(warning)
  if (info) toast(info)
}

let unsubscribe = null
let hmrDisposeRegistered = false
let lastKey = null
let lastAt = 0

function shouldToast(flash) {
  const normalized = normalizeFlash(flash)
  const hasAny = Boolean(
    normalized.success || normalized.error || normalized.warning || normalized.info
  )
  if (!hasAny) return false

  const key = JSON.stringify(normalized)
  const now = Date.now()

  if (key === lastKey && now - lastAt < 750) return false

  lastKey = key
  lastAt = now
  return true
}

export function initInertiaFlashToasts(router, initialPage = null) {
  if (!router?.on) return

  unsubscribe?.()
  unsubscribe = router.on('success', event => {
    const flash = event.detail.page?.props?.flash
    if (shouldToast(flash)) toastFlash(flash)
  })

  if (import.meta.hot) {
    if (!hmrDisposeRegistered) {
      hmrDisposeRegistered = true
      import.meta.hot.dispose(() => unsubscribe?.())
    }
  }

  const initialFlash = initialPage?.props?.flash
  if (shouldToast(initialFlash)) toastFlash(initialFlash)
}

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: string
  type: ToastType
  title: string
  message?: string
  duration?: number
}

export const useToast = () => {
  const toasts = useState<Toast[]>('toasts_state', () => [])

  const removeToast = (id: string) => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const addToast = (
    type: ToastType,
    title: string,
    message?: string,
    duration = 4000
  ) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    const newToast: Toast = { id, type, title, message, duration }

    toasts.value.push(newToast)

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }

    return id
  }

  return {
    toasts,
    removeToast,
    success: (title: string, message?: string, duration?: number) =>
      addToast('success', title, message, duration),
    error: (title: string, message?: string, duration?: number) =>
      addToast('error', title, message, duration),
    info: (title: string, message?: string, duration?: number) =>
      addToast('info', title, message, duration),
    warning: (title: string, message?: string, duration?: number) =>
      addToast('warning', title, message, duration),
  }
}

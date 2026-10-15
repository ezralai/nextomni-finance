export type ThemeMode = 'dark' | 'light'

export const useTheme = () => {
  const themeCookie = useCookie<ThemeMode>('nextomni_theme', {
    default: () => 'dark',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
  })

  const isDark = computed(() => themeCookie.value === 'dark')

  const toggleTheme = () => {
    themeCookie.value = themeCookie.value === 'dark' ? 'light' : 'dark'
    if (import.meta.client) {
      if (themeCookie.value === 'dark') {
        document.documentElement.classList.add('dark')
      } else {
        document.documentElement.classList.remove('dark')
      }
    }
  }

  const setTheme = (mode: ThemeMode) => {
    themeCookie.value = mode
    if (import.meta.client) {
      if (mode === 'dark') {
        document.documentElement.classList.add('dark')
      } else {
        document.documentElement.classList.remove('dark')
      }
    }
  }

  // SSR-safe HTML class synchronization to avoid FOUC
  useHead(() => ({
    htmlAttrs: {
      class: isDark.value ? 'dark' : '',
    },
  }))

  return {
    theme: themeCookie,
    isDark,
    toggleTheme,
    setTheme,
  }
}

// Lightweight CSRF helper for cross-domain setups with Laravel Sanctum

export function getCookie(name: string): string | null {
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) return decodeURIComponent(parts.pop()!.split(';').shift() || '')
  return null
}

export function getXsrfToken(): string | null {
  // Laravel Sanctum issues XSRF-TOKEN cookie (URL encoded)
  const token = getCookie('XSRF-TOKEN')
  return token
}

export async function ensureCsrfCookie(apiBase = ''): Promise<void> {
  // Call Sanctum endpoint to set XSRF-TOKEN and session cookies
  const url = `${apiBase}/sanctum/csrf-cookie`
  await fetch(url, {
    method: 'GET',
    credentials: 'include',
    headers: { 'Accept': 'application/json' },
  })
}

export function apiBase(): string {
  // Configure backend origin if frontend is hosted on a different domain
  // Example: VITE_BACKEND_URL=https://api.example.com
  // No trailing slash
  const env: any = (import.meta as any)?.env || {}
  const base = (env.VITE_BACKEND_URL as string | undefined) || ''
  return base.replace(/\/$/, '')
}

export async function fetchJson(path: string, options: RequestInit = {}): Promise<any> {
  const base = apiBase()
  const url = `${base}${path}`
  const isGet = !options.method || options.method.toUpperCase() === 'GET'
  // For non-GET, ensure CSRF cookie exists first
  if (!isGet) {
    await ensureCsrfCookie(base)
  }
  const headers: HeadersInit = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(!isGet ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getXsrfToken() || '' } : {}),
    ...(options.headers || {}),
  }
  const res = await fetch(url, { credentials: 'include', ...options, headers })
  if (!res.ok) {
    const msg = await res.text()
    throw new Error(msg || `Request failed: ${res.status}`)
  }
  if (res.status === 204) return null
  return res.json()
}

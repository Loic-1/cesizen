export const API_BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

export async function apiRequest(path, options = {}) {
  const { accessToken, headers, ...rest } = options
  const isFormData = typeof FormData !== 'undefined' && rest.body instanceof FormData

  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...headers,
    },
    ...rest,
  })

  if (response.status === 204) {
    return null
  }

  const isJson = response.headers.get('content-type')?.includes('application/json')
  const data = isJson ? await response.json() : null

  if (!response.ok) {
    const message = data?.message ?? 'Une erreur est survenue.'
    throw new Error(message)
  }

  return data
}

export function resolveAssetUrl(path) {
  if (!path) {
    return null
  }

  if (/^https?:\/\//i.test(path)) {
    return path
  }

  return path.startsWith('/') ? `${API_BASE_URL}${path}` : `${API_BASE_URL}/${path}`
}

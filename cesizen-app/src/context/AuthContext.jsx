import { createContext, useContext, useEffect, useState } from 'react'
import { apiRequest } from '../lib/api.js'

const STORAGE_KEY = 'cesizen:auth'

const AuthContext = createContext(null)

function readStoredAuth() {
  if (typeof window === 'undefined') {
    return { accessToken: null, user: null }
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) {
      return { accessToken: null, user: null }
    }

    const parsed = JSON.parse(raw)

    return {
      accessToken: parsed?.accessToken ?? null,
      user: parsed?.user ?? null,
    }
  } catch {
    return { accessToken: null, user: null }
  }
}

function persistAuth(auth) {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(auth))
}

function clearPersistedAuth() {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.removeItem(STORAGE_KEY)
}

export function AuthProvider({ children }) {
  const [auth, setAuth] = useState(() => readStoredAuth())
  const [isBootstrapping, setIsBootstrapping] = useState(Boolean(readStoredAuth().accessToken))

  useEffect(() => {
    let cancelled = false

    async function bootstrap() {
      if (!auth.accessToken) {
        setIsBootstrapping(false)
        return
      }

      try {
        const user = await apiRequest('/users/me', {
          method: 'GET',
          accessToken: auth.accessToken,
        })

        if (!cancelled) {
          const nextAuth = { accessToken: auth.accessToken, user }
          setAuth(nextAuth)
          persistAuth(nextAuth)
        }
      } catch {
        if (!cancelled) {
          setAuth({ accessToken: null, user: null })
          clearPersistedAuth()
        }
      } finally {
        if (!cancelled) {
          setIsBootstrapping(false)
        }
      }
    }

    bootstrap()

    return () => {
      cancelled = true
    }
  }, [auth.accessToken])

  const value = {
    accessToken: auth.accessToken,
    user: auth.user,
    isAuthenticated: Boolean(auth.accessToken),
    isAdmin: Array.isArray(auth.user?.roles) && auth.user.roles.includes('ROLE_ADMIN'),
    isBootstrapping,
    async login(payload) {
      const data = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify(payload),
      })

      const nextAuth = {
        accessToken: data.accessToken,
        user: data.user ?? null,
      }

      setAuth(nextAuth)
      persistAuth(nextAuth)

      return data
    },
    async register(payload) {
      return apiRequest('/auth/register', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    },
    async logout() {
      try {
        if (auth.accessToken) {
          await apiRequest('/auth/logout', {
            method: 'POST',
            accessToken: auth.accessToken,
          })
        }
      } finally {
        setAuth({ accessToken: null, user: null })
        clearPersistedAuth()
      }
    },
    async updateProfile(payload) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      const user = await apiRequest('/users/me', {
        method: 'PATCH',
        accessToken: auth.accessToken,
        body: JSON.stringify(payload),
      })

      const nextAuth = {
        accessToken: auth.accessToken,
        user,
      }

      setAuth(nextAuth)
      persistAuth(nextAuth)

      return user
    },
    async changePassword(payload) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest('/users/me/password', {
        method: 'PATCH',
        accessToken: auth.accessToken,
        body: JSON.stringify(payload),
      })
    },
    async deleteAccount() {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      try {
        await apiRequest('/users/me', {
          method: 'DELETE',
          accessToken: auth.accessToken,
        })
      } finally {
        setAuth({ accessToken: null, user: null })
        clearPersistedAuth()
      }
    },
    async deleteArticle(articleId) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/articles/${articleId}`, {
        method: 'DELETE',
        accessToken: auth.accessToken,
      })
    },
    async createArticle(payload) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest('/admin/articles', {
        method: 'POST',
        accessToken: auth.accessToken,
        body: JSON.stringify(payload),
      })
    },
    async updateArticle(articleId, payload) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/articles/${articleId}`, {
        method: 'PATCH',
        accessToken: auth.accessToken,
        headers: {
          'Content-Type': 'application/merge-patch+json',
        },
        body: JSON.stringify(payload),
      })
    },
    async uploadArticleImages(articleId, files) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      const selectedFiles = Array.from(files ?? []).filter(Boolean)

      if (selectedFiles.length === 0) {
        return []
      }

      const formData = new FormData()

      selectedFiles.forEach((file) => {
        formData.append('images[]', file)
      })

      const response = await apiRequest(`/admin/articles/${articleId}/images`, {
        method: 'POST',
        accessToken: auth.accessToken,
        body: formData,
      })

      return Array.isArray(response?.files) ? response.files : []
    },
    async fetchAdminUsers() {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest('/admin/users', {
        method: 'GET',
        accessToken: auth.accessToken,
      })
    },
    async fetchAdminFiles() {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest('/admin/files', {
        method: 'GET',
        accessToken: auth.accessToken,
      })
    },
    async deleteAdminFile(fileId) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/files/${fileId}`, {
        method: 'DELETE',
        accessToken: auth.accessToken,
      })
    },
    async fetchAdminUser(userId) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/users/${userId}`, {
        method: 'GET',
        accessToken: auth.accessToken,
      })
    },
    async updateAdminUser(userId, payload) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/users/${userId}`, {
        method: 'PATCH',
        accessToken: auth.accessToken,
        headers: {
          'Content-Type': 'application/merge-patch+json',
        },
        body: JSON.stringify(payload),
      })
    },
    async deleteAdminUser(userId) {
      if (!auth.accessToken) {
        throw new Error('Vous devez être connecté.')
      }

      return apiRequest(`/admin/users/${userId}`, {
        method: 'DELETE',
        accessToken: auth.accessToken,
      })
    },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth doit être utilisé dans un AuthProvider')
  }

  return context
}

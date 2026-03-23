import { apiRequest, resolveAssetUrl } from './api.js'

export function normalizeArticles(payload) {
  if (Array.isArray(payload)) {
    return payload
  }

  if (Array.isArray(payload?.['hydra:member'])) {
    return payload['hydra:member']
  }

  if (Array.isArray(payload?.member)) {
    return payload.member
  }

  return []
}

export function normalizeFiles(payload) {
  if (Array.isArray(payload)) {
    return payload
  }

  if (Array.isArray(payload?.['hydra:member'])) {
    return payload['hydra:member']
  }

  if (Array.isArray(payload?.member)) {
    return payload.member
  }

  return []
}

export function formatArticleDate(value, options = {}) {
  if (!value) {
    return 'Date inconnue'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Date inconnue'
  }

  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    ...options,
  }).format(date)
}

export function stripHtml(value) {
  return String(value ?? '')
    .replace(/<style[\s\S]*?>[\s\S]*?<\/style>/gi, ' ')
    .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
}

export function buildArticleExcerpt(article, maxLength = 180) {
  const source = article?.description || article?.content || ''
  const clean = stripHtml(source).replace(/\s+/g, ' ').trim()

  if (!clean) {
    return 'Aucun résumé disponible pour cet article.'
  }

  return clean.length > maxLength ? `${clean.slice(0, maxLength - 1)}…` : clean
}

export function resolveArticleThumbnail(files) {
  const imageFile = files.find((file) => String(file?.mimeType ?? '').startsWith('image/'))
  return imageFile ? resolveAssetUrl(imageFile.storagePath) : null
}

export function resolveArticleImages(files) {
  return files
    .filter((file) => String(file?.mimeType ?? '').startsWith('image/'))
    .map((file) => ({
      id: file.id ?? file.storagePath ?? file.originalName,
      url: resolveAssetUrl(file.storagePath),
      alt: file.originalName ?? "Illustration de l'article",
      name: file.originalName ?? 'Image',
    }))
    .filter((image) => Boolean(image.url))
}

export async function withArticleThumbnail(article) {
  try {
    const payload = await apiRequest(`/articles/${article.id}/files`, {
      method: 'GET',
    })
    const files = normalizeFiles(payload)

    return {
      ...article,
      thumbnailUrl: resolveArticleThumbnail(files),
    }
  } catch {
    return {
      ...article,
      thumbnailUrl: null,
    }
  }
}

export async function withArticleThumbnails(articles) {
  return Promise.all(articles.map((article) => withArticleThumbnail(article)))
}

export async function fetchArticles({ limit, withThumbnails = true } = {}) {
  const payload = await apiRequest('/articles', {
    method: 'GET',
  })

  const normalizedArticles = normalizeArticles(payload)
  const articles = typeof limit === 'number' ? normalizedArticles.slice(0, limit) : normalizedArticles

  if (!withThumbnails) {
    return articles
  }

  return withArticleThumbnails(articles)
}

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import SearchToolbar from '../../../components/filters/SearchToolbar.jsx'
import { useAuth } from '../../../context/AuthContext.jsx'
import { resolveAssetUrl } from '../../../lib/api.js'
import { formatArticleDate } from '../../../lib/articles.js'

const SORT_OPTIONS = [
  { value: 'created-desc', label: 'Création: plus récent' },
  { value: 'created-asc', label: 'Création: plus ancien' },
]

function normalizeAdminFiles(payload) {
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

function formatFileSize(size) {
  const value = Number(size ?? 0)

  if (!Number.isFinite(value) || value <= 0) {
    return 'Taille inconnue'
  }

  if (value < 1024) {
    return `${value} o`
  }

  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} Ko`
  }

  return `${(value / (1024 * 1024)).toFixed(1)} Mo`
}

function isImageFile(file) {
  return String(file?.mimeType ?? '').startsWith('image/')
}

function toTimestamp(value) {
  const timestamp = new Date(value ?? '').getTime()
  return Number.isNaN(timestamp) ? 0 : timestamp
}

function filterAndSortFiles(files, search, sort) {
  const query = search.trim().toLowerCase()

  const filteredFiles = files.filter((file) => {
    if (!query) {
      return true
    }

    return String(file?.originalName ?? '').toLowerCase().includes(query)
  })

  const sortedFiles = [...filteredFiles]

  sortedFiles.sort((left, right) => {
    if (sort === 'created-asc') {
      return toTimestamp(left?.createdAt) - toTimestamp(right?.createdAt)
    }

    return toTimestamp(right?.createdAt) - toTimestamp(left?.createdAt)
  })

  return sortedFiles
}

export default function AdminFilesPage() {
  const { deleteAdminFile, fetchAdminFiles, isAdmin, isAuthenticated, isBootstrapping } = useAuth()
  const [files, setFiles] = useState([])
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState('created-desc')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    let cancelled = false

    async function loadFiles() {
      setIsLoading(true)
      setError('')

      try {
        const payload = await fetchAdminFiles()

        if (!cancelled) {
          setFiles(normalizeAdminFiles(payload))
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError.message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    if (isAuthenticated && isAdmin) {
      loadFiles()
    } else {
      setIsLoading(false)
    }

    return () => {
      cancelled = true
    }
  }, [fetchAdminFiles, isAdmin, isAuthenticated])

  async function handleDelete(file) {
    if (!file?.id) {
      return
    }

    const confirmed = window.confirm(
      `Voulez-vous vraiment supprimer le fichier "${file.originalName ?? 'sans nom'}" ?`,
    )

    if (!confirmed) {
      return
    }

    setFeedback('')
    setError('')
    setDeletingId(file.id)

    try {
      await deleteAdminFile(file.id)
      setFiles((current) => current.filter((currentFile) => currentFile.id !== file.id))
      setFeedback('Fichier supprimé avec succès.')
    } catch (deleteError) {
      setError(deleteError.message)
    } finally {
      setDeletingId(null)
    }
  }

  const visibleFiles = filterAndSortFiles(files, search, sort)

  if (isBootstrapping) {
    return (
      <main className="page">
        <section className="page__content page__content--wide">
          <p>Chargement…</p>
        </section>
      </main>
    )
  }

  if (!isAuthenticated || !isAdmin) {
    return (
      <main className="page">
        <section className="page__content page__content--wide">
          <p className="eyebrow">Administration</p>
          <h1>Gestion des fichiers</h1>
          <p>Vous devez être administrateur pour accéder à cette page.</p>
        </section>
      </main>
    )
  }

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Gestion des fichiers</h1>
        <p>Recherchez les fichiers par nom et triez-les par date d'ajout.</p>

        <div className="page__actions page__actions--left">
          <Link to="/admin" className="button button--ghost">
            Retour à l'administration
          </Link>
        </div>

        <SearchToolbar
          searchLabel="Nom du fichier"
          searchPlaceholder="Rechercher un fichier par nom"
          searchValue={search}
          onSearchChange={setSearch}
          sortLabel="Tri"
          sortValue={sort}
          onSortChange={setSort}
          sortOptions={SORT_OPTIONS}
        />

        {feedback ? <p className="form-feedback form-feedback--success">{feedback}</p> : null}
        {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}
        {isLoading ? <p className="articles-state">Chargement des fichiers…</p> : null}

        {!isLoading && !error && visibleFiles.length === 0 ? (
          <p className="articles-state">Aucun fichier ne correspond à votre recherche.</p>
        ) : null}

        {!isLoading && !error && visibleFiles.length > 0 ? (
          <div className="files-grid">
            {visibleFiles.map((file) => {
              const fileUrl = resolveAssetUrl(file.storagePath)
              const articleId = file?.article?.id

              return (
                <article key={file.id} className="file-card">
                  {isImageFile(file) && fileUrl ? (
                    <img
                      src={fileUrl}
                      alt={file.originalName ?? 'Aperçu du fichier'}
                      className="file-card__preview"
                    />
                  ) : (
                    <div className="file-card__preview file-card__preview--empty">
                      <span>{file.mimeType ?? 'Fichier'}</span>
                    </div>
                  )}

                  <div className="file-card__body">
                    <h2>{file.originalName ?? 'Fichier sans nom'}</h2>

                    <dl className="detail-list">
                      <div>
                        <dt>Type</dt>
                        <dd>{file.mimeType ?? 'Inconnu'}</dd>
                      </div>
                      <div>
                        <dt>Taille</dt>
                        <dd>{formatFileSize(file.size)}</dd>
                      </div>
                      <div>
                        <dt>Ajouté le</dt>
                        <dd>{formatArticleDate(file.createdAt, { hour: '2-digit', minute: '2-digit' })}</dd>
                      </div>
                      <div>
                        <dt>Article lié</dt>
                        <dd>{file?.article?.title ?? 'Article inconnu'}</dd>
                      </div>
                    </dl>

                    <div className="article-card__actions">
                      {fileUrl ? (
                        <a
                          href={fileUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="button button--ghost"
                        >
                          Ouvrir le fichier
                        </a>
                      ) : null}

                      {articleId ? (
                        <Link to={`/admin/articles/${articleId}`} className="button button--ghost">
                          Voir l'article
                        </Link>
                      ) : null}

                      <button
                        type="button"
                        className="button button--danger"
                        disabled={deletingId === file.id}
                        onClick={() => handleDelete(file)}
                      >
                        {deletingId === file.id ? 'Suppression…' : 'Supprimer'}
                      </button>
                    </div>
                  </div>
                </article>
              )
            })}
          </div>
        ) : null}
      </section>
    </main>
  )
}

import { lazy, Suspense, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import ArticleGallery from '../../../components/articles/ArticleGallery.jsx'
import { useAuth } from '../../../context/AuthContext.jsx'
import { apiRequest } from '../../../lib/api.js'
import { formatArticleDate, normalizeFiles } from '../../../lib/articles.js'

const RichTextEditor = lazy(() => import('../../../components/forms/RichTextEditor.jsx'))

const initialForm = {
  title: '',
  description: '',
  content: '',
}

export default function AdminEditArticlePage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { deleteArticle, updateArticle, uploadArticleImages } = useAuth()
  const [article, setArticle] = useState(null)
  const [files, setFiles] = useState([])
  const [form, setForm] = useState(initialForm)
  const [selectedImages, setSelectedImages] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false

    async function loadArticle() {
      if (!id) {
        setError('Article introuvable.')
        setIsLoading(false)
        return
      }

      setIsLoading(true)
      setError('')

      try {
        const [articleData, filesData] = await Promise.all([
          apiRequest(`/articles/${id}`, { method: 'GET' }),
          apiRequest(`/articles/${id}/files`, { method: 'GET' }).catch(() => []),
        ])

        if (!cancelled) {
          setArticle(articleData)
          setFiles(normalizeFiles(filesData))
          setForm({
            title: articleData?.title ?? '',
            description: articleData?.description ?? '',
            content: articleData?.content ?? '',
          })
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

    loadArticle()

    return () => {
      cancelled = true
    }
  }, [id])

  function updateField(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  function handleImagesChange(event) {
    setSelectedImages(Array.from(event.target.files ?? []))
  }

  async function reloadFiles(articleId) {
    const payload = await apiRequest(`/articles/${articleId}/files`, {
      method: 'GET',
    }).catch(() => [])

    setFiles(normalizeFiles(payload))
  }

  async function handleSubmit(event) {
    event.preventDefault()

    if (!article?.id) {
      return
    }

    setMessage('')
    setError('')
    setIsSaving(true)

    try {
      const updatedArticle = await updateArticle(article.id, {
        title: form.title,
        description: form.description || null,
        content: form.content,
      })

      if (selectedImages.length > 0) {
        await uploadArticleImages(article.id, selectedImages)
        await reloadFiles(article.id)
      }

      setArticle(updatedArticle)
      setForm({
        title: updatedArticle?.title ?? '',
        description: updatedArticle?.description ?? '',
        content: updatedArticle?.content ?? '',
      })
      setSelectedImages([])
      setMessage(
        selectedImages.length > 0
          ? 'Article et nouvelles images enregistrés avec succès.'
          : 'Article mis à jour avec succès.',
      )
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDelete() {
    if (!article?.id) {
      return
    }

    const confirmed = window.confirm(
      `Voulez-vous vraiment supprimer l'article "${article.title ?? 'sans titre'}" ?`,
    )

    if (!confirmed) {
      return
    }

    setMessage('')
    setError('')
    setIsDeleting(true)

    try {
      await deleteArticle(article.id)
      navigate('/admin/articles')
    } catch (deleteError) {
      setError(deleteError.message)
      setIsDeleting(false)
    }
  }

  if (isLoading) {
    return (
      <main className="page">
        <section className="page__content page__content--wide page__content--left">
          <p>Chargement de l'article…</p>
        </section>
      </main>
    )
  }

  if (error && !article) {
    return (
      <main className="page">
        <section className="page__content page__content--wide page__content--left">
          <p className="form-feedback form-feedback--error">{error}</p>
          <div className="page__actions page__actions--left">
            <Link to="/admin/articles" className="button button--ghost">
              Retour à la liste
            </Link>
          </div>
        </section>
      </main>
    )
  }

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Modifier l'article</h1>
        <p>Modifiez le contenu, ajoutez des images et gérez cet article depuis une seule page.</p>

        <div className="article-detail">
          <ArticleGallery articleTitle={article?.title} files={files} />

          <div className="article-card__meta">
            <span>{formatArticleDate(article?.createdAt, { hour: '2-digit', minute: '2-digit' })}</span>
            <span>{article?.user?.email ?? 'Auteur inconnu'}</span>
          </div>

          {files.length > 0 ? (
            <div className="article-detail__attachments">
              <h2>Fichiers joints</h2>
              <ul className="article-detail__attachments-list">
                {files.map((file) => (
                  <li key={file.id ?? file.storagePath ?? file.originalName}>
                    {file.originalName ?? file.storagePath ?? 'Fichier'}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>

        <form className="form-card" onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Titre</span>
            <input
              type="text"
              name="title"
              value={form.title}
              onChange={updateField}
              placeholder="Titre de l'article"
              maxLength="255"
              required
            />
          </label>

          <label className="form-field">
            <span>Description (optionnel)</span>
            <input
              type="text"
              name="description"
              value={form.description}
              onChange={updateField}
              placeholder="Résumé court de l'article"
              maxLength="255"
            />
          </label>

          <Suspense fallback={<p className="form-helper">Chargement de l'editeur...</p>}>
            <RichTextEditor
              id="article-content-edit"
              label="Contenu"
              value={form.content}
              onChange={(content) => setForm((current) => ({ ...current, content }))}
              required
            />
          </Suspense>

          <label className="form-field">
            <span>Ajouter de nouvelles images (optionnel)</span>
            <input type="file" accept="image/*" multiple onChange={handleImagesChange} />
          </label>

          {selectedImages.length > 0 ? (
            <div className="form-helper">
              <p>{selectedImages.length} image(s) sélectionnée(s).</p>
              <ul className="article-detail__attachments-list">
                {selectedImages.map((image) => (
                  <li key={`${image.name}-${image.size}`}>{image.name}</li>
                ))}
              </ul>
            </div>
          ) : null}

          {message ? <p className="form-feedback form-feedback--success">{message}</p> : null}
          {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}

          <div className="page__actions page__actions--left">
            <button type="submit" className="button button--primary" disabled={isSaving}>
              {isSaving ? 'Enregistrement…' : 'Enregistrer les modifications'}
            </button>
            <Link to="/admin/articles" className="button button--ghost">
              Retour à la liste
            </Link>
            <button
              type="button"
              className="button button--danger"
              disabled={isDeleting}
              onClick={handleDelete}
            >
              {isDeleting ? 'Suppression…' : "Supprimer l'article"}
            </button>
          </div>
        </form>
      </section>
    </main>
  )
}

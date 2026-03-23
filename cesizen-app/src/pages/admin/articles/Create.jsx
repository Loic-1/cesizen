import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import RichTextEditor from '../../../components/forms/RichTextEditor.jsx'
import { useAuth } from '../../../context/AuthContext.jsx'

const initialForm = {
  title: '',
  description: '',
  content: '',
}

export default function AdminCreateArticlePage() {
  const navigate = useNavigate()
  const { createArticle, uploadArticleImages, isAuthenticated, isBootstrapping, user } = useAuth()
  const [form, setForm] = useState(initialForm)
  const [selectedImages, setSelectedImages] = useState([])
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateField(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  function handleImagesChange(event) {
    setSelectedImages(Array.from(event.target.files ?? []))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setMessage('')
    setError('')
    setIsSubmitting(true)

    try {
      const article = await createArticle({
        userId: user?.id,
        title: form.title,
        description: form.description || null,
        content: form.content,
      })

      if (article?.id && selectedImages.length > 0) {
        await uploadArticleImages(article.id, selectedImages)
      }

      setMessage(
        selectedImages.length > 0
          ? 'Article et images créés avec succès.'
          : 'Article créé avec succès.',
      )
      setForm(initialForm)
      setSelectedImages([])

      if (article?.id) {
        setTimeout(() => navigate(`/admin/articles/${article.id}`), 600)
      }
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isBootstrapping) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p>Chargement…</p>
        </section>
      </main>
    )
  }

  if (!isAuthenticated) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p className="eyebrow">Administration</p>
          <h1>Créer un article</h1>
          <p>Vous devez être connecté pour créer un article.</p>
          <div className="page__actions">
            <Link to="/login" className="button button--primary">
              Aller à la connexion
            </Link>
          </div>
        </section>
      </main>
    )
  }

  return (
    <main className="page">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Créer un article</h1>
        <p>Renseignez les informations de l'article puis validez sa création.</p>

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

          <RichTextEditor
            id="article-content-create"
            label="Contenu"
            value={form.content}
            onChange={(content) => setForm((current) => ({ ...current, content }))}
            required
          />

          <label className="form-field">
            <span>Images de l'article (optionnel)</span>
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
            <button type="submit" className="button button--primary" disabled={isSubmitting}>
              {isSubmitting ? 'Création en cours…' : "Créer l'article"}
            </button>
            <Link to="/admin/articles" className="button button--ghost">
              Retour à la liste
            </Link>
          </div>
        </form>
      </section>
    </main>
  )
}

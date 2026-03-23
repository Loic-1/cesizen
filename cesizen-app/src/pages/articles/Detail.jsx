import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import ArticleGallery from '../../components/articles/ArticleGallery.jsx'
import { apiRequest } from '../../lib/api.js'
import { buildArticleExcerpt, formatArticleDate, normalizeFiles } from '../../lib/articles.js'

export default function ArticleDetailPage() {
  const { id } = useParams()
  const [article, setArticle] = useState(null)
  const [files, setFiles] = useState([])
  const [isLoading, setIsLoading] = useState(true)
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
          apiRequest(`/articles/${id}`, {
            method: 'GET',
          }),
          apiRequest(`/articles/${id}/files`, {
            method: 'GET',
          }).catch(() => []),
        ])

        if (!cancelled) {
          setArticle(articleData)
          setFiles(normalizeFiles(filesData))
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
            <Link to="/articles" className="button button--ghost">
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
        <p className="eyebrow">Article</p>
        <h1>{article?.title ?? 'Article sans titre'}</h1>
        <p>{buildArticleExcerpt(article, 260)}</p>

        <div className="article-detail">
          <ArticleGallery articleTitle={article?.title} files={files} />

          <div className="article-card__meta">
            <span>{formatArticleDate(article?.createdAt, { hour: '2-digit', minute: '2-digit' })}</span>
            <span>{article?.user?.email ?? 'Auteur inconnu'}</span>
          </div>

          {article?.description ? <p className="article-detail__lead">{article.description}</p> : null}

          <div
            className="article-detail__content article-detail__content--rich"
            dangerouslySetInnerHTML={{ __html: article?.content ?? '<p>Aucun contenu disponible.</p>' }}
          />

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

        <div className="page__actions page__actions--left">
          <Link to="/articles" className="button button--ghost">
            Retour à la liste
          </Link>
        </div>
      </section>
    </main>
  )
}

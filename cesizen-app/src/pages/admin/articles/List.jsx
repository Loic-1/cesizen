import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import ArticleCard from '../../../components/articles/ArticleCard.jsx'
import SearchToolbar from '../../../components/filters/SearchToolbar.jsx'
import { useAuth } from '../../../context/AuthContext.jsx'
import {
  buildArticleExcerpt,
  fetchArticles,
  formatArticleDate,
} from '../../../lib/articles.js'

const SORT_OPTIONS = [
  { value: 'created-desc', label: 'Création : plus récent' },
  { value: 'created-asc', label: 'Création : plus ancien' },
  { value: 'updated-desc', label: 'Modification : plus récente' },
  { value: 'updated-asc', label: 'Modification : plus ancienne' },
]

function toTimestamp(value) {
  const timestamp = new Date(value ?? '').getTime()
  return Number.isNaN(timestamp) ? 0 : timestamp
}

function filterAndSortArticles(articles, search, sort) {
  const query = search.trim().toLowerCase()

  const filteredArticles = articles.filter((article) => {
    if (!query) {
      return true
    }

    const haystack = [article?.title, article?.description, article?.content]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(query)
  })

  const sortedArticles = [...filteredArticles]

  sortedArticles.sort((left, right) => {
    switch (sort) {
      case 'created-asc':
        return toTimestamp(left?.createdAt) - toTimestamp(right?.createdAt)
      case 'updated-desc':
        return (
          toTimestamp(right?.updatedAt ?? right?.createdAt) -
          toTimestamp(left?.updatedAt ?? left?.createdAt)
        )
      case 'updated-asc':
        return (
          toTimestamp(left?.updatedAt ?? left?.createdAt) -
          toTimestamp(right?.updatedAt ?? right?.createdAt)
        )
      case 'created-desc':
      default:
        return toTimestamp(right?.createdAt) - toTimestamp(left?.createdAt)
    }
  })

  return sortedArticles
}

export default function AdminArticlesPage() {
  const { deleteArticle } = useAuth()
  const [articles, setArticles] = useState([])
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState('created-desc')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    let cancelled = false

    async function loadArticles() {
      setIsLoading(true)
      setError('')

      try {
        const articlesWithThumbnails = await fetchArticles()

        if (!cancelled) {
          setArticles(articlesWithThumbnails)
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

    loadArticles()

    return () => {
      cancelled = true
    }
  }, [])

  async function handleDelete(articleId, articleTitle) {
    const confirmed = window.confirm(
      `Voulez-vous vraiment supprimer l'article "${articleTitle ?? 'sans titre'}" ?`,
    )

    if (!confirmed) {
      return
    }

    setFeedback('')
    setError('')
    setDeletingId(articleId)

    try {
      await deleteArticle(articleId)
      setArticles((current) => current.filter((article) => article.id !== articleId))
      setFeedback('Article supprimé avec succès.')
    } catch (deleteError) {
      setError(deleteError.message)
    } finally {
      setDeletingId(null)
    }
  }

  const visibleArticles = filterAndSortArticles(articles, search, sort)

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Gestion des articles</h1>
        <p>Consultez les articles publiés, recherchez-les et triez-les par date.</p>

        <div className="page__actions page__actions--left">
          <Link to="/admin" className="button button--ghost">
            Retour à l'administration
          </Link>
          <Link to="/admin/articles/new" className="button button--primary">
            Créer un article
          </Link>
        </div>

        <SearchToolbar
          searchLabel="Mot-clé"
          searchPlaceholder="Rechercher par titre, description ou contenu"
          searchValue={search}
          onSearchChange={setSearch}
          sortLabel="Tri"
          sortValue={sort}
          onSortChange={setSort}
          sortOptions={SORT_OPTIONS}
        />

        {feedback ? <p className="form-feedback form-feedback--success">{feedback}</p> : null}
        {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}
        {isLoading ? <p className="articles-state">Chargement des articles…</p> : null}

        {!isLoading && !error && visibleArticles.length === 0 ? (
          <p className="articles-state">Aucun article ne correspond à votre recherche.</p>
        ) : null}

        {!isLoading && !error && visibleArticles.length > 0 ? (
          <div className="articles-grid">
            {visibleArticles.map((article) => (
              <ArticleCard
                key={article.id}
                article={article}
                formattedDate={formatArticleDate(article.createdAt)}
                excerpt={buildArticleExcerpt(article)}
                detailHref={`/admin/articles/${article.id}`}
                primaryAction={
                  <Link to={`/admin/articles/${article.id}`} className="button button--ghost">
                    Voir le détail
                  </Link>
                }
                secondaryAction={
                  <button
                    type="button"
                    className="button button--danger"
                    disabled={deletingId === article.id}
                    onClick={() => handleDelete(article.id, article.title)}
                  >
                    {deletingId === article.id ? 'Suppression…' : 'Supprimer'}
                  </button>
                }
              />
            ))}
          </div>
        ) : null}
      </section>
    </main>
  )
}

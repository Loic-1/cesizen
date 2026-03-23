import { Link } from 'react-router-dom'

export default function ArticleCard({
  article,
  excerpt,
  formattedDate,
  detailHref,
  primaryAction,
  secondaryAction,
}) {
  const thumbnail = article?.thumbnailUrl ? (
    <img
      src={article.thumbnailUrl}
      alt={article.title ?? "Illustration de l'article"}
      className="article-card__thumbnail"
    />
  ) : (
    <div className="article-card__thumbnail article-card__thumbnail--empty" />
  )

  return (
    <article className="article-card">
      {detailHref ? (
        <Link to={detailHref} className="article-card__thumbnail-link">
          <div className="article-card__thumbnail-frame">{thumbnail}</div>
        </Link>
      ) : (
        <div className="article-card__thumbnail-frame">{thumbnail}</div>
      )}

      <div className="article-card__body">
        <div className="article-card__meta">
          <span>{formattedDate}</span>
          <span>{article?.user?.email ?? 'Auteur inconnu'}</span>
        </div>

        <h2>{article?.title ?? 'Sans titre'}</h2>
        <p>{excerpt}</p>

        {primaryAction || secondaryAction ? (
          <div className="article-card__actions">
            {primaryAction}
            {secondaryAction}
          </div>
        ) : null}
      </div>
    </article>
  )
}

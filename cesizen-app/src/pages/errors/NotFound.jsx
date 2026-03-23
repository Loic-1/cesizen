import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <main className="page">
      <section className="page__content page__content--narrow">
        <p className="eyebrow">Erreur</p>
        <h1>404 - Page introuvable</h1>
        <p>La page que vous cherchez n'existe pas ou n'est plus disponible.</p>

        <div className="page__actions">
          <Link to="/" className="button button--ghost">
            Retour à l'accueil
          </Link>
          <Link to="/articles" className="button button--primary">
            Voir les articles
          </Link>
        </div>
      </section>
    </main>
  )
}

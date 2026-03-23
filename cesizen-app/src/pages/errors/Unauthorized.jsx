import { Link, useLocation } from 'react-router-dom'

export default function UnauthorizedPage() {
  const location = useLocation()
  const title = location.state?.title ?? '401 - Accès non autorisé'
  const message =
    location.state?.message ?? "Vous n'avez pas l'autorisation nécessaire pour accéder à cette page."

  return (
    <main className="page">
      <section className="page__content page__content--narrow">
        <p className="eyebrow">Erreur</p>
        <h1>{title}</h1>
        <p>{message}</p>

        <div className="page__actions">
          <Link to="/" className="button button--ghost">
            Retour à l'accueil
          </Link>
          <Link to="/login" className="button button--primary">
            Aller à la connexion
          </Link>
        </div>
      </section>
    </main>
  )
}

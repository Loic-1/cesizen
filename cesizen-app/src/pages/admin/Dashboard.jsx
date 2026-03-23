import { Link } from 'react-router-dom'

export default function AdminDashboardPage() {
  return (
    <main className="page">
      <section className="page__content page__content--wide">
        <p className="eyebrow">Administration</p>
        <h1>Tableau de bord</h1>
        <p>Ceci est le point d'entrée de l'administration Cesizen.</p>

        <div className="page__actions">
          <Link to="/admin/articles" className="button button--ghost">
            Voir la liste des articles
          </Link>
          <Link to="/admin/files" className="button button--ghost">
            Gérer les fichiers
          </Link>
          <Link to="/admin/users" className="button button--ghost">
            Voir la liste des utilisateurs
          </Link>
          <Link to="/admin/articles/new" className="button button--primary">
            Créer un article
          </Link>
        </div>
      </section>
    </main>
  )
}

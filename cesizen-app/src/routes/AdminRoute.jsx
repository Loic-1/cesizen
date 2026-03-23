import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'

export default function AdminRoute() {
  const { isAdmin, isAuthenticated, isBootstrapping } = useAuth()

  if (isBootstrapping) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p>Vérification de l'accès…</p>
        </section>
      </main>
    )
  }

  if (!isAuthenticated) {
    return (
      <Navigate
        to="/401"
        replace
        state={{
          title: '401 - Accès non autorisé',
          message: "Vous devez être connecté pour accéder à l'administration.",
        }}
      />
    )
  }

  if (!isAdmin) {
    return (
      <Navigate
        to="/401"
        replace
        state={{
          title: '401 - Accès administrateur requis',
          message: "Vous devez être administrateur pour accéder à cette page.",
        }}
      />
    )
  }

  return <Outlet />
}

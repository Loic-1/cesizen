import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../../context/AuthContext.jsx'

const ROLE_OPTIONS = [
  { value: 'ROLE_USER', label: 'Utilisateur' },
  { value: 'ROLE_ADMIN', label: 'Administrateur' },
]

export default function AdminUserDetailPage() {
  const navigate = useNavigate()
  const { id } = useParams()
  const { deleteAdminUser, fetchAdminUser, isAdmin, isAuthenticated, isBootstrapping, updateAdminUser } =
    useAuth()
  const [user, setUser] = useState(null)
  const [selectedRole, setSelectedRole] = useState('ROLE_USER')
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  useEffect(() => {
    let cancelled = false

    async function loadUser() {
      if (!id) {
        setError('Utilisateur introuvable.')
        setIsLoading(false)
        return
      }

      setIsLoading(true)
      setError('')

      try {
        const payload = await fetchAdminUser(id)

        if (!cancelled) {
          setUser(payload)
          setSelectedRole(
            Array.isArray(payload?.roles) && payload.roles.includes('ROLE_ADMIN')
              ? 'ROLE_ADMIN'
              : 'ROLE_USER',
          )
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
      loadUser()
    } else {
      setIsLoading(false)
    }

    return () => {
      cancelled = true
    }
  }, [fetchAdminUser, id, isAdmin, isAuthenticated])

  async function handleRoleSubmit(event) {
    event.preventDefault()
    if (!user?.id) {
      return
    }

    setMessage('')
    setError('')
    setIsSaving(true)

    try {
      const updatedUser = await updateAdminUser(user.id, {
        roles: [selectedRole],
      })

      setUser(updatedUser)
      setSelectedRole(
        Array.isArray(updatedUser?.roles) && updatedUser.roles.includes('ROLE_ADMIN')
          ? 'ROLE_ADMIN'
          : 'ROLE_USER',
      )
      setMessage('Rôle mis à jour avec succès.')
    } catch (saveError) {
      setError(saveError.message)
    } finally {
      setIsSaving(false)
    }
  }

  async function handleDeleteUser() {
    if (!user?.id) {
      return
    }

    const confirmed = window.confirm(
      `Voulez-vous vraiment supprimer le compte ${user?.email ?? 'cet utilisateur'} ?`,
    )

    if (!confirmed) {
      return
    }

    setMessage('')
    setError('')
    setIsDeleting(true)

    try {
      await deleteAdminUser(user.id)
      navigate('/admin/users')
    } catch (deleteError) {
      setError(deleteError.message)
      setIsDeleting(false)
    }
  }

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
          <h1>Détail utilisateur</h1>
          <p>Vous devez être administrateur pour accéder à cette page.</p>
        </section>
      </main>
    )
  }

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Détail utilisateur</h1>
        <p>Consultez ce compte, modifiez son rôle et supprimez-le si nécessaire.</p>

        <div className="page__actions page__actions--left">
          <Link to="/admin/users" className="button button--ghost">
            Retour à la liste
          </Link>
        </div>

        {message ? <p className="form-feedback form-feedback--success">{message}</p> : null}
        {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}
        {isLoading ? <p className="articles-state">Chargement de l'utilisateur…</p> : null}

        {!isLoading && user ? (
          <div className="details-grid">
            <article className="detail-card">
              <h2>Informations du compte</h2>
              <dl className="detail-list">
                <div>
                  <dt>Identifiant</dt>
                  <dd>{user.id}</dd>
                </div>
                <div>
                  <dt>E-mail</dt>
                  <dd>{user.email ?? '-'}</dd>
                </div>
                <div>
                  <dt>Rôles actuels</dt>
                  <dd>{Array.isArray(user.roles) ? user.roles.join(', ') : '-'}</dd>
                </div>
                <div>
                  <dt>Vérifié</dt>
                  <dd>{user.isVerified ? 'Oui' : 'Non'}</dd>
                </div>
                <div>
                  <dt>Créé le</dt>
                  <dd>{user.createdAt ?? '-'}</dd>
                </div>
                <div>
                  <dt>Mis à jour le</dt>
                  <dd>{user.updatedAt ?? '-'}</dd>
                </div>
              </dl>
            </article>

            <article className="detail-card">
              <h2>Modifier le rôle</h2>
              <form className="form-card form-card--compact" onSubmit={handleRoleSubmit}>
                <label className="form-field">
                  <span>Rôle principal</span>
                  <select value={selectedRole} onChange={(event) => setSelectedRole(event.target.value)}>
                    {ROLE_OPTIONS.map((role) => (
                      <option key={role.value} value={role.value}>
                        {role.label}
                      </option>
                    ))}
                  </select>
                </label>

                <button type="submit" className="button button--primary" disabled={isSaving}>
                  {isSaving ? 'Enregistrement…' : 'Enregistrer le rôle'}
                </button>
              </form>

              <div className="page__actions page__actions--left">
                <button
                  type="button"
                  className="button button--danger"
                  disabled={isDeleting}
                  onClick={handleDeleteUser}
                >
                  {isDeleting ? 'Suppression…' : 'Supprimer ce compte'}
                </button>
              </div>
            </article>
          </div>
        ) : null}
      </section>
    </main>
  )
}

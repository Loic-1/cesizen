import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import SearchToolbar from '../../../components/filters/SearchToolbar.jsx'
import { useAuth } from '../../../context/AuthContext.jsx'
import { normalizeUsers } from '../../../lib/users.js'

const SORT_OPTIONS = [
  { value: 'created-desc', label: 'Création: plus récent' },
  { value: 'created-asc', label: 'Création: plus ancien' },
]

function toTimestamp(value) {
  const timestamp = new Date(value ?? '').getTime()
  return Number.isNaN(timestamp) ? 0 : timestamp
}

function filterAndSortUsers(users, search, sort) {
  const query = search.trim().toLowerCase()

  const filteredUsers = users.filter((user) => {
    if (!query) {
      return true
    }

    return String(user?.email ?? '').toLowerCase().includes(query)
  })

  const sortedUsers = [...filteredUsers]

  sortedUsers.sort((left, right) => {
    if (sort === 'created-asc') {
      return toTimestamp(left?.createdAt) - toTimestamp(right?.createdAt)
    }

    return toTimestamp(right?.createdAt) - toTimestamp(left?.createdAt)
  })

  return sortedUsers
}

export default function AdminUsersPage() {
  const { deleteAdminUser, fetchAdminUsers, isAdmin, isAuthenticated, isBootstrapping } = useAuth()
  const [users, setUsers] = useState([])
  const [search, setSearch] = useState('')
  const [sort, setSort] = useState('created-desc')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [deletingId, setDeletingId] = useState(null)

  useEffect(() => {
    let cancelled = false

    async function loadUsers() {
      setIsLoading(true)
      setError('')

      try {
        const payload = await fetchAdminUsers()

        if (!cancelled) {
          setUsers(normalizeUsers(payload))
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
      loadUsers()
    } else {
      setIsLoading(false)
    }

    return () => {
      cancelled = true
    }
  }, [fetchAdminUsers, isAdmin, isAuthenticated])

  async function handleDeleteUser(user) {
    const confirmed = window.confirm(
      `Voulez-vous vraiment supprimer le compte ${user?.email ?? 'cet utilisateur'} ?`,
    )

    if (!confirmed) {
      return
    }

    setFeedback('')
    setError('')
    setDeletingId(user.id)

    try {
      await deleteAdminUser(user.id)
      setUsers((current) => current.filter((currentUser) => currentUser.id !== user.id))
      setFeedback('Utilisateur supprimé avec succès.')
    } catch (deleteError) {
      setError(deleteError.message)
    } finally {
      setDeletingId(null)
    }
  }

  const visibleUsers = filterAndSortUsers(users, search, sort)

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
          <h1>Gestion des utilisateurs</h1>
          <p>Vous devez être administrateur pour accéder à cette page.</p>
        </section>
      </main>
    )
  }

  return (
    <main className="page page--top">
      <section className="page__content page__content--wide page__content--left">
        <p className="eyebrow">Administration</p>
        <h1>Gestion des utilisateurs</h1>
        <p>Recherchez les comptes par e-mail et triez-les par date de création.</p>

        <div className="page__actions page__actions--left">
          <Link to="/admin" className="button button--ghost">
            Retour à l'administration
          </Link>
        </div>

        <SearchToolbar
          searchLabel="E-mail"
          searchPlaceholder="Rechercher un utilisateur par e-mail"
          searchValue={search}
          onSearchChange={setSearch}
          sortLabel="Tri"
          sortValue={sort}
          onSortChange={setSort}
          sortOptions={SORT_OPTIONS}
        />

        {feedback ? <p className="form-feedback form-feedback--success">{feedback}</p> : null}
        {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}
        {isLoading ? <p className="articles-state">Chargement des utilisateurs…</p> : null}

        {!isLoading && !error && visibleUsers.length === 0 ? (
          <p className="articles-state">Aucun utilisateur ne correspond à votre recherche.</p>
        ) : null}

        {!isLoading && !error && visibleUsers.length > 0 ? (
          <div className="details-grid">
            {visibleUsers.map((user) => (
              <article key={user.id} className="detail-card">
                <h2>{user.email ?? 'Utilisateur sans e-mail'}</h2>
                <dl className="detail-list">
                  <div>
                    <dt>Identifiant</dt>
                    <dd>{user.id}</dd>
                  </div>
                  <div>
                    <dt>Rôles</dt>
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
                </dl>

                <div className="page__actions page__actions--left">
                  <Link to={`/admin/users/${user.id}`} className="button button--ghost">
                    Voir le détail
                  </Link>
                  <button
                    type="button"
                    className="button button--danger"
                    disabled={deletingId === user.id}
                    onClick={() => handleDeleteUser(user)}
                  >
                    {deletingId === user.id ? 'Suppression…' : 'Supprimer'}
                  </button>
                </div>
              </article>
            ))}
          </div>
        ) : null}
      </section>
    </main>
  )
}

import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext.jsx'

export default function ProfilePage() {
  const navigate = useNavigate()
  const { deleteAccount, isAuthenticated, isBootstrapping, updateProfile, user } = useAuth()
  const [email, setEmail] = useState(user?.email ?? '')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    setEmail(user?.email ?? '')
  }, [user?.email])

  async function handleSubmit(event) {
    event.preventDefault()
    setMessage('')
    setError('')
    setIsSubmitting(true)

    try {
      const updatedUser = await updateProfile({ email })
      setEmail(updatedUser.email ?? '')
      setMessage('Informations mises à jour.')
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleDeleteAccount() {
    const confirmed = window.confirm(
      'Voulez-vous vraiment supprimer votre compte ? Cette action est définitive.',
    )

    if (!confirmed) {
      return
    }

    setMessage('')
    setError('')
    setIsDeleting(true)

    try {
      await deleteAccount()
      navigate('/login')
    } catch (deleteError) {
      setError(deleteError.message)
      setIsDeleting(false)
    }
  }

  if (isBootstrapping) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p>Chargement du profil…</p>
        </section>
      </main>
    )
  }

  if (!isAuthenticated) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p className="eyebrow">Compte</p>
          <h1>Mon profil</h1>
          <p>Vous devez être connecté pour consulter vos informations.</p>
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
      <section className="page__content page__content--wide">
        <p className="eyebrow">Compte</p>
        <h1>Mon profil</h1>
        <p>Consultez vos informations et gérez votre compte.</p>

        <div className="details-grid">
          <article className="detail-card">
            <h2>Informations actuelles</h2>
            <dl className="detail-list">
              <div>
                <dt>E-mail</dt>
                <dd>{user?.email ?? '-'}</dd>
              </div>
              <div>
                <dt>Rôles</dt>
                <dd>{Array.isArray(user?.roles) ? user.roles.join(', ') : '-'}</dd>
              </div>
              <div>
                <dt>Vérifié</dt>
                <dd>{user?.isVerified ? 'Oui' : 'Non'}</dd>
              </div>
              <div>
                <dt>Créé le</dt>
                <dd>{user?.createdAt ?? '-'}</dd>
              </div>
            </dl>
          </article>

          <article className="detail-card">
            <h2>Mettre à jour mes informations</h2>
            <form className="form-card form-card--compact" onSubmit={handleSubmit}>
              <label className="form-field">
                <span>Adresse e-mail</span>
                <input
                  type="email"
                  name="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  required
                />
              </label>

              {message ? <p className="form-feedback form-feedback--success">{message}</p> : null}
              {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}

              <button type="submit" className="button button--primary" disabled={isSubmitting}>
                {isSubmitting ? 'Mise à jour…' : 'Enregistrer'}
              </button>
            </form>
          </article>
        </div>

        <div className="page__actions">
          <Link to="/me/password" className="button button--ghost">
            Modifier mon mot de passe
          </Link>
          <button
            type="button"
            className="button button--danger"
            onClick={handleDeleteAccount}
            disabled={isDeleting}
          >
            {isDeleting ? 'Suppression…' : 'Supprimer mon compte'}
          </button>
        </div>
      </section>
    </main>
  )
}

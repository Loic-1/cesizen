import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext.jsx'

const initialForm = {
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
}

export default function ChangePasswordPage() {
  const { changePassword, isAuthenticated, isBootstrapping } = useAuth()
  const [form, setForm] = useState(initialForm)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateField(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setMessage('')
    setError('')

    if (form.newPassword !== form.confirmPassword) {
      setError('La confirmation du mot de passe ne correspond pas.')
      return
    }

    setIsSubmitting(true)

    try {
      const response = await changePassword({
        currentPassword: form.currentPassword,
        newPassword: form.newPassword,
      })

      setMessage(response?.message ?? 'Mot de passe mis à jour.')
      setForm(initialForm)
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (isBootstrapping) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p>Chargement...</p>
        </section>
      </main>
    )
  }

  if (!isAuthenticated) {
    return (
      <main className="page">
        <section className="page__content page__content--narrow">
          <p className="eyebrow">Compte</p>
          <h1>Changer mon mot de passe</h1>
          <p>Vous devez être connecté pour modifier votre mot de passe.</p>
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
      <section className="page__content page__content--narrow">
        <p className="eyebrow">Compte</p>
        <h1>Changer mon mot de passe</h1>
        <p>Utilisez ce formulaire pour modifier votre mot de passe.</p>

        <form className="form-card" onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Mot de passe actuel</span>
            <input
              type="password"
              name="currentPassword"
              value={form.currentPassword}
              onChange={updateField}
              placeholder="Votre mot de passe actuel"
              autoComplete="current-password"
              required
            />
          </label>

          <label className="form-field">
            <span>Nouveau mot de passe</span>
            <input
              type="password"
              name="newPassword"
              value={form.newPassword}
              onChange={updateField}
              placeholder="12 caractères minimum"
              minLength="12"
              autoComplete="new-password"
              required
            />
          </label>

          <label className="form-field">
            <span>Confirmer le nouveau mot de passe</span>
            <input
              type="password"
              name="confirmPassword"
              value={form.confirmPassword}
              onChange={updateField}
              placeholder="Retapez votre nouveau mot de passe"
              minLength="12"
              autoComplete="new-password"
              required
            />
          </label>

          <p className="form-helper">
            Utilisez au minimum 12 caractères avec une minuscule, une majuscule et un caractère spécial.
          </p>

          {message ? <p className="form-feedback form-feedback--success">{message}</p> : null}
          {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}

          <button type="submit" className="button button--primary" disabled={isSubmitting}>
            {isSubmitting ? 'Mise à jour en cours...' : 'Mettre à jour mon mot de passe'}
          </button>
        </form>

        <div className="page__actions">
          <Link to="/me" className="button button--ghost">
            Retour au profil
          </Link>
        </div>
      </section>
    </main>
  )
}

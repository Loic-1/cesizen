import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext.jsx'

const initialForm = {
  email: '',
  password: '',
}

export default function RegisterPage() {
  const navigate = useNavigate()
  const { register } = useAuth()
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
    setIsSubmitting(true)

    try {
      const data = await register(form)
      setMessage(data?.message ?? 'Compte créé. Vérifiez votre adresse e-mail.')
      setTimeout(() => navigate('/login'), 1200)
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="page">
      <section className="page__content page__content--narrow">
        <p className="eyebrow">Authentification</p>
        <h1>Inscription</h1>
        <p>Créez votre compte Cesizen puis validez votre adresse e-mail.</p>

        <form className="form-card" onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Adresse e-mail</span>
            <input
              type="email"
              name="email"
              value={form.email}
              onChange={updateField}
              placeholder="vous@exemple.com"
              required
            />
          </label>

          <label className="form-field">
            <span>Mot de passe</span>
            <input
              type="password"
              name="password"
              value={form.password}
              onChange={updateField}
              placeholder="P@ssw0rd!2024"
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
            {isSubmitting ? 'Inscription en cours...' : "S'inscrire"}
          </button>
        </form>

        <p className="form-helper">
          Déjà un compte ? <Link to="/login">Se connecter</Link>
        </p>
      </section>
    </main>
  )
}

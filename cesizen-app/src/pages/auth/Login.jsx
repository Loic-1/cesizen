import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext.jsx'

const initialForm = {
  email: '',
  password: '',
}

export default function LoginPage() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [form, setForm] = useState(initialForm)
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateField(event) {
    const { name, value } = event.target
    setForm((current) => ({ ...current, [name]: value }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setIsSubmitting(true)

    try {
      const data = await login(form)
      const isAdmin = Array.isArray(data?.user?.roles) && data.user.roles.includes('ROLE_ADMIN')
      navigate(isAdmin ? '/admin' : '/')
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
        <h1>Connexion</h1>
        <p>Connectez-vous pour accéder à votre espace Cesizen.</p>

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
              placeholder="Votre mot de passe"
              required
            />
          </label>

          {error ? <p className="form-feedback form-feedback--error">{error}</p> : null}

          <button type="submit" className="button button--primary" disabled={isSubmitting}>
            {isSubmitting ? 'Connexion en cours…' : 'Se connecter'}
          </button>
        </form>

        <p className="form-helper">
          Pas encore de compte ? <Link to="/register">S'inscrire</Link>
        </p>
      </section>
    </main>
  )
}

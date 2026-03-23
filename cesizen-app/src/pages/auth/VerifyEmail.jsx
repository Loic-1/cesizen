import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { apiRequest } from '../../lib/api.js'

export default function VerifyEmailPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token')?.trim() ?? ''
  const [status, setStatus] = useState(token ? 'loading' : 'missing-token')
  const [message, setMessage] = useState('')
  const [email, setEmail] = useState('')
  const [resendMessage, setResendMessage] = useState('')
  const [resendError, setResendError] = useState('')
  const [isResending, setIsResending] = useState(false)

  useEffect(() => {
    let cancelled = false

    async function verifyEmail() {
      if (!token) {
        setStatus('missing-token')
        setMessage("Le lien de vérification est incomplet ou invalide.")
        return
      }

      setStatus('loading')
      setMessage('')

      try {
        const data = await apiRequest(`/auth/verify-email?token=${encodeURIComponent(token)}`, {
          method: 'GET',
        })

        if (!cancelled) {
          setStatus('success')
          setMessage(data?.message ?? 'Adresse e-mail vérifiée avec succès.')
        }
      } catch (error) {
        if (!cancelled) {
          setStatus('error')
          setMessage(error.message)
        }
      }
    }

    verifyEmail()

    return () => {
      cancelled = true
    }
  }, [token])

  async function handleResend(event) {
    event.preventDefault()
    setResendMessage('')
    setResendError('')
    setIsResending(true)

    try {
      const data = await apiRequest('/auth/resend-verification-email', {
        method: 'POST',
        body: JSON.stringify({ email }),
      })

      setResendMessage(
        data?.message ?? "Si le compte existe, un nouvel e-mail de vérification a été envoyé.",
      )
      setEmail('')
    } catch (error) {
      setResendError(error.message)
    } finally {
      setIsResending(false)
    }
  }

  return (
    <main className="page">
      <section className="page__content page__content--narrow">
        <p className="eyebrow">Authentification</p>
        <h1>Vérification de l'e-mail</h1>

        {status === 'loading' ? <p>Vérification de votre adresse e-mail en cours…</p> : null}

        {status === 'success' ? (
          <>
            <p className="form-feedback form-feedback--success">{message}</p>
            <p>Votre compte est maintenant actif. Vous pouvez vous connecter.</p>
            <div className="page__actions">
              <Link to="/login" className="button button--primary">
                Aller à la connexion
              </Link>
              <Link to="/" className="button button--ghost">
                Retour à l'accueil
              </Link>
            </div>
          </>
        ) : null}

        {status === 'missing-token' || status === 'error' ? (
          <>
            <p className="form-feedback form-feedback--error">{message}</p>
            <p>
              Vous pouvez demander un nouveau lien de vérification si votre e-mail existe déjà
              dans Cesizen.
            </p>

            <form className="form-card" onSubmit={handleResend}>
              <label className="form-field">
                <span>Adresse e-mail</span>
                <input
                  type="email"
                  name="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="vous@exemple.com"
                  required
                />
              </label>

              {resendMessage ? (
                <p className="form-feedback form-feedback--success">{resendMessage}</p>
              ) : null}
              {resendError ? <p className="form-feedback form-feedback--error">{resendError}</p> : null}

              <button type="submit" className="button button--primary" disabled={isResending}>
                {isResending ? 'Envoi en cours…' : 'Renvoyer un e-mail de vérification'}
              </button>
            </form>

            <div className="page__actions">
              <Link to="/login" className="button button--ghost">
                Retour à la connexion
              </Link>
              <Link to="/register" className="button button--ghost">
                Créer un compte
              </Link>
            </div>
          </>
        ) : null}
      </section>
    </main>
  )
}

import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
// import logoCesizen from '../assets/logo-cesizen.jpg'
import logoCesizen from '../assets/logo-cesizen.png'
import { useAuth } from '../context/AuthContext.jsx'

const primaryLinks = [
  { to: '/articles', label: 'Articles' },
  { to: '/coherence-cardiaque', label: 'Cohérence cardiaque' },
]

function HeaderLink({ to, children }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        isActive ? 'app-header__link app-header__link--active' : 'app-header__link'
      }
    >
      {children}
    </NavLink>
  )
}

function ChevronIcon({ direction }) {
  const path =
    direction === 'up'
      ? 'M6 15l6-6 6 6'
      : 'M6 9l6 6 6-6'

  return (
    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none">
      <path
        d={path}
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

export default function AppLayout() {
  const navigate = useNavigate()
  const { isAdmin, isAuthenticated, isBootstrapping, logout, user } = useAuth()
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false)

  async function handleLogout() {
    await logout()
    setIsUserMenuOpen(false)
    navigate('/login')
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <div className="app-header__inner">
          <div className="app-header__brand-group">
            <NavLink to="/" className="app-header__brand" aria-label="Retour à l'accueil">
              <img
                src={logoCesizen}
                alt="Cesizen"
                className="app-header__logo"
                width="56"
                height="56"
              />
            </NavLink>
          </div>

          <nav className="app-header__nav" aria-label="Navigation principale">
            <HeaderLink to="/">Accueil</HeaderLink>

            {primaryLinks.map((link) => (
              <div key={link.to} className="app-header__nav-item">
                <span className="app-header__separator" aria-hidden="true">
                  |
                </span>
                <HeaderLink to={link.to}>{link.label}</HeaderLink>
              </div>
            ))}

            {isBootstrapping ? (
              <div className="app-header__nav-item">
                <span className="app-header__separator" aria-hidden="true">
                  |
                </span>
                <span className="app-header__status">Chargement…</span>
              </div>
            ) : null}

            {!isBootstrapping && !isAuthenticated ? (
              <div className="app-header__nav-item">
                <span className="app-header__separator" aria-hidden="true">
                  |
                </span>
                <HeaderLink to="/login">Connexion</HeaderLink>
              </div>
            ) : null}

            {isAuthenticated ? (
              <div className="app-header__nav-item user-menu">
                <span className="app-header__separator" aria-hidden="true">
                  |
                </span>
                <button
                  type="button"
                  className="user-menu__trigger"
                  onClick={() => setIsUserMenuOpen((current) => !current)}
                >
                  <span className="user-menu__name">{user?.email ?? 'Mon compte'}</span>
                  {isUserMenuOpen ? <ChevronIcon direction="up" /> : <ChevronIcon direction="down" />}
                </button>

                {isUserMenuOpen ? (
                  <div className="user-menu__panel">
                    <NavLink
                      to="/me"
                      className="user-menu__item"
                      onClick={() => setIsUserMenuOpen(false)}
                    >
                      Mon profil
                    </NavLink>

                    {isAdmin ? (
                      <NavLink
                        to="/admin"
                        className="user-menu__item"
                        onClick={() => setIsUserMenuOpen(false)}
                      >
                        Administration
                      </NavLink>
                    ) : null}

                    <button type="button" className="user-menu__item" onClick={handleLogout}>
                      Déconnexion
                    </button>
                  </div>
                ) : null}
              </div>
            ) : null}
          </nav>
        </div>
      </header>

      <div className="app-shell__content">
        <Outlet />
      </div>
    </div>
  )
}

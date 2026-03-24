import { createBrowserRouter } from 'react-router-dom'

function lazyComponent(loader) {
  return async () => {
    const module = await loader()

    return { Component: module.default }
  }
}

export const router = createBrowserRouter([
  {
    path: '/',
    lazy: lazyComponent(() => import('./layouts/AppLayout.jsx')),
    children: [
      { index: true, lazy: lazyComponent(() => import('./pages/App.jsx')) },
      { path: 'articles', lazy: lazyComponent(() => import('./pages/articles/List.jsx')) },
      { path: 'articles/:id', lazy: lazyComponent(() => import('./pages/articles/Detail.jsx')) },
      { path: 'login', lazy: lazyComponent(() => import('./pages/auth/Login.jsx')) },
      { path: 'register', lazy: lazyComponent(() => import('./pages/auth/Register.jsx')) },
      { path: 'verify-email', lazy: lazyComponent(() => import('./pages/auth/VerifyEmail.jsx')) },
      { path: 'coherence-cardiaque', lazy: lazyComponent(() => import('./pages/wellness/CardiacCoherence.jsx')) },
      { path: '401', lazy: lazyComponent(() => import('./pages/errors/Unauthorized.jsx')) },
      {
        path: 'me',
        lazy: lazyComponent(() => import('./routes/AccountRoute.jsx')),
        children: [
          { index: true, lazy: lazyComponent(() => import('./pages/account/Profile.jsx')) },
          { path: 'password', lazy: lazyComponent(() => import('./pages/account/ChangePassword.jsx')) },
        ],
      },
      {
        path: 'admin',
        lazy: lazyComponent(() => import('./routes/AdminRoute.jsx')),
        children: [
          { index: true, lazy: lazyComponent(() => import('./pages/admin/Dashboard.jsx')) },
          { path: 'articles', lazy: lazyComponent(() => import('./pages/admin/articles/List.jsx')) },
          { path: 'articles/new', lazy: lazyComponent(() => import('./pages/admin/articles/Create.jsx')) },
          { path: 'articles/:id', lazy: lazyComponent(() => import('./pages/admin/articles/Edit.jsx')) },
          { path: 'files', lazy: lazyComponent(() => import('./pages/admin/files/List.jsx')) },
          { path: 'users', lazy: lazyComponent(() => import('./pages/admin/users/List.jsx')) },
          { path: 'users/:id', lazy: lazyComponent(() => import('./pages/admin/users/Detail.jsx')) },
        ],
      },
      { path: '*', lazy: lazyComponent(() => import('./pages/errors/NotFound.jsx')) },
    ],
  },
])

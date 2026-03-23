import { createBrowserRouter } from 'react-router-dom'
import AppLayout from './layouts/AppLayout.jsx'
import HomePage from './pages/App.jsx'
import ArticlesPage from './pages/articles/List.jsx'
import ArticleDetailPage from './pages/articles/Detail.jsx'
import LoginPage from './pages/auth/Login.jsx'
import RegisterPage from './pages/auth/Register.jsx'
import VerifyEmailPage from './pages/auth/VerifyEmail.jsx'
import CardiacCoherencePage from './pages/wellness/CardiacCoherence.jsx'
import NotFoundPage from './pages/errors/NotFound.jsx'
import UnauthorizedPage from './pages/errors/Unauthorized.jsx'
import AccountRoute from './routes/AccountRoute.jsx'
import ProfilePage from './pages/account/Profile.jsx'
import ChangePasswordPage from './pages/account/ChangePassword.jsx'
import AdminRoute from './routes/AdminRoute.jsx'
import AdminDashboardPage from './pages/admin/Dashboard.jsx'
import AdminArticlesPage from './pages/admin/articles/List.jsx'
import AdminCreateArticlePage from './pages/admin/articles/Create.jsx'
import AdminEditArticlePage from './pages/admin/articles/Edit.jsx'
import AdminFilesPage from './pages/admin/files/List.jsx'
import AdminUsersPage from './pages/admin/users/List.jsx'
import AdminUserDetailPage from './pages/admin/users/Detail.jsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'articles', element: <ArticlesPage /> },
      { path: 'articles/:id', element: <ArticleDetailPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'register', element: <RegisterPage /> },
      { path: 'verify-email', element: <VerifyEmailPage /> },
      { path: 'coherence-cardiaque', element: <CardiacCoherencePage /> },
      { path: '401', element: <UnauthorizedPage /> },
      {
        path: 'me',
        element: <AccountRoute />,
        children: [
          { index: true, element: <ProfilePage /> },
          { path: 'password', element: <ChangePasswordPage /> },
        ],
      },
      {
        path: 'admin',
        element: <AdminRoute />,
        children: [
          { index: true, element: <AdminDashboardPage /> },
          { path: 'articles', element: <AdminArticlesPage /> },
          { path: 'articles/new', element: <AdminCreateArticlePage /> },
          { path: 'articles/:id', element: <AdminEditArticlePage /> },
          { path: 'files', element: <AdminFilesPage /> },
          { path: 'users', element: <AdminUsersPage /> },
          { path: 'users/:id', element: <AdminUserDetailPage /> },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])

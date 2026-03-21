import { createBrowserRouter } from 'react-router-dom'
import AppPage from './pages/App.jsx'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <AppPage />,
  },
])

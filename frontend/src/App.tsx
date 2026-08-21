import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AppLayout } from './components/AppLayout';
import { RequireAuth } from './components/RequireAuth';
import { AuthProvider } from './context/AuthContext';
import { ConfigProvider, useConfig } from './context/ConfigContext';
import { LoginPage } from './pages/LoginPage';
import { ScreenerPage } from './pages/ScreenerPage';
import { StockDetailPage } from './pages/StockDetailPage';
import { NotFoundPage } from './pages/NotFoundPage';
import { ErrorNotice, Spinner } from './components/ui';

/**
 * Konfigurasi harus siap sebelum apa pun dirender: nama aplikasi dan tema
 * ikut menentukan tampilan halaman login itu sendiri.
 */
function ConfigGate({ children }: { children: React.ReactNode }) {
  const { loading, error, reload } = useConfig();

  if (loading) {
    return (
      <div className="grid h-screen place-items-center bg-canvas">
        <Spinner label="Memuat konfigurasi…" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="grid h-screen place-items-center bg-canvas px-4">
        <div className="w-full max-w-md">
          <ErrorNotice
            message={`Gagal memuat konfigurasi aplikasi. ${error}`}
            onRetry={reload}
          />
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

export default function App() {
  return (
    <ConfigProvider>
      <ConfigGate>
        <AuthProvider>
          <BrowserRouter>
            <Routes>
              <Route path="/login" element={<LoginPage />} />

              <Route
                element={
                  <RequireAuth>
                    <AppLayout />
                  </RequireAuth>
                }
              >
                {/* Fase 1 membuka satu alur: screener dan detail emiten. */}
                <Route path="/screener" element={<ScreenerPage />} />
                <Route path="/saham/:symbol" element={<StockDetailPage />} />
                <Route path="/dashboard" element={<Navigate to="/screener" replace />} />
                <Route path="/" element={<Navigate to="/screener" replace />} />
                <Route path="*" element={<NotFoundPage />} />
              </Route>
            </Routes>
          </BrowserRouter>
        </AuthProvider>
      </ConfigGate>
    </ConfigProvider>
  );
}

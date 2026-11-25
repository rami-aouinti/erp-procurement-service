import { useCallback, useEffect, useState } from 'react';
import { Building2, LayoutDashboard, Moon, Sun } from 'lucide-react';
import MetricCard from './components/MetricCard.jsx';
import StatusPanel from './components/StatusPanel.jsx';
import SuppliersPage from './pages/SuppliersPage.jsx';
import SupplierEditPage from './pages/SupplierEditPage.jsx';
import SupplierDetails from './components/SupplierDetails.jsx';
import { suppliersApi } from './api/suppliers.js';

function routeFromLocation() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  const detail = path.match(/^\/suppliers\/([^/]+)$/);
  const edit = path.match(/^\/suppliers\/([^/]+)\/edit$/);
  if (edit) return { page: 'edit', id: decodeURIComponent(edit[1]) };
  if (detail) return { page: 'detail', id: decodeURIComponent(detail[1]) };
  if (path === '/suppliers') return { page: 'suppliers' };
  return { page: 'dashboard' };
}

export default function App() {
  const [route, setRoute] = useState(routeFromLocation);
  const [apiStatus, setApiStatus] = useState('checking');
  const [apiMessage, setApiMessage] = useState('Connecting to Symfony...');
  const [supplierCount, setSupplierCount] = useState(0);
  const [theme, setTheme] = useState(() => window.localStorage.getItem('erp-theme') || 'light');

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    window.localStorage.setItem('erp-theme', theme);
  }, [theme]);

  const navigate = useCallback((path) => {
    window.history.pushState({}, '', path);
    setRoute(routeFromLocation());
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, []);

  useEffect(() => {
    const onPopState = () => setRoute(routeFromLocation());
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  useEffect(() => {
    let cancelled = false;
    async function checkApi() {
      try {
        const response = await fetch('/api/health');
        if (!response.ok) throw new Error(`Symfony returned HTTP ${response.status}`);
        const data = await response.json();
        if (!cancelled) {
          setApiStatus('online');
          setApiMessage(`${data.application || 'Symfony API'} is responding`);
        }
      } catch (error) {
        if (!cancelled) {
          setApiStatus('offline');
          setApiMessage(error.message || 'Could not reach the backend');
        }
      }
    }
    checkApi();
    const interval = window.setInterval(checkApi, 30000);
    return () => { cancelled = true; window.clearInterval(interval); };
  }, []);

  useEffect(() => {
    suppliersApi.list().then((data) => setSupplierCount(data.length)).catch(() => {});
  }, [route.page]);

  const isActive = (page) => route.page === page || (page === 'suppliers' && ['detail', 'edit'].includes(route.page));
  const pageTitle = route.page === 'dashboard' ? 'Dashboard' : route.page === 'suppliers' ? 'Suppliers' : route.page === 'edit' ? 'Edit supplier' : 'Supplier details';

  return (
    <div className="erp-app">
      <aside className="sidebar">
        <a className="brand" href="/" onClick={(event) => { event.preventDefault(); navigate('/'); }}>
          <span className="brand-mark">P</span><span className="brand-copy"><strong>Procurement</strong><small>MANAGEMENT SUITE</small></span>
        </a>
        <div className="sidebar-label">WORKSPACE</div>
        <nav className="side-nav" aria-label="Main navigation">
          <a href="/" className={isActive('dashboard') ? 'nav-link active' : 'nav-link'} onClick={(e) => { e.preventDefault(); navigate('/'); }}><span className="nav-icon"><LayoutDashboard size={18} /></span>Dashboard</a>
          <a href="/suppliers" className={isActive('suppliers') ? 'nav-link active' : 'nav-link'} onClick={(e) => { e.preventDefault(); navigate('/suppliers'); }}><span className="nav-icon"><Building2 size={18} /></span>Suppliers<span className="nav-count">{supplierCount}</span></a>
        </nav>
        <div className="sidebar-bottom">
          <div className="sidebar-connection"><span className={`connection-dot ${apiStatus}`}></span><span><strong>Backend API</strong><small>{apiStatus === 'online' ? 'Connected' : apiStatus === 'offline' ? 'Unavailable' : 'Checking...'}</small></span></div>
          <div className="sidebar-version">ERP Procurement · Development</div>
        </div>
      </aside>
      <main className="main-area">
        <header className="topbar">
          <div className="breadcrumbs"><span>Workspace</span><span className="crumb-separator">/</span><strong>{pageTitle}</strong></div>
          <div className="topbar-right">
            <span className="environment-label"><span className="environment-dot"></span>LOCAL DEVELOPMENT</span>
            <button className="theme-toggle" type="button" onClick={() => setTheme((current) => current === 'dark' ? 'light' : 'dark')} aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'} title={theme === 'dark' ? 'Light mode' : 'Dark mode'}>
              {theme === 'dark' ? <Sun size={17} /> : <Moon size={17} />}<span>{theme === 'dark' ? 'Light mode' : 'Dark mode'}</span>
            </button>
            <span className="avatar">RA</span>
          </div>
        </header>
        {route.page === 'dashboard' && <section className="page-content">
          <div className="page-heading"><div><p className="eyebrow">OVERVIEW</p><h1>Dashboard</h1><p className="subtitle">Welcome back. Here is what's happening in your procurement workspace.</p></div><span className="date-label">System overview</span></div>
          <div className="metrics-grid"><MetricCard label="Suppliers" value={String(supplierCount)} detail="Registered business partners" /><MetricCard label="Purchase orders" value="—" detail="Procurement activity" /><MetricCard label="Products in stock" value="—" detail="Inventory overview" /></div>
          <div className="dashboard-grid"><StatusPanel status={apiStatus} message={apiMessage} /><section className="welcome-panel"><div className="welcome-icon">↗</div><div><h3>Supplier management</h3><p>Keep your supplier records, contacts, addresses and payment information up to date.</p><button className="primary-button" onClick={() => navigate('/suppliers')}>Open suppliers <span>→</span></button></div></section></div>
          <footer className="page-footer">React + Vite <span>·</span> Symfony 7.4 <span>·</span> Oracle Database</footer>
        </section>}
        {route.page === 'suppliers' && <SuppliersPage navigate={navigate} onCountChange={setSupplierCount} />}
        {route.page === 'edit' && <SupplierEditPage supplierId={route.id} navigate={navigate} />}
        {route.page === 'detail' && <SupplierDetailRoute supplierId={route.id} navigate={navigate} />}
      </main>
    </div>
  );
}

function SupplierDetailRoute({ supplierId, navigate }) {
  const [supplier, setSupplier] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    let active = true;
    suppliersApi.list().then((items) => {
      const found = items.find((item) => String(item.id) === String(supplierId));
      if (active) found ? setSupplier(found) : setError('Supplier not found.');
    }).catch((err) => { if (active) setError(err.message); });
    return () => { active = false; };
  }, [supplierId]);
  return <section className="page-content compact-page">{error && <div className="alert alert-error">{error}</div>}{supplier ? <SupplierDetails supplier={supplier} onBack={() => navigate('/suppliers')} onEdit={() => navigate(`/suppliers/${encodeURIComponent(supplier.id)}/edit`)} /> : !error ? <div className="supplier-details"><div className="table-state"><span className="loader"></span>Loading supplier...</div></div> : null}</section>;
}

import { useCallback, useEffect, useState } from 'react';
import MetricCard from './components/MetricCard.jsx';
import StatusPanel from './components/StatusPanel.jsx';
import SuppliersSection from './components/SuppliersSection.jsx';

export default function App() {
  const [apiStatus, setApiStatus] = useState('checking');
  const [apiMessage, setApiMessage] = useState('Connecting to Symfony...');
  const [supplierCount, setSupplierCount] = useState(0);

  const updateSupplierCount = useCallback((count) => setSupplierCount(count), []);

  useEffect(() => {
    let cancelled = false;
    async function checkApi() {
      try {
        const response = await fetch('/api/health');
        if (!response.ok) throw new Error(`Symfony returned HTTP ${response.status}`);
        const data = await response.json();
        if (!cancelled) {
          setApiStatus('online');
          setApiMessage(`${data.application} is responding`);
        }
      } catch (error) {
        if (!cancelled) {
          setApiStatus('offline');
          setApiMessage(error.message || 'Could not reach the backend');
        }
      }
    }
    checkApi();
    return () => { cancelled = true; };
  }, []);

  return (
    <main className="app-shell">
      <header className="topbar">
        <a className="brand" href="/">
          <span className="brand-mark">ERP</span>
          <span>Procurement</span>
        </a>
        <span className="environment-label">LOCAL DEVELOPMENT</span>
      </header>
      <section className="page-content">
        <div className="page-heading">
          <div>
            <p className="eyebrow">OVERVIEW</p>
            <h1>Procurement dashboard</h1>
            <p className="subtitle">Your purchasing and inventory workspace.</p>
          </div>
          <span className="date-label">Learning project</span>
        </div>
        <div className="metrics-grid">
          <MetricCard label="Suppliers" value={String(supplierCount)} detail="Connected to Oracle via Symfony" />
          <MetricCard label="Purchase orders" value="—" detail="Next module" />
          <MetricCard label="Products in stock" value="—" detail="Next module" />
        </div>
        <StatusPanel status={apiStatus} message={apiMessage} />
        <SuppliersSection onCountChange={updateSupplierCount} />
        <footer className="page-footer">
          React + Vite <span>·</span> Symfony 7.4 <span>·</span> Oracle Database
        </footer>
      </section>
    </main>
  );
}

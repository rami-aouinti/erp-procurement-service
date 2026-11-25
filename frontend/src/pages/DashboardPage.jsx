import { useEffect, useMemo, useState } from 'react';
import { Activity, ArrowRight, Building2, CheckCircle2, Clock3, RefreshCw, ShieldCheck, TrendingUp, Users } from 'lucide-react';
import { suppliersApi } from '../api/suppliers.js';
import { onboardingApi } from '../api/onboarding.js';

function getItems(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.items)) return payload.items;
  if (Array.isArray(payload?.suppliers)) return payload.suppliers;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

function statusOf(supplier) {
  return String(supplier.status || supplier.onboardingStatus || supplier.approvalStatus || 'unknown').toLowerCase().replace(/[ _]/g, '-');
}

function formatDate(value) {
  if (!value) return 'Recently added';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'Recently added' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
}

export default function DashboardPage({ navigate, roles = [], apiStatus = 'checking', apiMessage = '' }) {
  const [suppliers, setSuppliers] = useState([]);
  const [pending, setPending] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const canReview = roles.includes('supplier-reviewer') || roles.includes('supplier-admin');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const tasks = [suppliersApi.list()];
      if (canReview) tasks.push(onboardingApi.queue());
      const [supplierPayload, queuePayload] = await Promise.all(tasks);
      setSuppliers(getItems(supplierPayload));
      if (canReview) setPending(getItems(queuePayload).length);
    } catch (err) {
      setError(err.message || 'Unable to load dashboard data.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [canReview]);

  const stats = useMemo(() => {
    const active = suppliers.filter((item) => ['active', 'approved', 'enabled'].includes(statusOf(item))).length;
    const pendingCount = suppliers.filter((item) => ['pending', 'submitted', 'in-review', 'under-review'].includes(statusOf(item))).length;
    const inactive = suppliers.filter((item) => ['inactive', 'rejected', 'disabled'].includes(statusOf(item))).length;
    return { active, pending: pendingCount, inactive };
  }, [suppliers]);

  const recent = useMemo(() => [...suppliers]
    .sort((a, b) => new Date(b.createdAt || b.created_at || 0) - new Date(a.createdAt || a.created_at || 0))
    .slice(0, 5), [suppliers]);

  const total = suppliers.length;
  const maxStatus = Math.max(stats.active, stats.pending, stats.inactive, 1);

  return (
    <section className="page-content dashboard-page">
      <div className="dashboard-welcome">
        <div>
          <p className="eyebrow">PROCUREMENT OVERVIEW</p>
          <h1>Dashboard</h1>
          <p className="subtitle">A clear view of your supplier workspace and the latest activity.</p>
        </div>
        <div className="dashboard-actions">
          <span className={'dashboard-live-state ' + (apiStatus === 'online' ? 'is-online' : apiStatus === 'offline' ? 'is-offline' : '')}><span />{apiStatus === 'online' ? 'System operational' : apiStatus === 'offline' ? 'System unavailable' : 'Checking system'}</span>
          <button type="button" className="secondary-button dashboard-refresh" onClick={load} disabled={loading}><RefreshCw size={15} className={loading ? 'spin' : ''} /> Refresh</button>
        </div>
      </div>

      {error && <div className="alert alert-error" role="alert">{error}</div>}

      <div className="dashboard-kpis">
        <article className="dashboard-kpi">
          <div className="dashboard-kpi-top"><span className="dashboard-kpi-icon accent"><Building2 size={19}/></span><span className="dashboard-kpi-caption">SUPPLIER BASE</span></div>
          <strong>{loading ? '—' : total.toLocaleString()}</strong><span className="dashboard-kpi-foot">Registered suppliers</span>
        </article>
        <article className="dashboard-kpi">
          <div className="dashboard-kpi-top"><span className="dashboard-kpi-icon green"><CheckCircle2 size={19}/></span><span className="dashboard-kpi-caption">ACTIVE</span></div>
          <strong>{loading ? '—' : stats.active.toLocaleString()}</strong><span className="dashboard-kpi-foot">{total ? Math.round(stats.active / total * 100) : 0}% of supplier base</span>
        </article>
        <article className="dashboard-kpi">
          <div className="dashboard-kpi-top"><span className="dashboard-kpi-icon amber"><Clock3 size={19}/></span><span className="dashboard-kpi-caption">IN REVIEW</span></div>
          <strong>{loading ? '—' : (canReview && pending !== null ? pending : stats.pending).toLocaleString()}</strong><span className="dashboard-kpi-foot">{canReview ? 'Awaiting review decision' : 'Submitted or under review'}</span>
        </article>
        <article className="dashboard-kpi">
          <div className="dashboard-kpi-top"><span className="dashboard-kpi-icon blue"><Users size={19}/></span><span className="dashboard-kpi-caption">INACTIVE</span></div>
          <strong>{loading ? '—' : stats.inactive.toLocaleString()}</strong><span className="dashboard-kpi-foot">Inactive or rejected</span>
        </article>
      </div>

      <div className="dashboard-content-grid">
        <section className="dashboard-card dashboard-status-card">
          <div className="dashboard-card-heading"><div><h2>Supplier status</h2><p>Distribution based on the supplier records returned by the API.</p></div><span className="dashboard-card-icon"><Activity size={18}/></span></div>
          {loading ? <div className="dashboard-loading-lines"><i/><i/><i/></div> : total === 0 ? <div className="dashboard-empty">No supplier records are available yet.</div> : <div className="status-breakdown">
            {[['Active', stats.active, 'active'], ['In review', stats.pending, 'pending'], ['Inactive', stats.inactive, 'inactive']].map(([label, count, key]) => <div className="status-breakdown-row" key={key}><div className="status-breakdown-label"><span className={'status-marker ' + key}/><span>{label}</span><strong>{count}</strong></div><div className="status-track"><span className={key} style={{ width: (count / maxStatus * 100) + '%' }}/></div></div>)}
          </div>}
          <div className="dashboard-card-footer"><span>{loading ? 'Loading supplier data…' : `${total} supplier records`}</span><button type="button" className="text-action" onClick={() => navigate('/suppliers')}>View suppliers <ArrowRight size={15}/></button></div>
        </section>

        <section className="dashboard-card dashboard-quick-card">
          <div className="dashboard-card-heading"><div><h2>Quick actions</h2><p>Common tasks in your workspace.</p></div><span className="dashboard-card-icon"><TrendingUp size={18}/></span></div>
          <button className="dashboard-quick-action" type="button" onClick={() => navigate('/suppliers')}><span className="quick-action-icon"><Building2 size={17}/></span><span><strong>Manage suppliers</strong><small>Search, review and update supplier records</small></span><ArrowRight size={16}/></button>
          {canReview && <button className="dashboard-quick-action" type="button" onClick={() => navigate('/onboarding')}><span className="quick-action-icon"><ShieldCheck size={17}/></span><span><strong>Review onboarding</strong><small>Process supplier registration requests</small></span><ArrowRight size={16}/></button>}
          <button className="dashboard-quick-action" type="button" onClick={() => navigate('/configuration')}><span className="quick-action-icon"><Activity size={17}/></span><span><strong>Workspace settings</strong><small>Manage preferences and site configuration</small></span><ArrowRight size={16}/></button>
        </section>

        <section className="dashboard-card dashboard-recent-card">
          <div className="dashboard-card-heading"><div><h2>Recently added suppliers</h2><p>Your latest supplier records.</p></div><button type="button" className="text-action" onClick={() => navigate('/suppliers')}>See all <ArrowRight size={15}/></button></div>
          {loading ? <div className="dashboard-loading-lines"><i/><i/><i/></div> : recent.length === 0 ? <div className="dashboard-empty">New suppliers will appear here when they are registered.</div> : <div className="recent-suppliers">{recent.map((supplier, index) => {
            const name = supplier.name || supplier.companyName || supplier.legalName || 'Unnamed supplier';
            const initials = name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
            const status = statusOf(supplier);
            return <div className="recent-supplier-row" key={supplier.id ?? name + index}><span className="recent-supplier-avatar">{initials}</span><span className="recent-supplier-main"><strong>{name}</strong><small>{formatDate(supplier.createdAt || supplier.created_at)}</small></span><span className={'supplier-status-tag ' + (['active','approved','enabled'].includes(status) ? 'success' : ['pending','submitted','in-review','under-review'].includes(status) ? 'warning' : 'neutral')}>{status.replace(/-/g, ' ')}</span></div>;
          })}</div>}
        </section>

        <section className="dashboard-card dashboard-system-card">
          <div className="dashboard-card-heading"><div><h2>System health</h2><p>Backend connectivity.</p></div><span className="dashboard-card-icon"><Activity size={18}/></span></div>
          <div className="system-health-line"><span className={'system-health-dot ' + apiStatus}/><span><strong>Symfony API</strong><small>{apiMessage || 'Waiting for health check'}</small></span><span className={'system-health-label ' + apiStatus}>{apiStatus === 'online' ? 'Online' : apiStatus === 'offline' ? 'Offline' : 'Checking'}</span></div>
          <p className="system-health-note">Supplier metrics are loaded from the authenticated application API. Purchase order and inventory metrics will appear when those modules are available.</p>
        </section>
      </div>
      <footer className="page-footer">ERP Procurement <span>·</span> Workspace overview</footer>
    </section>
  );
}

import { useCallback, useEffect, useMemo, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const PAGE_SIZE = 5;
const blank = { name: '', email: '', phone: '', taxNumber: '', contactPerson: '', street: '', city: '', postalCode: '', country: 'DE' };
const steps = ['Company', 'Contact', 'Address'];

export default function SuppliersPage({ navigate, onCountChange, searchValue, onSearchChange, canCreate = false }) {
  const [items, setItems] = useState([]);
  const [sortField, setSortField] = useState('name');
  const [sortDirection, setSortDirection] = useState('asc');
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('ALL');
  const [location, setLocation] = useState('ALL');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [modal, setModal] = useState(false);
  const [step, setStep] = useState(0);
  const [form, setForm] = useState(blank);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);

  const load = useCallback(async () => {
    setLoading(true); setError('');
    try {
      const data = await suppliersApi.list({ search: query, status });
      setItems(Array.isArray(data) ? data : []);
      onCountChange(data.length);
      setPage(1);
    } catch (err) { setError(err.message || 'Unable to load suppliers.'); }
    finally { setLoading(false); }
  }, [query, status, onCountChange]);

  useEffect(() => { const timer = window.setTimeout(() => setQuery(searchValue.trim()), 300); return () => window.clearTimeout(timer); }, [searchValue]);
  useEffect(() => { load(); }, [load]);

  const locations = useMemo(() => [...new Map(items.filter((item) => item.city || item.country).map((item) => { const key = [item.city || '', item.country || ''].join('|'); return [key, { key, label: [item.city, item.country].filter(Boolean).join(', ') }]; })).values()].sort((a, b) => a.label.localeCompare(b.label)), [items]);
  const filteredItems = useMemo(() => location === 'ALL' ? items : items.filter((item) => [item.city || '', item.country || ''].join('|') === location), [items, location]);
  const pages = Math.max(1, Math.ceil(filteredItems.length / PAGE_SIZE));
  const sortedItems = useMemo(() => [...filteredItems].sort((a, b) => {
    const value = (item) => sortField === 'location' ? [item.city, item.postalCode, item.country].filter(Boolean).join(' ') : item[sortField] || '';
    const comparison = String(value(a)).localeCompare(String(value(b)), undefined, { numeric: true, sensitivity: 'base' });
    return sortDirection === 'asc' ? comparison : -comparison;
  }), [filteredItems, sortField, sortDirection]);
  const visible = useMemo(() => sortedItems.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE), [sortedItems, page]);
  const resetFilters = () => { onSearchChange(''); setStatus('ALL'); setLocation('ALL'); setPage(1); };
  const toggleSort = (field) => {
    if (sortField === field) setSortDirection((direction) => direction === 'asc' ? 'desc' : 'asc');
    else { setSortField(field); setSortDirection('asc'); }
  };
  const sortLabel = (field, label) => <button type="button" className="table-sort" onClick={() => toggleSort(field)}>{label}<span>{sortField === field ? (sortDirection === 'asc' ? '↑' : '↓') : '↕'}</span></button>;
  const exportCsv = () => {
    const columns = [['Supplier', 'name'], ['Tax / VAT number', 'taxNumber'], ['Contact person', 'contactPerson'], ['Email', 'email'], ['Phone', 'phone'], ['Street', 'street'], ['Postal code', 'postalCode'], ['City', 'city'], ['Country', 'country'], ['Status', 'status']];
    const escapeCsv = (value) => '"' + String(value ?? '').replace(/"/g, '""') + '"';
    const csv = [columns.map(([label]) => escapeCsv(label)).join(','), ...sortedItems.map((item) => columns.map(([, key]) => escapeCsv(item[key])).join(','))].join('\r\n');
    const url = URL.createObjectURL(new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url; link.download = 'suppliers.csv'; link.click();
    URL.revokeObjectURL(url);
  };
  const change = (event) => setForm((current) => ({ ...current, [event.target.name]: event.target.value }));
  const openCreate = () => { setForm(blank); setStep(0); setModal(true); setError(''); setNotice(''); };
  const closeCreate = () => { if (!saving) setModal(false); };
  const next = () => {
    if (step === 0 && (!form.name.trim() || form.name.trim().length < 2)) { setError('Enter a company name with at least 2 characters.'); return; }
    if (step === 1 && form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { setError('Enter a valid email address.'); return; }
    setError(''); setStep((value) => Math.min(2, value + 1));
  };
  const previous = () => { setError(''); setStep((value) => Math.max(0, value - 1)); };

  async function createSupplier(event) {
    event.preventDefault(); setSaving(true); setError('');
    try {
      await suppliersApi.create({ ...form, name: form.name.trim(), country: form.country.trim().toUpperCase() || null });
      setModal(false); setForm(blank); setStep(0); setNotice('Supplier submitted for approval. It will become active after a reviewer approves it.');
      await load();
    } catch (err) { setError(err.message || 'Unable to create supplier.'); }
    finally { setSaving(false); }
  }

  async function confirmDelete() {
    if (!deleting) return;
    setSaving(true); setError('');
    try {
      await suppliersApi.remove(deleting.id);
      setDeleting(null); setNotice('Supplier deleted successfully.'); await load();
    } catch (err) { setError(err.message || 'Unable to delete supplier.'); }
    finally { setSaving(false); }
  }

  return (
    <section className="page-content suppliers-page">
      <section className="supplier-table-card directory-card">
        <div className="directory-heading directory-actions">
          <div className="directory-action-group">
            {canCreate && <button className="primary-button" onClick={openCreate}>＋ Create supplier</button>}
            <button className="secondary-button" onClick={load} disabled={loading}>↻ Refresh</button>
            <button className="secondary-button" onClick={exportCsv} disabled={filteredItems.length === 0}>↓ Export CSV</button>
            <select className="directory-filter" value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} aria-label="Filter suppliers by status"><option value="ALL">All statuses</option><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="BLOCKED">Blocked</option></select>
            <select className="directory-filter" value={location} onChange={(event) => { setLocation(event.target.value); setPage(1); }} aria-label="Filter suppliers by location"><option value="ALL">All locations</option>{locations.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select>
            <button className="secondary-button reset-filters-button" onClick={resetFilters} disabled={!searchValue && status === 'ALL' && location === 'ALL'}>↺ Reset filters</button>
          </div>
        </div>
        {notice && <div className="alert alert-success" role="status">{notice}</div>}
        {error && !modal && <div className="alert alert-error" role="alert">{error}</div>}
        {loading ? <div className="table-state"><span className="loader"></span>Loading supplier records...</div> : filteredItems.length === 0 ? <div className="table-state empty-directory"><div className="empty-icon">▤</div><strong>No suppliers found</strong><span>Try changing your search or create your first supplier.</span>{canCreate && <button className="primary-button" onClick={openCreate}>Create supplier</button>}</div> : <>
          <div className="table-scroll"><table className="supplier-table directory-table"><thead><tr><th>{sortLabel('name', 'SUPPLIER')}</th><th>{sortLabel('contactPerson', 'CONTACT INFORMATION')}</th><th>{sortLabel('location', 'LOCATION')}</th><th>{sortLabel('status', 'STATUS')}</th><th className="actions-heading">ACTIONS</th></tr></thead>
            <tbody>{visible.map((supplier) => <tr key={supplier.id}>
              <td><button className="supplier-name-link" onClick={() => navigate(`/suppliers/${encodeURIComponent(supplier.id)}`)}>{supplier.name}</button><small>SUP-{String(supplier.id).padStart(4, '0')} · {supplier.taxNumber || 'No tax number'}</small></td>
              <td><span className="cell-primary">{supplier.contactPerson || '—'}</span><small>{supplier.email || supplier.phone || 'No contact details'}</small></td>
              <td><span className="cell-primary">{supplier.city || '—'}</span><small>{[supplier.postalCode, supplier.country].filter(Boolean).join(' ')}</small></td>
              <td><span className={`supplier-status status-${String(supplier.status || 'inactive').toLowerCase()}`}><i></i>{supplier.status || 'UNKNOWN'}</span></td>
              <td><div className="table-actions"><button title="View supplier" aria-label="View supplier" onClick={() => navigate(`/suppliers/${encodeURIComponent(supplier.id)}`)}>↗</button><button title="Edit supplier" aria-label="Edit supplier" onClick={() => navigate(`/suppliers/${encodeURIComponent(supplier.id)}/edit`)}>✎</button><button className="delete-action" title="Delete supplier" aria-label="Delete supplier" onClick={() => { setError(''); setDeleting(supplier); }}>⌫</button></div></td>
            </tr>)}</tbody></table></div>
          <div className="pagination directory-pagination"><span>Showing <strong>{(page - 1) * PAGE_SIZE + 1}–{Math.min(page * PAGE_SIZE, filteredItems.length)}</strong> of <strong>{filteredItems.length}</strong> suppliers</span><div><button className="secondary-button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button><span className="page-number">{page} / {pages}</span><button className="secondary-button" disabled={page >= pages} onClick={() => setPage((value) => value + 1)}>Next</button></div></div>
        </>}
      </section>

      {modal && <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) closeCreate(); }}><section className="modal-card create-modal" role="dialog" aria-modal="true" aria-labelledby="create-title">
        <header className="modal-header"><div><p className="eyebrow">NEW BUSINESS PARTNER</p><h2 id="create-title">Create supplier</h2><p className="section-description">Add a new supplier to your procurement directory.</p></div><button className="modal-close" onClick={closeCreate} aria-label="Close modal">×</button></header>
        <div className="stepper">{steps.map((label, index) => <div className={index <= step ? 'step active' : 'step'} key={label}><span>{index < step ? '✓' : index + 1}</span><small>{label}</small></div>)}</div>
        {error && <div className="alert alert-error" role="alert">{error}</div>}
        <form onSubmit={step === 2 ? createSupplier : (event) => { event.preventDefault(); next(); }}>
          {step === 0 && <div className="modal-form-grid"><div className="form-section-title">Company information</div><label className="full-field">Company name <b>*</b><input name="name" required autoFocus maxLength="150" value={form.name} onChange={change} placeholder="e.g. Acme GmbH" /></label><label>Tax / VAT number<input name="taxNumber" maxLength="50" value={form.taxNumber} onChange={change} placeholder="DE123456789" /></label></div>}
          {step === 1 && <div className="modal-form-grid"><div className="form-section-title">Primary contact</div><label>Contact person<input name="contactPerson" maxLength="150" value={form.contactPerson} onChange={change} placeholder="Full name" /></label><label>Email address<input name="email" type="email" maxLength="150" value={form.email} onChange={change} placeholder="contact@company.com" /></label><label className="full-field">Phone number<input name="phone" maxLength="30" value={form.phone} onChange={change} placeholder="+49 30 123456" /></label></div>}
          {step === 2 && <div className="modal-form-grid"><div className="form-section-title">Business address</div><label className="full-field">Street and number<input name="street" maxLength="180" value={form.street} onChange={change} placeholder="Hafenstraße 12" /></label><label>Postal code<input name="postalCode" maxLength="20" value={form.postalCode} onChange={change} placeholder="10115" /></label><label>City<input name="city" maxLength="100" value={form.city} onChange={change} placeholder="Berlin" /></label><label>Country code<input name="country" maxLength="2" value={form.country} onChange={change} placeholder="DE" /></label><div className="form-hint full-field">Legal details, additional contacts, addresses and bank accounts can be managed from the supplier detail page after creation.</div></div>}
          <footer className="modal-footer"><button type="button" className="secondary-button" onClick={step === 0 ? closeCreate : previous}>{step === 0 ? 'Cancel' : '← Back'}</button><button type="submit" className="primary-button" disabled={saving}>{saving ? 'Creating...' : step === 2 ? 'Create supplier' : 'Continue →'}</button></footer>
        </form>
      </section></div>}

      {deleting && <div className="modal-backdrop" role="presentation"><section className="modal-card confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="delete-title"><div className="confirm-icon">!</div><h2 id="delete-title">Delete supplier?</h2><p>You are about to permanently delete <strong>{deleting.name}</strong>. This action cannot be undone.</p>{error && <div className="alert alert-error">{error}</div>}<div className="modal-footer"><button className="secondary-button" disabled={saving} onClick={() => setDeleting(null)}>Cancel</button><button className="danger-button" disabled={saving} onClick={confirmDelete}>{saving ? 'Deleting...' : 'Delete supplier'}</button></div></section></div>}
    </section>
  );
}

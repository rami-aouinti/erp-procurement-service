import { useCallback, useEffect, useMemo, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const emptyForm = {
  name: '', email: '', phone: '', taxNumber: '', status: 'ACTIVE',
  contactPerson: '', street: '', city: '', postalCode: '', country: 'DE',
};
const PAGE_SIZE = 8;

export default function SuppliersSection({ onCountChange }) {
  const [suppliers, setSuppliers] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('ALL');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadSuppliers = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await suppliersApi.list({ search, status: statusFilter });
      setSuppliers(data);
      onCountChange(data.length);
      setPage(1);
    } catch (err) {
      setError(err.message || 'Unable to load suppliers.');
    } finally {
      setLoading(false);
    }
  }, [onCountChange, search, statusFilter]);

  useEffect(() => {
    const timeout = window.setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => window.clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => { loadSuppliers(); }, [loadSuppliers]);

  const pageCount = Math.max(1, Math.ceil(suppliers.length / PAGE_SIZE));
  const visibleSuppliers = useMemo(
    () => suppliers.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE),
    [suppliers, page],
  );

  function startEdit(supplier) {
    setEditingId(supplier.id);
    setForm(Object.fromEntries(Object.keys(emptyForm).map((key) => [key, supplier[key] ?? (key === 'status' ? 'ACTIVE' : '')])));
    setNotice('');
    setError('');
  }

  function resetForm() { setEditingId(null); setForm(emptyForm); }

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    const payload = { ...form, country: form.country.trim().toUpperCase() || null };
    try {
      if (editingId !== null) {
        await suppliersApi.update(editingId, payload);
        setNotice('Supplier updated successfully.');
      } else {
        await suppliersApi.create(payload);
        setNotice('Supplier created successfully.');
      }
      resetForm();
      await loadSuppliers();
    } catch (err) {
      setError(err.message || 'Unable to save supplier.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(supplier) {
    if (!window.confirm(`Delete supplier "${supplier.name}"?`)) return;
    setError('');
    setNotice('');
    try {
      await suppliersApi.remove(supplier.id);
      setNotice('Supplier deleted successfully.');
      await loadSuppliers();
      if (editingId === supplier.id) resetForm();
    } catch (err) {
      setError(err.message || 'Unable to delete supplier.');
    }
  }

  function updateField(event) {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }));
  }

  return (
    <section className="suppliers-section">
      <div className="section-heading">
        <div>
          <p className="eyebrow">MASTER DATA</p>
          <h2>Suppliers</h2>
          <p className="section-description">Manage supplier contacts, addresses and lifecycle status.</p>
        </div>
        <button className="secondary-button" type="button" onClick={loadSuppliers}>Refresh</button>
      </div>

      {error && <div className="alert alert-error" role="alert">{error}</div>}
      {notice && <div className="alert alert-success" role="status">{notice}</div>}

      <div className="supplier-layout">
        <form className="supplier-form" onSubmit={handleSubmit}>
          <div className="form-heading">
            <h3>{editingId !== null ? 'Edit supplier' : 'New supplier'}</h3>
            {editingId !== null && <button className="text-button" type="button" onClick={resetForm}>Cancel</button>}
          </div>
          <label>Company name <span>*</span><input name="name" value={form.name} onChange={updateField} maxLength="150" minLength="2" required placeholder="Acme GmbH" /></label>
          <label>Email<input name="email" type="email" value={form.email} onChange={updateField} maxLength="150" placeholder="contact@company.de" /></label>
          <label>Phone<input name="phone" value={form.phone} onChange={updateField} maxLength="30" pattern="[+0-9 ().-]{7,30}" title="Use at least 7 digits and common phone characters." placeholder="+49 30 123456" /></label>
          <label>Tax number<input name="taxNumber" value={form.taxNumber} onChange={updateField} maxLength="50" placeholder="DE123456789" /></label>
          <label>Primary contact<input name="contactPerson" value={form.contactPerson} onChange={updateField} maxLength="150" placeholder="Jane Smith" /></label>
          <label>Status
            <select name="status" value={form.status} onChange={updateField}>
              <option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="BLOCKED">Blocked</option>
            </select>
          </label>
          <div className="form-subheading">Business address</div>
          <label>Street<input name="street" value={form.street} onChange={updateField} maxLength="180" placeholder="Hafenstraße 12" /></label>
          <div className="form-row">
            <label>Postal code<input name="postalCode" value={form.postalCode} onChange={updateField} maxLength="20" pattern="[A-Za-z0-9][A-Za-z0-9 -]{1,19}" placeholder="28195" /></label>
            <label>City<input name="city" value={form.city} onChange={updateField} maxLength="100" placeholder="Bremen" /></label>
          </div>
          <label>Country code<input name="country" value={form.country} onChange={updateField} maxLength="2" pattern="[A-Za-z]{2}" title="Enter a two-letter ISO country code, for example DE." placeholder="DE" /></label>
          <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : editingId !== null ? 'Save changes' : 'Create supplier'}</button>
        </form>

        <div className="supplier-table-card">
          <div className="table-heading">
            <div><h3>All suppliers</h3><span>{suppliers.length} matching suppliers</span></div>
          </div>
          <div className="supplier-filters">
            <input aria-label="Search suppliers" value={searchInput} onChange={(event) => setSearchInput(event.target.value)} placeholder="Search name, email, tax ID, contact or city..." />
            <select aria-label="Filter by status" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
              <option value="ALL">All statuses</option><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="BLOCKED">Blocked</option>
            </select>
          </div>
          {loading ? <p className="empty-state">Loading suppliers...</p> : suppliers.length === 0 ? (
            <p className="empty-state">No suppliers match these filters.</p>
          ) : (
            <>
              <div className="table-scroll">
                <table className="supplier-table">
                  <thead><tr><th>Supplier</th><th>Contact</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead>
                  <tbody>
                    {visibleSuppliers.map((supplier) => (
                      <tr key={supplier.id}>
                        <td><strong>{supplier.name}</strong><small>#{supplier.id} · {supplier.taxNumber || 'No tax number'}</small></td>
                        <td><span>{supplier.contactPerson || supplier.email || '—'}</span><small>{supplier.email || supplier.phone || 'No contact details'}</small></td>
                        <td>{[supplier.postalCode, supplier.city, supplier.country].filter(Boolean).join(' ') || '—'}</td>
                        <td><span className={`supplier-status status-${supplier.status.toLowerCase()}`}>{supplier.status}</span></td>
                        <td><div className="row-actions"><button className="text-button" type="button" onClick={() => startEdit(supplier)}>Edit</button><button className="text-button danger-text" type="button" onClick={() => handleDelete(supplier)}>Delete</button></div></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="pagination">
                <span>Page {page} of {pageCount}</span>
                <div><button className="secondary-button" type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button><button className="secondary-button" type="button" disabled={page >= pageCount} onClick={() => setPage((value) => value + 1)}>Next</button></div>
              </div>
            </>
          )}
        </div>
      </div>
    </section>
  );
}

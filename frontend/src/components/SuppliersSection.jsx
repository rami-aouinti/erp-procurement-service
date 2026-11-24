import { useCallback, useEffect, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const emptyForm = { name: '', email: '', phone: '', taxNumber: '' };

export default function SuppliersSection({ onCountChange }) {
  const [suppliers, setSuppliers] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadSuppliers = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const data = await suppliersApi.list();
      setSuppliers(data);
      onCountChange(data.length);
    } catch (err) {
      setError(err.message || 'Unable to load suppliers.');
    } finally {
      setLoading(false);
    }
  }, [onCountChange]);

  useEffect(() => {
    loadSuppliers();
  }, [loadSuppliers]);

  function startEdit(supplier) {
    setEditingId(supplier.id);
    setForm({
      name: supplier.name ?? '',
      email: supplier.email ?? '',
      phone: supplier.phone ?? '',
      taxNumber: supplier.taxNumber ?? '',
    });
    setNotice('');
    setError('');
  }

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    try {
      if (editingId !== null) {
        await suppliersApi.update(editingId, form);
        setNotice('Supplier updated successfully.');
      } else {
        await suppliersApi.create(form);
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
          <p className="section-description">Manage the companies you purchase from.</p>
        </div>
        <button className="secondary-button" type="button" onClick={loadSuppliers}>
          Refresh
        </button>
      </div>

      {error && <div className="alert alert-error" role="alert">{error}</div>}
      {notice && <div className="alert alert-success" role="status">{notice}</div>}

      <div className="supplier-layout">
        <form className="supplier-form" onSubmit={handleSubmit}>
          <div className="form-heading">
            <h3>{editingId !== null ? 'Edit supplier' : 'New supplier'}</h3>
            {editingId !== null && <button className="text-button" type="button" onClick={resetForm}>Cancel edit</button>}
          </div>

          <label>
            Company name <span>*</span>
            <input name="name" value={form.name} onChange={updateField} maxLength="150" required placeholder="Acme GmbH" />
          </label>
          <label>
            Email
            <input name="email" type="email" value={form.email} onChange={updateField} maxLength="150" placeholder="contact@company.de" />
          </label>
          <label>
            Phone
            <input name="phone" value={form.phone} onChange={updateField} maxLength="30" placeholder="+49 30 123456" />
          </label>
          <label>
            Tax number
            <input name="taxNumber" value={form.taxNumber} onChange={updateField} maxLength="50" placeholder="DE123456789" />
          </label>
          <button className="primary-button" type="submit" disabled={saving}>
            {saving ? 'Saving...' : editingId !== null ? 'Save changes' : 'Create supplier'}
          </button>
        </form>

        <div className="supplier-table-card">
          <div className="table-heading">
            <h3>All suppliers</h3>
            <span>{suppliers.length} total</span>
          </div>
          {loading ? (
            <p className="empty-state">Loading suppliers...</p>
          ) : suppliers.length === 0 ? (
            <p className="empty-state">No suppliers yet. Create your first supplier.</p>
          ) : (
            <div className="table-scroll">
              <table className="supplier-table">
                <thead>
                  <tr><th>Supplier</th><th>Contact</th><th>Tax number</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  {suppliers.map((supplier) => (
                    <tr key={supplier.id}>
                      <td>
                        <strong>{supplier.name}</strong>
                        <small>#{supplier.id}</small>
                      </td>
                      <td>
                        <span>{supplier.email || '—'}</span>
                        <small>{supplier.phone || 'No phone'}</small>
                      </td>
                      <td>{supplier.taxNumber || '—'}</td>
                      <td>
                        <div className="row-actions">
                          <button className="text-button" type="button" onClick={() => startEdit(supplier)}>Edit</button>
                          <button className="text-button danger-text" type="button" onClick={() => handleDelete(supplier)}>Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

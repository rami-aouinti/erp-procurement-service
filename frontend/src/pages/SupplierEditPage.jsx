import { useEffect, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const fields = [
  ['name', 'Company name', true], ['taxNumber', 'Tax / VAT number'], ['contactPerson', 'Primary contact'],
  ['email', 'Email address'], ['phone', 'Phone number'], ['status', 'Status'],
  ['street', 'Street and number'], ['postalCode', 'Postal code'], ['city', 'City'], ['country', 'Country code'],
];
export default function SupplierEditPage({ supplierId, navigate, onNotify = () => {} }) {
  const [form, setForm] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  useEffect(() => {
    let active = true;
    suppliersApi.list().then((items) => {
      const item = items.find((supplier) => String(supplier.id) === String(supplierId));
      if (active) item ? setForm(item) : setError('Supplier not found.');
    }).catch((err) => { if (active) setError(err.message); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [supplierId]);
  const change = (event) => setForm((current) => ({ ...current, [event.target.name]: event.target.value }));
  async function submit(event) {
    event.preventDefault(); setSaving(true); setError(''); setNotice('');
    try {
      await suppliersApi.update(supplierId, { ...form, country: (form.country || '').trim().toUpperCase() || null });
      setNotice('Supplier changes saved successfully.');
      onNotify('success', 'Supplier updated', 'Your changes have been saved.');
      window.setTimeout(() => navigate(`/suppliers/${encodeURIComponent(supplierId)}`), 650);
    } catch (err) { setError(err.message || 'Unable to update supplier.'); onNotify('error', 'Update failed', err.message || 'Unable to update supplier.'); }
    finally { setSaving(false); }
  }
  return <section className="page-content edit-page compact-page">
    <div className="edit-form-card">
      {loading ? <div className="table-state"><span className="loader"></span>Loading supplier...</div> : form && <form onSubmit={submit}>
      <div className="edit-card-heading"><div><h2>{form.name}</h2></div><button type="button" className="secondary-button" onClick={() => navigate(`/suppliers/${encodeURIComponent(supplierId)}`)}>← Back</button></div>
      {error && <div className="alert alert-error" role="alert">{error}</div>}{notice && <div className="alert alert-success" role="status">{notice}</div>}
      <div className="edit-form-grid">{fields.map(([name, label, required]) => <label key={name}>{label}{name === 'status' ? <select name={name} value={form[name] || 'ACTIVE'} onChange={change}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="BLOCKED">Blocked</option></select> : <input name={name} required={Boolean(required)} type={name === 'email' ? 'email' : 'text'} maxLength={name === 'name' ? 150 : 180} value={form[name] || ''} onChange={change} />}</label>)}</div>
      <div className="edit-form-footer"><button type="submit" className="primary-button" disabled={saving}>{saving ? 'Saving changes...' : 'Save changes'}</button></div>
      </form>}
      {error && !form && <div className="alert alert-error">{error}</div>}
    </div>
  </section>;
}

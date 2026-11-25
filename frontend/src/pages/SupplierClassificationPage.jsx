import { useCallback, useEffect, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const TYPES = [
  { value: 'categories', label: 'Categories' },
  { value: 'commodities', label: 'Commodity codes' },
  { value: 'tags', label: 'Tags' },
  { value: 'business-units', label: 'Business units' },
];

export default function SupplierClassificationPage() {
  const [type, setType] = useState('categories');
  const [items, setItems] = useState([]);
  const [categories, setCategories] = useState([]);
  const [form, setForm] = useState({ code: '', name: '', description: '', parentId: '', color: '' });
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    setLoading(true); setError('');
    try {
      const data = await suppliersApi.listClassifications(type);
      setItems(data);
      if (type === 'categories') setCategories(data);
    } catch (e) { setError(e.message || 'Unable to load taxonomy.'); }
    finally { setLoading(false); }
  }, [type]);

  useEffect(() => { load(); }, [load]);

  async function create(event) {
    event.preventDefault(); setSaving(true); setError(''); setNotice('');
    try {
      await suppliersApi.createClassification(type, {
        code: form.code.trim().toUpperCase(),
        name: form.name.trim(),
        description: form.description.trim() || null,
        ...(type === 'categories' && form.parentId ? { parentId: Number(form.parentId) } : {}),
        ...(type === 'tags' && form.color ? { color: form.color } : {}),
      });
      setForm({ code: '', name: '', description: '', parentId: '', color: '' });
      setNotice('Classification entry created.');
      await load();
    } catch (e) { setError(e.message || 'Unable to create entry.'); }
    finally { setSaving(false); }
  }

  async function toggle(item) {
    setError(''); setNotice('');
    try {
      await suppliersApi.setClassificationActive(type, item.id, !item.active);
      setNotice(item.active ? 'Entry deactivated. Existing supplier assignments are preserved.' : 'Entry activated.');
      await load();
    } catch (e) { setError(e.message || 'Unable to update entry.'); }
  }

  return <section className="page-content">
    <div className="page-heading"><div><p className="eyebrow">MASTER DATA</p><h1>Supplier classification</h1><p className="subtitle">Manage the controlled taxonomy used to segment and report on suppliers.</p></div></div>
    {notice && <div className="alert alert-success" role="status">{notice}</div>}
    {error && <div className="alert alert-error" role="alert">{error}</div>}
    <section className="supplier-table-card directory-card">
      <div className="directory-heading directory-actions">
        <div className="directory-action-group">
          <select className="directory-filter" value={type} onChange={(e) => setType(e.target.value)} aria-label="Classification type">{TYPES.map((x) => <option key={x.value} value={x.value}>{x.label}</option>)}</select>
          <button className="secondary-button" onClick={load} disabled={loading}>↻ Refresh</button>
        </div>
      </div>
      <form className="classification-create-form" onSubmit={create}>
        <label>Code<input required maxLength="50" value={form.code} onChange={(e) => setForm((v) => ({ ...v, code: e.target.value }))} placeholder="e.g. IT-HARDWARE" /></label>
        <label>Name<input required maxLength="150" value={form.name} onChange={(e) => setForm((v) => ({ ...v, name: e.target.value }))} placeholder="Display name" /></label>
        {type === 'categories' && <label>Parent category<select value={form.parentId} onChange={(e) => setForm((v) => ({ ...v, parentId: e.target.value }))}><option value="">Top-level category</option>{categories.filter((x) => x.active).map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select></label>}
        {type === 'tags' && <label>Color<input type="color" value={form.color || '#647cff'} onChange={(e) => setForm((v) => ({ ...v, color: e.target.value }))} /></label>}
        <label className="classification-description">Description<input maxLength="500" value={form.description} onChange={(e) => setForm((v) => ({ ...v, description: e.target.value }))} placeholder="Optional description" /></label>
        <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving…' : '＋ Add entry'}</button>
      </form>
      {loading ? <div className="table-state">Loading taxonomy…</div> : <div className="table-scroll"><table className="supplier-table directory-table">
        <thead><tr><th>CODE</th><th>NAME</th><th>DETAILS</th><th>STATUS</th><th>ACTION</th></tr></thead>
        <tbody>{items.map((item) => <tr key={item.id}><td><strong>{item.code}</strong></td><td>{item.name}</td><td>{item.parentId ? 'Parent ID: ' + item.parentId : item.description || item.color || '—'}</td><td><span className={'supplier-status ' + (item.active ? 'status-active' : 'status-inactive')}><i></i>{item.active ? 'ACTIVE' : 'INACTIVE'}</span></td><td><button className="secondary-button" onClick={() => toggle(item)}>{item.active ? 'Deactivate' : 'Activate'}</button></td></tr>)}</tbody>
      </table>{items.length === 0 && <div className="table-state">No entries yet. Create the first one above.</div>}</div>}
    </section>
  </section>;
}

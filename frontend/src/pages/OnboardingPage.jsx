import { useCallback, useEffect, useState } from 'react';
import { Check, History, RefreshCw, X } from 'lucide-react';
import { onboardingApi } from '../api/onboarding.js';

const field = (item, name) => item?.[name] ?? item?.[name.toUpperCase()] ?? '';

export default function OnboardingPage() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [decision, setDecision] = useState(null);
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);
  const [historyId, setHistoryId] = useState(null);
  const [history, setHistory] = useState([]);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await onboardingApi.queue();
      setItems(Array.isArray(result.items) ? result.items : []);
    } catch (err) {
      setError(err.message || 'Unable to load the approval queue.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function submitDecision(event) {
    event.preventDefault();
    if (!decision) return;
    if (decision.action !== 'approve' && !reason.trim()) {
      setError('A reason is required for this decision.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      if (decision.action === 'approve') await onboardingApi.approve(decision.id, reason.trim() || null);
      if (decision.action === 'reject') await onboardingApi.reject(decision.id, reason.trim());
      if (decision.action === 'changes') await onboardingApi.requestChanges(decision.id, reason.trim());
      setNotice('Decision saved successfully.');
      setDecision(null);
      setReason('');
      await load();
    } catch (err) {
      setError(err.message || 'Unable to save the decision.');
    } finally {
      setSaving(false);
    }
  }

  async function toggleHistory(id) {
    if (historyId === id) {
      setHistoryId(null);
      return;
    }
    setError('');
    try {
      const result = await onboardingApi.history(id);
      setHistory(Array.isArray(result.items) ? result.items : []);
      setHistoryId(id);
    } catch (err) {
      setError(err.message || 'Unable to load onboarding history.');
    }
  }

  return (
    <section className="page-content suppliers-page">
      <div className="page-heading">
        <div><p className="eyebrow">SUPPLIER GOVERNANCE</p><h1>Onboarding approvals</h1><p className="subtitle">Review supplier submissions before they become operational.</p></div>
        <button className="secondary-button" type="button" onClick={load} disabled={loading}><RefreshCw size={15} /> Refresh</button>
      </div>
      {notice && <div className="alert alert-success" role="status">{notice}</div>}
      {error && <div className="alert alert-error" role="alert">{error}</div>}
      <section className="supplier-table-card directory-card">
        <div className="directory-heading"><div><h2>Pending approval</h2><p className="section-description">{items.length} supplier{items.length === 1 ? '' : 's'} waiting for review</p></div></div>
        {loading ? <div className="table-state"><span className="loader"></span>Loading approval queue...</div> : items.length === 0 ? <div className="table-state empty-directory"><strong>No pending submissions</strong><span>New supplier submissions will appear here.</span></div> : (
          <div className="table-scroll"><table className="supplier-table directory-table">
            <thead><tr><th>SUPPLIER</th><th>EMAIL</th><th>SUBMITTED</th><th>VERSION</th><th>ACTIONS</th></tr></thead>
            <tbody>{items.map((item) => {
              const id = field(item, 'supplier_id');
              return <tr key={id}>
                <td><strong>{field(item, 'name')}</strong><small>SUP-{String(id).padStart(4, '0')}</small></td>
                <td>{field(item, 'email') || '—'}</td>
                <td>{field(item, 'submitted_at') || '—'}</td>
                <td>{field(item, 'version') || '—'}</td>
                <td><div className="table-actions">
                  <button type="button" title="Approve" aria-label="Approve supplier" onClick={() => { setDecision({ id, name: field(item, 'name'), action: 'approve' }); setReason(''); }}><Check size={16} /></button>
                  <button type="button" title="Request changes" aria-label="Request changes" onClick={() => { setDecision({ id, name: field(item, 'name'), action: 'changes' }); setReason(''); }}>↩</button>
                  <button type="button" className="delete-action" title="Reject" aria-label="Reject supplier" onClick={() => { setDecision({ id, name: field(item, 'name'), action: 'reject' }); setReason(''); }}><X size={16} /></button>
                  <button type="button" title="History" aria-label="View history" onClick={() => toggleHistory(id)}><History size={16} /></button>
                </div></td>
              </tr>;
            })}</tbody>
          </table></div>
        )}
        {historyId !== null && <section className="onboarding-history"><h3>Decision history · SUP-{String(historyId).padStart(4, '0')}</h3>{history.length === 0 ? <p>No history available.</p> : <ol>{history.map((entry, index) => <li key={index}><strong>{field(entry, 'to_status')}</strong><span>{field(entry, 'actor_name')} · {field(entry, 'occurred_at')}</span>{field(entry, 'reason') && <p>{field(entry, 'reason')}</p>}</li>)}</ol>}</section>}
      </section>

      {decision && <div className="modal-backdrop" role="presentation"><section className="modal-card confirm-modal" role="dialog" aria-modal="true" aria-labelledby="decision-title">
        <header className="modal-header"><div><p className="eyebrow">SUPPLIER REVIEW</p><h2 id="decision-title">{decision.action === 'approve' ? 'Approve supplier' : decision.action === 'reject' ? 'Reject supplier' : 'Request changes'}</h2><p className="section-description">{decision.name}</p></div><button className="modal-close" type="button" onClick={() => setDecision(null)} aria-label="Close">×</button></header>
        <form onSubmit={submitDecision}>
          <label className="full-field">Reason {decision.action !== 'approve' && <b>*</b>}<textarea rows="4" maxLength="1000" value={reason} onChange={(event) => setReason(event.target.value)} placeholder={decision.action === 'approve' ? 'Optional approval note' : 'Explain what is missing or why this submission is rejected'} /></label>
          <div className="modal-footer"><button type="button" className="secondary-button" disabled={saving} onClick={() => setDecision(null)}>Cancel</button><button type="submit" className={decision.action === 'reject' ? 'danger-button' : 'primary-button'} disabled={saving}>{saving ? 'Saving...' : 'Confirm decision'}</button></div>
        </form>
      </section></div>}
    </section>
  );
}

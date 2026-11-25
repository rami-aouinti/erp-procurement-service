import { useState } from 'react';
import { Check, Palette, SlidersHorizontal } from 'lucide-react';

const palettes = [
  { id: 'indigo', name: 'Indigo', colors: ['#647cff', '#4f64e8', '#edf0ff'] },
  { id: 'ocean', name: 'Ocean', colors: ['#0891b2', '#0e7490', '#e0f7fb'] },
  { id: 'emerald', name: 'Emerald', colors: ['#059669', '#047857', '#e2f8ef'] },
  { id: 'violet', name: 'Violet', colors: ['#8b5cf6', '#6d28d9', '#f1eaff'] },
  { id: 'rose', name: 'Rose', colors: ['#e11d48', '#be123c', '#fff0f3'] },
  { id: 'amber', name: 'Amber', colors: ['#d97706', '#b45309', '#fff5df'] },
];

export default function SettingsPage({ settings, onChange }) {
  const [tab, setTab] = useState('general');
  const [saved, setSaved] = useState(false);
  const update = (field, value) => { onChange((current) => ({ ...current, [field]: value })); setSaved(false); };
  const save = (event) => { event.preventDefault(); setSaved(true); };
  return (
    <section className="page-content settings-page">
      <div className="settings-card">
        <div className="settings-tabs" role="tablist" aria-label="Settings sections">
          <button type="button" role="tab" aria-selected={tab === 'general'} className={tab === 'general' ? 'settings-tab active' : 'settings-tab'} onClick={() => setTab('general')}><SlidersHorizontal size={16} />General</button>
          <button type="button" role="tab" aria-selected={tab === 'theme'} className={tab === 'theme' ? 'settings-tab active' : 'settings-tab'} onClick={() => setTab('theme')}><Palette size={16} />Theme</button>
        </div>
        {tab === 'general' ? (
          <form className="settings-panel" onSubmit={save}>
            <div className="settings-section-heading"><h2>General information</h2><p>Configure the application identity and the shop details used by your workspace.</p></div>
            <div className="settings-form-grid">
              <label>Application name<input value={settings.appName} onChange={(e) => update('appName', e.target.value)} placeholder="Procurement Management Suite" /></label>
              <label>Shop name<input value={settings.shopName} onChange={(e) => update('shopName', e.target.value)} placeholder="Your company or shop name" /></label>
              <label>Shop email<input type="email" value={settings.shopEmail} onChange={(e) => update('shopEmail', e.target.value)} placeholder="contact@example.com" /></label>
              <label>Default currency<select value={settings.currency} onChange={(e) => update('currency', e.target.value)}><option value="EUR">EUR — Euro</option><option value="USD">USD — US Dollar</option><option value="GBP">GBP — Pound Sterling</option><option value="CHF">CHF — Swiss Franc</option></select></label>
              <label className="settings-full-field">Shop address<input value={settings.shopAddress} onChange={(e) => update('shopAddress', e.target.value)} placeholder="Street, postal code, city, country" /></label>
            </div>
            <div className="settings-footer">{saved && <span className="settings-saved"><Check size={15} />Settings saved</span>}<button type="submit" className="primary-button">Save changes</button></div>
          </form>
        ) : (
          <div className="settings-panel">
            <div className="settings-section-heading"><h2>Color palette</h2><p>Choose an accent palette for buttons, active navigation and interface highlights.</p></div>
            <div className="palette-grid">
              {palettes.map((palette) => (
                <button type="button" key={palette.id} className={settings.palette === palette.id ? 'palette-option selected' : 'palette-option'} onClick={() => update('palette', palette.id)} aria-pressed={settings.palette === palette.id}>
                  <span className="palette-swatches">{palette.colors.map((color) => <i key={color} style={{ background: color }} />)}</span>
                  <span className="palette-name">{palette.name}</span>
                  {settings.palette === palette.id && <Check className="palette-check" size={16} />}
                </button>
              ))}
            </div>
            <div className="settings-footer"><span className="settings-hint">Your selection is applied immediately and saved on this browser.</span></div>
          </div>
        )}
      </div>
    </section>
  );
}

import { useState } from 'react';
import { Check, Palette, Save, Settings2, SlidersHorizontal, UserRound } from 'lucide-react';

const palettes = [
  { id: 'indigo', name: 'Indigo', colors: ['#647cff', '#4f64e8', '#edf0ff'] },
  { id: 'ocean', name: 'Ocean', colors: ['#0891b2', '#0e7490', '#e0f7fb'] },
  { id: 'emerald', name: 'Emerald', colors: ['#059669', '#047857', '#e2f8ef'] },
  { id: 'violet', name: 'Violet', colors: ['#8b5cf6', '#6d28d9', '#f1eaff'] },
  { id: 'rose', name: 'Rose', colors: ['#e11d48', '#be123c', '#fff0f3'] },
  { id: 'amber', name: 'Amber', colors: ['#d97706', '#b45309', '#fff5df'] },
];

const sections = [
  { id: 'general', label: 'General', icon: SlidersHorizontal },
  { id: 'company', label: 'Company', icon: Settings2 },
  { id: 'regional', label: 'Regional', icon: Settings2 },
  { id: 'forms', label: 'Forms', icon: Settings2 },
  { id: 'preferences', label: 'My preferences', icon: UserRound },
];

function readField(settings, key) {
  return key.split('.').reduce((value, part) => value?.[part], settings);
}

function writeField(settings, key, value) {
  const parts = key.split('.');
  if (parts.length === 1) return { ...settings, [key]: value };
  return {
    ...settings,
    [parts[0]]: { ...settings[parts[0]], [parts[1]]: value },
  };
}

function SettingField({ field, value, onChange }) {
  if (field.type === 'toggle') {
    return (
      <label className="configuration-toggle">
        <span><strong>{field.label}</strong><small>{field.description || 'Enable or disable this option.'}</small></span>
        <input type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />
        <i aria-hidden="true" />
      </label>
    );
  }

  return (
    <label className="configuration-field">
      <span>{field.label}{field.required && <em> *</em>}</span>
      {field.type === 'textarea' ? (
        <textarea value={value ?? ''} maxLength={field.maxLength} onChange={(event) => onChange(event.target.value)} rows={3} />
      ) : field.type === 'select' ? (
        <select value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
          {(field.options || []).map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
      ) : (
        <input
          type={field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : 'text'}
          value={value ?? ''}
          min={field.min}
          max={field.max}
          maxLength={field.maxLength}
          required={Boolean(field.required)}
          onChange={(event) => onChange(field.type === 'number' ? Number(event.target.value) : event.target.value)}
        />
      )}
    </label>
  );
}

export default function ConfigurationPage({
  settings,
  onSettingsChange,
  schema,
  preferences,
  onPreferencesChange,
  canManage,
  onSaveSite,
  saving,
  error,
  saved,
}) {
  const [activeSection, setActiveSection] = useState(canManage ? 'general' : 'preferences');
  const visibleSections = canManage ? sections : sections.filter((section) => section.id === 'preferences');
  const fields = schema.filter((field) => field.group === activeSection);

  const updateSetting = (key, value) => onSettingsChange((current) => writeField(current, key, value));
  const updatePreference = (key, value) => onPreferencesChange({ ...preferences, [key]: value });

  return (
    <section className="page-content configuration-page">
      <div className="configuration-card">
        <aside className="configuration-nav" aria-label="Configuration sections">
          <div className="configuration-nav-heading">CONFIGURATION</div>
          {visibleSections.map((section) => {
            const Icon = section.icon;
            return (
              <button
                type="button"
                key={section.id}
                className={activeSection === section.id ? 'configuration-nav-item active' : 'configuration-nav-item'}
                onClick={() => setActiveSection(section.id)}
              >
                <Icon size={16} />{section.label}
              </button>
            );
          })}
        </aside>

        <div className="configuration-content">
          {activeSection === 'preferences' ? (
            <div className="configuration-panel">
              <div className="configuration-heading">
                <h2>Personal preferences</h2>
                <p>These preferences belong to your account and do not change the appearance for other users.</p>
              </div>
              <div className="configuration-subheading"><Palette size={17} /><div><strong>Color palette</strong><small>Choose the accent color used across the application.</small></div></div>
              <div className="palette-grid">
                {palettes.map((palette) => (
                  <button
                    type="button"
                    key={palette.id}
                    className={preferences.palette === palette.id ? 'palette-option selected' : 'palette-option'}
                    onClick={() => updatePreference('palette', palette.id)}
                    aria-pressed={preferences.palette === palette.id}
                  >
                    <span className="palette-swatches">{palette.colors.map((color) => <i key={color} style={{ background: color }} />)}</span>
                    <span className="palette-name">{palette.name}</span>
                    {preferences.palette === palette.id && <Check className="palette-check" size={16} />}
                  </button>
                ))}
              </div>
              <div className="configuration-divider" />
              <div className="configuration-subheading"><SlidersHorizontal size={17} /><div><strong>Appearance</strong><small>Choose how the interface is displayed on this device.</small></div></div>
              <div className="configuration-mode-options">
                {[
                  { id: 'light', label: 'Light' },
                  { id: 'dark', label: 'Dark' },
                  { id: 'system', label: 'Use system setting' },
                ].map((mode) => (
                  <button type="button" key={mode.id} className={preferences.colorMode === mode.id ? 'configuration-mode selected' : 'configuration-mode'} onClick={() => updatePreference('colorMode', mode.id)} aria-pressed={preferences.colorMode === mode.id}>
                    <span className={`configuration-mode-preview ${mode.id}`}><i /><i /><i /></span>
                    <strong>{mode.label}</strong>
                    {preferences.colorMode === mode.id && <Check size={15} />}
                  </button>
                ))}
              </div>
              <p className="configuration-note">Changes are saved automatically to your user account and cached in this browser for faster startup.</p>
            </div>
          ) : (
            <form className="configuration-panel" onSubmit={(event) => { event.preventDefault(); onSaveSite(); }}>
              <div className="configuration-heading">
                <h2>{sections.find((section) => section.id === activeSection)?.label} settings</h2>
                <p>{activeSection === 'forms' ? 'Define the default behavior of data-entry forms.' : 'Manage shared application settings. These values apply to every user.'}</p>
              </div>
              {activeSection === 'forms' ? (
                <div className="configuration-fields configuration-fields-single">
                  {fields.filter((field) => field.key !== 'itemsPerPage').map((field) => (
                    <SettingField key={field.key} field={field} value={readField(settings, field.key)} onChange={(value) => updateSetting(field.key, value)} />
                  ))}
                  <div className="configuration-subsection-title">List defaults</div>
                  {fields.filter((field) => field.key === 'itemsPerPage').map((field) => (
                    <SettingField key={field.key} field={field} value={readField(settings, field.key)} onChange={(value) => updateSetting(field.key, value)} />
                  ))}
                </div>
              ) : (
                <div className="configuration-fields">
                  {fields.map((field) => (
                    <SettingField key={field.key} field={field} value={readField(settings, field.key)} onChange={(value) => updateSetting(field.key, value)} />
                  ))}
                </div>
              )}
              {error && <div className="alert alert-error configuration-alert">{error}</div>}
              <div className="configuration-footer">
                {saved && <span className="settings-saved"><Check size={15} />Configuration saved</span>}
                <button type="submit" className="primary-button" disabled={saving}><Save size={15} />{saving ? 'Saving...' : 'Save changes'}</button>
              </div>
            </form>
          )}
        </div>
      </div>
    </section>
  );
}

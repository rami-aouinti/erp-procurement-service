import { useCallback, useEffect, useState } from 'react';
import { suppliersApi } from '../api/suppliers.js';

const tabs = [
  ['profile', 'Company profile'],
  ['contacts', 'Contacts'],
  ['addresses', 'Addresses'],
  ['banks', 'Bank accounts'],
  ['classification', 'Classification'],
];

const emptyProfile = {
  legalName: '', registrationNumber: '', vatNumber: '', website: '',
  preferredLanguage: 'de', preferredCurrency: 'EUR', paymentTermsDays: 30,
};

export default function SupplierDetails({ supplier, onBack, onEdit, canClassify = false }) {
  const [tab, setTab] = useState('profile');
  const [profile, setProfile] = useState(emptyProfile);
  const [contacts, setContacts] = useState([]);
  const [addresses, setAddresses] = useState([]);
  const [banks, setBanks] = useState([]);
  const [classification, setClassification] = useState({ categories: [], commodities: [], tags: [], businessUnits: [], strategicTier: '' });
  const [taxonomy, setTaxonomy] = useState({ categories: [], commodities: [], tags: [], units: [] });
  const [contactForm, setContactForm] = useState({ contactType: 'GENERAL', firstName: '', lastName: '', jobTitle: '', email: '', phone: '', primary: false });
  const [addressForm, setAddressForm] = useState({ addressType: 'REGISTERED', label: '', companyName: '', street: '', street2: '', postalCode: '', city: '', region: '', country: 'DE', default: false });
  const [bankForm, setBankForm] = useState({ accountHolder: '', iban: '', bic: '', bankName: '', currency: 'EUR', primary: false });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadDetails = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [profileData, contactData, addressData, bankData] = await Promise.all([
        suppliersApi.getProfile(supplier.id),
        suppliersApi.listContacts(supplier.id),
        suppliersApi.listAddresses(supplier.id),
        suppliersApi.listBankAccounts(supplier.id),
      ]);
      setProfile({ ...emptyProfile, ...profileData });
      setContacts(contactData);
      setAddresses(addressData);
      setBanks(bankData);
    } catch (err) {
      setError(err.message || 'Unable to load supplier details.');
    } finally {
      setLoading(false);
    }
  }, [supplier.id]);

  useEffect(() => { loadDetails(); }, [loadDetails]);

  const loadClassification = useCallback(async () => {
    try {
      const [current, categories, commodities, tags, units] = await Promise.all([
        suppliersApi.getClassification(supplier.id),
        suppliersApi.listClassifications('categories'), suppliersApi.listClassifications('commodities'),
        suppliersApi.listClassifications('tags'), suppliersApi.listClassifications('business-units'),
      ]);
      setClassification({ ...current, strategicTier: current.strategicTier || '' });
      setTaxonomy({ categories, commodities, tags, units });
    } catch (err) { setError(err.message || 'Unable to load supplier classification.'); }
  }, [supplier.id]);

  useEffect(() => { if (tab === 'classification') loadClassification(); }, [tab, loadClassification]);

  const selectedIds = (items) => items.map((item) => String(item.id));
  const selectIds = (event) => Array.from(event.target.selectedOptions).map((option) => Number(option.value));

  async function submit(event, action, successMessage) {
    event.preventDefault();
    setSaving(true);
    setError('');
    setNotice('');
    try {
      await action();
      setNotice(successMessage);
      await loadDetails();
    } catch (err) {
      setError(err.message || 'The operation failed.');
    } finally {
      setSaving(false);
    }
  }

  async function deleteBank(account) {
    if (!window.confirm('Delete this bank account?')) return;
    setError('');
    setNotice('');
    try {
      await suppliersApi.removeBankAccount(supplier.id, account.id);
      setNotice('Bank account deleted.');
      await loadDetails();
    } catch (err) {
      setError(err.message || 'Unable to delete bank account.');
    }
  }

  async function makePrimary(account) {
    setError('');
    setNotice('');
    try {
      await suppliersApi.setPrimaryBankAccount(supplier.id, account.id);
      setNotice('Primary bank account updated.');
      await loadDetails();
    } catch (err) {
      setError(err.message || 'Unable to update primary bank account.');
    }
  }

  const update = (setter) => (event) => {
    const { name, value, type, checked } = event.target;
    setter((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
  };

  return (
    <section className="supplier-details" aria-label={`Details for ${supplier.name}`}>
      <div className="details-heading">
        <div><h3>{supplier.name}</h3></div>
        <div className="details-page-actions">
          <button className="secondary-button" type="button" onClick={onBack}>← Back</button>
          <button className="primary-button" type="button" onClick={onEdit}>Edit supplier</button>
        </div>
      </div>

      <div className="details-tabs" role="tablist" aria-label="Supplier detail sections">
        {tabs.map(([key, label]) => (
          <button key={key} type="button" role="tab" aria-selected={tab === key} className={tab === key ? 'details-tab active' : 'details-tab'} onClick={() => { setTab(key); setError(''); setNotice(''); }}>{label}</button>
        ))}
      </div>

      {error && <div className="alert alert-error" role="alert">{error}</div>}
      {notice && <div className="alert alert-success" role="status">{notice}</div>}
      {loading ? <p className="empty-state">Loading supplier details...</p> : (
        <div className="details-content">
          {tab === 'profile' && (
            <form className="details-form" onSubmit={(event) => submit(event, () => suppliersApi.updateProfile(supplier.id, { ...profile, paymentTermsDays: Number(profile.paymentTermsDays) }), 'Company profile saved.')}>
              <div className="details-form-grid">
                <label>Legal name<input name="legalName" value={profile.legalName || ''} onChange={update(setProfile)} /></label>
                <label>Registration number<input name="registrationNumber" value={profile.registrationNumber || ''} onChange={update(setProfile)} /></label>
                <label>VAT number<input name="vatNumber" value={profile.vatNumber || ''} onChange={update(setProfile)} /></label>
                <label>Website<input name="website" type="url" placeholder="https://example.com" value={profile.website || ''} onChange={update(setProfile)} /></label>
                <label>Preferred language<select name="preferredLanguage" value={profile.preferredLanguage || 'de'} onChange={update(setProfile)}><option value="de">German</option><option value="en">English</option><option value="fr">French</option></select></label>
                <label>Preferred currency<select name="preferredCurrency" value={profile.preferredCurrency || 'EUR'} onChange={update(setProfile)}><option value="EUR">EUR</option><option value="USD">USD</option><option value="CHF">CHF</option><option value="GBP">GBP</option></select></label>
                <label>Payment terms (days)<input name="paymentTermsDays" type="number" min="0" max="365" value={profile.paymentTermsDays ?? 30} onChange={update(setProfile)} /></label>
              </div>
              <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Save company profile'}</button>
            </form>
          )}

          {tab === 'contacts' && (
            <div className="details-columns">
              <div className="details-list">
                <h4>Registered contacts <span>{contacts.length}</span></h4>
                {contacts.length === 0 ? <p className="empty-state">No contacts added yet.</p> : contacts.map((contact, index) => (
                  <article className="detail-item" key={contact.id ?? `contact-${index}`}>
                    <div><strong>{contact.firstName} {contact.lastName}</strong>{contact.primary && <span className="supplier-status status-active">Primary</span>}</div>
                    <small>{contact.contactType} · {contact.jobTitle || 'No job title'}</small>
                    <small>{contact.email || 'No email'} {contact.phone ? `· ${contact.phone}` : ''}</small>
                  </article>
                ))}
              </div>
              <form className="details-form compact-form" onSubmit={(event) => submit(event, () => suppliersApi.addContact(supplier.id, contactForm), 'Contact added.')}>
                <h4>Add contact</h4>
                <div className="details-form-grid">
                  <label>Contact type<select name="contactType" value={contactForm.contactType} onChange={update(setContactForm)}><option value="GENERAL">General</option><option value="SALES">Sales</option><option value="FINANCE">Finance</option><option value="LEGAL">Legal</option><option value="TECHNICAL">Technical</option></select></label>
                  <label>First name<input name="firstName" required value={contactForm.firstName} onChange={update(setContactForm)} /></label>
                  <label>Last name<input name="lastName" required value={contactForm.lastName} onChange={update(setContactForm)} /></label>
                  <label>Job title<input name="jobTitle" value={contactForm.jobTitle} onChange={update(setContactForm)} /></label>
                  <label>Email<input name="email" type="email" value={contactForm.email} onChange={update(setContactForm)} /></label>
                  <label>Phone<input name="phone" value={contactForm.phone} onChange={update(setContactForm)} /></label>
                </div>
                <label className="checkbox-label"><input type="checkbox" name="primary" checked={contactForm.primary} onChange={update(setContactForm)} /> Primary contact</label>
                <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Add contact'}</button>
              </form>
            </div>
          )}

          {tab === 'addresses' && (
            <div className="details-columns">
              <div className="details-list">
                <h4>Saved addresses <span>{addresses.length}</span></h4>
                {addresses.length === 0 ? <p className="empty-state">No addresses added yet.</p> : addresses.map((address, index) => (
                  <article className="detail-item" key={address.id ?? `address-${index}`}>
                    <div><strong>{address.label || address.addressType}</strong>{address.default && <span className="supplier-status status-active">Default</span>}</div>
                    <small>{address.companyName || ''}</small>
                    <small>{address.street}{address.street2 ? `, ${address.street2}` : ''}</small>
                    <small>{[address.postalCode, address.city, address.region, address.country].filter(Boolean).join(', ')}</small>
                  </article>
                ))}
              </div>
              <form className="details-form compact-form" onSubmit={(event) => submit(event, () => suppliersApi.addAddress(supplier.id, addressForm), 'Address added.')}>
                <h4>Add address</h4>
                <div className="details-form-grid">
                  <label>Address type<select name="addressType" value={addressForm.addressType} onChange={update(setAddressForm)}><option value="REGISTERED">Registered</option><option value="BILLING">Billing</option><option value="SHIPPING">Shipping</option><option value="OTHER">Other</option></select></label>
                  <label>Label<input name="label" value={addressForm.label} onChange={update(setAddressForm)} placeholder="Head office" /></label>
                  <label>Company<input name="companyName" value={addressForm.companyName} onChange={update(setAddressForm)} /></label>
                  <label>Street<input name="street" required value={addressForm.street} onChange={update(setAddressForm)} /></label>
                  <label>Address line 2<input name="street2" value={addressForm.street2} onChange={update(setAddressForm)} /></label>
                  <label>Postal code<input name="postalCode" required value={addressForm.postalCode} onChange={update(setAddressForm)} /></label>
                  <label>City<input name="city" required value={addressForm.city} onChange={update(setAddressForm)} /></label>
                  <label>Region<input name="region" value={addressForm.region} onChange={update(setAddressForm)} /></label>
                  <label>Country code<input name="country" required maxLength="2" value={addressForm.country} onChange={update(setAddressForm)} /></label>
                </div>
                <label className="checkbox-label"><input type="checkbox" name="default" checked={addressForm.default} onChange={update(setAddressForm)} /> Default address</label>
                <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Add address'}</button>
              </form>
            </div>
          )}

          {tab === 'classification' && (
            <form className="details-form" onSubmit={(event) => submit(event, () => suppliersApi.updateClassification(supplier.id, {
              categoryIds: classification.categories.map((x) => Number(x.id)),
              commodityIds: classification.commodities.map((x) => Number(x.id)),
              tagIds: classification.tags.map((x) => Number(x.id)),
              businessUnitIds: classification.businessUnits.map((x) => Number(x.id)),
              strategicTier: classification.strategicTier || null,
            }), 'Supplier classification saved.')}>
              <p className="security-note">A supplier can have multiple categories, commodity codes, tags and business units. Inactive taxonomy entries remain visible on existing assignments but cannot be newly assigned.</p>
              <div className="details-form-grid">
                <label>Categories<select multiple size="5" disabled={!canClassify} value={selectedIds(classification.categories)} onChange={(e) => setClassification((v) => ({ ...v, categories: taxonomy.categories.filter((x) => selectIds(e).includes(Number(x.id))) }))}>{taxonomy.categories.map((x) => <option key={x.id} value={x.id} disabled={!x.active && !classification.categories.some((v) => Number(v.id) === Number(x.id))}>{x.name}{x.active ? '' : ' (inactive)'}</option>)}</select></label>
                <label>Commodity codes<select multiple size="5" disabled={!canClassify} value={selectedIds(classification.commodities)} onChange={(e) => setClassification((v) => ({ ...v, commodities: taxonomy.commodities.filter((x) => selectIds(e).includes(Number(x.id))) }))}>{taxonomy.commodities.map((x) => <option key={x.id} value={x.id} disabled={!x.active && !classification.commodities.some((v) => Number(v.id) === Number(x.id))}>{x.code} · {x.name}{x.active ? '' : ' (inactive)'}</option>)}</select></label>
                <label>Tags<select multiple size="5" disabled={!canClassify} value={selectedIds(classification.tags)} onChange={(e) => setClassification((v) => ({ ...v, tags: taxonomy.tags.filter((x) => selectIds(e).includes(Number(x.id))) }))}>{taxonomy.tags.map((x) => <option key={x.id} value={x.id} disabled={!x.active && !classification.tags.some((v) => Number(v.id) === Number(x.id))}>{x.name}{x.active ? '' : ' (inactive)'}</option>)}</select></label>
                <label>Business units<select multiple size="5" disabled={!canClassify} value={selectedIds(classification.businessUnits)} onChange={(e) => setClassification((v) => ({ ...v, businessUnits: taxonomy.units.filter((x) => selectIds(e).includes(Number(x.id))) }))}>{taxonomy.units.map((x) => <option key={x.id} value={x.id} disabled={!x.active && !classification.businessUnits.some((v) => Number(v.id) === Number(x.id))}>{x.name}{x.active ? '' : ' (inactive)'}</option>)}</select></label>
                <label>Strategic tier<select disabled={!canClassify} value={classification.strategicTier} onChange={(e) => setClassification((v) => ({ ...v, strategicTier: e.target.value }))}><option value="">Not classified</option><option value="STANDARD">Standard</option><option value="PREFERRED">Preferred</option><option value="STRATEGIC">Strategic</option></select></label>
              </div>
              {canClassify && <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Save classification'}</button>}
            </form>
          )}

          {tab === 'banks' && (
            <div className="details-columns">
              <div className="details-list">
                <h4>Bank accounts <span>{banks.length}</span></h4>
                <p className="security-note">IBAN values are encrypted at rest. Only masked IBANs are returned by the API.</p>
                {banks.length === 0 ? <p className="empty-state">No bank accounts added yet.</p> : banks.map((account, index) => (
                  <article className="detail-item" key={account.id ?? `bank-${index}`}>
                    <div><strong>{account.bankName || 'Bank account'}</strong>{account.primary && <span className="supplier-status status-active">Primary</span>}</div>
                    <small className="masked-iban">{account.iban}</small>
                    <small>{account.bic || 'No BIC'} · {account.currency}</small>
                    <div className="row-actions">{!account.primary && <button className="text-button" type="button" onClick={() => makePrimary(account)}>Set primary</button>}<button className="text-button danger-text" type="button" onClick={() => deleteBank(account)}>Delete</button></div>
                  </article>
                ))}
              </div>
              <form className="details-form compact-form" onSubmit={(event) => submit(event, () => suppliersApi.addBankAccount(supplier.id, bankForm), 'Bank account added.')}>
                <h4>Add bank account</h4>
                <p className="security-note">The full IBAN is sent once to the backend for encryption. It is never displayed again.</p>
                <div className="details-form-grid">
                  <label>Account holder<input name="accountHolder" required value={bankForm.accountHolder} onChange={update(setBankForm)} /></label>
                  <label>IBAN<input name="iban" required autoComplete="off" value={bankForm.iban} onChange={update(setBankForm)} placeholder="DE89 3704 0044 0532 0130 00" /></label>
                  <label>BIC<input name="bic" value={bankForm.bic} onChange={update(setBankForm)} /></label>
                  <label>Bank name<input name="bankName" value={bankForm.bankName} onChange={update(setBankForm)} /></label>
                  <label>Currency<select name="currency" value={bankForm.currency} onChange={update(setBankForm)}><option value="EUR">EUR</option><option value="USD">USD</option><option value="CHF">CHF</option><option value="GBP">GBP</option></select></label>
                </div>
                <label className="checkbox-label"><input type="checkbox" name="primary" checked={bankForm.primary} onChange={update(setBankForm)} /> Primary bank account</label>
                <button className="primary-button" type="submit" disabled={saving}>{saving ? 'Saving...' : 'Add bank account'}</button>
              </form>
            </div>
          )}
        </div>
      )}
    </section>
  );
}

import { getKeycloakAccessToken } from '../auth/AuthProvider.jsx';

const API_URL = '/api/suppliers';
const ONBOARDING_API_URL = '/api/supplier-onboarding';

async function request(path = '', options = {}, baseUrl = API_URL) {
  const token = await getKeycloakAccessToken();
  const response = await fetch(`${baseUrl}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });
  if (response.status === 204) return null;
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const details = Array.isArray(payload.violations) ? payload.violations.join(' ') : '';
    throw new Error(details || payload.error || `Request failed with HTTP ${response.status}`);
  }
  return payload;
}

const json = (method, data) => ({ method, body: JSON.stringify(data) });
const supplierPath = (id) => `/${encodeURIComponent(id)}`;

export const suppliersApi = {
  list: (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.search) params.set('search', filters.search);
    if (filters.status && filters.status !== 'ALL') params.set('status', filters.status);
    return request(params.size ? `?${params.toString()}` : '');
  },
  create: (data) => request('', json('POST', data), ONBOARDING_API_URL),
  update: (id, data) => request(supplierPath(id), json('PUT', data)),
  remove: (id) => request(supplierPath(id), { method: 'DELETE' }),

  getProfile: (id) => request(`${supplierPath(id)}/profile`),
  updateProfile: (id, data) => request(`${supplierPath(id)}/profile`, json('PUT', data)),

  listContacts: (id) => request(`${supplierPath(id)}/contacts`),
  addContact: (id, data) => request(`${supplierPath(id)}/contacts`, json('POST', data)),

  listAddresses: (id) => request(`${supplierPath(id)}/addresses`),
  addAddress: (id, data) => request(`${supplierPath(id)}/addresses`, json('POST', data)),

  listBankAccounts: (id) => request(`${supplierPath(id)}/bank-accounts`),
  addBankAccount: (id, data) => request(`${supplierPath(id)}/bank-accounts`, json('POST', data)),
  setPrimaryBankAccount: (id, accountId) => request(`${supplierPath(id)}/bank-accounts/${encodeURIComponent(accountId)}/primary`, { method: 'POST' }),
  removeBankAccount: (id, accountId) => request(`${supplierPath(id)}/bank-accounts/${encodeURIComponent(accountId)}`, { method: 'DELETE' }),
};

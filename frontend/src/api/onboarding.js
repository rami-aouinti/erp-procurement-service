import { getKeycloakAccessToken } from '../auth/AuthProvider.jsx';

const API_URL = '/api/supplier-onboarding';

async function request(path, options = {}) {
  const token = await getKeycloakAccessToken();
  const response = await fetch(API_URL + path, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: 'Bearer ' + token } : {}),
      ...options.headers,
    },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || 'Request failed with HTTP ' + response.status);
  }
  return payload;
}

const json = (method, data = {}) => ({ method, body: JSON.stringify(data) });

export const onboardingApi = {
  queue: () => request('/queue'),
  approve: (supplierId, reason = null) => request('/' + encodeURIComponent(supplierId) + '/approve', json('POST', reason ? { reason } : {})),
  reject: (supplierId, reason) => request('/' + encodeURIComponent(supplierId) + '/reject', json('POST', { reason })),
  requestChanges: (supplierId, reason) => request('/' + encodeURIComponent(supplierId) + '/request-changes', json('POST', { reason })),
  history: (supplierId) => request('/' + encodeURIComponent(supplierId) + '/history'),
};

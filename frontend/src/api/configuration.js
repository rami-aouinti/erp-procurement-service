import { getKeycloakAccessToken } from '../auth/AuthProvider.jsx';

const API_URL = '/api/configuration';

async function request(path, options = {}) {
  const token = await getKeycloakAccessToken();
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || `Configuration request failed with HTTP ${response.status}`);
  }
  return payload;
}

const put = (payload) => ({
  method: 'PUT',
  body: JSON.stringify(payload),
});

export const configurationApi = {
  getSite: () => request('/site'),
  updateSite: (settings) => request('/site', put({ settings })),
  getPreferences: () => request('/me/preferences'),
  updatePreferences: (preferences) => request('/me/preferences', put({ preferences })),
};

const API_URL = '/api/suppliers';

async function request(path = '', options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });

  if (response.status === 204) return null;

  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || `Request failed with HTTP ${response.status}`);
  }

  return payload;
}

export const suppliersApi = {
  list: () => request(),
  create: (data) => request('', { method: 'POST', body: JSON.stringify(data) }),
  update: (id, data) => request(`/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  remove: (id) => request(`/${id}`, { method: 'DELETE' }),
};

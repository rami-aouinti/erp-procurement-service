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
    const details = Array.isArray(payload.violations) ? payload.violations.join(' ') : '';
    throw new Error(details || payload.error || `Request failed with HTTP ${response.status}`);
  }
  return payload;
}

export const suppliersApi = {
  list: (filters = {}) => {
    const params = new URLSearchParams();
    if (filters.search) params.set('search', filters.search);
    if (filters.status && filters.status !== 'ALL') params.set('status', filters.status);
    return request(params.size ? `?${params.toString()}` : '');
  },
  create: (data) => request('', { method: 'POST', body: JSON.stringify(data) }),
  update: (id, data) => request(`/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  remove: (id) => request(`/${id}`, { method: 'DELETE' }),
};

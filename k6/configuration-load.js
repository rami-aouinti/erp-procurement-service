import http from 'k6/http';
import { check } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://backend:8000').replace(/\/$/, '');
const TOKEN = __ENV.AUTH_TOKEN || '';

export const options = {
  scenarios: {
    configuration_read_load: {
      executor: 'ramping-arrival-rate',
      startRate: 5,
      timeUnit: '1s',
      preAllocatedVUs: 10,
      maxVUs: 80,
      stages: [
        { target: 10, duration: '30s' },
        { target: 25, duration: '60s' },
        { target: 40, duration: '60s' },
        { target: 0, duration: '15s' },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500', 'p(99)<1000'],
    'http_req_duration{endpoint:configuration_site}': ['p(95)<350'],
    'http_req_duration{endpoint:user_preferences}': ['p(95)<350'],
    checks: ['rate>0.99'],
  },
};

export function setup() {
  if (!TOKEN) {
    throw new Error('AUTH_TOKEN is required. Use a dedicated test user access token.');
  }
  return { headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' } };
}

export default function (data) {
  const responses = http.batch([
    {
      method: 'GET',
      url: `${BASE_URL}/api/configuration/site`,
      params: { headers: data.headers, tags: { endpoint: 'configuration_site' }, timeout: '10s' },
    },
    {
      method: 'GET',
      url: `${BASE_URL}/api/configuration/me/preferences`,
      params: { headers: data.headers, tags: { endpoint: 'user_preferences' }, timeout: '10s' },
    },
  ]);

  check(responses[0], {
    'site configuration returns 200': (r) => r.status === 200,
    'site configuration returns JSON': (r) => (r.headers['Content-Type'] || '').includes('application/json'),
  });
  check(responses[1], {
    'user preferences return 200': (r) => r.status === 200,
    'user preferences return JSON': (r) => (r.headers['Content-Type'] || '').includes('application/json'),
  });

  if (__ENV.K6_WRITE_TESTS === 'true') {
    const payload = JSON.stringify({ preferences: { palette: 'indigo', colorMode: 'dark' } });
    const update = http.put(`${BASE_URL}/api/configuration/me/preferences`, payload, {
      headers: { ...data.headers, 'Content-Type': 'application/json' },
      tags: { endpoint: 'user_preferences_update' },
      timeout: '10s',
    });
    check(update, { 'user preferences update returns 200': (r) => r.status === 200 });
  }
}

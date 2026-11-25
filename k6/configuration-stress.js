import http from 'k6/http';
import { check } from 'k6';

const BASE_URL = (__ENV.BASE_URL || 'http://backend:8000').replace(/\/$/, '');
const TOKEN = __ENV.AUTH_TOKEN || '';

export const options = {
  scenarios: {
    configuration_stress: {
      executor: 'ramping-arrival-rate',
      startRate: 20,
      timeUnit: '1s',
      preAllocatedVUs: 30,
      maxVUs: 200,
      stages: [
        { target: 50, duration: '30s' },
        { target: 100, duration: '60s' },
        { target: 150, duration: '60s' },
        { target: 0, duration: '15s' },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    checks: ['rate>0.98'],
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

  check(responses[0], { 'site configuration returns 200': (r) => r.status === 200 });
  check(responses[1], { 'user preferences return 200': (r) => r.status === 200 });
}

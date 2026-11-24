import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  // Fixed-load scenario: exactly 20 virtual users for one minute.
  scenarios: {
    steady_20_vus: {
      executor: 'constant-vus',
      vus: 20,
      duration: '1m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<500'],
    checks: ['rate>0.99'],
  },
};

const BASE_URL = (__ENV.BASE_URL || 'http://backend:8000').replace(/\/$/, '');

export default function () {
  const response = http.get(`${BASE_URL}/api/health`, {
    tags: { endpoint: 'health' },
    timeout: '10s',
  });

  check(response, {
    'health endpoint returns 200': (r) => r.status === 200,
    'health response is JSON': (r) => (r.headers['Content-Type'] || '').includes('application/json'),
    'health response reports ok': (r) => {
      try {
        return r.json('status') === 'ok';
      } catch (_) {
        return false;
      }
    },
  });

  sleep(1);
}

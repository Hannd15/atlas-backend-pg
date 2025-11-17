import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

const token = `${__ENV.ATLAS_TOKEN}`;
const base = `${__ENV.BASE_URL || 'http://localhost:8001'}/api/pg`;

export let getPeriods = new Trend('get_periods');
export let getPhases = new Trend('get_phases');
export let getDeliverables = new Trend('get_deliverables');

export const options = {
  thresholds: {
    http_req_duration: ['p(95)<3000'], // 3s p95 across all requests
    get_periods: ['p(95)<3000'],
    get_phases: ['p(95)<3000'],
    get_deliverables: ['p(95)<3000'],
  },
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 50 },
    { duration: '30s', target: 0 },
  ],
};

export default function () {
  const params = { headers: { Authorization: `Bearer ${token}` } };

  let r1 = http.get(`${base}/academic-periods`, params);
  getPeriods.add(r1.timings.duration);
  check(r1, { '200 /academic-periods': (r) => r.status === 200 });

  // Replace 1 with an existing period id in seed; or iterate from list
  let r2 = http.get(`${base}/academic-periods/1/phases`, params);
  getPhases.add(r2.timings.duration);
  check(r2, { '200 /periods/1/phases': (r) => r.status === 200 });

  // Replace 1,1 with valid period/phase ids
  let r3 = http.get(`${base}/academic-periods/1/phases/1/deliverables`, params);
  getDeliverables.add(r3.timings.duration);
  check(r3, { '200 /deliverables': (r) => r.status === 200 });

  sleep(1);
}
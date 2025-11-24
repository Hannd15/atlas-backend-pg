import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 50, 
  duration: '30s',
  thresholds: {
    http_req_duration: ['p(95)<3000'],
    http_req_failed: ['rate<0.01'], 
  },
};

export default function () {
  const res = http.get('http://localhost:8001/api/pg/projects', {
        headers: { Authorization: 'Bearer 12|noaWiq5E9MGLz1qNHElsA718w6F702llHcXoXrfD6a7cb48f' }
    });

  
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  
}
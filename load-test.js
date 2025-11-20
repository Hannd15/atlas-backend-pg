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
        headers: { Authorization: 'Bearer 4|b6xuGrQAn2mazDMrrQuTNtkhYGHrspcl2iE34Yfmc1090de5' }
    });

  
  check(res, {
    'status is 200': (r) => r.status === 200,
  });
  
}
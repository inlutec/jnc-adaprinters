import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v2',
  withCredentials: true,
});

const token = localStorage.getItem('adaprinters_token');
if (token) {
  api.defaults.headers.common.Authorization = `Bearer ${token}`;
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('adaprinters_token');
    }
    return Promise.reject(error);
  }
);

export { api };


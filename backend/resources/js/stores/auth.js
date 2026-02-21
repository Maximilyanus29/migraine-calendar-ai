import { defineStore } from 'pinia';
import { apiRequest } from '../lib/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    initialized: false,
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
  },
  actions: {
    async loadMe() {
      try {
        this.user = await apiRequest('/auth/me');
      } catch (error) {
        if (error.status === 401) {
          this.user = null;
        } else {
          throw error;
        }
      } finally {
        this.initialized = true;
      }
    },
    async login(email, password) {
      this.user = await apiRequest('/auth/login', {
        method: 'POST',
        body: { email, password },
      });
      return this.user;
    },
    async register(name, email, password) {
      this.user = await apiRequest('/auth/register', {
        method: 'POST',
        body: { name, email, password },
      });
      return this.user;
    },
    async logout() {
      await apiRequest('/auth/logout', { method: 'POST' });
      this.user = null;
    },
  },
});

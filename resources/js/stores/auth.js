import { defineStore } from 'pinia';
import api, { ensureCsrfCookie } from '../api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        checked: false,
    }),

    getters: {
        isAuthenticated: (state) => state.user !== null,
    },

    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/api/user');
                this.user = data;
            } catch {
                this.user = null;
            } finally {
                this.checked = true;
            }
        },

        async login(email, password) {
            await ensureCsrfCookie();
            await api.post('/login', { email, password });
            await this.fetchUser();
        },

        async logout() {
            await api.post('/logout');
            this.user = null;
        },
    },
});

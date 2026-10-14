import { defineStore } from 'pinia'
import api, { ensureCsrfCookie } from '@/shared/lib/api'
import type { User } from '../types'

interface AuthState {
    user: User | null
    checked: boolean
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        checked: false,
    }),

    getters: {
        isAuthenticated: (state): boolean => state.user !== null,
    },

    actions: {
        async fetchUser(): Promise<void> {
            try {
                const { data } = await api.get<User>('/api/user')
                this.user = data
            } catch {
                this.user = null
            } finally {
                this.checked = true
            }
        },

        async login(email: string, password: string): Promise<void> {
            await ensureCsrfCookie()
            await api.post('/login', { email, password })
            await this.fetchUser()
        },

        async logout(): Promise<void> {
            await api.post('/logout')
            this.user = null
        },
    },
})

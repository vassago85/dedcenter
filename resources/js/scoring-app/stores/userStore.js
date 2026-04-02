import { defineStore } from 'pinia';
import axios from 'axios';

const MODE_KEY = 'dc_pwa_mode';

export const useUserStore = defineStore('user', {
    state: () => ({
        user: null,
        loading: false,
        loaded: false,
    }),

    getters: {
        canScore: (state) => state.user?.can_score ?? false,
        mode: () => localStorage.getItem(MODE_KEY),
        isScoreMode() {
            return this.canScore && this.mode === 'score';
        },
        isMemberMode() {
            return !this.canScore || this.mode === 'member';
        },
    },

    actions: {
        async fetchUser() {
            if (this.loading) return this.user;
            this.loading = true;
            try {
                const { data } = await axios.get('/api/user');
                this.user = data.user;
                this.loaded = true;

                if (!this.canScore) {
                    this.setMode('member');
                }

                return this.user;
            } catch (e) {
                console.error('Failed to fetch user:', e);
                return null;
            } finally {
                this.loading = false;
            }
        },

        async ensureLoaded() {
            if (this.loaded) return this.user;
            return this.fetchUser();
        },

        setMode(mode) {
            localStorage.setItem(MODE_KEY, mode);
        },

        clearMode() {
            localStorage.removeItem(MODE_KEY);
        },
    },
});

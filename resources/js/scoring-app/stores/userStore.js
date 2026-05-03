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
                const { data } = await axios.get('/api/user', { timeout: 8000 });
                this.user = data.user;

                if (!this.canScore) {
                    this.setMode('member');
                }

                return this.user;
            } catch (e) {
                // Logging only — we deliberately swallow the error so the app
                // can boot offline. If we leave `loaded` as false here the
                // router's `ensureLoaded()` will re-fire on every navigation
                // and a slow / hung axios call will block route transitions
                // forever (the symptom is a stuck spinner / blank screen on
                // a fresh phone with no internet). Mark the boot as resolved
                // either way; downstream views already gate on `canScore`.
                console.error('Failed to fetch user:', e);
                return null;
            } finally {
                this.loaded = true;
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

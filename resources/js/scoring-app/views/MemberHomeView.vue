<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <svg class="h-5 w-5 text-red-500" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="2.5" fill="currentColor"/>
                    <line x1="12" y1="3" x2="12" y2="7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="3" y1="12" x2="7" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="17" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <h1 class="text-xl font-bold tracking-tight">
                    <span class="text-white/90">DEAD</span><span class="text-red-500">CENTER</span>
                </h1>
                <div class="ml-auto flex items-center gap-3">
                    <button
                        @click="showNotifications = !showNotifications"
                        class="relative text-slate-400 hover:text-white transition-colors"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        <span
                            v-if="unreadCount > 0"
                            class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold"
                        >
                            {{ unreadCount > 9 ? '9+' : unreadCount }}
                        </span>
                    </button>
                    <button
                        v-if="userStore.canScore"
                        @click="switchToScoreMode"
                        class="rounded-lg bg-red-600/15 px-2.5 py-1 text-[11px] font-semibold text-red-400 transition-colors hover:bg-red-600/25"
                    >
                        Score Mode
                    </button>
                </div>
            </div>
        </header>

        <!-- Greeting -->
        <div class="mx-auto max-w-lg px-4 pt-6 pb-2">
            <p class="text-lg font-semibold">
                Welcome{{ userStore.user?.name ? ', ' + userStore.user.name.split(' ')[0] : '' }}
            </p>
        </div>

        <!-- Scoring Mode CTA (only for users who can score) -->
        <section v-if="userStore.canScore" class="mx-auto max-w-lg px-4 pt-2 pb-1">
            <button
                @click="switchToScoreMode"
                class="group flex w-full items-center gap-3 rounded-xl border border-red-600/40 bg-red-600/10 px-4 py-3 text-left transition-colors hover:bg-red-600/20 active:scale-[0.99]"
            >
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-red-600 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-white">Start Scoring</p>
                    <p class="text-xs text-slate-400">Open the scoring interface for active matches</p>
                </div>
                <svg class="h-4 w-4 flex-shrink-0 text-slate-500 group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </section>

        <!-- Live Now -->
        <section v-if="matchData.live.length" class="mx-auto max-w-lg px-4 py-4">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-400">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                </span>
                Live Now
            </h2>
            <div class="space-y-2">
                <MatchCard
                    v-for="match in matchData.live"
                    :key="match.id"
                    :match="match"
                    action-label="View Live Scores"
                    @action="openScoreboard(match.id)"
                />
            </div>
        </section>

        <!-- Tabs -->
        <div class="mx-auto max-w-lg px-4">
            <div class="flex border-b border-slate-700">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="relative px-4 py-2.5 text-sm font-medium transition-colors"
                    :class="activeTab === tab.key ? 'text-white' : 'text-slate-500 hover:text-slate-300'"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count > 0"
                        class="ml-1 rounded-full bg-red-600/20 px-1.5 py-0.5 text-[10px] text-red-400"
                    >
                        {{ tab.count }}
                    </span>
                    <span
                        v-if="activeTab === tab.key"
                        class="absolute inset-x-0 -bottom-px h-0.5 bg-red-600"
                    />
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <main class="mx-auto max-w-lg px-4 py-4">
            <div v-if="loading" class="flex justify-center py-12">
                <svg class="h-6 w-6 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>

            <!-- Upcoming -->
            <template v-else-if="activeTab === 'upcoming'">
                <div v-if="!matchData.upcoming.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-sm text-slate-400">No upcoming matches.</p>
                    <button @click="activeTab = 'browse'" class="mt-3 text-sm font-medium text-red-500 hover:text-red-400 transition-colors">
                        Find Matches &rarr;
                    </button>
                </div>
                <div v-else class="space-y-2">
                    <MatchCard
                        v-for="match in matchData.upcoming"
                        :key="match.id"
                        :match="match"
                    />
                </div>
            </template>

            <!-- Recent Results -->
            <template v-else-if="activeTab === 'recent'">
                <div v-if="!matchData.recent.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-sm text-slate-400">No completed matches yet.</p>
                </div>
                <div v-else class="space-y-2">
                    <MatchCard
                        v-for="match in matchData.recent"
                        :key="match.id"
                        :match="match"
                        action-label="View Results"
                        @action="openScoreboard(match.id)"
                    />
                </div>
            </template>

            <!-- Browse / Find Matches -->
            <template v-else-if="activeTab === 'browse'">
                <div v-if="!matchData.browse.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-sm text-slate-400">No matches currently accepting registrations.</p>
                </div>
                <div v-else class="space-y-2">
                    <MatchCard
                        v-for="match in matchData.browse"
                        :key="match.id"
                        :match="match"
                    />
                </div>
            </template>

            <!-- Notifications Panel -->
            <template v-else-if="activeTab === 'notifications'">
                <div v-if="!notifications.length" class="rounded-xl border border-slate-700 bg-slate-800 p-8 text-center">
                    <p class="text-sm text-slate-400">No notifications.</p>
                </div>
                <div v-else class="space-y-2">
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllRead"
                        class="mb-2 text-xs font-medium text-red-400 hover:text-red-300 transition-colors"
                    >
                        Mark all as read
                    </button>
                    <div
                        v-for="n in notifications"
                        :key="n.id"
                        class="rounded-xl border bg-slate-800 px-4 py-3 transition-colors"
                        :class="n.read_at ? 'border-slate-700' : 'border-red-600/30 bg-slate-800/80'"
                        @click="markRead(n)"
                    >
                        <p class="text-sm font-medium">{{ notificationTitle(n) }}</p>
                        <p v-if="n.data?.match_name" class="mt-0.5 text-xs text-slate-400">{{ n.data.match_name }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ formatTimeAgo(n.created_at) }}</p>
                    </div>
                </div>
            </template>
        </main>

        <!-- Notification Slide-over (compact) -->
        <Teleport to="body">
            <Transition name="slide">
                <div
                    v-if="showNotifications"
                    class="fixed inset-0 z-50 flex justify-end"
                >
                    <div class="absolute inset-0 bg-black/50" @click="showNotifications = false" />
                    <div class="relative w-full max-w-sm bg-slate-800 shadow-xl overflow-y-auto">
                        <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                            <h2 class="text-sm font-semibold">Notifications</h2>
                            <button @click="showNotifications = false" class="text-slate-400 hover:text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div v-if="!notifications.length" class="p-8 text-center text-sm text-slate-400">
                            No notifications yet.
                        </div>
                        <div v-else class="divide-y divide-slate-700">
                            <div
                                v-for="n in notifications"
                                :key="n.id"
                                class="px-4 py-3"
                                :class="n.read_at ? '' : 'bg-red-600/5'"
                                @click="markRead(n)"
                            >
                                <p class="text-sm font-medium">{{ notificationTitle(n) }}</p>
                                <p v-if="n.data?.match_name" class="mt-0.5 text-xs text-slate-400">{{ n.data.match_name }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ formatTimeAgo(n.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '../stores/userStore';
import axios from 'axios';
import MatchCard from '../components/MemberMatchCard.vue';

const router = useRouter();
const userStore = useUserStore();

const loading = ref(true);
const activeTab = ref('upcoming');
const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const matchData = ref({ live: [], upcoming: [], recent: [], browse: [] });

const tabs = computed(() => [
    { key: 'upcoming', label: 'Upcoming', count: matchData.value.upcoming.length },
    { key: 'recent', label: 'Results', count: 0 },
    { key: 'browse', label: 'Find Matches', count: matchData.value.browse.length },
    { key: 'notifications', label: 'Notifications', count: unreadCount.value },
]);

function switchToScoreMode() {
    userStore.setMode('score');
    router.push({ name: 'home' });
}

function openScoreboard(matchId) {
    window.location.href = `/scoreboard/${matchId}`;
}

function notificationTitle(n) {
    const typeMap = {
        RegistrationOpenNotification: 'Registration Open',
        SquaddingOpenNotification: 'Squadding Open',
        ScoresPublishedNotification: 'Scores Published',
        MatchUpdateNotification: 'Match Update',
        MatchReminderNotification: 'Match Reminder',
    };
    return typeMap[n.type] || n.type?.replace(/Notification$/, '').replace(/([A-Z])/g, ' $1').trim() || 'Notification';
}

function formatTimeAgo(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateStr).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' });
}

async function markRead(n) {
    if (n.read_at) return;
    try {
        await axios.post(`/api/notifications/${n.id}/read`);
        n.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch { /* ignore */ }
}

async function markAllRead() {
    try {
        await axios.post('/api/notifications/read-all');
        notifications.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
        unreadCount.value = 0;
    } catch { /* ignore */ }
}

onMounted(async () => {
    try {
        const [matchRes, notifRes] = await Promise.all([
            axios.get('/api/member/matches'),
            axios.get('/api/notifications'),
        ]);
        matchData.value = matchRes.data;
        notifications.value = notifRes.data.notifications;
        unreadCount.value = notifRes.data.unread_count;
    } catch (e) {
        console.error('Failed to load member data:', e);
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: opacity 0.2s ease;
}
.slide-enter-active > div:last-child,
.slide-leave-active > div:last-child {
    transition: transform 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
}
.slide-enter-from > div:last-child {
    transform: translateX(100%);
}
.slide-leave-to > div:last-child {
    transform: translateX(100%);
}
</style>

<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <router-link :to="{ name: 'home' }" class="text-slate-400 hover:text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </router-link>
                <h1 class="text-lg font-bold truncate">{{ matchStore.currentMatch?.name ?? 'Loading...' }}</h1>
                <div class="ml-auto flex items-center gap-2">
                    <DeviceRoleChip />
                    <button
                        @click="toggleDeviceSettings"
                        class="rounded-lg p-1.5 transition-colors"
                        :class="showDeviceSettings ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                        title="Device Settings"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <SyncStatusBar />

        <main class="mx-auto max-w-lg px-4 py-6">
            <div v-if="matchStore.loading" class="flex justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
            </div>

            <template v-else-if="matchStore.currentMatch">
                <div class="space-y-4">
                    <!-- Device Settings Panel -->
                    <div v-if="showDeviceSettings" class="rounded-xl border border-amber-600/40 bg-slate-800 overflow-hidden">
                        <div class="border-b border-amber-600/30 bg-amber-900/20 px-4 py-3">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                                <h3 class="text-sm font-bold text-amber-400">Device Lock Settings</h3>
                                <span class="ml-auto text-[10px] uppercase text-slate-500">Local only</span>
                            </div>
                        </div>

                        <div class="p-4 space-y-4">
                                <!-- Squad Lock -->
                                <div class="space-y-2">
                                    <label class="text-xs font-medium text-slate-400">Lock to Squad</label>
                                    <div v-if="matchStore.hasSquadLock" class="flex items-center gap-2">
                                        <span class="flex-1 rounded-lg border border-green-700/50 bg-green-900/20 px-3 py-2 text-sm font-medium text-green-400">
                                            {{ matchStore.lockedSquadName }}
                                        </span>
                                        <button @click="clearSquadLock" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-600">Clear</button>
                                    </div>
                                    <select
                                        v-else
                                        v-model="selectedSquadId"
                                        @change="applySquadLock"
                                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    >
                                        <option :value="null">No squad lock</option>
                                        <option v-for="squad in matchStore.squads" :key="squad.id" :value="squad.id">{{ squad.name }} ({{ squad.shooters.length }})</option>
                                    </select>
                                </div>

                                <!-- Stage Lock -->
                                <div class="space-y-2">
                                    <label class="text-xs font-medium text-slate-400">Lock to Stage</label>
                                    <div v-if="matchStore.hasStageLock" class="flex items-center gap-2">
                                        <span class="flex-1 rounded-lg border border-green-700/50 bg-green-900/20 px-3 py-2 text-sm font-medium text-green-400">
                                            {{ matchStore.lockedStageName }}
                                        </span>
                                        <button @click="clearStageLock" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-600">Clear</button>
                                    </div>
                                    <select
                                        v-else
                                        v-model="selectedStageId"
                                        @change="applyStageLock"
                                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    >
                                        <option :value="null">No stage lock</option>
                                        <option v-for="ts in matchStore.targetSets" :key="ts.id" :value="ts.id">{{ ts.label }}</option>
                                    </select>
                                </div>

                                <!-- Active locks summary -->
                                <div v-if="matchStore.hasAnyLock" class="rounded-lg border border-slate-700 bg-slate-900/50 px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <p v-if="matchStore.hasSquadLock" class="text-xs text-slate-400">Squad: <span class="text-white font-medium">{{ matchStore.lockedSquadName }}</span></p>
                                            <p v-if="matchStore.hasStageLock" class="text-xs text-slate-400">Stage: <span class="text-white font-medium">{{ matchStore.lockedStageName }}</span></p>
                                        </div>
                                        <button
                                            @click="clearAllLocks"
                                            class="rounded bg-red-600/20 px-2.5 py-1 text-[10px] font-bold uppercase text-red-400 hover:bg-red-600/30"
                                        >Clear All</button>
                                    </div>
                                </div>

                        </div>
                    </div>

                    <!-- Match info -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-slate-400">Date</span>
                                <p class="font-medium">{{ formatDate(matchStore.currentMatch.date) }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Location</span>
                                <p class="font-medium">{{ matchStore.currentMatch.location || '—' }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Stages</span>
                                <p class="font-medium">{{ matchStore.targetSets.length }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400">Shooters</span>
                                <p class="font-medium">{{ matchStore.allShooters.length }}</p>
                            </div>
                            <div v-if="matchStore.currentMatch.scoring_type === 'prs'" class="col-span-2">
                                <span class="text-slate-400">Scoring</span>
                                <p class="font-medium"><span class="rounded bg-amber-600 px-1.5 py-0.5 text-xs font-bold uppercase">PRS</span> Hit/Miss + Stage Times</p>
                            </div>
                        </div>
                    </div>

                    <!-- Target sets summary -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wider">Target Sets</h3>
                        <div class="space-y-2">
                            <div
                                v-for="ts in matchStore.targetSets"
                                :key="ts.id"
                                class="flex items-center justify-between rounded-lg bg-slate-700/40 px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ ts.label }}</span>
                                <span class="text-slate-400">{{ ts.distance_meters }}m &middot; {{ ts.gongs.length }} gongs</span>
                            </div>
                        </div>
                    </div>

                    <!-- Squads summary -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                        <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wider">Squads</h3>
                        <div class="space-y-2">
                            <div
                                v-for="squad in matchStore.squads"
                                :key="squad.id"
                                class="flex items-center justify-between rounded-lg bg-slate-700/40 px-3 py-2 text-sm"
                            >
                                <span class="font-medium">{{ squad.name }}</span>
                                <span class="text-slate-400">{{ squad.shooters.length }} shooters</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lock indicator bar -->
                    <div v-if="matchStore.hasAnyLock" class="flex items-center gap-2 rounded-lg border border-amber-700/40 bg-amber-900/10 px-3 py-2.5">
                        <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span class="flex-1 text-xs font-medium text-amber-400">
                            Device Locked<template v-if="matchStore.hasSquadLock"> &middot; {{ matchStore.lockedSquadName }}</template><template v-if="matchStore.hasStageLock"> &middot; {{ matchStore.lockedStageName }}</template>
                        </span>
                        <button @click="showDeviceSettings = true" class="text-[10px] font-bold uppercase text-amber-500 hover:text-amber-400">Edit</button>
                    </div>

                    <!-- Action buttons -->
                    <div class="grid grid-cols-1 gap-3 pt-2">
                        <!-- Match already scored (Completed) -->
                        <template v-if="matchStore.currentMatch.status === 'completed'">
                            <div class="rounded-xl border border-slate-600 bg-slate-800 p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 h-6 w-6 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-bold text-white">Match already scored</h3>
                                        <p class="mt-1 text-sm text-slate-400">
                                            Scores have been finalised, badges awarded, and post-match emails sent. New scores can't be captured until the match is re-opened.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <router-link
                                :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:bg-red-800"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                                View Scoreboard
                            </router-link>

                            <template v-if="matchStore.canManage">
                                <button
                                    v-if="!showReopenConfirm"
                                    @click="showReopenConfirm = true"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-amber-700 bg-amber-900/20 py-3 font-semibold text-amber-300 transition-colors hover:bg-amber-900/40"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                    Re-open match to edit
                                </button>

                                <div v-else class="rounded-xl border border-amber-600/40 bg-slate-800 p-4 space-y-3">
                                    <p class="text-sm font-semibold text-amber-400">Re-open this match?</p>
                                    <p class="text-xs text-slate-400">
                                        Scoring will go live again so you can fix scores. Badges already awarded and emails already sent stay in place. When you're done, complete the match again to re-finalise.
                                    </p>
                                    <p v-if="reopenError" class="text-xs text-red-400">{{ reopenError }}</p>
                                    <div class="flex gap-2">
                                        <button
                                            @click="confirmReopenMatch"
                                            :disabled="reopenLoading"
                                            class="flex-1 rounded-lg bg-amber-600 py-2 text-sm font-bold text-white transition-colors hover:bg-amber-700 disabled:opacity-60"
                                        >
                                            {{ reopenLoading ? 'Re-opening...' : 'Yes, Re-open' }}
                                        </button>
                                        <button
                                            @click="showReopenConfirm = false"
                                            class="flex-1 rounded-lg border border-slate-600 py-2 text-sm font-medium text-slate-400 transition-colors hover:bg-slate-700"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- PRS / ELR: single Start Scoring button that goes through ScoringRouter -->
                        <template v-else-if="matchStore.currentMatch.scoring_type === 'prs' || matchStore.currentMatch.scoring_type === 'elr'">
                            <router-link
                                :to="{ name: 'scoring', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:bg-red-800"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>
                                Start Scoring
                            </router-link>
                        </template>

                        <!-- Standard / Relay: Scoring Matrix + squad-based buttons -->
                        <template v-else>
                            <router-link
                                :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                                class="flex items-center justify-center gap-2 rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:bg-red-800"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-7.5m8.625 0h7.5m-8.625 0c.621 0 1.125.504 1.125 1.125" />
                                </svg>
                                Scoring Matrix
                            </router-link>

                            <template v-if="matchStore.hasSquadLock">
                                <router-link
                                    :to="{ name: 'scoring', params: { matchId: props.matchId } }"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    Continue Scoring &mdash; {{ matchStore.lockedSquadName }}
                                </router-link>
                                <router-link
                                    :to="{ name: 'squad-select', params: { matchId: props.matchId } }"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-amber-700 bg-amber-900/20 py-3 font-semibold text-amber-300 transition-colors hover:bg-amber-900/40"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                    Change Squad
                                </router-link>
                            </template>
                            <template v-else>
                                <router-link
                                    :to="{ name: 'scoring', params: { matchId: props.matchId } }"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    Start Scoring
                                </router-link>
                            </template>
                        </template>
                        <router-link
                            v-if="matchStore.currentMatch.status !== 'completed'"
                            :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                            class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800 py-3 font-semibold text-white transition-colors hover:bg-slate-700"
                        >
                            View Scoreboard
                        </router-link>

                        <!-- Complete Match (MD only) -->
                        <template v-if="matchStore.canManage && matchStore.currentMatch.status !== 'completed'">
                            <button
                                v-if="!showCompleteConfirm"
                                @click="prepareCompleteMatch"
                                :disabled="completeLoading"
                                class="flex items-center justify-center gap-2 rounded-xl border border-amber-700 bg-amber-900/20 py-3 font-semibold text-amber-300 transition-colors hover:bg-amber-900/40"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                {{ completeLoading ? 'Checking...' : 'Complete Match' }}
                            </button>

                            <div v-if="showCompleteConfirm" class="rounded-xl border border-amber-600/40 bg-slate-800 p-4 space-y-3">
                                <p class="text-sm font-semibold text-amber-400">Complete this match?</p>
                                <p class="text-xs text-slate-400">
                                    {{ completeInfo.scored_shooters }} of {{ completeInfo.total_shooters }} shooters scored.
                                </p>
                                <p v-if="completeInfo.warnings?.length" class="text-xs text-amber-400">
                                    ⚠ {{ completeInfo.warnings.join(' ') }}
                                </p>
                                <p class="text-xs text-slate-500">This will finalize scores, award badges, and notify shooters.</p>
                                <div class="flex gap-2">
                                    <button
                                        @click="confirmCompleteMatch"
                                        :disabled="completeLoading"
                                        class="flex-1 rounded-lg bg-amber-600 py-2 text-sm font-bold text-white transition-colors hover:bg-amber-700"
                                    >
                                        {{ completeLoading ? 'Completing...' : 'Yes, Complete' }}
                                    </button>
                                    <button
                                        @click="showCompleteConfirm = false"
                                        class="flex-1 rounded-lg border border-slate-600 py-2 text-sm font-medium text-slate-400 transition-colors hover:bg-slate-700"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import DeviceRoleChip from '../components/DeviceRoleChip.vue';
import SyncStatusBar from '../components/SyncStatusBar.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();

const showDeviceSettings = ref(false);
const showCompleteConfirm = ref(false);
const completeLoading = ref(false);
const completeInfo = ref({ warnings: [], total_shooters: 0, scored_shooters: 0 });
const showReopenConfirm = ref(false);
const reopenLoading = ref(false);
const reopenError = ref('');
const selectedSquadId = ref(null);
const selectedStageId = ref(null);

function toggleDeviceSettings() {
    showDeviceSettings.value = !showDeviceSettings.value;
}

function applySquadLock() {
    if (!selectedSquadId.value) return;
    const squad = matchStore.squads.find(s => s.id === selectedSquadId.value);
    if (squad) {
        matchStore.lockSquad(props.matchId, squad.id, squad.name);
    }
    selectedSquadId.value = null;
}

function clearSquadLock() {
    matchStore.unlockSquad();
}

function applyStageLock() {
    if (!selectedStageId.value) return;
    const stage = matchStore.targetSets.find(ts => ts.id === selectedStageId.value);
    if (stage) {
        matchStore.lockStage(props.matchId, stage.id, stage.label);
    }
    selectedStageId.value = null;
}

function clearStageLock() {
    matchStore.unlockStage();
}

function clearAllLocks() {
    matchStore.clearAllLocks();
}

async function prepareCompleteMatch() {
    completeLoading.value = true;
    try {
        const data = await matchStore.completeMatch(props.matchId, true);
        completeInfo.value = data;
        showCompleteConfirm.value = true;
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to check match status.');
    } finally {
        completeLoading.value = false;
    }
}

async function confirmCompleteMatch() {
    completeLoading.value = true;
    try {
        await matchStore.completeMatch(props.matchId, false);
        showCompleteConfirm.value = false;
        router.push({ name: 'scoreboard', params: { matchId: props.matchId } });
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to complete match.');
    } finally {
        completeLoading.value = false;
    }
}

async function confirmReopenMatch() {
    reopenLoading.value = true;
    reopenError.value = '';
    try {
        await matchStore.reopenMatch(props.matchId);
        showReopenConfirm.value = false;
    } catch (e) {
        reopenError.value = e.response?.data?.message || 'Failed to re-open match.';
    } finally {
        reopenLoading.value = false;
    }
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' });
}

onMounted(() => {
    // Show whatever's already in the store immediately, then revalidate in
    // the background. Without this, tapping back from Scoreboard / scoring
    // re-fires fetchMatch, flips `loading` to true, and the user sees a
    // "redownloading the match" spinner even though the match is already
    // cached locally.
    if (matchStore.currentMatch?.id === props.matchId) {
        matchStore.fetchMatch(props.matchId, { silent: true });
    } else {
        matchStore.fetchMatch(props.matchId);
    }
});
</script>

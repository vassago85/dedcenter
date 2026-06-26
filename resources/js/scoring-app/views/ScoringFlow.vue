<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <button
                    @click="handleHeaderBack"
                    class="text-slate-400 hover:text-white"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    <p v-if="isScoped" class="text-[11px] text-amber-400">
                        Relay {{ scopedRelayIndex }} &mdash; {{ scopedDistanceLabel }}
                    </p>
                    <p v-else-if="currentView === 'scoring' && isMultiSquad && currentScoringSquad" class="text-[11px] text-amber-400">
                        {{ currentScoringSquad.name }} &mdash; {{ currentDistanceLabel }}
                    </p>
                    <p v-else-if="currentView === 'squad-select' || currentView === 'squad-picker'" class="text-[11px] text-amber-400">
                        {{ currentTargetSet?.label }} &mdash; {{ currentTargetSet?.distance_meters }}m
                    </p>
                    <router-link
                        v-else-if="matchStore.lockedSquadName"
                        :to="{ name: 'squad-select', params: { matchId: props.matchId } }"
                        class="flex items-center gap-1 text-[11px] text-amber-400 hover:text-amber-300"
                    >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        {{ matchStore.lockedSquadName }}
                    </router-link>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <SyncBadge :pending="scoringStore.pendingCount" :syncing="scoringStore.syncing" @sync="scoringStore.syncScores()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <DeviceLockBanner />

        <!-- Auth expired warning -->
        <div v-if="scoringStore.authExpired" class="border-b border-amber-800 bg-amber-900/40 px-4 py-2">
            <div class="mx-auto flex max-w-lg items-center gap-2 text-sm text-amber-300">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>Session expired. Scores are saved locally. Please log in again to sync.</span>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-red-500"></div>
        </div>

        <!-- STAGE SELECT (choose which distance/stage) -->
        <div v-else-if="currentView === 'stage-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <h2 class="text-xl font-bold">Select Stage</h2>
                    <p class="text-sm text-slate-400">Choose which distance to score</p>
                </div>
                <div class="space-y-3">
                    <button
                        v-for="(ts, idx) in targetSets"
                        :key="ts.id"
                        @click="selectStage(idx)"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-800 p-5 text-left transition-all hover:border-red-600 active:scale-[0.98]"
                    >
                        <div>
                            <span class="text-lg font-bold">{{ ts.label }}</span>
                            <div class="mt-1 text-sm text-slate-400">{{ ts.distance_meters }}m &bull; {{ ts.gongs?.length }} gongs</div>
                        </div>
                        <div class="text-right">
                            <span
                                class="text-xs font-bold"
                                :class="stageProgress(ts.id).allDone ? 'text-green-400' : stageProgress(ts.id).someDone ? 'text-amber-400' : 'text-slate-500'"
                            >
                                {{ stageProgress(ts.id).label }}
                            </span>
                            <div v-if="stageProgress(ts.id).allDone" class="mt-1">
                                <svg class="ml-auto h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SQUAD SELECT / SQUAD PICKER (choose which relay) -->
        <div v-else-if="(currentView === 'squad-select' || currentView === 'squad-picker') && isMultiSquad" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <p class="text-sm font-medium uppercase tracking-widest text-slate-400">
                        {{ currentView === 'squad-picker' ? 'Next Relay' : 'Select Relay' }}
                    </p>
                    <h2 class="mt-1 text-xl font-bold">{{ currentTargetSet?.label }}</h2>
                    <p class="text-sm text-slate-400">{{ currentTargetSet?.distance_meters }}m &mdash; Select a squad to score</p>
                </div>

                <!-- Recommended squad highlight -->
                <div v-if="recommendedSquad && !allSquadsScoredAtCurrentStage" class="rounded-xl border-2 border-red-600 bg-red-600/10 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-medium uppercase text-red-400">Recommended</p>
                            <p class="text-lg font-bold">{{ recommendedSquad.squad.name }}</p>
                            <p class="text-xs text-slate-400">{{ (recommendedSquad.squad.shooters ?? []).filter(s => s.status === 'active').length }} active shooters</p>
                        </div>
                        <button
                            @click="selectSquad(recommendedSquad.index)"
                            class="rounded-xl bg-red-600 px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-red-700 active:scale-95"
                        >
                            Score Next
                        </button>
                    </div>
                </div>

                <!-- All squads scored at this stage -->
                <div v-if="allSquadsScoredAtCurrentStage" class="rounded-xl border border-green-700/50 bg-green-900/20 p-4 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <p class="font-bold text-green-400">All squads scored at {{ currentTargetSet?.label }}</p>
                    <button
                        @click="goToStageSelect"
                        class="mt-3 w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Choose Another Stage
                    </button>
                </div>

                <!-- Full squad list -->
                <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
                    <div class="border-b border-slate-700 px-4 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">All Squads</p>
                    </div>
                    <div class="divide-y divide-slate-700/50">
                        <button
                            v-for="item in squadPickerItems"
                            :key="item.squad.id"
                            @click="item.status !== 'scored' ? selectSquad(item.index) : null"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors"
                            :class="{
                                'opacity-50 cursor-not-allowed': item.status === 'scored',
                                'hover:bg-slate-700/50 active:scale-[0.99]': item.status !== 'scored',
                                'bg-red-600/5 border-l-4 border-l-red-500': item.isRecommended && item.status !== 'scored',
                                'border-l-4 border-l-transparent': !item.isRecommended || item.status === 'scored',
                            }"
                        >
                            <div
                                class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                :class="{
                                    'bg-green-600/20 text-green-400': item.status === 'scored',
                                    'bg-amber-600/20 text-amber-400': item.status === 'in-progress',
                                    'bg-slate-700 text-slate-400': item.status === 'pending',
                                }"
                            >
                                <template v-if="item.status === 'scored'">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </template>
                                <template v-else>{{ item.index + 1 }}</template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    {{ item.squad.name }}
                                    <span v-if="item.isRecommended && item.status !== 'scored'" class="ml-1.5 rounded bg-red-600/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-red-400">Next</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ item.activeCount }} shooters</p>
                            </div>
                            <div class="text-right">
                                <span v-if="item.status === 'scored'" class="text-xs font-bold text-green-400">Complete</span>
                                <span v-else-if="item.status === 'in-progress'" class="text-xs font-bold text-amber-400">{{ item.fraction }}</span>
                                <span v-else class="text-xs text-slate-500">Pending</span>
                            </div>
                            <svg v-if="item.status !== 'scored'" class="h-4 w-4 flex-shrink-0 text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    @click="goToStageSelect"
                    class="block w-full rounded-xl border border-slate-600 py-3 text-center text-sm font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                >
                    Change Stage
                </button>
            </div>
        </div>

        <!-- Relay summary -->
        <div v-else-if="currentView === 'relay-summary'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold">{{ currentDistanceLabel }} &mdash; {{ currentRelayLabel }} Complete</h2>
                    <p class="text-sm text-slate-400">{{ currentTargetSet?.label }}</p>
                </div>

                <RelaySummaryTable
                    :match-id="props.matchId"
                    :target-set="currentTargetSet"
                    :shooters="shooters"
                    @correct-shooter="openCorrectionFor"
                    @corrected="onCorrectionApplied"
                />

                <div class="flex flex-col gap-3 pt-2">
                    <button
                        @click="scoringStore.syncScores()"
                        class="w-full rounded-xl bg-green-600 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                    >
                        Sync Scores
                    </button>
                    <button
                        v-if="isScoped && nextSquadSuggestion"
                        @click="goToNextSquad"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Score Next &rarr; {{ nextSquadSuggestion.name }} at {{ scopedDistanceLabel }}
                    </button>
                    <router-link
                        v-if="isScoped"
                        :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                        class="block w-full rounded-xl border border-slate-600 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                    >
                        Back to Matrix
                    </router-link>
                    <button
                        v-if="!isScoped"
                        @click="dismissSummary"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Continue
                    </button>
                </div>
            </div>
        </div>

        <!-- Gong transition interstitial -->
        <div v-else-if="currentView === 'gong-transition'" class="flex flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-slate-400">Next Gong</p>

            <div class="rounded-2xl border border-slate-700 bg-slate-800 px-8 py-8 shadow-lg">
                <p class="text-5xl font-black">#{{ currentGong?.number }}</p>
                <p v-if="currentGong?.label" class="mt-2 text-lg text-slate-300">{{ currentGong.label }}</p>
                <p class="mt-2 text-lg font-semibold text-amber-400">{{ effectiveMultiplier }}x points</p>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-5 py-3">
                <p class="text-sm text-slate-400">{{ currentTargetSet?.label }}</p>
                <p class="text-lg font-bold">{{ currentTargetSet?.distance_meters }}m</p>
            </div>

            <p class="text-xs text-slate-500">Gong {{ scoringStore.currentGongIndex + 1 }} of {{ currentGongs.length }}</p>

            <button
                @click="dismissGongTransition"
                class="w-full max-w-xs rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow-lg transition-colors hover:bg-red-700 active:scale-95 active:bg-red-800"
            >
                Continue Scoring
            </button>
        </div>

        <!-- Stage summary (break between stages for locked-squad / single-squad mode) -->
        <div v-else-if="currentView === 'stage-summary'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold">{{ currentTargetSet?.label }} Complete</h2>
                    <p class="text-sm text-slate-400">{{ currentTargetSet?.distance_meters }}m &mdash; All shooters scored</p>
                </div>

                <RelaySummaryTable
                    :match-id="props.matchId"
                    :target-set="currentTargetSet"
                    :shooters="shooters"
                    @correct-shooter="openCorrectionFor"
                    @corrected="onCorrectionApplied"
                />

                <div class="flex flex-col gap-3 pt-2">
                    <button
                        @click="scoringStore.syncScores()"
                        class="w-full rounded-xl bg-green-600 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                    >
                        Sync Scores
                    </button>
                    <button
                        v-if="scoringStore.currentTargetSetIndex < targetSets.length - 1"
                        @click="dismissStageSummary"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Next Stage &rarr; {{ targetSets[scoringStore.currentTargetSetIndex + 1]?.label }} ({{ targetSets[scoringStore.currentTargetSetIndex + 1]?.distance_meters }}m)
                    </button>
                    <button
                        v-else
                        @click="dismissStageSummary"
                        class="w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Finish
                    </button>
                    <button
                        @click="goToStageSelect"
                        class="block w-full rounded-xl border border-slate-600 py-3 text-center text-sm font-semibold text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                    >
                        Choose Another Stage
                    </button>
                </div>
            </div>
        </div>

        <!-- Match complete -->
        <div v-else-if="currentView === 'complete'" class="flex flex-1 flex-col items-center justify-center gap-4 px-4 text-center">
            <div class="rounded-full bg-green-600/20 p-4">
                <svg class="h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold">{{ isScoped ? 'Stage Complete!' : 'Scoring Complete!' }}</h2>
            <p class="text-slate-400">
                {{ isScoped ? `Relay ${scopedRelayIndex} at ${scopedDistanceLabel} scored.` : 'All squads have been scored at all distances.' }}
            </p>
            <div class="flex flex-col gap-3 pt-4">
                <button
                    @click="scoringStore.syncScores()"
                    class="rounded-xl bg-green-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-green-700"
                >
                    Sync Scores
                </button>
                <button
                    v-if="isScoped && nextSquadSuggestion"
                    @click="goToNextSquad"
                    class="rounded-xl bg-red-600 px-6 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                >
                    Score Next &rarr; {{ nextSquadSuggestion.name }} at {{ scopedDistanceLabel }}
                </button>
                <router-link
                    v-if="isScoped"
                    :to="{ name: 'scoring-matrix', params: { matchId: props.matchId } }"
                    class="rounded-xl border border-slate-600 px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    Back to Matrix
                </router-link>
                <button
                    v-if="!isScoped"
                    @click="goToStageSelect"
                    class="rounded-xl border border-slate-600 px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    Back to Stage Select
                </button>
                <router-link
                    :to="{ name: 'scoreboard', params: { matchId: props.matchId } }"
                    class="rounded-xl border border-slate-600 px-6 py-3 text-center font-semibold text-white transition-colors hover:bg-slate-800"
                >
                    View Scoreboard
                </router-link>
            </div>
        </div>

        <!-- Scoring interface -->
        <template v-else>
            <!-- Progress bar -->
            <div class="bg-slate-800 px-4 py-2">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span v-if="!isScoped">Set {{ scoringStore.currentTargetSetIndex + 1 }}/{{ targetSets.length }}</span>
                        <span v-if="isMultiSquad">Squad {{ scoringStore.currentSquadIndex + 1 }}/{{ squadOrder.length }}</span>
                        <span>Gong {{ scoringStore.currentGongIndex + 1 }}/{{ currentGongs.length }}</span>
                        <span>Shooter {{ scoringStore.currentShooterIndex + 1 }}/{{ shooters.length }}</span>
                    </div>
                    <div class="mt-1 h-1.5 rounded-full bg-slate-700">
                        <div
                            class="h-full rounded-full bg-red-600 transition-all duration-300"
                            :style="{ width: progressPercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Target set info -->
            <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-2">
                <div class="mx-auto max-w-lg flex items-center justify-between text-sm">
                    <span class="font-semibold text-amber-400">{{ currentTargetSet?.label }}</span>
                    <span class="text-slate-400">{{ currentTargetSet?.distance_meters }}m</span>
                </div>
            </div>

            <!-- "Up for a Royal Flush" pressure pill. Surfaces the moment
                 the current shooter has hit every other gong at this distance
                 and a hit on the current gong would complete the flush. Sits
                 at the very top of the scoring area so the scorer sees it
                 before they even read the shooter name — adds the pressure
                 the MD asked for. Pulsing dot + amber gradient strip. -->
            <div
                v-if="isRoyalFlushShot"
                class="border-b border-amber-500/40 bg-gradient-to-r from-amber-900/40 via-amber-700/50 to-amber-900/40 px-4 py-2.5 shadow-inner shadow-amber-500/10"
                role="status"
                aria-live="polite"
            >
                <div class="mx-auto flex max-w-lg items-center justify-center gap-2.5">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-300"></span>
                    </span>
                    <span class="rounded-full border-2 border-amber-300 bg-amber-500/20 px-4 py-1 text-xs font-black uppercase tracking-[0.18em] text-amber-100 shadow-lg shadow-amber-500/30">
                        ★ Up for Royal Flush ★
                    </span>
                </div>
            </div>

            <!-- Main scoring area -->
            <main class="flex flex-1 flex-col px-4 py-6">
                <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                    <!-- Current gong -->
                    <div class="mb-4 text-center">
                        <p class="text-sm text-slate-400">Gong</p>
                        <p class="text-3xl font-bold">
                            #{{ currentGong?.number }}
                            <span v-if="currentGong?.label" class="text-lg text-slate-400">{{ currentGong.label }}</span>
                        </p>
                        <p class="mt-1 text-sm text-amber-400">{{ effectiveMultiplier }}x points</p>
                    </div>

                    <!-- Current shooter -->
                    <div class="mb-8 rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                        <p class="text-sm text-slate-400">Shooter</p>
                        <p class="text-2xl font-bold">{{ currentShooter?.name }}</p>
                        <div class="mt-1 flex items-center justify-center gap-3 text-sm text-slate-400">
                            <span v-if="currentShooter?.bib_number">Bib #{{ currentShooter.bib_number }}</span>
                            <span>{{ currentShooter?.squadName }}</span>
                        </div>
                    </div>

                    <!-- Existing score indicator -->
                    <div
                        v-if="existingScore !== null"
                        class="mb-4 rounded-lg px-4 py-2 text-center text-sm font-medium"
                        :class="existingScore.isHit ? 'bg-green-900/40 text-green-400' : 'bg-red-900/40 text-red-400'"
                    >
                        Previously scored: {{ existingScore.isHit ? 'HIT' : 'MISS' }}
                    </div>

                    <!-- Royal Flush prompt: this shot completes the flush at this distance -->
                    <div
                        v-if="isRoyalFlushShot"
                        class="mb-4 overflow-hidden rounded-xl border-2 border-amber-500 bg-gradient-to-br from-amber-700/40 to-amber-900/40 shadow-lg shadow-amber-500/20"
                        role="status"
                    >
                        <div class="flex items-center gap-3 px-4 py-3">
                            <svg class="h-7 w-7 flex-shrink-0 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M5 16L3 5l5.5 4L12 4l3.5 5L21 5l-2 11H5zm0 2h14v2H5v-2z" />
                            </svg>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-amber-400">Royal Flush this shot</p>
                                <p class="text-sm font-semibold text-amber-100">Hit completes the flush at {{ currentTargetSet?.distance_meters }}m</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hit / Miss buttons -->
                    <div class="mt-auto grid grid-cols-2 gap-4">
                        <button
                            @click="recordScore(true)"
                            class="flex h-32 flex-col items-center justify-center rounded-2xl bg-green-600 text-white shadow-lg transition-all active:scale-95 active:bg-green-700"
                            :class="{ 'ring-4 ring-green-400': existingScore?.isHit === true }"
                        >
                            <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span class="text-2xl font-black">HIT</span>
                        </button>
                        <button
                            @click="recordScore(false)"
                            class="flex h-32 flex-col items-center justify-center rounded-2xl bg-red-700 text-white shadow-lg transition-all active:scale-95 active:bg-red-800"
                            :class="{ 'ring-4 ring-red-400': existingScore?.isHit === false }"
                        >
                            <svg class="mb-1 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <span class="text-2xl font-black">MISS</span>
                        </button>
                    </div>

                    <!-- Nav buttons -->
                    <div class="mt-4 flex gap-3">
                        <button
                            @click="goBack"
                            :disabled="isFirst"
                            class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-30"
                        >
                            &larr; Previous
                        </button>
                        <button
                            @click="goForward"
                            class="flex-1 rounded-xl border border-slate-600 py-3 text-sm font-medium transition-colors hover:bg-slate-800"
                        >
                            Next &rarr;
                        </button>
                    </div>
                </div>
            </main>
        </template>

        <ScoringSponsorship />

        <CorrectShooterModal
            :open="correctionOpen"
            mode="standard"
            :match-id="props.matchId"
            :shooter="correctionTarget?.shooter"
            :target-set="correctionTarget?.targetSet"
            :existing-scores="correctionTarget?.existingScores ?? []"
            :existing-stage-time="correctionTarget?.existingStageTime ?? null"
            @close="correctionTarget = null"
            @corrected="onCorrectionApplied"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useScoringStore } from '../stores/scoringStore';
import axios from 'axios';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';
import ScoringSponsorship from '../components/ScoringSponsorship.vue';
import CorrectShooterModal from '../components/CorrectShooterModal.vue';
import RelaySummaryTable from '../components/RelaySummaryTable.vue';
import { getCorrectionQueue, removeCorrectionQueueEntry } from '../lib/offlineDb.js';

const props = defineProps({
    matchId: { type: Number, required: true },
    squadId: { type: Number, default: null },
    targetSetId: { type: Number, default: null },
});

const route = useRoute();
const router = useRouter();
const matchStore = useMatchStore();
const scoringStore = useScoringStore();
const ready = ref(false);
const currentView = ref('scoring');

// Inline single-shooter correction modal — opened by tapping the
// pencil on a relay/stage-summary row. Reuses CorrectShooterModal so
// the same UX is shared with PRS and the web deep-link entry point.
const correctionTarget = ref(null); // { shooter, targetSet, existingScores, existingStageTime }
const correctionOpen = computed(() => correctionTarget.value !== null);

const STATE_KEY = 'dc_relay_state';

// ── Mode detection ──
const isScoped = computed(() => route.name === 'scoped-scoring' && props.squadId && props.targetSetId);
const isMultiSquad = computed(() => !isScoped.value && !matchStore.hasSquadLock && matchStore.squads.length > 1);

// ── Scoped-mode helpers (matrix-launched) ──
const scopedSquad = computed(() => {
    if (!isScoped.value) return null;
    return matchStore.squads.find(s => s.id === props.squadId);
});

const scopedRelayIndex = computed(() => {
    if (!isScoped.value) return 0;
    const idx = matchStore.squads.findIndex(s => s.id === props.squadId);
    return idx >= 0 ? idx + 1 : '?';
});

const scopedTargetSet = computed(() => {
    if (!isScoped.value) return null;
    return matchStore.targetSets.find(ts => ts.id === props.targetSetId);
});

const scopedDistanceLabel = computed(() => {
    return scopedTargetSet.value ? `${scopedTargetSet.value.distance_meters}m` : '';
});

// ── Squad ordering (for multi-squad flow) ──
const squadOrder = computed(() => {
    if (!isMultiSquad.value) return [];
    return [...matchStore.squads].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
});

const currentScoringSquad = computed(() => {
    if (!isMultiSquad.value) return null;
    return squadOrder.value[scoringStore.currentSquadIndex] ?? null;
});

const currentRelayLabel = computed(() => {
    if (isScoped.value) return `Relay ${scopedRelayIndex.value}`;
    if (isMultiSquad.value && currentScoringSquad.value) {
        return currentScoringSquad.value.name;
    }
    return '';
});

const currentDistanceLabel = computed(() => {
    if (isScoped.value) return scopedDistanceLabel.value;
    return currentTargetSet.value ? `${currentTargetSet.value.distance_meters}m` : '';
});

// ── Target sets ──
const targetSets = computed(() => {
    if (isScoped.value && scopedTargetSet.value) {
        return [scopedTargetSet.value];
    }
    return matchStore.targetSets;
});
const currentTargetSet = computed(() => targetSets.value[scoringStore.currentTargetSetIndex]);
const currentGongs = computed(() => currentTargetSet.value?.gongs ?? []);
const currentGong = computed(() => currentGongs.value[scoringStore.currentGongIndex]);
const distanceMultiplier = computed(() => parseFloat(currentTargetSet.value?.distance_multiplier) || 1);
const effectiveMultiplier = computed(() => {
    const dm = distanceMultiplier.value;
    const gm = parseFloat(currentGong.value?.multiplier) || 1;
    return Math.round(dm * gm * 100) / 100;
});

// ── Shooters ──
const shooters = computed(() => {
    if (isScoped.value && scopedSquad.value) {
        return scopedSquad.value.shooters
            .filter(s => s.status === 'active')
            .map(s => ({ ...s, squadName: scopedSquad.value.name }));
    }
    if (isMultiSquad.value && currentScoringSquad.value) {
        const squad = currentScoringSquad.value;
        return (squad.shooters ?? [])
            .filter(s => s.status === 'active')
            .map(s => ({ ...s, squadName: squad.name }));
    }
    return matchStore.hasSquadLock ? matchStore.squadShooters : matchStore.allShooters;
});
const currentShooter = computed(() => shooters.value[scoringStore.currentShooterIndex]);

const existingScore = computed(() => {
    if (!currentShooter.value || !currentGong.value) return null;
    return scoringStore.getScore(currentShooter.value.id, currentGong.value.id);
});

// ── Royal Flush "this shot completes it" detector ──
// For Royal-Flush-enabled matches, surface a loud banner when the
// current shooter has hit every OTHER gong at this distance and the
// current gong is the only one left to call. Gives the MD time to
// shout it out / get the cameras ready instead of finding out on the
// scoreboard after the fact.
//
// Counts hits across every gong at this distance *except* the one
// being scored. If that count equals (gongs.length - 1), a hit here
// completes the flush at this distance. Reactive to back-edits — if
// the MD goes back and flips a previous miss to a hit, the banner
// shows up automatically.
const isRoyalFlushShot = computed(() => {
    if (!matchStore.currentMatch?.royal_flush_enabled) return false;
    if (!currentShooter.value || !currentTargetSet.value || !currentGong.value) return false;
    const gongs = currentGongs.value;
    if (gongs.length < 2) return false;

    let hitsOnOthers = 0;
    for (const g of gongs) {
        if (g.id === currentGong.value.id) continue;
        const score = scoringStore.getScore(currentShooter.value.id, g.id);
        if (score?.isHit) hitsOnOthers++;
    }
    return hitsOnOthers === gongs.length - 1;
});

// ── Progress ──
const totalSteps = computed(() => {
    if (isMultiSquad.value) {
        let total = 0;
        for (const ts of targetSets.value) {
            for (const squad of squadOrder.value) {
                const activeCount = (squad.shooters ?? []).filter(s => s.status === 'active').length;
                total += ts.gongs.length * activeCount;
            }
        }
        return total;
    }
    return targetSets.value.reduce((sum, ts) => sum + ts.gongs.length * shooters.value.length, 0);
});

const currentStep = computed(() => {
    if (isMultiSquad.value) {
        let step = 0;
        for (let t = 0; t < scoringStore.currentTargetSetIndex; t++) {
            const ts = targetSets.value[t];
            for (const squad of squadOrder.value) {
                const activeCount = (squad.shooters ?? []).filter(s => s.status === 'active').length;
                step += ts.gongs.length * activeCount;
            }
        }
        const ts = currentTargetSet.value;
        if (ts) {
            for (let sq = 0; sq < scoringStore.currentSquadIndex; sq++) {
                const squad = squadOrder.value[sq];
                const activeCount = (squad?.shooters ?? []).filter(s => s.status === 'active').length;
                step += ts.gongs.length * activeCount;
            }
        }
        step += scoringStore.currentGongIndex * shooters.value.length;
        step += scoringStore.currentShooterIndex;
        return step;
    }
    let step = 0;
    for (let t = 0; t < scoringStore.currentTargetSetIndex; t++) {
        step += targetSets.value[t].gongs.length * shooters.value.length;
    }
    step += scoringStore.currentGongIndex * shooters.value.length;
    step += scoringStore.currentShooterIndex;
    return step;
});

const progressPercent = computed(() => {
    if (!totalSteps.value) return 0;
    return Math.round((currentStep.value / totalSteps.value) * 100);
});

const isFirst = computed(() => {
    return scoringStore.currentTargetSetIndex === 0 &&
        scoringStore.currentSquadIndex === 0 &&
        scoringStore.currentGongIndex === 0 &&
        scoringStore.currentShooterIndex === 0;
});

// ── Next-squad suggestion (scoped mode, from matrix) ──
const nextSquadSuggestion = computed(() => {
    if (!isScoped.value || !props.targetSetId) return null;
    const squads = matchStore.squads;
    const matrix = matchStore.completionMatrix;
    const concurrentRelays = matchStore.currentMatch?.concurrent_relays ?? 2;
    const currentIdx = squads.findIndex(s => s.id === props.squadId);
    if (currentIdx < 0) return null;

    const stride = Math.max(1, concurrentRelays);
    for (let offset = stride; offset < squads.length; offset += stride) {
        const idx = (currentIdx + offset) % squads.length;
        const squad = squads[idx];
        const status = matrix[squad.id]?.[props.targetSetId]?.status;
        if (status !== 'scored') return squad;
    }
    return null;
});

function goToNextSquad() {
    if (!nextSquadSuggestion.value) return;
    router.push({
        name: 'roll-call',
        params: {
            matchId: props.matchId,
            squadId: nextSquadSuggestion.value.id,
            targetSetId: props.targetSetId,
        },
    });
}

// ── Squad picker: recommended squad (concurrent_relays stride + completion matrix) ──
const recommendedSquad = computed(() => {
    if (!isMultiSquad.value || !currentTargetSet.value) return null;
    const matrix = matchStore.completionMatrix;
    const concurrentRelays = matchStore.currentMatch?.concurrent_relays ?? 2;
    const currentIdx = scoringStore.currentSquadIndex;
    const squads = squadOrder.value;
    const tsId = currentTargetSet.value.id;
    const stride = Math.max(1, concurrentRelays);

    for (let offset = stride; offset < squads.length; offset += stride) {
        const idx = (currentIdx + offset) % squads.length;
        const squad = squads[idx];
        const status = matrix[squad.id]?.[tsId]?.status;
        if (status !== 'scored') return { squad, index: idx };
    }
    for (let i = 0; i < squads.length; i++) {
        if (i === currentIdx) continue;
        const squad = squads[i];
        const status = matrix[squad.id]?.[tsId]?.status;
        if (status !== 'scored') return { squad, index: i };
    }
    return null;
});

const allSquadsScoredAtCurrentStage = computed(() => {
    if (!isMultiSquad.value || !currentTargetSet.value) return false;
    const matrix = matchStore.completionMatrix;
    const tsId = currentTargetSet.value.id;
    return squadOrder.value.every(sq => matrix[sq.id]?.[tsId]?.status === 'scored');
});

const squadPickerItems = computed(() => {
    if (!isMultiSquad.value || !currentTargetSet.value) return [];
    const matrix = matchStore.completionMatrix;
    const tsId = currentTargetSet.value.id;
    const recIdx = recommendedSquad.value?.index ?? -1;
    return squadOrder.value.map((squad, idx) => {
        const cell = matrix[squad.id]?.[tsId];
        const status = cell?.status ?? 'pending';
        const activeCount = (squad.shooters ?? []).filter(s => s.status === 'active').length;
        return {
            squad,
            index: idx,
            status,
            activeCount,
            fraction: cell ? `${cell.actual}/${cell.expected}` : '',
            isRecommended: idx === recIdx,
        };
    });
});

// ── Stage progress for stage-select screen ──
function stageProgress(targetSetId) {
    const matrix = matchStore.completionMatrix;
    const squads = isMultiSquad.value ? squadOrder.value : matchStore.squads;
    const totalSquads = squads.length;
    let scoredSquads = 0;
    for (const sq of squads) {
        if (matrix[sq.id]?.[targetSetId]?.status === 'scored') scoredSquads++;
    }
    return {
        allDone: totalSquads > 0 && scoredSquads === totalSquads,
        someDone: scoredSquads > 0,
        label: isMultiSquad.value ? `${scoredSquads}/${totalSquads} squads` : (scoredSquads > 0 ? 'Scored' : 'Pending'),
    };
}

// ── Persistence ──
function saveProgress() {
    if (isScoped.value) return;
    try {
        const state = {
            matchId: props.matchId,
            view: currentView.value,
            targetSetIdx: scoringStore.currentTargetSetIndex,
            squadIdx: scoringStore.currentSquadIndex,
            gongIdx: scoringStore.currentGongIndex,
            shooterIdx: scoringStore.currentShooterIndex,
        };
        localStorage.setItem(STATE_KEY, JSON.stringify(state));
    } catch { /* quota exceeded or private mode */ }
}

function restoreProgress() {
    try {
        const raw = localStorage.getItem(STATE_KEY);
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (state.matchId !== props.matchId) return false;

        const validViews = ['stage-select', 'squad-select', 'squad-picker', 'scoring', 'relay-summary', 'stage-summary', 'gong-transition', 'complete'];
        const view = validViews.includes(state.view) ? state.view : 'stage-select';

        scoringStore.currentTargetSetIndex = Math.min(state.targetSetIdx ?? 0, targetSets.value.length - 1);
        scoringStore.currentSquadIndex = Math.min(state.squadIdx ?? 0, Math.max(0, squadOrder.value.length - 1));
        scoringStore.currentGongIndex = state.gongIdx ?? 0;
        scoringStore.currentShooterIndex = state.shooterIdx ?? 0;
        currentView.value = view;
        return true;
    } catch { return false; }
}

function clearProgress() {
    try { localStorage.removeItem(STATE_KEY); } catch { /* ignore */ }
}

// ── Navigation ──
function selectStage(idx) {
    scoringStore.currentTargetSetIndex = idx;
    scoringStore.currentSquadIndex = 0;
    scoringStore.currentGongIndex = 0;
    scoringStore.currentShooterIndex = 0;
    if (isMultiSquad.value) {
        currentView.value = 'squad-select';
    } else {
        currentView.value = 'scoring';
    }
    saveProgress();
}

function selectSquad(squadIndex) {
    scoringStore.jumpToSquad(squadIndex);
    currentView.value = 'scoring';
    saveProgress();
}

function goToStageSelect() {
    currentView.value = 'stage-select';
    saveProgress();
}

function handleHeaderBack() {
    if (currentView.value === 'scoring') {
        if (isScoped.value) {
            router.push({ name: 'scoring-matrix', params: { matchId: props.matchId } });
        } else if (isMultiSquad.value) {
            currentView.value = 'squad-select';
            saveProgress();
        } else {
            currentView.value = 'stage-select';
            saveProgress();
        }
    } else if (currentView.value === 'squad-select' || currentView.value === 'squad-picker') {
        currentView.value = 'stage-select';
        saveProgress();
    } else if (currentView.value === 'stage-summary') {
        currentView.value = 'stage-select';
        saveProgress();
    } else if (currentView.value === 'stage-select') {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
    } else {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
    }
}

// ── Scoring actions ──
async function recordScore(isHit) {
    if (!currentShooter.value || !currentGong.value) return;
    await scoringStore.recordScore(currentShooter.value.id, currentGong.value.id, isHit);
    syncLocalScoresToMatch();
    advance();
}

function advance() {
    const s = scoringStore;
    if (s.advanceToNextShooter(shooters.value.length)) { saveProgress(); return; }
    if (s.advanceToNextGong(currentGongs.value.length, shooters.value.length)) {
        currentView.value = 'gong-transition';
        saveProgress();
        return;
    }
    if (isMultiSquad.value || isScoped.value) {
        currentView.value = 'relay-summary';
        saveProgress();
        return;
    }
    currentView.value = 'stage-summary';
    saveProgress();
    return;
}

function dismissGongTransition() {
    currentView.value = 'scoring';
    saveProgress();
}

function dismissSummary() {
    if (isMultiSquad.value) {
        currentView.value = 'squad-picker';
        saveProgress();
        return;
    }
    if (scoringStore.advanceToNextTargetSet(targetSets.value.length)) {
        currentView.value = 'scoring';
        saveProgress();
        return;
    }
    currentView.value = 'complete';
    clearProgress();
}

function dismissStageSummary() {
    if (scoringStore.advanceToNextTargetSet(targetSets.value.length)) {
        currentView.value = 'scoring';
        saveProgress();
        return;
    }
    currentView.value = 'complete';
    clearProgress();
}

function goForward() {
    advance();
}

function goBack() {
    const s = scoringStore;
    if (s.currentShooterIndex > 0) {
        s.currentShooterIndex--;
        saveProgress();
        return;
    }
    if (s.currentGongIndex > 0) {
        s.currentGongIndex--;
        s.currentShooterIndex = shooters.value.length - 1;
        saveProgress();
        return;
    }
    if (isMultiSquad.value && s.currentSquadIndex > 0) {
        s.currentSquadIndex--;
        const prevSquad = squadOrder.value[s.currentSquadIndex];
        const prevShooterCount = (prevSquad?.shooters ?? []).filter(sh => sh.status === 'active').length;
        const prevGongs = currentTargetSet.value?.gongs ?? [];
        s.currentGongIndex = prevGongs.length - 1;
        s.currentShooterIndex = prevShooterCount - 1;
        saveProgress();
        return;
    }
    if (s.currentTargetSetIndex > 0) {
        s.currentTargetSetIndex--;
        if (isMultiSquad.value) {
            s.currentSquadIndex = squadOrder.value.length - 1;
            const prevSquad = squadOrder.value[s.currentSquadIndex];
            const prevShooterCount = (prevSquad?.shooters ?? []).filter(sh => sh.status === 'active').length;
            const prevGongs = targetSets.value[s.currentTargetSetIndex].gongs;
            s.currentGongIndex = prevGongs.length - 1;
            s.currentShooterIndex = prevShooterCount - 1;
        } else {
            const prevGongs = targetSets.value[s.currentTargetSetIndex].gongs;
            s.currentGongIndex = prevGongs.length - 1;
            s.currentShooterIndex = shooters.value.length - 1;
        }
        saveProgress();
    }
}

// Open the correction modal for one shooter on the current summary
// stage. Used by the pencil button on relay-summary / stage-summary
// rows. We snapshot the current per-gong state from scoringStore so
// the modal can pre-fill HIT / MISS / blank for every gong.
function openCorrectionFor(shooter) {
    const ts = currentTargetSet.value;
    if (!ts || !shooter) return;
    const existingScores = [];
    for (const g of (ts.gongs ?? [])) {
        const s = scoringStore.getScore(shooter.id, g.id);
        if (s && s.isHit !== undefined && s.isHit !== null) {
            existingScores.push({ gong_id: g.id, is_hit: !!s.isHit });
        }
    }
    const stTime = scoringStore.getStageTime(shooter.id, ts.id);
    correctionTarget.value = {
        shooter,
        targetSet: ts,
        existingScores,
        existingStageTime: stTime?.timeSeconds ?? null,
    };
}

async function onCorrectionApplied(response) {
    // Pull the latest scores so the summary recomputes off the server
    // state (which is now authoritative). Fall back to a local-only
    // patch if the refresh fails (e.g. transient network blip).
    try {
        await matchStore.fetchMatch(props.matchId);
        const freshScores = matchStore.currentMatch?.scores ?? [];
        await scoringStore.refreshScores(props.matchId, freshScores);
        syncLocalScoresToMatch();
    } catch {
        if (Array.isArray(response?.scores)) {
            for (const s of response.scores) {
                await scoringStore.recordScore(s.shooter_id ?? correctionTarget.value?.shooter?.id, s.gong_id, !!s.is_hit);
            }
        }
    }
}

// Drain any corrections that were queued while offline. Called from
// the periodic sync loop. Safe to call when online too — if the queue
// is empty we no-op fast.
async function drainCorrectionQueue() {
    if (!navigator.onLine) return;
    const entries = await getCorrectionQueue(props.matchId);
    for (const entry of entries) {
        try {
            await axios.post(
                `/api/matches/${entry.match_id}/shooters/${entry.shooter_id}/correct`,
                entry.payload,
            );
            await removeCorrectionQueueEntry(entry.id);
        } catch (e) {
            // 423 = match completed: leave queued so the MD can decide
            // whether to reopen. Anything else: bail this cycle, try
            // again on the next tick.
            if (e.response?.status === 423) continue;
            break;
        }
    }
}

let syncInterval;

function syncLocalScoresToMatch() {
    if (!matchStore.currentMatch) return;
    const merged = [];
    for (const s of scoringStore.scores.values()) {
        merged.push({ shooter_id: s.shooterId, gong_id: s.gongId, is_hit: s.isHit });
    }
    matchStore.currentMatch.scores = merged;
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    const scores = matchStore.currentMatch?.scores ?? [];
    await scoringStore.initForMatch(props.matchId, scores);

    syncLocalScoresToMatch();

    ready.value = true;

    if (!isScoped.value) {
        if (!restoreProgress()) {
            currentView.value = (isMultiSquad.value || targetSets.value.length > 1)
                ? 'stage-select'
                : 'scoring';
        }
    }

    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (scoringStore.pendingCount > 0) {
            await scoringStore.syncScores();
        }
        await drainCorrectionQueue();
        try {
            await matchStore.fetchMatch(props.matchId);
            const freshScores = matchStore.currentMatch?.scores ?? [];
            await scoringStore.refreshScores(props.matchId, freshScores);
            syncLocalScoresToMatch();
        } catch { /* offline or transient failure */ }
    }, 15000);

    drainCorrectionQueue();

    // Deep-link entry: `/score/{matchId}?correct=<shooterId>&stage=<targetSetId>`
    // opens the correction modal straight away. Used by the web MD's
    // "Correct shooter" link in the corrections feed so the MD doesn't
    // have to navigate the whole flow just to fix one score.
    const correctShooterId = Number(route.query.correct);
    const correctStageId = Number(route.query.stage);
    if (correctShooterId && correctStageId) {
        const ts = targetSets.value.find(t => t.id === correctStageId);
        if (ts) {
            scoringStore.currentTargetSetIndex = Math.max(0, targetSets.value.indexOf(ts));
        }
        const allShooters = matchStore.allShooters ?? [];
        const target = allShooters.find(s => s.id === correctShooterId);
        if (target) openCorrectionFor(target);
    }
});

onUnmounted(() => {
    clearInterval(syncInterval);
});
</script>

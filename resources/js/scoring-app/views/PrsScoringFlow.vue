<template>
    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Global Header -->
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-3">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <button @click="goBack" class="p-2 text-slate-400 hover:text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-amber-600 px-2 py-0.5 text-xs font-bold uppercase">PRS</span>
                        <h1 class="truncate text-base font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <SyncBadge :pending="prsStore.pendingCount" :syncing="prsStore.syncing" @sync="prsStore.syncPendingResults()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <DeviceLockBanner max-width-class="max-w-2xl" />

        <!-- Auth expired warning -->
        <div v-if="prsStore.authExpired" class="border-b border-amber-800 bg-amber-900/40 px-4 py-2">
            <div class="mx-auto flex max-w-2xl items-center gap-2 text-sm text-amber-300">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>Session expired. Scores saved locally. Log in again to sync.</span>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-600 border-t-amber-500"></div>
        </div>

        <!-- SCREEN: Match Home -->
        <div v-else-if="prsStore.currentScreen === 'match-home'" class="flex flex-1 flex-col">
            <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center gap-6 px-4 text-center">
                <h2 class="text-3xl font-bold">{{ matchStore.currentMatch?.name }}</h2>
                <p class="text-lg text-slate-400">{{ matchStore.currentMatch?.date }} &bull; {{ matchStore.currentMatch?.location }}</p>

                <div v-if="deviceLockMode !== 'open'" class="flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <span v-if="deviceLockMode === 'locked_to_stage'" class="text-slate-300">Locked to Stage: {{ lockedStageName }}</span>
                    <span v-else-if="deviceLockMode === 'locked_to_squad'" class="text-slate-300">Locked to Squad: {{ lockedSquadName }}</span>
                </div>

                <button @click="startScoring" class="mt-4 w-full max-w-sm rounded-2xl bg-red-600 px-8 py-5 text-xl font-bold text-white transition-all hover:bg-red-700 active:scale-[0.98]">
                    Start Scoring
                </button>

                <router-link
                    :to="{ name: 'match-overview', params: { matchId: props.matchId } }"
                    class="mt-2 text-sm text-slate-500 hover:text-slate-300 transition-colors"
                >
                    &larr; Match Overview
                </router-link>

                <button @click="showCorrections = !showCorrections" class="mt-4 w-full max-w-sm rounded-xl border border-slate-700 bg-slate-800/60 px-6 py-3 text-sm font-semibold text-amber-400 transition-all hover:border-amber-600 hover:bg-slate-800">
                    {{ showCorrections ? 'Hide Corrections' : 'Manage Corrections' }}
                </button>

                <button @click="showCorrectionLogs = true" class="mt-1 text-xs text-slate-500 hover:text-slate-300 transition-colors">
                    View Corrections Log
                </button>
            </div>

            <!-- Corrections Management Panel -->
            <div v-if="showCorrections" class="border-t border-slate-700 bg-slate-800/30 px-4 py-6">
                <div class="mx-auto w-full max-w-2xl space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-amber-400">Score Corrections</h3>

                    <select
                        v-model="correctionStageId"
                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none"
                    >
                        <option :value="null" disabled>Select a stage...</option>
                        <option v-for="ts in targetSets" :key="ts.id" :value="ts.id">
                            {{ ts.display_name || ts.label }}
                        </option>
                    </select>

                    <div v-if="correctionStageId && correctionShooters.length === 0" class="rounded-lg border border-slate-700 bg-slate-900/50 p-4 text-center text-sm text-slate-500">
                        No completed scores for this stage yet.
                    </div>

                    <div v-if="correctionStageId && correctionShooters.length > 0" class="space-y-2">
                        <div
                            v-for="shooter in correctionShooters"
                            :key="shooter.id"
                            class="flex items-center justify-between rounded-lg border border-slate-700 bg-slate-900/60 p-3"
                        >
                            <div class="min-w-0 flex-1 mr-3">
                                <span class="text-sm font-bold block truncate">{{ shooter.name }}</span>
                                <span class="text-xs text-green-400">{{ shooter.hits }}/{{ shooter.total }} hits</span>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="openReassignModal(shooter, correctionStageId)" class="rounded bg-amber-600/20 px-3 py-1.5 text-xs font-bold text-amber-400 hover:bg-amber-600/30">
                                    Reassign
                                </button>
                                <button @click="openMoveModal(shooter, correctionStageId)" class="rounded bg-blue-600/20 px-3 py-1.5 text-xs font-bold text-blue-400 hover:bg-blue-600/30">
                                    Move
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SCREEN: Squad Select (first step — PRS is squad-first) -->
        <div v-else-if="prsStore.currentScreen === 'squad-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-2xl">
                <h2 class="mb-2 text-xl font-bold">Select Squad</h2>
                <p v-if="matchStore.hasSquadLock" class="mb-4 flex items-center gap-2 text-sm text-amber-400">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    This device is locked to <span class="font-bold">{{ matchStore.lockedSquadName }}</span>. Other squads are hidden.
                </p>
                <p v-else class="mb-4 text-sm text-slate-400">Choose the squad you are scoring</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="squad in selectableSquads"
                        :key="squad.id"
                        @click="selectSquad(squad)"
                        class="flex flex-col gap-1 rounded-xl border border-slate-700 bg-slate-800 p-5 text-left transition-all hover:border-amber-600 hover:bg-slate-700/80 active:scale-[0.98]"
                    >
                        <span class="text-lg font-bold">{{ squad.name }}</span>
                        <span class="text-sm text-slate-400">{{ squad.shooters.length }} shooters</span>
                        <div class="mt-1 text-xs text-slate-500">{{ squadOverallProgress(squad.id) }}</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Stage Select (second step — after squad is chosen) -->
        <div v-else-if="prsStore.currentScreen === 'stage-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-2xl">
                <h2 class="mb-2 text-xl font-bold">Select Stage</h2>
                <p v-if="selectedSquadObj" class="mb-4 text-sm text-slate-400">{{ selectedSquadObj.name }}</p>
                <div class="space-y-3">
                    <button
                        v-for="ts in targetSets"
                        :key="ts.id"
                        @click="selectStage(ts)"
                        class="flex w-full items-center justify-between rounded-xl border bg-slate-800 p-5 text-left transition-all active:scale-[0.98]"
                        :class="{
                            'border-green-500/60 hover:border-green-400': stageStatus(ts.id) === 'complete',
                            'border-amber-500/60 hover:border-amber-400': stageStatus(ts.id) === 'partial',
                            'border-slate-700 hover:border-amber-600': stageStatus(ts.id) === 'none',
                        }"
                    >
                        <div>
                            <span class="text-lg font-bold">{{ ts.display_name || ts.label }}</span>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="text-sm text-slate-400">{{ ts.total_shots || ts.gongs?.length || '?' }} shots</span>
                                <span v-if="ts.is_timed_stage" class="rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">Timed</span>
                                <span v-if="ts.is_tiebreaker" class="rounded bg-orange-600 px-1.5 py-0.5 text-[10px] font-bold uppercase">Tiebreaker</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm font-medium"
                                :class="{
                                    'text-green-400': stageStatus(ts.id) === 'complete',
                                    'text-amber-400': stageStatus(ts.id) === 'partial',
                                    'text-slate-500': stageStatus(ts.id) === 'none',
                                }"
                            >{{ stageSquadProgress(ts.id).text }}</span>
                            <svg v-if="stageStatus(ts.id) === 'complete'" class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Shooter List -->
        <div v-else-if="prsStore.currentScreen === 'shooter-list'" class="flex flex-1 flex-col px-4 py-4">
            <div class="mx-auto w-full max-w-2xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">{{ selectedSquadObj?.name }}</h2>
                        <p class="text-sm text-slate-400">{{ selectedStageObj?.display_name || selectedStageObj?.label }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="deviceLockMode !== 'locked_to_stage'" @click="prsStore.navigateTo('stage-select'); savePrsProgress()" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-medium hover:bg-slate-800">Change Stage</button>
                        <button v-if="deviceLockMode === 'open'" @click="prsStore.navigateTo('squad-select'); savePrsProgress()" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-medium hover:bg-slate-800">Change Squad</button>
                    </div>
                </div>

                <!-- All shooters completed banner -->
                <div v-if="allShootersDoneAtStage" class="mb-4 rounded-xl border border-green-700/50 bg-green-900/20 p-4 text-center">
                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <p class="font-bold text-green-400">All shooters scored at this stage!</p>
                    <button
                        v-if="deviceLockMode !== 'locked_to_stage'"
                        @click="prsStore.navigateTo('stage-select'); savePrsProgress()"
                        class="mt-3 w-full rounded-xl bg-red-600 py-3 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Choose Next Stage
                    </button>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="shooter in currentShooters"
                        :key="shooter.id"
                        @click="openScoring(shooter)"
                        class="flex w-full flex-col gap-2 rounded-xl border p-4 text-left transition-all active:scale-[0.98]"
                        :class="getShooterCompletion(shooter.id)
                            ? 'border-green-700/40 bg-green-900/10 hover:border-green-500'
                            : 'border-slate-700 bg-slate-800 hover:border-amber-600'"
                    >
                        <div class="flex w-full items-center justify-between">
                            <div class="min-w-0 flex-1 mr-3">
                                <span class="text-base font-bold block truncate">{{ shooter.name }}</span>
                                <span v-if="shooter.bib_number" class="text-xs text-slate-500">#{{ shooter.bib_number }}</span>
                                <span v-if="getShooterCompletion(shooter.id)" class="mt-0.5 block text-[11px] text-slate-400">
                                    Tap for reshoot / reassign / move
                                </span>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span v-if="getShooterCompletion(shooter.id)" class="text-sm font-bold text-green-400 whitespace-nowrap">
                                    {{ getShooterCompletion(shooter.id).hits }}/{{ selectedStageObj?.total_shots || selectedStageObj?.gongs?.length }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase whitespace-nowrap"
                                    :class="getStatusClass(shooter.id)"
                                >
                                    {{ getStatusLabel(shooter.id) }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SCREEN: Scoring -->
        <template v-else-if="prsStore.currentScreen === 'scoring'">
            <div class="flex flex-1 flex-col">
                <!-- Scoring Header -->
                <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
                    <div class="mx-auto max-w-2xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-2xl font-bold">{{ selectedShooterObj?.name }}</p>
                                <p class="text-sm text-slate-400">
                                    {{ matchStore.currentMatch?.name }}
                                    &bull; {{ selectedStageObj?.display_name || selectedStageObj?.label }}
                                    &bull; {{ selectedSquadObj?.name }}
                                    &bull; {{ prsStore.shots.length }} Shots
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-if="selectedStageObj?.is_timed_stage" class="rounded bg-red-600 px-2 py-1 text-[10px] font-bold uppercase">Timed</span>
                                <span v-if="selectedStageObj?.is_tiebreaker" class="rounded bg-orange-600 px-2 py-1 text-[10px] font-bold uppercase">Tiebreaker</span>
                                <span v-if="deviceLockMode !== 'open'" class="rounded bg-slate-600 px-2 py-1 text-[10px] font-bold uppercase">Locked</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scoring Body -->
                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <div class="mx-auto max-w-2xl space-y-4">
                        <!-- Score Summary -->
                        <div class="flex items-center justify-center gap-6 text-base">
                            <span class="font-bold text-green-400">{{ prsStore.hits }} <span class="font-normal text-sm">hits</span></span>
                            <span class="font-bold text-red-400">{{ prsStore.misses }} <span class="font-normal text-sm">misses</span></span>
                            <span class="font-bold text-slate-500">{{ prsStore.notTaken }} <span class="font-normal text-sm">not taken</span></span>
                        </div>

                        <!-- Shot Table -->
                        <div class="rounded-xl border border-slate-700 bg-slate-800">
                            <div class="max-h-[50vh] overflow-y-auto">
                                <div
                                    v-for="(shot, idx) in prsStore.shots"
                                    :key="shot.shot_number"
                                    @click="prsStore.goToShot(idx)"
                                    class="flex items-center border-b border-slate-700/50 px-4 py-2.5 transition-colors cursor-pointer"
                                    :class="idx === prsStore.currentShotIndex ? 'bg-amber-600/10 border-l-4 border-l-amber-500' : 'border-l-4 border-l-transparent hover:bg-slate-700/30'"
                                >
                                    <span class="w-16 text-sm font-bold text-slate-400">Shot {{ shot.shot_number }}</span>
                                    <span
                                        class="ml-auto rounded px-3 py-1 text-sm font-bold uppercase"
                                        :class="{
                                            'bg-green-600/20 text-green-400': shot.result === 'hit',
                                            'bg-red-600/20 text-red-400': shot.result === 'miss',
                                            'bg-slate-700/50 text-slate-500': shot.result === 'not_taken',
                                        }"
                                    >
                                        {{ shot.result === 'not_taken' ? 'Not Taken' : shot.result }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Target Info (collapsible, read-only) -->
                        <details v-if="selectedStageObj?.stage_targets?.length" class="rounded-xl border border-slate-700 bg-slate-800">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-400">Target Info</summary>
                            <div class="border-t border-slate-700 px-4 py-2">
                                <div v-for="t in selectedStageObj.stage_targets" :key="t.id" class="flex items-center justify-between py-1.5 text-sm">
                                    <span class="text-white">{{ t.target_name || `Target ${t.sequence_number}` }}</span>
                                    <span class="text-slate-400">
                                        <template v-if="t.distance_meters">{{ t.distance_meters }}m</template>
                                        <template v-if="t.target_size_mm"> &bull; {{ t.target_size_mm }}mm</template>
                                        <template v-if="t.target_size_mrad"> ({{ t.target_size_mrad }} mrad)</template>
                                    </span>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>

                <!-- Fixed Bottom Actions -->
                <div class="border-t border-slate-700 bg-slate-800 px-4 py-3">
                    <div class="mx-auto max-w-2xl space-y-3">
                        <!-- Hit / Miss — primary action, top of fixed area for speed -->
                        <div class="grid grid-cols-2 gap-3">
                            <button @click="recordHit" class="rounded-2xl bg-green-600 py-5 text-xl font-bold text-white transition-all hover:bg-green-700 active:scale-[0.97]">
                                HIT
                            </button>
                            <button @click="recordMiss" class="rounded-2xl bg-red-600 py-5 text-xl font-bold text-white transition-all hover:bg-red-700 active:scale-[0.97]">
                                MISS
                            </button>
                        </div>
                        <!-- Undo -->
                        <button @click="undoShot" class="w-full rounded-xl border border-slate-600 py-3 text-sm font-bold text-slate-300 transition-colors hover:bg-slate-700 active:scale-[0.98]">
                            Undo Last Shot
                        </button>
                        <!-- Timer display (when app timer is active) -->
                        <div v-if="prsStore.timerMode === 'app'" class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                            <div class="flex items-center gap-3">
                                <p class="flex-1 text-center font-mono text-3xl font-bold tracking-wider" :class="prsStore.isTimerRunning ? 'text-amber-400' : 'text-white'">
                                    {{ formattedTime }}
                                </p>
                                <div class="flex gap-1.5">
                                    <button @click="startTimer" :disabled="prsStore.isTimerRunning" class="rounded-lg px-3 py-2 text-xs font-bold transition-colors" :class="prsStore.isTimerRunning ? 'bg-slate-700 text-slate-500' : 'bg-green-600 text-white hover:bg-green-700'">Start</button>
                                    <button @click="stopTimer" :disabled="!prsStore.isTimerRunning" class="rounded-lg px-3 py-2 text-xs font-bold transition-colors" :class="!prsStore.isTimerRunning ? 'bg-slate-700 text-slate-500' : 'bg-red-600 text-white hover:bg-red-700'">Stop</button>
                                    <button @click="resetTimer" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600">Reset</button>
                                </div>
                            </div>
                        </div>
                        <!-- Time preview when already entered manually -->
                        <div v-else-if="prsStore.rawTimeSeconds > 0" class="flex items-center justify-center gap-2 rounded-xl border border-slate-700 bg-slate-900 px-3 py-2">
                            <span class="text-xs text-slate-400">Time:</span>
                            <span class="font-mono text-lg font-bold text-white">{{ prsStore.rawTimeSeconds.toFixed(2) }}s</span>
                            <button @click="clearTimeInput" class="ml-2 rounded bg-slate-700 px-2 py-1 text-[10px] font-bold text-slate-400 hover:bg-slate-600">Clear</button>
                        </div>

                        <!-- Complete Stage -->
                        <button @click="handleCompleteStage" class="w-full rounded-2xl bg-red-600 py-4 text-lg font-bold text-white transition-all hover:bg-red-700 active:scale-[0.98]">
                            COMPLETE STAGE
                        </button>
                        <p v-if="completeError" class="text-center text-sm text-red-400">{{ completeError }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- PIN Modal -->
        <Teleport to="body">
            <div v-if="showPinModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="closePinModal">
                <div class="w-full max-w-sm rounded-2xl bg-slate-800 p-6 shadow-2xl">
                    <h3 class="mb-4 text-lg font-bold text-white">Enter Corrections PIN</h3>
                    <input
                        v-model="pinInput"
                        type="password"
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="4-6 digit PIN"
                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 text-center text-xl font-mono text-white placeholder-slate-600 tracking-widest focus:border-amber-500 focus:outline-none"
                        @keydown.enter="submitPin"
                    />
                    <p v-if="pinError" class="mt-2 text-center text-sm text-red-400">{{ pinError }}</p>
                    <div class="mt-4 flex gap-3">
                        <button @click="closePinModal" class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700">Cancel</button>
                        <button @click="submitPin" class="flex-1 rounded-lg bg-amber-600 py-2.5 text-sm font-bold text-white hover:bg-amber-700">Submit</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Time Entry Modal -->
        <Teleport to="body">
            <div v-if="showTimeModal" class="fixed inset-0 z-50 flex flex-col bg-black/70 px-4 pt-8 pb-4 overflow-y-auto">
                <div class="w-full max-w-sm mx-auto rounded-2xl bg-slate-800 p-5 shadow-2xl flex-shrink-0">
                    <h3 class="mb-1 text-lg font-bold text-white">Enter Stage Time</h3>
                    <p class="mb-3 text-sm text-slate-400">Time is required for this stage.</p>

                    <input
                        ref="modalTimeInput"
                        type="text"
                        inputmode="decimal"
                        :value="modalTimeDigits"
                        @input="onModalTimeInput"
                        @keydown="onDigitKeydown"
                        placeholder="e.g. 105.00"
                        class="w-full rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 text-center font-mono text-2xl text-white placeholder-slate-600 tracking-widest focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    />

                    <div v-if="modalTimeSeconds > 0" class="mt-2 text-center">
                        <p class="font-mono text-3xl font-bold text-amber-400">{{ modalTimeSeconds.toFixed(2) }}s</p>
                    </div>

                    <div v-if="modalTimeLowWarning" class="mt-2 rounded-lg border border-amber-500/50 bg-amber-900/30 px-3 py-2 text-center">
                        <p class="text-sm font-medium text-amber-400">Time is under 10 seconds. Are you sure?</p>
                    </div>

                    <div class="mt-3 flex gap-3">
                        <button @click="showTimeModal = false" class="flex-1 rounded-lg border border-slate-600 py-3 text-sm font-medium text-slate-300 hover:bg-slate-700">
                            Cancel
                        </button>
                        <button
                            @click="confirmTimeAndComplete"
                            :disabled="modalTimeSeconds <= 0"
                            class="flex-1 rounded-lg py-3 text-sm font-bold text-white transition-colors disabled:opacity-50"
                            :class="modalTimeLowWarning ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700'"
                        >
                            {{ modalTimeLowWarning ? 'Yes, Confirm' : 'Confirm' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Reassign Modal -->
        <Teleport to="body">
            <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="showReassignModal = false">
                <div class="w-full max-w-sm rounded-2xl bg-slate-800 p-6 shadow-2xl">
                    <h3 class="mb-2 text-lg font-bold text-white">Reassign Score</h3>
                    <p class="mb-4 text-sm text-slate-400">Transfer {{ reassignShooter?.name }}'s score at {{ activeCorrectStageName }} to another shooter.</p>
                    <select v-model="reassignTargetId" class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none">
                        <option :value="null" disabled>Select target shooter</option>
                        <option v-for="s in availableReassignTargets" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div class="mt-4 flex gap-3">
                        <button @click="showReassignModal = false" class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700">Cancel</button>
                        <button @click="executeReassign" :disabled="!reassignTargetId" class="flex-1 rounded-lg bg-amber-600 py-2.5 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-50">Reassign</button>
                    </div>
                    <p v-if="correctionError" class="mt-2 text-center text-sm text-red-400">{{ correctionError }}</p>
                </div>
            </div>
        </Teleport>

        <!-- Move Stage Modal -->
        <Teleport to="body">
            <div v-if="showMoveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="showMoveModal = false">
                <div class="w-full max-w-sm rounded-2xl bg-slate-800 p-6 shadow-2xl">
                    <h3 class="mb-2 text-lg font-bold text-white">Move to Different Stage</h3>
                    <p class="mb-4 text-sm text-slate-400">Move {{ moveShooter?.name }}'s score from {{ activeCorrectStageName }} to another stage.</p>
                    <select v-model="moveTargetStageId" class="w-full rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none">
                        <option :value="null" disabled>Select target stage</option>
                        <option v-for="ts in availableMoveTargets" :key="ts.id" :value="ts.id">{{ ts.display_name || ts.label }}</option>
                    </select>
                    <div class="mt-4 flex gap-3">
                        <button @click="showMoveModal = false" class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700">Cancel</button>
                        <button @click="executeMove" :disabled="!moveTargetStageId" class="flex-1 rounded-lg bg-blue-600 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Move</button>
                    </div>
                    <p v-if="correctionError" class="mt-2 text-center text-sm text-red-400">{{ correctionError }}</p>
                </div>
            </div>
        </Teleport>

        <!-- Already-Scored Shooter Action Panel: blocks accidental over-scoring.
             Tapping a completed shooter on the shooter-list opens this panel
             so the MD must pick an explicit corrective action. -->
        <Teleport to="body">
            <div v-if="showShooterActionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="closeShooterActionModal">
                <div class="w-full max-w-sm rounded-2xl bg-slate-800 p-6 shadow-2xl">
                    <div class="mb-4 flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/20">
                            <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-white truncate">{{ shooterActionTarget?.shooter?.name }}</h3>
                            <p class="text-xs text-slate-400">Already scored at {{ shooterActionTarget?.stageName }}</p>
                            <p v-if="shooterActionTarget?.completion" class="mt-1 text-sm font-bold text-green-400">
                                {{ shooterActionTarget.completion.hits }}/{{ shooterActionTarget.totalShots }} hits
                                <span v-if="shooterActionTarget.completion.time" class="ml-2 font-normal text-slate-400">
                                    &middot; {{ Number(shooterActionTarget.completion.time).toFixed(2) }}s
                                </span>
                            </p>
                        </div>
                    </div>
                    <p class="mb-4 text-sm text-slate-300">
                        This shooter already has a score. Direct re-scoring is blocked — choose an action below. All actions are logged.
                    </p>
                    <div class="space-y-2">
                        <button
                            @click="startReshootForShooter"
                            class="flex w-full items-center gap-3 rounded-xl border border-red-700/40 bg-red-900/20 px-4 py-3 text-left hover:border-red-500 hover:bg-red-900/40"
                        >
                            <svg class="h-5 w-5 flex-shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-white">Reshoot</div>
                                <div class="text-xs text-slate-400">Same shooter, same stage — previous score is replaced.</div>
                            </div>
                        </button>
                        <button
                            @click="reassignFromActionModal"
                            class="flex w-full items-center gap-3 rounded-xl border border-amber-700/40 bg-amber-900/10 px-4 py-3 text-left hover:border-amber-500 hover:bg-amber-900/30"
                        >
                            <svg class="h-5 w-5 flex-shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-white">Reassign to another shooter</div>
                                <div class="text-xs text-slate-400">Score was recorded against the wrong shooter.</div>
                            </div>
                        </button>
                        <button
                            @click="moveFromActionModal"
                            class="flex w-full items-center gap-3 rounded-xl border border-blue-700/40 bg-blue-900/10 px-4 py-3 text-left hover:border-blue-500 hover:bg-blue-900/30"
                        >
                            <svg class="h-5 w-5 flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" /></svg>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-white">Move to another stage</div>
                                <div class="text-xs text-slate-400">Score was recorded against the wrong stage.</div>
                            </div>
                        </button>
                    </div>
                    <div class="mt-4 flex">
                        <button @click="closeShooterActionModal" class="flex-1 rounded-lg border border-slate-600 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700">Cancel</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Corrections Log Modal -->
        <Teleport to="body">
            <div v-if="showCorrectionLogs" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="showCorrectionLogs = false">
                <div class="w-full max-w-lg max-h-[80vh] overflow-y-auto rounded-2xl bg-slate-800 p-6 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-white">Corrections Log</h3>
                        <button @click="showCorrectionLogs = false" class="text-slate-400 hover:text-white">&times;</button>
                    </div>
                    <div v-if="correctionLogEntries.length === 0" class="text-center py-8 text-slate-400">
                        No corrections have been made.
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="log in correctionLogEntries" :key="log.id" class="rounded-lg border border-slate-700 bg-slate-900 p-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="log.action === 'reassign' ? 'bg-amber-600/20 text-amber-400' : 'bg-blue-600/20 text-blue-400'">{{ log.action }}</span>
                                <span class="text-xs text-slate-500">{{ log.performed_at }}</span>
                            </div>
                            <p class="text-sm text-slate-300">{{ log.details }}</p>
                            <p v-if="log.device_id" class="text-[10px] text-slate-600 mt-1">Device: {{ log.device_id }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <ScoringSponsorship />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useMatchStore } from '../stores/matchStore';
import { usePrsScoringStore } from '../stores/prsScoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';
import ScoringSponsorship from '../components/ScoringSponsorship.vue';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();

const matchStore = useMatchStore();
const prsStore = usePrsScoringStore();
const ready = ref(false);
const completeError = ref('');
const timeInput = ref(null);

const showTimeModal = ref(false);
const modalTimeDigits = ref('');
const modalTimeSeconds = ref(0);
const modalTimeLowWarning = ref(false);
const modalTimeInput = ref(null);

let timerInterval = null;
let syncInterval = null;

// Corrections management
const showCorrections = ref(false);
const correctionStageId = ref(null);
const activeCorrectStageId = ref(null);
const showPinModal = ref(false);
const pinInput = ref('');
const pinError = ref('');
const showReassignModal = ref(false);
const showMoveModal = ref(false);
const showCorrectionLogs = ref(false);
const reassignShooter = ref(null);
const moveShooter = ref(null);
const reassignTargetId = ref(null);
const moveTargetStageId = ref(null);
const correctionError = ref('');
const correctionLogEntries = ref([]);
let pendingAction = null;
let pendingReshootExecutor = null;

// Already-scored shooter action panel
const showShooterActionModal = ref(false);
const shooterActionTarget = ref(null); // { shooter, stageId, stageName, completion, totalShots }

const squads = computed(() => matchStore.currentMatch?.squads ?? []);
const targetSets = computed(() => matchStore.currentMatch?.target_sets ?? []);
const deviceLockMode = computed(() => matchStore.currentMatch?.device_lock_mode ?? 'open');

// Only the locked squad is selectable when a squad lock is active on this
// device. Other squads are hidden entirely so the scorer can't accidentally
// pick a squad they aren't running.
const selectableSquads = computed(() => {
    if (matchStore.lockedSquadId) {
        return squads.value.filter(s => s.id === matchStore.lockedSquadId);
    }
    return squads.value;
});

const lockedStageName = computed(() => {
    const lock = readStageLock(props.matchId);
    if (!lock) return '';
    const ts = targetSets.value.find(s => s.id === lock.stageId);
    return ts?.display_name || ts?.label || `Stage ${lock.stageId}`;
});

const lockedSquadName = computed(() => {
    if (matchStore.lockedSquadName) return matchStore.lockedSquadName;
    return '';
});

const selectedSquadObj = computed(() => squads.value.find(s => s.id === prsStore.selectedSquadId));
const selectedStageObj = computed(() => targetSets.value.find(s => s.id === prsStore.selectedStageId));
const selectedShooterObj = computed(() => {
    if (!selectedSquadObj.value) return null;
    return selectedSquadObj.value.shooters.find(s => s.id === prsStore.selectedShooterId);
});

const currentShooters = computed(() => {
    if (!selectedSquadObj.value) return [];
    return selectedSquadObj.value.shooters.filter(s => s.status === 'active');
});

const correctionsPin = computed(() => matchStore.currentMatch?.corrections_pin ?? null);

const allActiveShooters = computed(() => {
    return squads.value.flatMap(sq => sq.shooters?.filter(s => s.status === 'active') ?? []);
});

const correctionStageObj = computed(() => targetSets.value.find(ts => ts.id === correctionStageId.value));

const correctionShooters = computed(() => {
    if (!correctionStageId.value) return [];
    const stageId = correctionStageId.value;
    const stageGongs = correctionStageObj.value?.total_shots || correctionStageObj.value?.gongs?.length || 0;
    return allActiveShooters.value
        .filter(s => prsStore.stageCompletions.has(`${s.id}-${stageId}`))
        .map(s => {
            const comp = prsStore.stageCompletions.get(`${s.id}-${stageId}`);
            return { ...s, hits: comp?.hits ?? 0, total: stageGongs };
        });
});

const availableReassignTargets = computed(() => {
    if (!reassignShooter.value) return [];
    return allActiveShooters.value.filter(s => s.id !== reassignShooter.value.id);
});

const availableMoveTargets = computed(() => {
    return targetSets.value.filter(ts => ts.id !== activeCorrectStageId.value);
});

const activeCorrectStageName = computed(() => {
    const ts = targetSets.value.find(s => s.id === activeCorrectStageId.value);
    return ts?.display_name || ts?.label || 'Stage';
});

const stageRequiresTime = computed(() => {
    return selectedStageObj.value?.is_timed_stage || selectedStageObj.value?.is_tiebreaker;
});

const allShootersDoneAtStage = computed(() => {
    if (!selectedSquadObj.value || !prsStore.selectedStageId) return false;
    const active = currentShooters.value;
    if (active.length === 0) return false;
    return active.every(s => prsStore.stageCompletions.has(`${s.id}-${prsStore.selectedStageId}`));
});

const PRS_STATE_KEY = 'dc_prs_state';

function savePrsProgress() {
    try {
        const state = {
            matchId: props.matchId,
            screen: prsStore.currentScreen,
            stageId: prsStore.selectedStageId,
            squadId: prsStore.selectedSquadId,
        };
        localStorage.setItem(PRS_STATE_KEY, JSON.stringify(state));
    } catch { /* ignore */ }
}

function restorePrsProgress() {
    try {
        const raw = localStorage.getItem(PRS_STATE_KEY);
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (state.matchId !== props.matchId) return false;
        const validScreens = ['squad-select', 'stage-select', 'shooter-list'];
        if (!validScreens.includes(state.screen)) return false;
        if (state.squadId) prsStore.selectSquad(state.squadId);
        if (state.stageId) prsStore.selectStage(state.stageId);
        prsStore.navigateTo(state.screen);
        return true;
    } catch { return false; }
}

function clearPrsProgress() {
    try { localStorage.removeItem(PRS_STATE_KEY); } catch { /* ignore */ }
}

function squadOverallProgress(squadId) {
    const squad = squads.value.find(s => s.id === squadId);
    if (!squad) return '';
    const active = (squad.shooters ?? []).filter(s => s.status === 'active');
    if (active.length === 0) return '';
    let totalCompleted = 0;
    const totalPossible = active.length * targetSets.value.length;
    for (const ts of targetSets.value) {
        totalCompleted += active.filter(s => prsStore.stageCompletions.has(`${s.id}-${ts.id}`)).length;
    }
    return `${totalCompleted}/${totalPossible} scored`;
}

function readStageLock(matchId) {
    try {
        const raw = localStorage.getItem('dc_locked_stage');
        if (!raw) return null;
        const lock = JSON.parse(raw);
        return lock.matchId === matchId ? lock : null;
    } catch { return null; }
}

// PRS flow: Squad first → Stage → Shooter → Score
function startScoring() {
    const lock = deviceLockMode.value;
    if (lock === 'locked_to_squad' && matchStore.lockedSquadId) {
        prsStore.selectSquad(matchStore.lockedSquadId);
        const stageLock = readStageLock(props.matchId);
        if (stageLock) {
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('shooter-list');
        } else {
            prsStore.navigateTo('stage-select');
        }
    } else if (lock === 'locked_to_stage') {
        const stageLock = readStageLock(props.matchId);
        if (stageLock && matchStore.lockedSquadId) {
            prsStore.selectSquad(matchStore.lockedSquadId);
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('shooter-list');
        } else if (stageLock) {
            prsStore.selectStage(stageLock.stageId);
            prsStore.navigateTo('squad-select');
        } else {
            prsStore.navigateTo('squad-select');
        }
    } else {
        prsStore.navigateTo('squad-select');
    }
    savePrsProgress();
}

function selectSquad(squad) {
    if (matchStore.lockedSquadId && squad.id !== matchStore.lockedSquadId) {
        return;
    }
    prsStore.selectSquad(squad.id);
    const stageLock = readStageLock(props.matchId);
    if (deviceLockMode.value === 'locked_to_stage' && stageLock) {
        prsStore.selectStage(stageLock.stageId);
        prsStore.navigateTo('shooter-list');
    } else {
        prsStore.navigateTo('stage-select');
    }
    savePrsProgress();
}

function selectStage(ts) {
    prsStore.selectStage(ts.id);
    prsStore.navigateTo('shooter-list');
    savePrsProgress();
}

function openScoring(shooter) {
    // Block direct re-scoring of a shooter who already has a completed stage
    // result. Force the MD to pick an explicit corrective action (reshoot,
    // reassign, or move) — every path is gated by the corrections PIN and
    // leaves an audit trail.
    const existing = getShooterCompletion(shooter.id);
    if (existing && !shooterActionInProgress(shooter.id)) {
        shooterActionTarget.value = {
            shooter,
            stageId: prsStore.selectedStageId,
            stageName: selectedStageObj.value?.display_name || selectedStageObj.value?.label || 'Stage',
            completion: existing,
            totalShots: selectedStageObj.value?.total_shots || selectedStageObj.value?.gongs?.length || 0,
        };
        showShooterActionModal.value = true;
        return;
    }
    startFreshScoring(shooter);
}

/** Track in-flight reshoot for a shooter so we don't re-trigger the action
 *  panel after PIN verification has already cleared it. */
const reshootInProgressFor = ref(new Set());
function shooterActionInProgress(shooterId) {
    return reshootInProgressFor.value.has(shooterId);
}

function startFreshScoring(shooter) {
    prsStore.selectShooter(shooter.id);
    const totalShots = selectedStageObj.value?.total_shots || selectedStageObj.value?.gongs?.length || 10;
    prsStore.initStage(totalShots);
    prsStore.resetTimer();
    completeError.value = '';
    showTimeModal.value = false;
    prsStore.navigateTo('scoring');
}

function closeShooterActionModal() {
    showShooterActionModal.value = false;
    shooterActionTarget.value = null;
}

/** Reshoot this shooter at this stage. Gated by corrections PIN (if one is
 *  configured). Clears the local completion, logs a `reshoot` correction,
 *  and drops the user straight into the scoring screen. The subsequent
 *  completeStage call overwrites the existing PrsStageResult row server-side
 *  (PrsScoreController uses updateOrCreate). */
function startReshootForShooter() {
    const target = shooterActionTarget.value;
    if (!target) return;

    const runReshoot = async () => {
        const { shooter, stageId } = target;
        try {
            if (correctionsPin.value) {
                await axios.post(`/api/matches/${props.matchId}/correction-logs`, {
                    logs: [{
                        action: 'reshoot',
                        stage_id: stageId,
                        shooter_id: shooter.id,
                        details: {
                            previous_hits: target.completion?.hits ?? null,
                            previous_time: target.completion?.time ?? null,
                            initiated_from: 'scoring-app',
                        },
                        performed_at: new Date().toISOString(),
                    }],
                });
            }
        } catch {
            // Log failure shouldn't block the reshoot — MD is right there.
        }

        prsStore.stageCompletions.delete(`${shooter.id}-${stageId}`);
        reshootInProgressFor.value.add(shooter.id);
        closeShooterActionModal();
        startFreshScoring(shooter);
        setTimeout(() => reshootInProgressFor.value.delete(shooter.id), 500);
    };

    if (correctionsPin.value) {
        pendingAction = 'reshoot';
        pendingReshootExecutor = runReshoot;
        pinInput.value = '';
        pinError.value = '';
        showPinModal.value = true;
    } else {
        runReshoot();
    }
}

function reassignFromActionModal() {
    const target = shooterActionTarget.value;
    if (!target) return;
    closeShooterActionModal();
    openReassignModal(target.shooter, target.stageId);
}

function moveFromActionModal() {
    const target = shooterActionTarget.value;
    if (!target) return;
    closeShooterActionModal();
    openMoveModal(target.shooter, target.stageId);
}

function goBack() {
    const s = prsStore.currentScreen;
    if (s === 'scoring') prsStore.navigateTo('shooter-list');
    else if (s === 'shooter-list') prsStore.navigateTo('stage-select');
    else if (s === 'stage-select') prsStore.navigateTo('squad-select');
    else if (s === 'squad-select') prsStore.navigateTo('match-home');
    else if (s === 'match-home') {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
        return;
    }
    savePrsProgress();
}

function recordHit() { prsStore.recordShot('hit'); }
function recordMiss() { prsStore.recordShot('miss'); }
function undoShot() { prsStore.undoLastShot(); }

async function handleCompleteStage() {
    completeError.value = '';

    const time = prsStore.effectiveTime;
    if (stageRequiresTime.value && (!time || time <= 0)) {
        modalTimeDigits.value = '';
        modalTimeSeconds.value = 0;
        modalTimeLowWarning.value = false;
        showTimeModal.value = true;
        nextTick(() => modalTimeInput.value?.focus());
        return;
    }

    await doCompleteStage();
}

function onModalTimeInput(e) {
    const cleaned = e.target.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
    modalTimeDigits.value = cleaned;
    e.target.value = cleaned;
    const val = parseFloat(cleaned);
    modalTimeSeconds.value = isNaN(val) ? 0 : val;
    modalTimeLowWarning.value = modalTimeSeconds.value > 0 && modalTimeSeconds.value < 10;
}

function confirmTimeAndComplete() {
    if (modalTimeSeconds.value <= 0) return;
    if (modalTimeSeconds.value < 10 && !modalTimeLowWarning.value) {
        modalTimeLowWarning.value = true;
        return;
    }
    applyModalTimeAndComplete();
}

function applyModalTimeAndComplete() {
    prsStore.rawDigits = modalTimeDigits.value;
    prsStore.rawTimeSeconds = modalTimeSeconds.value;
    prsStore.timerMode = 'manual';
    showTimeModal.value = false;
    doCompleteStage();
}

async function doCompleteStage() {
    completeError.value = '';
    const result = await prsStore.completeStage(
        props.matchId,
        prsStore.selectedStageId,
        prsStore.selectedShooterId,
        prsStore.selectedSquadId,
        selectedStageObj.value,
    );
    if (!result.success) {
        completeError.value = result.error;
        return;
    }
    stopTimerInternal();
    prsStore.navigateTo('shooter-list');
    savePrsProgress();
}

function getShooterCompletion(shooterId) {
    return prsStore.stageCompletions.get(`${shooterId}-${prsStore.selectedStageId}`) ?? null;
}

function getStatusLabel(shooterId) {
    const c = getShooterCompletion(shooterId);
    return c ? 'Completed' : 'Not Started';
}

function getStatusClass(shooterId) {
    const c = getShooterCompletion(shooterId);
    return c ? 'bg-green-600/20 text-green-400' : 'bg-slate-700/50 text-slate-500';
}

function stageSquadProgress(stageId) {
    if (!prsStore.selectedSquadId) return { completed: 0, total: 0, text: '' };
    const squad = squads.value.find(s => s.id === prsStore.selectedSquadId);
    if (!squad) return { completed: 0, total: 0, text: '' };
    const active = squad.shooters.filter(s => s.status === 'active');
    const completed = active.filter(s => prsStore.stageCompletions.has(`${s.id}-${stageId}`));
    return { completed: completed.length, total: active.length, text: `${completed.length}/${active.length} scored` };
}

function stageStatus(stageId) {
    const p = stageSquadProgress(stageId);
    if (p.total === 0) return 'none';
    if (p.completed >= p.total) return 'complete';
    if (p.completed > 0) return 'partial';
    return 'none';
}

// Timer
function startTimer() {
    if (prsStore.isTimerRunning) return;
    prsStore.isTimerRunning = true;
    prsStore.rawTimeSeconds = null;
    const startTime = performance.now() - prsStore.timerElapsed * 1000;
    timerInterval = setInterval(() => {
        prsStore.timerElapsed = (performance.now() - startTime) / 1000;
    }, 10);
}

function stopTimer() {
    if (!prsStore.isTimerRunning) return;
    stopTimerInternal();
    prsStore.rawTimeSeconds = parseFloat(prsStore.timerElapsed.toFixed(2));
}

function stopTimerInternal() {
    prsStore.isTimerRunning = false;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function resetTimer() {
    stopTimerInternal();
    prsStore.resetTimer();
}

function formatTime(seconds) {
    if (seconds == null || seconds === 0) return '00:00.00';
    const t = parseFloat(seconds);
    const mins = Math.floor(t / 60);
    const secs = t % 60;
    return `${String(mins).padStart(2, '0')}:${secs.toFixed(2).padStart(5, '0')}`;
}

const formattedTime = computed(() => formatTime(prsStore.effectiveTime));

function onDigitKeydown(e) {
    const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
    if (allowed.includes(e.key)) return;
    if (!/^[\d.]$/.test(e.key)) e.preventDefault();
}

function clearTimeInput() {
    prsStore.rawDigits = '';
    prsStore.rawTimeSeconds = null;
}

function openReassignModal(shooter, stageId) {
    reassignShooter.value = shooter;
    activeCorrectStageId.value = stageId || correctionStageId.value;
    reassignTargetId.value = null;
    correctionError.value = '';
    if (correctionsPin.value) {
        pendingAction = 'reassign';
        pinInput.value = '';
        pinError.value = '';
        showPinModal.value = true;
    } else {
        showReassignModal.value = true;
    }
}

function openMoveModal(shooter, stageId) {
    moveShooter.value = shooter;
    activeCorrectStageId.value = stageId || correctionStageId.value;
    moveTargetStageId.value = null;
    correctionError.value = '';
    if (correctionsPin.value) {
        pendingAction = 'move';
        pinInput.value = '';
        pinError.value = '';
        showPinModal.value = true;
    } else {
        showMoveModal.value = true;
    }
}

function closePinModal() {
    showPinModal.value = false;
    pinInput.value = '';
    pinError.value = '';
    pendingAction = null;
}

function submitPin() {
    if (pinInput.value !== correctionsPin.value) {
        pinError.value = 'Incorrect PIN';
        return;
    }
    showPinModal.value = false;
    if (pendingAction === 'reassign') showReassignModal.value = true;
    else if (pendingAction === 'move') showMoveModal.value = true;
    else if (pendingAction === 'reshoot' && typeof pendingReshootExecutor === 'function') {
        const exec = pendingReshootExecutor;
        pendingReshootExecutor = null;
        exec();
    }
    pendingAction = null;
    pinInput.value = '';
}

async function executeReassign() {
    if (!reassignShooter.value || !reassignTargetId.value || !activeCorrectStageId.value) return;
    correctionError.value = '';
    try {
        const stageId = activeCorrectStageId.value;
        const resp = await axios.post(
            `/api/matches/${props.matchId}/stages/${stageId}/reassign`,
            {
                shooter_id: reassignShooter.value.id,
                new_shooter_id: reassignTargetId.value,
                pin: correctionsPin.value ? pinInput.value : undefined,
            }
        );
        if (resp.data?.success) {
            showReassignModal.value = false;
            prsStore.stageCompletions.delete(`${reassignShooter.value.id}-${stageId}`);
            prsStore.stageCompletions.set(`${reassignTargetId.value}-${stageId}`, resp.data);
        }
    } catch (e) {
        correctionError.value = e.response?.data?.message || 'Reassignment failed';
    }
}

async function executeMove() {
    if (!moveShooter.value || !moveTargetStageId.value || !activeCorrectStageId.value) return;
    correctionError.value = '';
    try {
        const stageId = activeCorrectStageId.value;
        const resp = await axios.post(
            `/api/matches/${props.matchId}/stages/${stageId}/move`,
            {
                shooter_id: moveShooter.value.id,
                new_stage_id: moveTargetStageId.value,
                pin: correctionsPin.value ? pinInput.value : undefined,
            }
        );
        if (resp.data?.success) {
            showMoveModal.value = false;
            prsStore.stageCompletions.delete(`${moveShooter.value.id}-${stageId}`);
        }
    } catch (e) {
        correctionError.value = e.response?.data?.message || 'Move failed';
    }
}

async function fetchCorrectionLogs() {
    try {
        const resp = await axios.get(`/api/matches/${props.matchId}/correction-logs`);
        correctionLogEntries.value = Array.isArray(resp.data) ? resp.data : [];
    } catch { correctionLogEntries.value = []; }
}

watch(showCorrectionLogs, (val) => { if (val) fetchCorrectionLogs(); });

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    const prsResults = matchStore.currentMatch?.prs_stage_results ?? [];
    await prsStore.initForMatch(props.matchId, prsResults);
    ready.value = true;

    if (prsStore.currentScreen === 'match-home') {
        restorePrsProgress();
    }

    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (prsStore.pendingCount > 0) {
            await prsStore.syncPendingResults();
        }
        try {
            await matchStore.fetchMatch(props.matchId);
            const freshResults = matchStore.currentMatch?.prs_stage_results ?? [];
            await prsStore.refreshCompletions(props.matchId, freshResults);
        } catch { /* offline or transient failure */ }
    }, 15000);
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
    if (timerInterval) clearInterval(timerInterval);
});
</script>

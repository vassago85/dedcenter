<template>
    <div class="flex min-h-screen flex-col bg-app text-primary">
        <!-- Header -->
        <header class="border-b border-border bg-surface px-4 py-3">
            <div class="mx-auto flex max-w-lg items-center gap-3">
                <button @click="handleHeaderBack" class="text-muted hover:text-primary">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-sm font-bold">{{ matchStore.currentMatch?.name }}</h1>
                    <div class="flex items-center gap-2 text-[11px] text-muted">
                        <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Team</span>
                        <span v-if="currentStage">{{ currentStage.label }}</span>
                        <span v-if="currentSquad">&bull; {{ currentSquad.name }}</span>
                        <span v-if="currentTeam" class="text-emerald-400">&bull; {{ currentTeam.name }}</span>
                    </div>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <SyncBadge :pending="elrStore.pendingCount" :syncing="elrStore.syncing" @sync="elrStore.syncShots()" />
                    <OnlineIndicator />
                </div>
            </div>
        </header>

        <DeviceLockBanner />

        <!-- Loading -->
        <div v-if="!ready" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-border border-t-accent"></div>
        </div>

        <!-- STEP 1: STAGE SELECT -->
        <div v-else-if="currentView === 'stage-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <p class="text-xs font-medium uppercase tracking-widest text-muted">Step 1</p>
                    <h2 class="text-xl font-bold">Select Stage</h2>
                    <p class="text-sm text-muted">Choose which stage to score</p>
                </div>
                <div class="space-y-3">
                    <button
                        v-for="(stage, idx) in stages"
                        :key="stage.id"
                        @click="selectStage(idx)"
                        class="flex w-full items-center justify-between rounded-xl border border-border bg-surface p-5 text-left transition-all hover:border-emerald-600 active:scale-[0.98]"
                    >
                        <div>
                            <span class="text-lg font-bold">{{ stage.label }}</span>
                            <div class="mt-1 text-sm text-muted">{{ stage.targets?.length ?? 0 }} gongs</div>
                        </div>
                        <div class="text-right text-xs font-bold" :class="stageProgress(stage).allDone ? 'text-green-400' : stageProgress(stage).someDone ? 'text-amber-400' : 'text-muted'">
                            {{ stageProgress(stage).label }}
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- STEP 2: TEAM SELECT -->
        <div v-else-if="currentView === 'team-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <p class="text-xs font-medium uppercase tracking-widest text-muted">Step 2</p>
                    <h2 class="text-xl font-bold">Select Team</h2>
                    <p class="text-sm text-muted">{{ currentStage?.label }} &mdash; choose a team to score</p>
                </div>

                <div v-if="teams.length === 0" class="rounded-xl border border-border bg-surface p-6 text-center text-sm text-muted">
                    No teams configured for this match. Add 2-shooter teams in the match setup.
                </div>

                <div class="space-y-3">
                    <button
                        v-for="team in teams"
                        :key="team.id"
                        @click="selectTeam(team.id)"
                        class="flex w-full items-center gap-3 rounded-xl border p-4 text-left transition-all active:scale-[0.99]"
                        :class="recommendedTeamId === team.id ? 'border-emerald-600 bg-emerald-600/10' : 'border-border bg-surface hover:border-emerald-500/50'"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                             :class="teamDone(team) ? 'bg-green-600/20 text-green-400' : 'bg-surface-2 text-muted'">
                            <svg v-if="teamDone(team)" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            <template v-else>{{ team.sort_order ?? '' }}</template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-sm font-bold">
                                {{ team.name }}
                                <span v-if="teamSquad(team)" class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-muted">{{ teamSquad(team).name }}</span>
                                <span v-if="recommendedTeamId === team.id && !teamDone(team)" class="rounded bg-emerald-600/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-400">Next</span>
                            </p>
                            <p class="text-xs text-muted">
                                <span v-if="team.division_category">{{ team.division_category }} &bull; </span>
                                {{ activeMembers(team).map(s => s.name).join(' & ') }}
                            </p>
                        </div>
                        <span class="text-xs font-bold" :class="teamDone(team) ? 'text-green-400' : teamTimedOut(team) ? 'text-red-400' : 'text-muted'">
                            {{ teamDone(team) ? (teamTimedOut(team) ? 'Timed out' : 'Done') : 'Pending' }}
                        </span>
                    </button>
                </div>

                <button @click="goToStageSelect" class="block w-full rounded-xl border border-border py-3 text-center text-sm font-semibold text-muted transition-colors hover:bg-surface hover:text-primary">
                    Change Stage
                </button>
            </div>
        </div>

        <!-- STEP 2.5: WHO SHOOTS FIRST -->
        <div v-else-if="currentView === 'shooter-select'" class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <p class="text-xs font-medium uppercase tracking-widest text-muted">Firing order</p>
                    <h2 class="text-xl font-bold">Who shoots first?</h2>
                    <p class="text-sm text-muted">{{ currentStage?.label }} &mdash; {{ currentTeam?.name }}</p>
                </div>

                <div class="space-y-3">
                    <button
                        v-for="shooter in orderedTeamMembers"
                        :key="shooter.id"
                        @click="selectFirstShooter(shooter.id)"
                        class="flex w-full items-center gap-3 rounded-xl border p-4 text-left transition-all active:scale-[0.99]"
                        :class="suggestedFirstShooterId === shooter.id ? 'border-emerald-600 bg-emerald-600/10' : 'border-border bg-surface hover:border-emerald-500/50'"
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-surface-2 text-sm font-bold text-muted">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" /></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-1.5 text-sm font-bold">
                                {{ shooter.name }}
                                <span v-if="shooter.division" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="divisionBadgeClass(shooter.division)">{{ shooter.division }}</span>
                                <span v-if="suggestedFirstShooterId === shooter.id" class="rounded bg-emerald-600/20 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-400">Suggested</span>
                            </p>
                            <p class="text-xs text-muted">Leads off &mdash; fires first on every gong</p>
                        </div>
                        <svg class="h-5 w-5 flex-shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>

                <button @click="enterOnGrid" class="block w-full rounded-xl border border-border py-3 text-center text-sm font-semibold text-muted transition-colors hover:bg-surface hover:text-primary">
                    Enter all shots on a grid
                </button>
                <button @click="goToTeamSelect" class="block w-full py-1 text-center text-xs font-semibold text-muted transition-colors hover:text-primary">
                    &larr; Back to teams
                </button>
            </div>
        </div>

        <!-- STEP 3 + 4: SCORING -->
        <!--
          Phone-first density: everything below the app header is sized to fit
          on a typical Android viewport (~640px tall after the system bars)
          without scrolling. Spacing/typography is deliberately tighter than
          the rest of the app — this is the one screen where the MD scores
          mid-string and shouldn't have to scroll between shots.
        -->
        <template v-else-if="currentView === 'scoring'">
            <!-- Progress + timer + layout toggle + "Up next" team chip -->
            <div class="bg-surface px-3 py-1.5">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between gap-2 text-[11px]">
                        <span class="text-muted">Step {{ currentLegIndex + 1 }}/{{ legs.length }}</span>
                        <div class="ml-auto flex items-center gap-2">
                            <div class="flex rounded-full bg-surface-2 p-0.5">
                                <button @click="setScoringLayout('guided')" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide transition-colors" :class="scoringLayout === 'guided' ? 'bg-emerald-600 text-white' : 'text-muted hover:text-primary'">Guided</button>
                                <button @click="setScoringLayout('grid')" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide transition-colors" :class="scoringLayout === 'grid' ? 'bg-emerald-600 text-white' : 'text-muted hover:text-primary'">Grid</button>
                            </div>
                            <span v-if="timeLimitSeconds" class="font-bold tabular-nums" :class="timerCritical ? 'text-red-400' : timerWarning ? 'text-amber-400' : 'text-emerald-400'">
                                {{ formattedTime }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-1 h-1 rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" :style="{ width: legProgressPercent + '%' }"></div>
                    </div>
                    <!-- Up-next chip: tells the RO which team to set up. -->
                    <div class="mt-1 flex items-center justify-between gap-2 text-[10.5px]">
                        <span v-if="nextTeam" class="flex min-w-0 items-center gap-1.5">
                            <span class="rounded bg-amber-500/15 px-1.5 py-0.5 font-bold uppercase tracking-wide text-amber-300">Up next</span>
                            <span class="truncate font-semibold text-amber-200">{{ nextTeam.name }}</span>
                            <span v-if="teamSquad(nextTeam)" class="hidden truncate text-muted xs:inline">&middot; {{ teamSquad(nextTeam).name }}</span>
                        </span>
                        <span v-else class="rounded bg-emerald-600/15 px-1.5 py-0.5 font-bold uppercase tracking-wide text-emerald-300">Last team on stage</span>
                        <span class="shrink-0 text-muted">{{ currentTeam?.name }}</span>
                    </div>
                </div>
            </div>

            <main class="flex flex-1 flex-col px-3 py-3">
                <div class="mx-auto flex w-full max-w-lg flex-1 flex-col">
                    <!-- Active shooter (compact one-line) -->
                    <div class="mb-2 rounded-lg border border-border bg-surface px-3 py-2 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-muted">{{ shooterOrdinal }} &bull; on the gun</p>
                        <p class="text-lg font-bold leading-tight">{{ currentShooter?.name }}</p>
                        <div class="mt-0.5 flex flex-wrap items-center justify-center gap-1">
                            <span v-if="currentShooter?.division" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="divisionBadgeClass(currentShooter.division)">
                                {{ currentShooter.division }}
                            </span>
                            <button v-if="canChangeLeadoff" @click="changeLeadoff" class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400 hover:text-emerald-300">
                                Change leadoff
                            </button>
                        </div>
                    </div>

                    <!-- Gong + shot info combined -->
                    <div class="mb-2 rounded-lg border border-border bg-surface px-3 py-2 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-muted">Gong {{ gongNumber }} &bull; Shot {{ currentShotNumber }}/{{ currentLeg?.shots }}</p>
                        <p class="text-xl font-bold leading-tight">{{ currentTarget?.name }} <span class="text-emerald-400">&bull; {{ currentTarget?.distance_m }}m</span></p>
                        <p class="mt-0.5 text-sm font-bold text-emerald-400">{{ pointsPreview }} pts if hit <span class="font-normal text-[10px] text-muted">(x{{ multiplierDisplay }})</span></p>
                        <div class="mt-1 flex justify-center gap-1">
                            <span v-for="s in currentLeg?.shots" :key="s" class="h-1.5 w-6 rounded-full"
                                  :class="legShotDotClass(s)"></span>
                        </div>
                    </div>

                    <!-- Points flash -->
                    <div v-if="lastPointsFlash" class="mb-2 animate-pulse rounded-lg bg-emerald-900/40 px-3 py-1 text-center text-sm font-bold text-emerald-400">
                        +{{ lastPointsFlash }} pts!
                    </div>

                    <!-- Timed out banner -->
                    <div v-if="locked" class="mb-2 rounded-lg border border-red-700 bg-red-900/40 px-3 py-2 text-center">
                        <p class="text-sm font-bold text-red-400">Time expired</p>
                        <p class="text-xs text-red-300">Shot entry locked. Stage marked timed out.</p>
                        <button @click="goToSummary" class="mt-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white">View Summary</button>
                    </div>

                    <!-- HIT / MISS -->
                    <div v-if="!locked" class="mt-auto space-y-2">
                        <div class="grid grid-cols-2 gap-3">
                            <button @click="recordShot('hit')"
                                class="flex h-20 flex-col items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg transition-all active:scale-95 active:bg-emerald-700">
                                <svg class="mb-0.5 h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                <span class="text-xl font-black">HIT</span>
                            </button>
                            <button @click="recordShot('miss')"
                                class="flex h-20 flex-col items-center justify-center rounded-xl bg-red-700 text-white shadow-lg transition-all active:scale-95 active:bg-red-800">
                                <svg class="mb-0.5 h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                <span class="text-xl font-black">MISS</span>
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button @click="undoLast" :disabled="!canUndo"
                                class="flex-1 rounded-lg border border-border py-2 text-xs font-medium transition-colors hover:bg-surface disabled:cursor-not-allowed disabled:opacity-30">
                                &larr; Undo last
                            </button>
                            <button @click="endStageEarly"
                                class="flex-1 rounded-lg border border-amber-700/60 py-2 text-xs font-medium text-amber-400 transition-colors hover:bg-amber-900/20">
                                End early
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </template>

        <!-- STEP 4: STAGE SUMMARY / FULL-STAGE GRID -->
        <template v-else-if="currentView === 'summary'">
            <!-- Layout toggle + timer mirror the scoring view so MDs can flip freely. -->
            <div class="bg-surface px-4 py-2">
                <div class="mx-auto max-w-lg">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-muted">{{ currentTeam?.name }} &mdash; {{ currentStage?.label }}</span>
                        <div class="ml-auto flex items-center gap-3">
                            <div class="flex rounded-full bg-surface-2 p-0.5">
                                <button @click="switchToGuidedFromGrid" class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide transition-colors" :class="scoringLayout === 'guided' ? 'bg-emerald-600 text-white' : 'text-muted hover:text-primary'">Guided</button>
                                <button @click="setScoringLayout('grid')" class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide transition-colors" :class="scoringLayout === 'grid' ? 'bg-emerald-600 text-white' : 'text-muted hover:text-primary'">Full stage</button>
                            </div>
                            <span v-if="timeLimitSeconds && !currentEntry?.completedAt" class="font-bold tabular-nums" :class="timerCritical ? 'text-red-400' : timerWarning ? 'text-amber-400' : 'text-emerald-400'">
                                {{ formattedTime }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        <div class="flex flex-1 flex-col px-4 py-6">
            <div class="mx-auto w-full max-w-lg space-y-4">
                <div class="text-center">
                    <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-green-600/20">
                        <svg class="h-7 w-7 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </div>
                    <h2 class="text-xl font-bold">{{ currentTeam?.name }} &mdash; {{ currentStage?.label }}</h2>
                    <p v-if="currentTimedOut" class="text-sm font-semibold text-red-400">Timed out</p>
                    <p class="text-sm text-muted">Tap an empty cell to record a HIT. Tap a recorded shot to change it.</p>
                </div>

                <!-- Team total -->
                <div class="rounded-xl border-2 border-emerald-600 bg-emerald-600/10 p-4 text-center">
                    <p class="text-xs font-medium uppercase text-emerald-400">Team total</p>
                    <p class="text-3xl font-black">{{ teamTotal }}</p>
                </div>

                <!-- Per shooter / per gong -->
                <div v-for="member in summaryMembers" :key="member.shooter.id" class="rounded-xl border border-border bg-surface overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold">{{ member.shooter.name }}</span>
                            <span v-if="member.shooter.division" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="divisionBadgeClass(member.shooter.division)">{{ member.shooter.division }}</span>
                            <span v-if="shooterAllComplete(member)" class="inline-flex items-center gap-1 rounded-full bg-emerald-600/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-300">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                All done
                            </span>
                        </div>
                        <span class="text-sm font-bold text-emerald-400">{{ member.total }} pts</span>
                    </div>
                    <div class="divide-y divide-border/50">
                        <div v-for="leg in member.legs" :key="leg.target.id"
                             class="px-4 py-2.5 transition-colors"
                             :class="legComplete(member.shooter.id, leg.target.id, leg.shots) ? 'bg-emerald-600/[0.04]' : ''">
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 text-xs font-semibold text-muted">
                                    <svg v-if="legComplete(member.shooter.id, leg.target.id, leg.shots)" class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span>{{ leg.target.name }} &bull; {{ leg.target.distance_m }}m</span>
                                    <span v-if="legComplete(member.shooter.id, leg.target.id, leg.shots)" class="rounded bg-emerald-600/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-300">Complete</span>
                                </span>
                                <span class="text-xs font-bold">{{ leg.points }} pts</span>
                            </div>
                            <div class="flex gap-1.5">
                                <button v-for="n in leg.shots" :key="n" @click="correctShot(member.shooter, leg.target, n)"
                                    class="flex h-9 flex-1 items-center justify-center rounded-lg text-xs font-bold transition-transform active:scale-95"
                                    :class="summaryShotClass(member.shooter.id, leg.target.id, n)">
                                    {{ summaryShotLabel(member.shooter.id, leg.target.id, n) }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-2">
                    <button @click="elrStore.syncShots()" class="w-full rounded-xl bg-green-600 py-3 font-semibold text-white transition-colors hover:bg-green-700">Sync Scores</button>
                    <button @click="continueFromSummary" class="w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white transition-colors hover:bg-emerald-700">Continue</button>
                </div>
            </div>
        </div>
        </template>

        <ScoringSponsorship />

        <!-- Accidental-touch safeguard: edit action sheet for an already-recorded shot. -->
        <div v-if="pendingEdit" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm" @click.self="cancelPendingEdit">
            <div class="w-full max-w-md rounded-t-2xl border-t border-border bg-surface p-4 pb-6 shadow-2xl">
                <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-surface-2"></div>
                <p class="text-center text-xs font-semibold uppercase tracking-widest text-muted">{{ pendingEdit.shooter?.name }}</p>
                <p class="text-center text-base font-bold">{{ pendingEdit.target?.name }} &bull; {{ pendingEdit.target?.distance_m }}m &bull; Shot {{ pendingEdit.shotNumber }}</p>
                <p class="mt-1 text-center text-xs text-muted">
                    Currently: <span class="font-bold" :class="pendingEdit.current === 'hit' ? 'text-emerald-400' : 'text-red-400'">{{ pendingEdit.current === 'hit' ? 'HIT' : 'MISS' }}</span> &mdash; tap a button to change it
                </p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button @click="confirmPendingEdit('hit')"
                        class="flex h-16 flex-col items-center justify-center rounded-xl bg-emerald-600 text-white transition-transform active:scale-95"
                        :class="pendingEdit.current === 'hit' ? 'opacity-50' : ''">
                        <span class="text-lg font-black">HIT</span>
                    </button>
                    <button @click="confirmPendingEdit('miss')"
                        class="flex h-16 flex-col items-center justify-center rounded-xl bg-red-700 text-white transition-transform active:scale-95"
                        :class="pendingEdit.current === 'miss' ? 'opacity-50' : ''">
                        <span class="text-lg font-black">MISS</span>
                    </button>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button @click="confirmPendingEdit('not_taken')"
                        class="rounded-xl border border-amber-700/60 py-3 text-sm font-semibold text-amber-400 transition-colors hover:bg-amber-900/20">
                        Clear shot
                    </button>
                    <button @click="cancelPendingEdit"
                        class="rounded-xl border border-border py-3 text-sm font-semibold text-muted transition-colors hover:bg-surface-2">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMatchStore } from '../stores/matchStore';
import { useElrScoringStore } from '../stores/elrScoringStore';
import OnlineIndicator from '../components/OnlineIndicator.vue';
import SyncBadge from '../components/SyncBadge.vue';
import DeviceLockBanner from '../components/DeviceLockBanner.vue';
import ScoringSponsorship from '../components/ScoringSponsorship.vue';
import {
    buildLegs, sortedTargets, defaultFirstShooterId, recomputeLeg,
    pointsForImpact, multiplierForImpact, rotateTeamsForStage,
} from '../composables/useTeamSequence';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const router = useRouter();
const matchStore = useMatchStore();
const elrStore = useElrScoringStore();

const ready = ref(false);
const currentView = ref('stage-select');
const currentStageIndex = ref(0);
const currentTeamId = ref(null);
const currentLegIndex = ref(0);
const lastPointsFlash = ref(null);
const nowTick = ref(Date.now());
let flashTimeout = null;
let timerInterval = null;
let syncInterval = null;

const STATE_KEY = 'dc_elr_team_state';
const LAYOUT_KEY = 'dc_elr_scoring_layout';

// 'guided'  = one-shooter-at-a-time flow (default).
// 'grid'    = whole-stage editable grid (both shooters x all gongs x all shots).
// Persisted per device — MDs typically pick one and stick with it for the match.
const scoringLayout = ref(
    (typeof localStorage !== 'undefined' && localStorage.getItem(LAYOUT_KEY)) === 'grid' ? 'grid' : 'guided'
);

function setScoringLayout(layout) {
    if (layout !== 'guided' && layout !== 'grid') return;
    scoringLayout.value = layout;
    try { localStorage.setItem(LAYOUT_KEY, layout); } catch { /* ignore */ }

    // Flip the active view to match the chosen layout (only while scoring a team).
    if (currentTeamId.value && currentStage.value) {
        if (layout === 'grid' && currentView.value === 'scoring') {
            // Jump into the grid for the current team-stage.
            // ensureStarted() keeps the timer accurate; fire-and-forget is fine.
            ensureStarted();
            currentView.value = 'summary';
            saveProgress();
        } else if (layout === 'guided' && currentView.value === 'summary') {
            currentLegIndex.value = firstIncompleteLegIndex();
            currentView.value = 'scoring';
            saveProgress();
        }
    }
}

// Toggle handler that's safe to call from the grid even when guided mode is the
// chosen pref but the user landed here via shooter-select's "Enter on grid".
function switchToGuidedFromGrid() {
    setScoringLayout('guided');
}

// ── Match data ──
const match = computed(() => matchStore.currentMatch);
const stages = computed(() => match.value?.elr_stages ?? []);
const currentStage = computed(() => stages.value[currentStageIndex.value] ?? null);
const profile = computed(() => currentStage.value?.profile ?? null);
const distanceBased = computed(() => !!match.value?.elr_distance_based_scoring);
const timeLimitSeconds = computed(() => match.value?.elr_team_time_limit_seconds ?? null);

// Map every shooter id to its squad so a team's squad can be derived by
// matching shooter ids (the team payload carries no squad_id, and this works
// identically on the cloud PWA and the offline native hub).
const squadByShooterId = computed(() => {
    const map = new Map();
    for (const squad of (match.value?.squads ?? [])) {
        for (const sh of (squad.shooters ?? [])) {
            map.set(sh.id, squad);
        }
    }
    return map;
});

function teamSquad(team) {
    for (const sh of (team?.shooters ?? [])) {
        const squad = squadByShooterId.value.get(sh.id);
        if (squad) return squad;
    }
    return null;
}

// Teams in firing order: scoped to the locked squad (if any), grouped by squad,
// and rotated per stage so the team that shot first last stage shoots last this
// stage. Squads are concatenated in their own sort order.
const teams = computed(() => {
    let list = [...(match.value?.teams ?? [])];
    if (matchStore.lockedSquadId) {
        list = list.filter(t => teamSquad(t)?.id === matchStore.lockedSquadId);
    }

    const bySquad = new Map();
    for (const t of list) {
        const squad = teamSquad(t);
        const key = squad?.id ?? 0;
        if (!bySquad.has(key)) {
            bySquad.set(key, { sortOrder: squad?.sort_order ?? 999, teams: [] });
        }
        bySquad.get(key).teams.push(t);
    }

    const groups = [...bySquad.values()].sort((a, b) => a.sortOrder - b.sortOrder);
    const ordered = [];
    for (const group of groups) {
        ordered.push(...rotateTeamsForStage(group.teams, currentStageIndex.value));
    }
    return ordered;
});
const currentTeam = computed(() => teams.value.find(t => t.id === currentTeamId.value) ?? null);
const currentSquad = computed(() => teamSquad(currentTeam.value));

function activeMembers(team) {
    return (team?.shooters ?? []).filter(s => (s.status ?? 'active') === 'active');
}

// Team members in their base firing order (by sort_order), independent of the
// current leadoff pick — used by the "Who shoots first?" selection screen.
const orderedTeamMembers = computed(() =>
    activeMembers(currentTeam.value)
        .slice()
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || (a.id - b.id))
);

// ── First shooter / rotation ──
// Non-binding leadoff suggestion for the selection screen. Prefer real rotation:
// whoever led the *previous* stage should go second here, so suggest the other
// shooter. Falls back to the deterministic stage-parity default.
const suggestedFirstShooterId = computed(() => {
    if (!currentTeam.value || !currentStage.value) return null;
    const active = orderedTeamMembers.value;
    if (active.length === 0) return null;

    const prevStage = stages.value[currentStageIndex.value - 1];
    if (prevStage) {
        const prevFirst = elrStore.getTeamStageEntry(currentTeam.value.id, prevStage.id)?.firstShooterId;
        if (prevFirst) {
            return (active.find(s => s.id !== prevFirst) ?? active.find(s => s.id === prevFirst))?.id ?? null;
        }
    }
    return defaultFirstShooterId(currentTeam.value, currentStageIndex.value);
});

const firstShooterId = computed(() => {
    if (!currentTeam.value || !currentStage.value) return null;
    // A saved entry wins so a stage in progress keeps its leadoff shooter.
    const entry = elrStore.getTeamStageEntry(currentTeam.value.id, currentStage.value.id);
    if (entry?.firstShooterId) return entry.firstShooterId;
    // No pick yet — use the (non-binding) suggested leadoff so the guided flow
    // still has a sensible default order.
    return suggestedFirstShooterId.value;
});

// ── Legs ──
const legs = computed(() => {
    if (!currentTeam.value || !currentStage.value) return [];
    return buildLegs(currentTeam.value, currentStage.value, firstShooterId.value);
});
const currentLeg = computed(() => legs.value[currentLegIndex.value] ?? null);
const currentShooter = computed(() => currentLeg.value?.shooter ?? null);
const currentTarget = computed(() => currentLeg.value?.target ?? null);

const gongNumber = computed(() => {
    if (!currentTarget.value || !currentStage.value) return '';
    const all = sortedTargets(currentStage.value);
    return all.findIndex(t => t.id === currentTarget.value.id) + 1;
});

const shooterOrdinal = computed(() => {
    if (!currentLeg.value || !currentTeam.value) return '';
    return currentLeg.value.shooter.id === firstShooterId.value ? 'S1' : 'S2';
});

// Recorded hit/miss shots for a leg (not_taken slots count as open).
function enteredShots(leg) {
    if (!leg) return [];
    return elrStore.getShotsForTarget(leg.shooter.id, leg.target.id)
        .filter(s => s.result === 'hit' || s.result === 'miss');
}

const currentEnteredCount = computed(() => enteredShots(currentLeg.value).length);
const currentShotNumber = computed(() => Math.min(currentEnteredCount.value + 1, currentLeg.value?.shots ?? 1));
const currentImpactIfHit = computed(() => enteredShots(currentLeg.value).filter(s => s.result === 'hit').length + 1);

const pointsPreview = computed(() => {
    if (!currentTarget.value) return 0;
    return pointsForImpact(currentTarget.value, currentImpactIfHit.value, profile.value, distanceBased.value);
});
const multiplierDisplay = computed(() => multiplierForImpact(profile.value, currentImpactIfHit.value));

const legProgressPercent = computed(() => {
    if (legs.value.length === 0) return 0;
    return Math.round((currentLegIndex.value / legs.value.length) * 100);
});

const canUndo = computed(() => {
    if (currentEnteredCount.value > 0) return true;
    return currentLegIndex.value > 0;
});

// ── Timer ──
const currentEntry = computed(() =>
    currentTeam.value && currentStage.value
        ? elrStore.getTeamStageEntry(currentTeam.value.id, currentStage.value.id)
        : null
);
const currentTimedOut = computed(() => !!currentEntry.value?.timedOut);

const elapsedSeconds = computed(() => {
    const startedAt = currentEntry.value?.startedAt;
    if (!startedAt) return 0;
    return Math.max(0, Math.floor((nowTick.value - new Date(startedAt).getTime()) / 1000));
});
const remainingSeconds = computed(() => {
    if (!timeLimitSeconds.value) return null;
    return Math.max(0, timeLimitSeconds.value - elapsedSeconds.value);
});
const locked = computed(() => currentTimedOut.value || (remainingSeconds.value !== null && remainingSeconds.value <= 0));
const formattedTime = computed(() => {
    const r = remainingSeconds.value;
    if (r === null) return '';
    const m = Math.floor(r / 60);
    const s = r % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
});
const timerWarning = computed(() => remainingSeconds.value !== null && remainingSeconds.value <= 60 && remainingSeconds.value > 20);
const timerCritical = computed(() => remainingSeconds.value !== null && remainingSeconds.value <= 20);

// ── Summary ──
function legPoints(shooterId, target) {
    const shots = elrStore.getShotsForTarget(shooterId, target.id);
    const recomputed = recomputeLeg(shots, target, profile.value, distanceBased.value);
    return Math.round(recomputed.reduce((sum, r) => sum + r.points, 0) * 100) / 100;
}

const summaryMembers = computed(() => {
    if (!currentTeam.value || !currentStage.value) return [];
    const order = legs.value;
    const byShooter = new Map();
    for (const leg of order) {
        if (!byShooter.has(leg.shooter.id)) {
            byShooter.set(leg.shooter.id, { shooter: leg.shooter, legs: [], total: 0 });
        }
        const points = legPoints(leg.shooter.id, leg.target);
        byShooter.get(leg.shooter.id).legs.push({ target: leg.target, shots: leg.shots, points });
        byShooter.get(leg.shooter.id).total += points;
    }
    return [...byShooter.values()].map(m => ({ ...m, total: Math.round(m.total * 100) / 100 }));
});

const teamTotal = computed(() =>
    Math.round(summaryMembers.value.reduce((sum, m) => sum + m.total, 0) * 100) / 100
);

function summaryShot(shooterId, targetId, shotNumber) {
    return elrStore.getShotsForTarget(shooterId, targetId).find(s => s.shotNumber === shotNumber) ?? null;
}

// A (shooter, target) leg is "complete" once every shot slot has a real result
// (hit or miss). Drives the green checkmark + tinted row in the grid and the
// "All done" badge per shooter — pairs with the accidental-touch safeguard so
// finalized rows are visually obvious before someone taps them.
function legComplete(shooterId, targetId, shotCount) {
    if (!shotCount) return false;
    const shots = elrStore.getShotsForTarget(shooterId, targetId);
    if (shots.length < shotCount) return false;
    for (let n = 1; n <= shotCount; n++) {
        const shot = shots.find(s => s.shotNumber === n);
        if (!shot || (shot.result !== 'hit' && shot.result !== 'miss')) return false;
    }
    return true;
}

function shooterAllComplete(member) {
    return member.legs.every(l => legComplete(member.shooter.id, l.target.id, l.shots));
}
function summaryShotLabel(shooterId, targetId, shotNumber) {
    const shot = summaryShot(shooterId, targetId, shotNumber);
    if (!shot || shot.result === 'not_taken') return '–';
    return shot.result === 'hit' ? 'HIT' : 'MISS';
}
function summaryShotClass(shooterId, targetId, shotNumber) {
    const shot = summaryShot(shooterId, targetId, shotNumber);
    if (!shot || shot.result === 'not_taken') return 'bg-surface-2 text-muted';
    return shot.result === 'hit' ? 'bg-emerald-600 text-white' : 'bg-red-700 text-white';
}

// ── Stage / team progress for selection screens ──
function teamDone(team) {
    if (!currentStage.value) return false;
    const entry = elrStore.getTeamStageEntry(team.id, currentStage.value.id);
    return !!entry?.completedAt;
}
function teamTimedOut(team) {
    if (!currentStage.value) return false;
    return !!elrStore.getTeamStageEntry(team.id, currentStage.value.id)?.timedOut;
}
function stageProgress(stage) {
    const total = teams.value.length;
    if (total === 0) return { allDone: false, someDone: false, label: 'No teams' };
    let done = 0;
    for (const t of teams.value) {
        if (elrStore.getTeamStageEntry(t.id, stage.id)?.completedAt) done++;
    }
    return {
        allDone: done === total,
        someDone: done > 0,
        label: `${done}/${total} teams`,
    };
}
const recommendedTeamId = computed(() => {
    const pending = teams.value.find(t => !teamDone(t));
    return pending?.id ?? null;
});

// Next un-done team after the one currently being scored, in firing order.
// Drives the "Up next" chip in the scoring strip so the RO can tell the next
// team to get set up. Returns null when the current team is the last one
// on the stage (so we can show "Last team" instead).
const nextTeam = computed(() => {
    if (!currentTeam.value) return null;
    const list = teams.value;
    const startIdx = list.findIndex(t => t.id === currentTeamId.value);
    if (startIdx === -1) return null;
    for (let i = startIdx + 1; i < list.length; i++) {
        if (!teamDone(list[i])) return list[i];
    }
    return null;
});

// ── Visual helpers ──
function divisionBadgeClass(division) {
    const name = (division ?? '').toLowerCase();
    if (name.includes('major')) return 'bg-amber-500/20 text-amber-300';
    if (name.includes('minor')) return 'bg-sky-500/20 text-sky-300';
    return 'bg-surface-2 text-muted';
}
function legShotDotClass(s) {
    if (s < currentShotNumber.value) return 'bg-muted';
    if (s === currentShotNumber.value) return 'bg-emerald-500';
    return 'bg-surface-2';
}

// ── Persistence of UI position ──
function saveProgress() {
    try {
        localStorage.setItem(STATE_KEY, JSON.stringify({
            matchId: props.matchId,
            view: currentView.value,
            stageIdx: currentStageIndex.value,
            teamId: currentTeamId.value,
            legIdx: currentLegIndex.value,
        }));
    } catch { /* ignore */ }
}
function restoreProgress() {
    try {
        const raw = localStorage.getItem(STATE_KEY);
        if (!raw) return false;
        const s = JSON.parse(raw);
        if (s.matchId !== props.matchId) return false;
        const validViews = ['stage-select', 'team-select', 'shooter-select', 'scoring', 'summary'];
        currentView.value = validViews.includes(s.view) ? s.view : 'stage-select';
        currentStageIndex.value = Math.min(s.stageIdx ?? 0, Math.max(0, stages.value.length - 1));
        currentTeamId.value = s.teamId ?? null;
        currentLegIndex.value = s.legIdx ?? 0;
        return true;
    } catch { return false; }
}
function clearProgress() {
    try { localStorage.removeItem(STATE_KEY); } catch { /* ignore */ }
}

// ── Navigation ──
function selectStage(idx) {
    currentStageIndex.value = idx;
    currentTeamId.value = null;
    currentLegIndex.value = 0;
    currentView.value = 'team-select';
    saveProgress();
}

async function selectTeam(teamId) {
    currentTeamId.value = teamId;
    // Only lock the leadoff once a real shot has been entered. A bare
    // `startedAt` (e.g. left over from a previous device or accidental tap) is
    // not enough — MDs must always be able to pick who shoots first.
    const hasShots = legs.value.some(l => enteredShots(l).length > 0);
    if (hasShots) {
        currentLegIndex.value = firstIncompleteLegIndex();
        currentView.value = 'scoring';
    } else {
        currentView.value = 'shooter-select';
    }
    saveProgress();
}

// Always allow a leadoff change as long as no shots have been recorded for
// this team-stage. The "Change leadoff" button in the scoring view calls this.
function changeLeadoff() {
    currentView.value = 'shooter-select';
    saveProgress();
}

// True when the current team-stage has zero recorded shots — controls visibility
// of "Change leadoff" and the safe re-pick path.
const canChangeLeadoff = computed(() => {
    if (!currentTeam.value || !currentStage.value) return false;
    return !legs.value.some(l => enteredShots(l).length > 0);
});

// Team picked who leads — lock that order, start the timer and enter the
// shooter's preferred scoring layout (guided one-at-a-time, or the whole-stage
// grid with both shooters visible).
async function selectFirstShooter(shooterId) {
    if (!currentTeam.value || !currentStage.value) return;
    await elrStore.saveTeamStageEntry({
        matchId: props.matchId,
        teamId: currentTeam.value.id,
        elrStageId: currentStage.value.id,
        squadId: currentSquad.value?.id ?? null,
        firstShooterId: shooterId,
        position: teams.value.findIndex(t => t.id === currentTeam.value.id) + 1 || null,
        startedAt: new Date().toISOString(),
    });
    currentLegIndex.value = firstIncompleteLegIndex();
    currentView.value = scoringLayout.value === 'grid' ? 'summary' : 'scoring';
    saveProgress();
}

// Skip guided entry and go straight to the editable whole-stage grid (both
// shooters x all gongs x all shots). ensureStarted() persists the entry so
// legs + timer are stable. Flipping the persisted layout means the toggle
// pill at the top reflects the user's choice.
async function enterOnGrid() {
    await ensureStarted();
    scoringLayout.value = 'grid';
    try { localStorage.setItem(LAYOUT_KEY, 'grid'); } catch { /* ignore */ }
    currentView.value = 'summary';
    saveProgress();
}

function goToTeamSelect() {
    currentView.value = 'team-select';
    saveProgress();
}

// Resume at the first leg that still has open shot slots.
function firstIncompleteLegIndex() {
    const list = legs.value;
    for (let i = 0; i < list.length; i++) {
        if (enteredShots(list[i]).length < list[i].shots) return i;
    }
    return Math.max(0, list.length - 1);
}

function goToStageSelect() {
    currentView.value = 'stage-select';
    saveProgress();
}

function handleHeaderBack() {
    if (currentView.value === 'scoring' || currentView.value === 'summary' || currentView.value === 'shooter-select') {
        currentView.value = 'team-select';
        saveProgress();
    } else if (currentView.value === 'team-select') {
        currentView.value = 'stage-select';
        saveProgress();
    } else {
        router.push({ name: 'match-overview', params: { matchId: props.matchId } });
    }
}

// ── Team stage lifecycle ──
async function ensureStarted() {
    if (!currentTeam.value || !currentStage.value) return;
    const entry = elrStore.getTeamStageEntry(currentTeam.value.id, currentStage.value.id);
    if (entry?.startedAt) return;
    await elrStore.saveTeamStageEntry({
        matchId: props.matchId,
        teamId: currentTeam.value.id,
        elrStageId: currentStage.value.id,
        squadId: currentSquad.value?.id ?? null,
        firstShooterId: firstShooterId.value,
        position: teams.value.findIndex(t => t.id === currentTeam.value.id) + 1 || null,
        startedAt: new Date().toISOString(),
    });
}

async function finishStage({ timedOut = false } = {}) {
    if (!currentTeam.value || !currentStage.value) return;
    await elrStore.saveTeamStageEntry({
        matchId: props.matchId,
        teamId: currentTeam.value.id,
        elrStageId: currentStage.value.id,
        completedAt: new Date().toISOString(),
        timedOut,
    });
    elrStore.syncShots();
}

// ── Scoring ──
function flashPoints(pts) {
    lastPointsFlash.value = pts;
    clearTimeout(flashTimeout);
    flashTimeout = setTimeout(() => { lastPointsFlash.value = null; }, 1400);
}

// Recompute impact numbers + points for a leg and re-store any changed shots
// so offline display matches the server's impact-based recalculation.
async function persistLeg(leg) {
    const recorded = elrStore.getShotsForTarget(leg.shooter.id, leg.target.id);
    const recomputed = recomputeLeg(recorded, leg.target, profile.value, distanceBased.value);
    for (const r of recomputed) {
        const existing = recorded.find(s => s.shotNumber === r.shotNumber);
        if (!existing) continue;
        if ((existing.impactNumber ?? null) !== (r.impactNumber ?? null) || Number(existing.pointsAwarded) !== Number(r.points)) {
            await elrStore.recordShot({
                matchId: props.matchId,
                shooterId: leg.shooter.id,
                elrTargetId: leg.target.id,
                shotNumber: r.shotNumber,
                result: r.result,
                pointsAwarded: r.points,
                impactNumber: r.impactNumber,
            });
        }
    }
}

async function recordShot(result) {
    if (locked.value || !currentLeg.value) return;
    const leg = currentLeg.value;
    const shotNumber = currentShotNumber.value;

    await elrStore.recordShot({
        matchId: props.matchId,
        shooterId: leg.shooter.id,
        elrTargetId: leg.target.id,
        shotNumber,
        result,
        pointsAwarded: 0,
        impactNumber: null,
    });
    await persistLeg(leg);

    if (result === 'hit') {
        const shot = elrStore.getShotsForTarget(leg.shooter.id, leg.target.id).find(s => s.shotNumber === shotNumber);
        if (shot) flashPoints(shot.pointsAwarded);
    }

    // Auto-advance after the leg's last shot.
    if (enteredShots(leg).length >= leg.shots) {
        if (currentLegIndex.value < legs.value.length - 1) {
            currentLegIndex.value++;
        } else {
            await finishStage();
            currentView.value = 'summary';
        }
    }
    saveProgress();
    elrStore.syncShots();
}

async function undoLast() {
    if (currentView.value === 'summary') currentView.value = 'scoring';
    let leg = currentLeg.value;
    if (enteredShots(leg).length === 0 && currentLegIndex.value > 0) {
        currentLegIndex.value--;
        leg = currentLeg.value;
    }
    const entered = enteredShots(leg);
    if (entered.length === 0) return;
    const last = entered.reduce((a, b) => (a.shotNumber > b.shotNumber ? a : b));
    await elrStore.removeShot({ shooterId: leg.shooter.id, elrTargetId: leg.target.id, shotNumber: last.shotNumber });
    await persistLeg(leg);
    lastPointsFlash.value = null;
    saveProgress();
    elrStore.syncShots();
}

async function endStageEarly() {
    await finishStage();
    currentView.value = 'summary';
    saveProgress();
}

function goToSummary() {
    currentView.value = 'summary';
    saveProgress();
}

// ── Editable grid (free entry + corrections) ──
//
// Accidental-touch safeguard: empty cells take the first tap as a HIT (fast
// path for live scoring), but any tap on an already-recorded cell opens an
// explicit Hit / Miss / Clear action sheet instead of silently cycling. This
// stops a stray thumb brush on a finalized score from rewriting it.
const pendingEdit = ref(null); // { shooter, target, shotNumber, current } | null

async function correctShot(shooter, target, shotNumber) {
    const shot = summaryShot(shooter.id, target.id, shotNumber);
    const isPopulated = !!shot && shot.result !== 'not_taken';

    if (isPopulated) {
        // Open the action sheet — explicit choice required to mutate a recorded shot.
        pendingEdit.value = {
            shooter,
            target,
            shotNumber,
            current: shot.result,
        };
        return;
    }

    // Empty slot — fast HIT entry, same as before.
    await applyShotEdit(shooter, target, shotNumber, 'hit');
}

// Confirm the pending edit with one of the action-sheet buttons (or close it).
async function confirmPendingEdit(nextResult) {
    const edit = pendingEdit.value;
    pendingEdit.value = null;
    if (!edit || !nextResult) return;
    if (nextResult === edit.current) return; // no-op confirmation
    await applyShotEdit(edit.shooter, edit.target, edit.shotNumber, nextResult);
}

function cancelPendingEdit() {
    pendingEdit.value = null;
}

// Single write path used by both the empty-cell fast tap and the action sheet.
async function applyShotEdit(shooter, target, shotNumber, nextResult) {
    if (nextResult === 'not_taken') {
        await elrStore.removeShot({ shooterId: shooter.id, elrTargetId: target.id, shotNumber });
    } else {
        await elrStore.recordShot({
            matchId: props.matchId,
            shooterId: shooter.id,
            elrTargetId: target.id,
            shotNumber,
            result: nextResult,
            pointsAwarded: 0,
            impactNumber: null,
        });
    }
    await persistLeg({ shooter, target, shots: target.max_shots || 3 });

    // Only an edit to an *already completed* team reopens its stage entry
    // (mirrors the server). During pre-completion grid entry we leave the entry
    // open so the team isn't marked done after the first tap.
    if (currentTeam.value && currentStage.value) {
        const entry = elrStore.getTeamStageEntry(currentTeam.value.id, currentStage.value.id);
        if (entry?.completedAt) {
            await elrStore.saveTeamStageEntry({
                matchId: props.matchId,
                teamId: currentTeam.value.id,
                elrStageId: currentStage.value.id,
                completedAt: new Date().toISOString(),
                timedOut: currentTimedOut.value,
            });
        }
    }
    elrStore.syncShots();
}

function continueFromSummary() {
    elrStore.syncShots();
    currentView.value = 'team-select';
    saveProgress();
}

// ── Timer tick + expiry handling ──
function startTimerLoop() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        nowTick.value = Date.now();
        // Auto-finalize when the countdown hits zero while actively scoring.
        if (currentView.value === 'scoring' && remainingSeconds.value === 0 && !currentTimedOut.value && currentEntry.value?.startedAt) {
            finishStage({ timedOut: true });
        }
    }, 1000);
}

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
    await elrStore.initForMatch(props.matchId);

    // Seed local team-stage entries from the server payload so progress/timers
    // survive a fresh device that hasn't scored yet.
    for (const e of (match.value?.elr_team_stage_entries ?? [])) {
        const existing = elrStore.getTeamStageEntry(e.team_id, e.elr_stage_id);
        if (!existing) {
            await elrStore.saveTeamStageEntry({
                matchId: props.matchId,
                teamId: e.team_id,
                elrStageId: e.elr_stage_id,
                squadId: e.squad_id,
                firstShooterId: e.first_shooter_id,
                position: e.position,
                startedAt: e.started_at,
                completedAt: e.completed_at,
                timedOut: e.timed_out,
            });
            // mark synced (came from server)
            const key = `${e.team_id}-${e.elr_stage_id}`;
            const entry = elrStore.teamStageEntries.get(key);
            if (entry) elrStore.teamStageEntries.set(key, { ...entry, synced: true });
        }
    }

    ready.value = true;
    if (!restoreProgress()) {
        currentView.value = 'stage-select';
    }

    startTimerLoop();

    syncInterval = setInterval(async () => {
        if (!navigator.onLine) return;
        if (elrStore.pendingCount > 0) await elrStore.syncShots();
        try {
            await matchStore.fetchMatch(props.matchId);
            await elrStore.refreshShots(props.matchId);
        } catch { /* offline */ }
    }, 15000);
});

onUnmounted(() => {
    clearInterval(timerInterval);
    clearInterval(syncInterval);
    clearTimeout(flashTimeout);
});
</script>

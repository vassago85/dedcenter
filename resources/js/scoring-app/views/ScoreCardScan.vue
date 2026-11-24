<template>
    <div class="min-h-screen bg-slate-900 text-white">
        <header class="border-b border-slate-700 bg-slate-800 px-4 py-4">
            <div class="mx-auto flex max-w-2xl items-center gap-3">
                <BackLink :to="{ name: 'match-overview', params: { matchId } }" class="text-slate-400 hover:text-white" aria-label="Back to match">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </BackLink>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">ALRHA score cards</p>
                    <h1 class="truncate text-lg font-bold">{{ matchStore.currentMatch?.name ?? 'Match' }}</h1>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4 py-5 space-y-4">

            <!-- ── Step 1: capture ── -->
            <section v-if="step === 'capture'" class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                <h2 class="text-base font-semibold text-white">Photograph the mark block</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Frame the scan block square-on so all four corner marks and the QR are inside the frame. Good lighting, no shadow. One still photo.
                </p>

                <label class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-4 text-base font-bold text-white transition-colors hover:bg-emerald-700 active:bg-emerald-800 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                    Take photo
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="onPhotoCaptured" />
                </label>

                <details class="mt-4 text-xs text-slate-500">
                    <summary class="cursor-pointer hover:text-slate-300">Can't scan? Enter the card ID by hand</summary>
                    <div class="mt-3 flex gap-2">
                        <input
                            v-model="manualToken"
                            type="text"
                            placeholder="e.g. 7H2K9Q4R"
                            class="flex-1 rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm font-mono text-white focus:border-emerald-500 focus:outline-none"
                            maxlength="24"
                        />
                        <button
                            @click="loadByToken(manualToken)"
                            :disabled="!manualToken || loading"
                            class="rounded-md bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600 disabled:opacity-40"
                        >
                            Load card
                        </button>
                    </div>
                </details>

                <p v-if="errorMessage" class="mt-4 rounded-md bg-red-900/40 px-3 py-2 text-sm text-red-300">{{ errorMessage }}</p>
            </section>

            <!-- ── Step 2: review ── -->
            <section v-else-if="step === 'review' && sheet" class="space-y-4">
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                Card {{ sheet.token }}
                                <span v-if="sheet.is_spare" class="ml-1 rounded bg-amber-500/20 px-1.5 py-0.5 text-amber-300">SPARE</span>
                            </p>
                            <h2 v-if="!sheet.is_spare" class="mt-0.5 text-lg font-bold">{{ sheet.shooter.name }}</h2>
                            <h2 v-else class="mt-0.5 text-lg font-bold text-amber-200">Unassigned spare card</h2>
                            <p v-if="!sheet.is_spare" class="mt-0.5 text-xs text-slate-400">
                                {{ sheet.shooter.squad ?? 'No relay' }}
                                <template v-if="sheet.shooter.alrha_class_label"> · {{ sheet.shooter.alrha_class_label }}</template>
                                <template v-if="sheet.shooter.team"> · Team {{ sheet.shooter.team }}</template>
                            </p>
                            <p v-else class="mt-0.5 text-xs text-slate-400">
                                {{ sheet.alrha_class_label ?? 'ALRHA' }} · pick the shooter below before confirming.
                            </p>
                        </div>
                        <button
                            @click="resetScan"
                            class="shrink-0 rounded-md border border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                        >
                            Rescan
                        </button>
                    </div>

                    <!-- Spare: assign shooter -->
                    <div v-if="sheet.is_spare" class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3">
                        <label for="assign_shooter" class="block text-[10px] font-bold uppercase tracking-wider text-amber-300">
                            Assign card to shooter
                        </label>
                        <select
                            id="assign_shooter"
                            v-model.number="assignShooterId"
                            class="mt-1.5 w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-white focus:border-amber-400 focus:outline-none"
                        >
                            <option :value="null">— pick a shooter —</option>
                            <option
                                v-for="s in (sheet.assignable_shooters ?? [])"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}<template v-if="s.bib_number"> · #{{ s.bib_number }}</template><template v-if="s.squad"> · {{ s.squad }}</template>
                            </option>
                        </select>
                        <p class="mt-2 text-[10px] text-amber-200/80">
                            Once you confirm, the card is permanently bound to this shooter. To change it, print a fresh card.
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-md bg-slate-900/60 px-2 py-1.5">
                            <p class="text-[10px] uppercase text-slate-500">Hits</p>
                            <p class="text-sm font-bold text-emerald-400">{{ counts.hit }}</p>
                        </div>
                        <div class="rounded-md bg-slate-900/60 px-2 py-1.5">
                            <p class="text-[10px] uppercase text-slate-500">Misses</p>
                            <p class="text-sm font-bold text-red-400">{{ counts.miss }}</p>
                        </div>
                        <div class="rounded-md bg-slate-900/60 px-2 py-1.5">
                            <p class="text-[10px] uppercase text-slate-500">To resolve</p>
                            <p class="text-sm font-bold" :class="counts.unresolved === 0 ? 'text-slate-300' : 'text-amber-400'">{{ counts.unresolved }}</p>
                        </div>
                    </div>

                    <p v-if="counts.cbcHit > 0" class="mt-2 text-[11px] text-amber-300">
                        Cold Bore marks are stored but kept out of the match total.
                    </p>
                </div>

                <!-- Per-target review -->
                <div v-for="row in reviewRows" :key="row.targetId" class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold">
                                <span v-if="row.isColdBore" class="rounded bg-amber-500/20 px-2 py-0.5 text-[10px] uppercase tracking-wider text-amber-300 mr-2">CBC</span>
                                {{ row.label }}
                            </p>
                            <p class="text-[11px] text-slate-500 uppercase tracking-wider">{{ row.block }} block</p>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            {{ row.shots.length }} {{ row.shots.length === 1 ? 'shot' : 'shots' }}
                        </p>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div v-for="shot in row.shots" :key="shot.shotNumber" class="flex items-center gap-2">
                            <span class="w-14 shrink-0 text-xs text-slate-500">
                                Shot {{ shot.shotNumber }}
                                <span class="block text-[10px] text-slate-600">{{ shot.shotNumber <= 5 ? (6 - shot.shotNumber) : 0 }} pt<span v-if="(6 - shot.shotNumber) !== 1">s</span></span>
                            </span>
                            <div class="flex-1 flex gap-1">
                                <button
                                    @click="setShot(shot, 'hit')"
                                    :class="[
                                        'flex-1 rounded-md py-2 text-xs font-bold uppercase tracking-wider transition',
                                        shot.override === 'hit' || (shot.override === null && shot.decoded === 'hit') ? 'bg-emerald-600 text-white' : 'border border-slate-600 text-slate-400 hover:text-white',
                                    ]"
                                >Hit</button>
                                <button
                                    @click="setShot(shot, 'miss')"
                                    :class="[
                                        'flex-1 rounded-md py-2 text-xs font-bold uppercase tracking-wider transition',
                                        shot.override === 'miss' || (shot.override === null && shot.decoded === 'miss') ? 'bg-red-600 text-white' : 'border border-slate-600 text-slate-400 hover:text-white',
                                    ]"
                                >Miss</button>
                                <button
                                    @click="setShot(shot, 'blank')"
                                    :class="[
                                        'flex-1 rounded-md py-2 text-xs font-bold uppercase tracking-wider transition',
                                        shot.resolvedResult === 'blank' ? 'bg-slate-600 text-white' : 'border border-slate-600 text-slate-400 hover:text-white',
                                    ]"
                                >Leave</button>
                            </div>
                            <div class="w-24 shrink-0 text-right text-[10px] text-slate-500">
                                <span v-if="shot.existing" class="block text-slate-400">
                                    was {{ shot.existing.result }}
                                </span>
                                <span v-if="shot.confidence === 'unclear'" class="block text-amber-400 font-semibold uppercase tracking-wider">Check</span>
                                <span v-else-if="shot.decoded === 'conflict'" class="block text-rose-400 font-semibold uppercase tracking-wider">Conflict</span>
                                <span v-else-if="shot.decoded === 'blank'" class="block text-slate-500">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="sticky bottom-0 -mx-4 border-t border-slate-700 bg-slate-800 px-4 py-3">
                    <button
                        @click="confirmImport"
                        :disabled="!canConfirm || importing"
                        class="w-full rounded-xl bg-emerald-600 py-3.5 text-base font-bold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <span v-if="importing">Importing…</span>
                        <span v-else-if="needsShooter">Pick a shooter for this spare card</span>
                        <span v-else-if="counts.unresolved > 0">Resolve {{ counts.unresolved }} more to continue</span>
                        <span v-else-if="writable === 0">Nothing new to import</span>
                        <span v-else>Confirm import · {{ writable }} {{ writable === 1 ? 'shot' : 'shots' }}</span>
                    </button>
                    <button
                        @click="resetScan"
                        class="mt-2 w-full rounded-md border border-slate-600 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700"
                    >
                        Cancel
                    </button>
                </div>

                <p v-if="errorMessage" class="rounded-md bg-red-900/40 px-3 py-2 text-sm text-red-300">{{ errorMessage }}</p>
            </section>

            <!-- ── Step 3: done ── -->
            <section v-else-if="step === 'done'" class="rounded-xl border border-emerald-700/40 bg-emerald-900/10 p-6 text-center">
                <svg class="mx-auto h-10 w-10 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <h2 class="mt-3 text-lg font-bold text-emerald-300">Card imported</h2>
                <p class="mt-1 text-sm text-emerald-200/80">
                    {{ lastImported }} {{ lastImported === 1 ? 'shot was' : 'shots were' }} written for {{ lastShooterName }}.
                </p>
                <button
                    @click="resetScan"
                    class="mt-4 w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white hover:bg-emerald-700"
                >
                    Scan next card
                </button>
            </section>

            <!-- Decoding spinner overlay -->
            <div v-if="loading" class="rounded-xl border border-slate-700 bg-slate-800 p-6 text-center text-slate-400">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-emerald-500"></div>
                <p class="mt-3 text-sm">{{ loadingMessage }}</p>
            </div>
        </main>

        <!-- Off-screen canvas used for sampling -->
        <canvas ref="canvas" class="hidden" aria-hidden="true"></canvas>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import BackLink from '../components/BackLink.vue';
import { useMatchStore } from '../stores/matchStore';
import {
    sampleBubble,
    decidePair,
    parseQrPayload,
    mmToPixel,
    FILLED_THRESHOLD,
    BLANK_THRESHOLD,
} from '../lib/scoreCardDecoder';

const props = defineProps({
    matchId: { type: Number, required: true },
});

const matchStore = useMatchStore();

const step = ref('capture');
const loading = ref(false);
const loadingMessage = ref('');
const errorMessage = ref('');
const sheet = ref(null); // from API: {token, shooter, layout, existing_shots, is_spare, assignable_shooters, ...}
const reviewRows = ref([]); // per-target with per-shot decoded state
const manualToken = ref('');
const importing = ref(false);
const lastImported = ref(0);
const lastShooterName = ref('');
const canvas = ref(null);
const assignShooterId = ref(null); // picked in the UI for a spare card

// Stable per-device id so audit logs show which phone did the import.
const deviceId = (() => {
    const key = 'dc-score-card-device';
    let id = localStorage.getItem(key);
    if (!id) {
        id = 'card-scan-' + Math.random().toString(36).slice(2, 10);
        localStorage.setItem(key, id);
    }
    return id;
})();

onMounted(async () => {
    if (!matchStore.currentMatch || matchStore.currentMatch.id !== props.matchId) {
        await matchStore.fetchMatch(props.matchId);
    }
});

function resetScan() {
    step.value = 'capture';
    sheet.value = null;
    reviewRows.value = [];
    errorMessage.value = '';
    manualToken.value = '';
    lastImported.value = 0;
    lastShooterName.value = '';
    assignShooterId.value = null;
}

async function loadByToken(token) {
    const clean = (token || '').trim().toUpperCase();
    if (!clean) {
        errorMessage.value = 'Enter a card ID.';
        return;
    }
    loading.value = true;
    loadingMessage.value = 'Loading card…';
    errorMessage.value = '';
    try {
        const { data } = await axios.get(`/api/matches/${props.matchId}/score-sheets/${encodeURIComponent(clean)}`);
        sheet.value = data.sheet;
        reviewRows.value = buildReviewRows(sheet.value, /* decoded fills */ null);
        step.value = 'review';
    } catch (err) {
        errorMessage.value = friendlyError(err, 'Could not load that card.');
    } finally {
        loading.value = false;
    }
}

async function onPhotoCaptured(event) {
    const file = event.target.files?.[0];
    event.target.value = ''; // let the same file re-trigger if the user retakes
    if (!file) return;
    loading.value = true;
    loadingMessage.value = 'Reading card…';
    errorMessage.value = '';
    try {
        const img = await loadImageFromFile(file);
        const { imageData, width, height } = drawToCanvas(img);

        // Decode QR with BarcodeDetector. Not every browser supports it; we
        // fall back to asking the user to enter the card ID by hand.
        const qr = await detectQr(img, imageData, width, height);
        if (!qr) {
            errorMessage.value = 'Could not read the QR on the card. Try again with better lighting, or enter the card ID by hand below.';
            step.value = 'capture';
            return;
        }

        const token = parseQrPayload(qr.rawValue);
        if (!token) {
            errorMessage.value = 'The QR is not a DeadCenter score card. Make sure you photographed the printed card.';
            step.value = 'capture';
            return;
        }

        loadingMessage.value = 'Looking up card…';
        const { data } = await axios.get(`/api/matches/${props.matchId}/score-sheets/${encodeURIComponent(token)}`);
        sheet.value = data.sheet;

        loadingMessage.value = 'Reading boxes…';
        const sampled = sampleAllBubbles(sheet.value.layout, imageData, qr.pixelBox);
        reviewRows.value = buildReviewRows(sheet.value, sampled);
        step.value = 'review';
    } catch (err) {
        errorMessage.value = friendlyError(err, 'Something went wrong reading that photo.');
    } finally {
        loading.value = false;
    }
}

function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Image failed to load')); };
        img.src = url;
    });
}

function drawToCanvas(img) {
    // Downscale giant phone photos (12MP+) before sampling. The scan
    // block only needs ~1200px wide to be accurate and the whole page
    // is substantially faster at that resolution.
    const maxW = 2200;
    const scale = img.width > maxW ? maxW / img.width : 1;
    const width = Math.round(img.width * scale);
    const height = Math.round(img.height * scale);

    const c = canvas.value;
    c.width = width;
    c.height = height;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, width, height);
    const imageData = ctx.getImageData(0, 0, width, height);
    return { imageData, width, height };
}

async function detectQr(img, imageData, width, height) {
    if (typeof window.BarcodeDetector === 'function') {
        try {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            if (supported.includes('qr_code')) {
                const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                const results = await detector.detect(img);
                if (results && results.length > 0) {
                    const r = results[0];
                    const box = r.boundingBox || { x: 0, y: 0, width: 0, height: 0 };
                    // Scale the detected box into the (possibly downscaled) canvas coordinate system.
                    const sx = width / img.width;
                    const sy = height / img.height;
                    return {
                        rawValue: r.rawValue,
                        pixelBox: {
                            x: box.x * sx,
                            y: box.y * sy,
                            width: box.width * sx,
                            height: box.height * sy,
                        },
                    };
                }
            }
        } catch {
            // fall through to the null return below
        }
    }
    return null;
}

function sampleAllBubbles(layout, imageData, qrPixel) {
    const qrMm = layout.qr;
    const sampleRadius = Math.max(3, Math.round((layout.bubble.diameter / 2) * (qrPixel.width / qrMm.size) * 0.55));

    const out = {};
    for (const row of layout.rows) {
        const shots = [];
        for (const s of row.shots) {
            const hitPx = mmToPixel(s.hit.x, s.hit.y, qrMm, qrPixel);
            const missPx = mmToPixel(s.miss.x, s.miss.y, qrMm, qrPixel);
            shots.push({
                shotNumber: s.shot_number,
                hitFill: sampleBubble(imageData, hitPx.x, hitPx.y, sampleRadius),
                missFill: sampleBubble(imageData, missPx.x, missPx.y, sampleRadius),
            });
        }
        out[row.target_id] = shots;
    }
    return out;
}

function buildReviewRows(apiSheet, sampled) {
    const existingByKey = new Map();
    for (const e of apiSheet.existing_shots ?? []) {
        existingByKey.set(`${e.elr_target_id}-${e.shot_number}`, e);
    }

    return apiSheet.layout.rows.map((row) => {
        const shots = row.shots.map((s) => {
            const sampledShot = sampled?.[row.target_id]?.find(x => x.shotNumber === s.shot_number) ?? null;
            const decisionInput = sampledShot ?? { hitFill: 0, missFill: 0 };
            const decision = decidePair(decisionInput.hitFill, decisionInput.missFill);
            const existing = existingByKey.get(`${row.target_id}-${s.shot_number}`) ?? null;

            return reactive({
                targetId: row.target_id,
                shotNumber: s.shot_number,
                hitFill: decisionInput.hitFill,
                missFill: decisionInput.missFill,
                decoded: decision.result,          // 'hit' | 'miss' | 'blank' | 'conflict'
                confidence: decision.confidence,   // 'confident' | 'unclear'
                override: null,                    // null | 'hit' | 'miss' | 'blank'
                existing,
                get resolvedResult() {
                    if (this.override !== null) return this.override;
                    if (this.decoded === 'hit' || this.decoded === 'miss') return this.decoded;
                    // Blank / conflict / unclear → not yet resolved
                    return this.decoded;
                },
            });
        });

        return {
            targetId: row.target_id,
            label: row.target_label,
            distance: row.distance_m,
            block: row.alrha_block,
            isColdBore: row.is_cold_bore,
            maxShots: row.max_shots,
            shots,
        };
    });
}

function setShot(shot, choice) {
    shot.override = choice;
}

const counts = computed(() => {
    let hit = 0, miss = 0, blank = 0, unresolved = 0, cbcHit = 0;
    for (const row of reviewRows.value) {
        for (const s of row.shots) {
            const r = effectiveResult(s);
            if (r === 'hit') { hit++; if (row.isColdBore) cbcHit++; }
            else if (r === 'miss') miss++;
            else if (r === 'blank') blank++;
            else unresolved++; // 'conflict' or an unresolved 'unclear'
        }
    }
    return { hit, miss, blank, unresolved, cbcHit };
});

// A shot is "writable" when the MD intends to post it: hit or miss with
// an explicit choice (either the decoder was confident OR the MD picked
// one). Blanks and still-unclear shots are NOT written.
const writable = computed(() => {
    let n = 0;
    for (const row of reviewRows.value) {
        for (const s of row.shots) {
            if (isWritable(s)) n++;
        }
    }
    return n;
});

// Spare cards also need a shooter picked before confirm is enabled.
// Assigned cards only need every shot resolved.
const needsShooter = computed(() => !!(sheet.value?.is_spare) && !assignShooterId.value);
const canConfirm = computed(() => counts.value.unresolved === 0 && !needsShooter.value);

function effectiveResult(shot) {
    if (shot.override !== null) return shot.override;
    if (shot.decoded === 'hit' || shot.decoded === 'miss') {
        return shot.confidence === 'confident' ? shot.decoded : 'unresolved';
    }
    if (shot.decoded === 'blank') return 'blank';
    return 'unresolved'; // conflict or unclear
}

function isWritable(shot) {
    const r = effectiveResult(shot);
    return r === 'hit' || r === 'miss';
}

async function confirmImport() {
    if (!canConfirm.value || importing.value || !sheet.value) return;

    importing.value = true;
    errorMessage.value = '';

    try {
        // Spare card: claim it first. The server does the atomic
        // "assign only if unclaimed" update. If we lose the race we
        // reload the sheet so the UI reflects the actual bound shooter.
        if (sheet.value.is_spare) {
            try {
                await axios.post(
                    `/api/matches/${props.matchId}/score-sheets/${encodeURIComponent(sheet.value.token)}/claim`,
                    { shooter_id: assignShooterId.value },
                );
            } catch (err) {
                if (err?.response?.status === 409) {
                    errorMessage.value = 'Another device assigned this card first. Reloading…';
                    await reloadSheet();
                    return;
                }
                throw err;
            }
            // Refetch so sheet.shooter + existing_shots reflect the binding.
            await reloadSheet();
        }

        // Shots are posted against the (now-bound) shooter.
        const shooterId = sheet.value.shooter?.id;
        if (!shooterId) {
            errorMessage.value = 'Card has no shooter assigned.';
            return;
        }

        const shots = [];
        const now = new Date().toISOString();
        for (const row of reviewRows.value) {
            for (const s of row.shots) {
                if (!isWritable(s)) continue;
                shots.push({
                    shooter_id: shooterId,
                    elr_target_id: s.targetId,
                    shot_number: s.shotNumber,
                    result: effectiveResult(s),
                    device_id: 'score-sheet:' + sheet.value.token,
                    recorded_at: now,
                });
            }
        }

        if (shots.length === 0) {
            // Pure assign-with-no-shots: still a valid outcome for a
            // freshly-claimed spare. Jump straight to the done screen.
            lastImported.value = 0;
            lastShooterName.value = sheet.value.shooter.name;
            step.value = 'done';
            return;
        }

        await axios.post(`/api/matches/${props.matchId}/elr-shots`, {
            shots,
            device_id: 'score-sheet:' + sheet.value.token,
        });
        lastImported.value = shots.length;
        lastShooterName.value = sheet.value.shooter.name;
        step.value = 'done';
    } catch (err) {
        errorMessage.value = friendlyError(err, 'Import failed.');
    } finally {
        importing.value = false;
    }
}

async function reloadSheet() {
    if (!sheet.value) return;
    const { data } = await axios.get(
        `/api/matches/${props.matchId}/score-sheets/${encodeURIComponent(sheet.value.token)}`,
    );
    sheet.value = data.sheet;
    // Keep the user's per-shot overrides; just refresh existing_shots.
    const existingByKey = new Map();
    for (const e of sheet.value.existing_shots ?? []) {
        existingByKey.set(`${e.elr_target_id}-${e.shot_number}`, e);
    }
    for (const row of reviewRows.value) {
        for (const s of row.shots) {
            s.existing = existingByKey.get(`${s.targetId}-${s.shotNumber}`) ?? null;
        }
    }
}

function friendlyError(err, fallback) {
    const status = err?.response?.status;
    const msg = err?.response?.data?.message;
    if (status === 423) return msg || 'Match is marked complete. Reopen it before importing more scores.';
    if (status === 422) return msg || 'The server rejected part of the import. Double-check the card.';
    if (status === 409) return msg || 'This spare card was assigned to someone else just now.';
    if (status === 404) return msg || 'Card not found. Check the ID or rescan the QR.';
    if (status === 403) return 'You are not authorized to score this match.';
    return msg || fallback;
}
</script>

// Pure-function tests for the ALRHA score card decoder.
//
// Covers the plan requirement: "The bubble decision is a pure function
// of sampled brightness, tested without a camera."
//
// Run: node --test resources/js/scoring-app/lib/__tests__/*.test.mjs
// (Also wired as `npm run test:js`.)

import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    sampleBubble,
    decidePair,
    parseQrPayload,
    mmToPixel,
    FILLED_THRESHOLD,
    BLANK_THRESHOLD,
    DOMINANCE_MARGIN,
} from '../scoreCardDecoder.js';

// ── decidePair ──────────────────────────────────────────────────

test('blank pair → no change', () => {
    const d = decidePair(0.05, 0.08);
    assert.deepEqual(d, { result: 'blank', confidence: 'confident' });
});

test('confident hit when only Hit is filled and Miss is blank', () => {
    const d = decidePair(0.75, 0.05);
    assert.deepEqual(d, { result: 'hit', confidence: 'confident' });
});

test('confident miss when only Miss is filled and Hit is blank', () => {
    const d = decidePair(0.05, 0.80);
    assert.deepEqual(d, { result: 'miss', confidence: 'confident' });
});

test('both filled with a clear winner → unclear but decided', () => {
    const d = decidePair(0.85, 0.55);
    assert.equal(d.result, 'hit');
    assert.equal(d.confidence, 'unclear');
});

test('both filled nearly equally → conflict (MD must pick)', () => {
    const d = decidePair(0.70, 0.68);
    assert.equal(d.result, 'conflict');
    assert.equal(d.confidence, 'unclear');
});

test('faint marks (above blank, below filled) still decided by margin', () => {
    const d = decidePair(0.35, 0.10);
    assert.equal(d.result, 'hit');
    assert.equal(d.confidence, 'unclear');
});

test('two faint marks within margin → conflict', () => {
    const d = decidePair(0.30, 0.28);
    assert.equal(d.result, 'conflict');
});

test('threshold constants are in the expected range', () => {
    assert.ok(FILLED_THRESHOLD > BLANK_THRESHOLD);
    assert.ok(DOMINANCE_MARGIN > 0);
});

// ── sampleBubble ────────────────────────────────────────────────

/**
 * Build a fake ImageData-like object filled with a uniform grey value.
 */
function uniformImage(width, height, gray) {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let i = 0; i < data.length; i += 4) {
        data[i] = gray;
        data[i + 1] = gray;
        data[i + 2] = gray;
        data[i + 3] = 255;
    }
    return { width, height, data };
}

test('sampleBubble reports ~0 on pure white paper', () => {
    const img = uniformImage(30, 30, 255);
    // Allow a fp-epsilon slop; the per-pixel math accumulates a
    // trailing 1e-16 that strict equality trips over.
    assert.ok(Math.abs(sampleBubble(img, 15, 15, 3)) < 1e-10);
});

test('sampleBubble reports ~1 on fully inked black', () => {
    const img = uniformImage(30, 30, 0);
    assert.ok(Math.abs(sampleBubble(img, 15, 15, 3) - 1) < 1e-10);
});

test('sampleBubble clamps out-of-bounds coordinates without throwing', () => {
    const img = uniformImage(10, 10, 255);
    // Sampling well outside the image is a no-op (treated as paper).
    assert.equal(sampleBubble(img, 1000, 1000, 3), 0);
});

// ── parseQrPayload ──────────────────────────────────────────────

test('parseQrPayload accepts the printed format and uppercases the token', () => {
    assert.equal(parseQrPayload('DCS1:7h2k9q4r'), '7H2K9Q4R');
});

test('parseQrPayload rejects a non-DeadCenter QR', () => {
    assert.equal(parseQrPayload('https://example.com'), null);
    assert.equal(parseQrPayload(null), null);
    assert.equal(parseQrPayload(''), null);
});

test('parseQrPayload rejects a well-formed prefix with an illegal token', () => {
    assert.equal(parseQrPayload('DCS1:abc'), null);        // too short
    assert.equal(parseQrPayload('DCS1:ok#$%'), null);      // bad chars
});

// ── mmToPixel ───────────────────────────────────────────────────

test('mmToPixel maps the QR corner to itself', () => {
    const qrLayout = { x: 100, y: 10, size: 20 };
    const qrPixel = { x: 400, y: 30, width: 80, height: 80 };
    const p = mmToPixel(qrLayout.x, qrLayout.y, qrLayout, qrPixel);
    assert.equal(p.x, 400);
    assert.equal(p.y, 30);
});

test('mmToPixel scales mm offsets by the QR pixel size', () => {
    const qrLayout = { x: 100, y: 10, size: 20 };    // QR is 20mm on paper
    const qrPixel = { x: 0, y: 0, width: 100, height: 100 }; // 20mm == 100px → 5 px/mm
    const p = mmToPixel(110, 15, qrLayout, qrPixel); // 10mm right, 5mm down
    assert.equal(p.x, 50);
    assert.equal(p.y, 25);
});

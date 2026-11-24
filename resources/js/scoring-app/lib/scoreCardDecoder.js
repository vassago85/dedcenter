// Pure helpers for ALRHA mark-sense score card decoding. Lives in its
// own file so the per-bubble decision is a function of numbers only —
// no camera, no canvas, no DOM — and tests can drive it with hand-rolled
// samples.
//
// The whole decode pipeline is split into two steps:
//
//   1. sampleBubble()  — takes raw image data and the pixel center of
//                        one bubble; returns a `fillRatio` in [0, 1].
//                        0 = paper (white), 1 = fully inked.
//
//   2. decidePair()    — takes the two fillRatio values for a Hit/Miss
//                        pair and returns one of:
//                           { result: 'hit',    confidence: 'confident' | 'unclear' }
//                           { result: 'miss',   confidence: 'confident' | 'unclear' }
//                           { result: 'blank',  confidence: 'confident' }
//                           { result: 'conflict', confidence: 'unclear' }
//
// A blank pair is distinct from an undecided one. Blank MUST leave the
// stored shot alone — the shooter just hasn't fired that shot yet. A
// conflict means both boxes look filled and the MD has to pick.

// Thresholds are deliberately conservative. The card prints with
// 1.2pt-wide bubble outlines, so an empty bubble reads ~0.10–0.15 fill
// from the circle itself. A pen fill on paper lands around 0.5–0.9.
export const FILLED_THRESHOLD = 0.45;
export const BLANK_THRESHOLD = 0.22;
// Difference-of-boxes gate: when both look filled, require the stronger
// one to beat the other by this margin before calling it; otherwise flag
// as a conflict so the MD resolves it.
export const DOMINANCE_MARGIN = 0.15;

/**
 * Compute a fill ratio for a single bubble by sampling a square of
 * pixels around its center.
 *
 * `imageData` is a standard `ImageData` object (as produced by a canvas
 * `getImageData` call). `cx` / `cy` are the center in CSS pixels on
 * that image. `radiusPx` is how many pixels out from the center to
 * sample on each side; the sample box is `(2r+1) × (2r+1)`.
 *
 * Returns a normalized `fillRatio`:
 *   0  — pure paper (white)
 *   1  — fully inked (black)
 * The function clamps pixel coordinates to the image bounds so a wonky
 * mapping doesn't throw.
 *
 * @param {ImageData} imageData
 * @param {number} cx
 * @param {number} cy
 * @param {number} radiusPx
 * @returns {number}
 */
export function sampleBubble(imageData, cx, cy, radiusPx) {
    const { width, height, data } = imageData;
    if (!width || !height) return 0;

    const r = Math.max(1, Math.round(radiusPx));
    const minX = Math.max(0, Math.round(cx) - r);
    const maxX = Math.min(width - 1, Math.round(cx) + r);
    const minY = Math.max(0, Math.round(cy) - r);
    const maxY = Math.min(height - 1, Math.round(cy) + r);

    if (maxX < minX || maxY < minY) return 0;

    let total = 0;
    let count = 0;
    for (let y = minY; y <= maxY; y++) {
        for (let x = minX; x <= maxX; x++) {
            const idx = (y * width + x) * 4;
            // Rec. 709 luminance; alpha is ignored because the frame is
            // always fully opaque.
            const lum = (data[idx] * 0.2126 + data[idx + 1] * 0.7152 + data[idx + 2] * 0.0722);
            total += (255 - lum) / 255; // invert: black = 1, white = 0
            count++;
        }
    }

    if (count === 0) return 0;
    return total / count;
}

/**
 * Decide the outcome of one Hit/Miss pair from two fill ratios.
 *
 * The function has no knowledge of the shot or the shooter — it just
 * looks at the two sampled values and labels the pair. Downstream code
 * decides what to do with that label (post to the server, block the
 * confirm button, leave the stored shot alone, etc.).
 *
 * @param {number} hitFill
 * @param {number} missFill
 * @returns {{ result: 'hit'|'miss'|'blank'|'conflict', confidence: 'confident'|'unclear' }}
 */
export function decidePair(hitFill, missFill) {
    const hitFilled = hitFill >= FILLED_THRESHOLD;
    const missFilled = missFill >= FILLED_THRESHOLD;
    const hitBlank = hitFill < BLANK_THRESHOLD;
    const missBlank = missFill < BLANK_THRESHOLD;

    // Both clearly blank → the shot wasn't marked. Don't invent a miss,
    // and don't overwrite a stored shot.
    if (hitBlank && missBlank) {
        return { result: 'blank', confidence: 'confident' };
    }

    // Both look filled → need a strong winner, otherwise ask the MD.
    if (hitFilled && missFilled) {
        const margin = hitFill - missFill;
        if (margin > DOMINANCE_MARGIN) return { result: 'hit', confidence: 'unclear' };
        if (-margin > DOMINANCE_MARGIN) return { result: 'miss', confidence: 'unclear' };
        return { result: 'conflict', confidence: 'unclear' };
    }

    // One side clearly wins.
    if (hitFilled && !missFilled) {
        return { result: 'hit', confidence: missBlank ? 'confident' : 'unclear' };
    }
    if (missFilled && !hitFilled) {
        return { result: 'miss', confidence: hitBlank ? 'confident' : 'unclear' };
    }

    // Neither reaches the filled threshold but at least one is above
    // the blank threshold — a faint mark. Let the MD confirm.
    if (hitFill > missFill + DOMINANCE_MARGIN) return { result: 'hit', confidence: 'unclear' };
    if (missFill > hitFill + DOMINANCE_MARGIN) return { result: 'miss', confidence: 'unclear' };
    return { result: 'conflict', confidence: 'unclear' };
}

/**
 * Extract the card token from a QR payload emitted by the printed PDF.
 * Returns the token on success, `null` on failure.
 *
 * @param {string|null|undefined} payload
 * @returns {string|null}
 */
export function parseQrPayload(payload) {
    if (typeof payload !== 'string') return null;
    const trimmed = payload.trim().toUpperCase();
    if (!trimmed.startsWith('DCS1:')) return null;
    const token = trimmed.slice(5);
    if (!/^[A-Z0-9]{8,32}$/.test(token)) return null;
    return token;
}

/**
 * Map a (mm, mm) point on the printed layout to pixels on the captured
 * image, using the QR's known printed position + size and its detected
 * position + size in pixels as the only anchor.
 *
 * We assume the capture is roughly axis-aligned (shooter / MD is told
 * to frame the card square). A tilted capture will drift the bubbles
 * further from the QR, which is fine because the MD reviews every pair
 * before confirming anything.
 *
 * @param {number} xMm
 * @param {number} yMm
 * @param {{x:number,y:number,size:number}} qrLayout  QR printed box in mm.
 * @param {{x:number,y:number,width:number,height:number}} qrPixel  QR detected box in px.
 * @returns {{x:number, y:number}}
 */
export function mmToPixel(xMm, yMm, qrLayout, qrPixel) {
    const scaleX = qrPixel.width / qrLayout.size;
    const scaleY = qrPixel.height / qrLayout.size;
    const dxMm = xMm - qrLayout.x;
    const dyMm = yMm - qrLayout.y;
    return {
        x: qrPixel.x + dxMm * scaleX,
        y: qrPixel.y + dyMm * scaleY,
    };
}

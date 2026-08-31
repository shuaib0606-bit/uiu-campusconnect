/** Facade textures: punched windows drawn to a canvas, as colour and emissive maps. */

import * as THREE from 'three';

/** Window modules across one tile. Zones pick a whole-module repeat from this. */
export const MODULES_PER_TILE = 16;

/** Target width of one window module, in metres. */
export const MODULE_WIDTH = 2.2;

/** Fraction of a storey taken by the solid spandrel above each window run. */
const SPANDREL = 0.30;

/** Fraction of windows that can light at all. */
const LIT_FRACTION = 0.88;

/** Deterministic noise, so the building looks the same on every reload. */
function makeRandom(seed) {
    let s = seed >>> 0;
    return () => {
        s = (s * 1664525 + 1013904223) >>> 0;
        return s / 4294967296;
    };
}

/**
 * @param {number} rows  upper storeys the texture spans
 * @returns {{map: THREE.CanvasTexture, emissiveMap: THREE.CanvasTexture}}
 */
export function createFacadeTextures(rows, options = {}) {
    const size = options.resolution || 1024;
    const seed = options.seed || 20030101;

    const colour = document.createElement('canvas');
    colour.width = size;
    colour.height = size;
    const c = colour.getContext('2d');

    const glow = document.createElement('canvas');
    glow.width = size;
    glow.height = size;
    const g = glow.getContext('2d');

    const cellW = size / MODULES_PER_TILE;
    const cellH = size / rows;
    const random = makeRandom(seed);

    // Base: the concrete the windows are punched into.
    c.fillStyle = '#9d9c90';
    c.fillRect(0, 0, size, size);

    g.fillStyle = '#000000';
    g.fillRect(0, 0, size, size);

    for (let row = 0; row < rows; row++) {
        const top = row * cellH;
        const spandrelH = cellH * SPANDREL;
        const openingTop = top + spandrelH;
        const openingH = cellH - spandrelH;

        // Shadow line under each spandrel.
        c.fillStyle = 'rgba(0,0,0,0.16)';
        c.fillRect(0, openingTop, size, Math.max(1, cellH * 0.05));

        for (let col = 0; col < MODULES_PER_TILE; col++) {
            const left = col * cellW;

            // Mullion either side of the opening.
            const inset = cellW * 0.11;
            const x = left + inset;
            const w = cellW - inset * 2;
            const y = openingTop + openingH * 0.12;
            const h = openingH * 0.74;

            // Recess shadow around the opening.
            c.fillStyle = 'rgba(0,0,0,0.34)';
            c.fillRect(x - 1, y - 1, w + 2, h + 2);

            // The glass, tinted and varied cell to cell.
            const tint = 0.72 + random() * 0.5;
            const r = Math.round(58 * tint);
            const gg = Math.round(72 * tint);
            const b = Math.round(84 * tint);
            c.fillStyle = `rgb(${r},${gg},${b})`;
            c.fillRect(x, y, w, h);

            // Horizontal transom on most units.
            if (random() < 0.75) {
                c.fillStyle = 'rgba(180,180,170,0.5)';
                c.fillRect(x, y + h * 0.42, w, Math.max(1, h * 0.05));
            }

            // Blinds or curtains: a pale block filling part of the pane.
            if (random() < 0.42) {
                const bh = h * (0.3 + random() * 0.45);
                c.fillStyle = `rgba(226,224,213,${0.55 + random() * 0.4})`;
                c.fillRect(x, y, w, bh);
            }

            // Air conditioner slung under the sill.
            if (random() < 0.3) {
                const aw = w * 0.42;
                c.fillStyle = 'rgba(198,196,186,0.95)';
                c.fillRect(x + w * 0.5 - aw / 2, y + h - 2, aw, openingH * 0.13);
            }

            // Vertical mullion down the middle of wider units.
            if (random() < 0.55) {
                c.fillStyle = 'rgba(170,170,160,0.45)';
                c.fillRect(x + w * 0.5, y, Math.max(1, w * 0.04), h);
            }

            // The same cell on the emissive map: only the glass area emits.
            if (random() < LIT_FRACTION) {
                const level = 0.55 + random() * 0.45;
                g.fillStyle = `rgba(255,255,255,${level})`;
                g.fillRect(x, y, w, h);

                // A lit room with the blind down is dimmer, not dark.
                if (random() < 0.35) {
                    g.fillStyle = `rgba(255,255,255,${level * 0.45})`;
                    g.fillRect(x, y, w, h * 0.4);
                }
            }
        }
    }

    const map = new THREE.CanvasTexture(colour);
    map.colorSpace = THREE.SRGBColorSpace;
    map.wrapS = THREE.RepeatWrapping;
    map.wrapT = THREE.ClampToEdgeWrapping;
    map.anisotropy = 8;

    const emissiveMap = new THREE.CanvasTexture(glow);
    emissiveMap.colorSpace = THREE.SRGBColorSpace;
    emissiveMap.wrapS = THREE.RepeatWrapping;
    emissiveMap.wrapT = THREE.ClampToEdgeWrapping;
    emissiveMap.anisotropy = 8;

    return { map, emissiveMap };
}

/** The curtain wall for C2, the centre's main front portion. */
export function createCurtainWallTextures(rows, options = {}) {
    const size = options.resolution || 1024;
    const seed = options.seed || 20180201;

    const colour = document.createElement('canvas');
    colour.width = size;
    colour.height = size;
    const c = colour.getContext('2d');

    const glow = document.createElement('canvas');
    glow.width = size;
    glow.height = size;
    const g = glow.getContext('2d');

    const cellW = size / MODULES_PER_TILE;
    const cellH = size / rows;
    const random = makeRandom(seed);

    // Base glass, greener and darker than the punched glazing.
    c.fillStyle = '#3f5a55';
    c.fillRect(0, 0, size, size);

    g.fillStyle = '#000000';
    g.fillRect(0, 0, size, size);

    for (let row = 0; row < rows; row++) {
        const top = row * cellH;

        // Spandrel: the opaque band hiding the floor slab.
        const spandrelH = cellH * 0.16;
        c.fillStyle = 'rgba(58,68,66,0.92)';
        c.fillRect(0, top, size, spandrelH);

        // Transom under the spandrel.
        c.fillStyle = 'rgba(196,200,196,0.42)';
        c.fillRect(0, top + spandrelH, size, Math.max(1, cellH * 0.022));

        for (let col = 0; col < MODULES_PER_TILE; col++) {
            const x = col * cellW;
            const paneTop = top + spandrelH + cellH * 0.03;
            const paneH = cellH - spandrelH - cellH * 0.05;

            // Panes vary slightly.
            const tint = 0.82 + random() * 0.4;
            c.fillStyle = `rgba(${Math.round(74 * tint)},${Math.round(102 * tint)},${Math.round(97 * tint)},1)`;
            c.fillRect(x + 1, paneTop, cellW - 2, paneH);

            // A few panes catch the sky harder than their neighbours.
            if (random() < 0.22) {
                c.fillStyle = `rgba(168,196,192,${0.10 + random() * 0.16})`;
                c.fillRect(x + 1, paneTop, cellW - 2, paneH * (0.35 + random() * 0.5));
            }

            // Slim vertical mullion.
            c.fillStyle = 'rgba(206,209,204,0.55)';
            c.fillRect(x, top, Math.max(1, cellW * 0.055), cellH);

            // Emissive: an atrium is mostly lit, with occasional dimmer bays.
            const level = random() < 0.86 ? 0.6 + random() * 0.4 : 0.18 + random() * 0.2;
            g.fillStyle = `rgba(255,255,255,${level})`;
            g.fillRect(x + 1, paneTop, cellW - 2, paneH);
        }
    }

    const map = new THREE.CanvasTexture(colour);
    map.colorSpace = THREE.SRGBColorSpace;
    map.wrapS = THREE.RepeatWrapping;
    map.wrapT = THREE.ClampToEdgeWrapping;
    map.anisotropy = 8;

    const emissiveMap = new THREE.CanvasTexture(glow);
    emissiveMap.colorSpace = THREE.SRGBColorSpace;
    emissiveMap.wrapS = THREE.RepeatWrapping;
    emissiveMap.wrapT = THREE.ClampToEdgeWrapping;
    emissiveMap.anisotropy = 8;

    return { map, emissiveMap };
}

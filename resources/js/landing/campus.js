/**
 * The UIU permanent campus, built procedurally from the ZONES table below.
 * Zones across the front, left to right:
 *     L1 · L2 · [CN] · C1 · C2 · C3 · [SC] · R2 · R3 · R4 · garage
 * Facade depths, all at or in front of the zone's front face FZ:
 *     FZ         the zone's front face
 *     FZ + 0.35  the textured facade plane
 *     FZ + 1.3   floor bands
 */

import * as THREE from 'three';
import { createFacadeTextures, createCurtainWallTextures, MODULES_PER_TILE, MODULE_WIDTH } from './facade.js';
import { createFoliageGeometry, createLeafTexture } from './world.js';

// Vertical

const GROUND_HEIGHT = 4.8;   // metres, ground floor
const UPPER_FLOORS = 10;
const FLOOR_HEIGHT = 3.6;    // metres per upper storey
const UPPER_HEIGHT = UPPER_FLOORS * FLOOR_HEIGHT;
const TOP = GROUND_HEIGHT + UPPER_HEIGHT;

/** Height of floor n above ground level, in metres. */
const floorLevel = (n) => (n === 0 ? 0 : GROUND_HEIGHT + (n - 1) * FLOOR_HEIGHT);

/** Top habitable floor; the roof slab is TOP, one storey higher. */
const TOP_FLOOR = floorLevel(UPPER_FLOORS);

export const FLOORS = UPPER_FLOORS + 1;
export const CAMPUS_HEIGHT = TOP;

// Depth: every zone shares the back wall at BACK; only the front face moves.

const BACK = -22;
const DEPTH = {
    front: 14,       // L2, R2, the forward-most portions
    centreFront: 8,  // C2
    mid: 3,          // L1, C1, C3, R3
    outer: -2,       // R4
    connector: -6,   // the link zones, recessed furthest
};

const GARAGE_WIDTH = 9;   // bike garage butted onto R4's end, metres

// Depth of the stair section of a stairFlank side, metres.
const STAIR_FLANK_RUN = 7;

const ENTRANCE_DECK = 0.9;   // top of the centre entrance deck, metres

// Plan. Widths are in window modules; `voidFloors` opens storeys above the
// ground floor; `stairFlank` marks a side (-1 left, +1 right) carrying a stair.
const ZONES = [
    { key: 'L1', width: 8.5, depth: 'mid', role: 'wing' },
    { key: 'L2', width: 15.4, depth: 'front', role: 'wing', stairFlank: 1 },
    { key: 'CN', width: 10, depth: 'connector', role: 'connector' },
    { key: 'C1', width: 3.9, depth: 'mid', role: 'centre', voidFloors: 2 },
    { key: 'C2', width: 8.5, depth: 'centreFront', role: 'centreFront', voidFloors: 2 },
    { key: 'C3', width: 6.2, depth: 'mid', role: 'centre', voidFloors: 2 },
    { key: 'SC', width: 10, depth: 'connector', role: 'stair' },
    { key: 'R2', width: 15.4, depth: 'front', role: 'wing' },
    { key: 'R3', width: 8.5, depth: 'mid', role: 'wing' },
    { key: 'R4', width: 8.5, depth: 'outer', role: 'wing' },
];

export const CAMPUS_WIDTH = ZONES.reduce((sum, z) => sum + z.width, 0);

/** Built mass including the garage. The camera frames on this. */
export const CAMPUS_SPAN = CAMPUS_WIDTH + GARAGE_WIDTH;

{
    let cursor = -CAMPUS_WIDTH / 2;
    for (const zone of ZONES) {
        zone.x = cursor + zone.width / 2;
        zone.front = DEPTH[zone.depth];
        zone.thickness = zone.front - BACK;

        //   voidBase   terrace the open floors sit on, top of the ground storey
        //   base       bottom of the solid mass above the void
        //   wallBase   where the upper facade starts
        //   wallFloors storeys of facade
        const open = zone.voidFloors || 0;
        zone.hasVoid = open > 0;
        zone.voidBase = GROUND_HEIGHT;
        zone.base = zone.hasVoid ? GROUND_HEIGHT + open * FLOOR_HEIGHT : 0;
        zone.wallBase = GROUND_HEIGHT + open * FLOOR_HEIGHT;
        zone.wallFloors = UPPER_FLOORS - open;

        cursor += zone.width;
    }
}

export { ZONES };

/** Number of windows across one zone's elevation. */
export function windowsFor(zone) {
    return Math.max(1, Math.round(zone.width / MODULE_WIDTH) - 1);
}

const zoneByKey = Object.fromEntries(ZONES.map((z) => [z.key, z]));


// Helpers

const _matrix = new THREE.Matrix4();
const _position = new THREE.Vector3();
const _quaternion = new THREE.Quaternion();
const _euler = new THREE.Euler();
const _scale = new THREE.Vector3();

function place(mesh, index, x, y, z, sx, sy, sz) {
    _position.set(x, y, z);
    _scale.set(sx, sy, sz);
    _quaternion.identity();
    _matrix.compose(_position, _quaternion, _scale);
    mesh.setMatrixAt(index, _matrix);
}

/** Same, with a rotation about z, for stair flights that run in x. */
function placeTilted(mesh, index, x, y, z, sx, sy, sz, roll) {
    _position.set(x, y, z);
    _scale.set(sx, sy, sz);
    _euler.set(0, 0, roll);
    _quaternion.setFromEuler(_euler);
    _matrix.compose(_position, _quaternion, _scale);
    mesh.setMatrixAt(index, _matrix);
}

/** Emissive map for the connectors: `rows` bands of lit landings, one per floor. */
function createConnectorGlowTexture(rows) {
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#000000';
    ctx.fillRect(0, 0, size, size);

    const cell = size / rows;
    for (let r = 0; r < rows; r++) {
        const top = r * cell;
        const fittings = 4;
        for (let f = 0; f < fittings; f++) {
            const fx = (f + 0.5) * (size / fittings);
            ctx.fillStyle = 'rgba(255,255,255,0.95)';
            ctx.fillRect(fx - size * 0.045, top + cell * 0.2, size * 0.09, cell * 0.13);
            const grad = ctx.createLinearGradient(fx - size * 0.16, 0, fx + size * 0.16, 0);
            grad.addColorStop(0, 'rgba(255,255,255,0)');
            grad.addColorStop(0.5, 'rgba(255,255,255,0.34)');
            grad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = grad;
            ctx.fillRect(fx - size * 0.16, top + cell * 0.36, size * 0.32, cell * 0.34);
        }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.ClampToEdgeWrapping;
    return texture;
}

/** Same, rotated about y, for the slanted sides of the ponds. */
function placeTurned(mesh, index, x, y, z, sx, sy, sz, turn) {
    _position.set(x, y, z);
    _scale.set(sx, sy, sz);
    _euler.set(0, turn, 0);
    _quaternion.setFromEuler(_euler);
    _matrix.compose(_position, _quaternion, _scale);
    mesh.setMatrixAt(index, _matrix);
}

/** Deterministic noise, so scattered planting is the same on every load. */
function makeRandom(seed) {
    let s = seed >>> 0;
    return () => {
        s = (s * 1664525 + 1013904223) >>> 0;
        return s / 4294967296;
    };
}

/** Same, rotated about x, for stair flights that run in z. */
function placeTiltedX(mesh, index, x, y, z, sx, sy, sz, pitch) {
    _position.set(x, y, z);
    _scale.set(sx, sy, sz);
    _euler.set(pitch, 0, 0);
    _quaternion.setFromEuler(_euler);
    _matrix.compose(_position, _quaternion, _scale);
    mesh.setMatrixAt(index, _matrix);
}

/** The red UIU wordmark on its dark backing panel. */
function createSignTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 512;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#e0442a';
    ctx.font = 'bold 360px Helvetica, Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if ('letterSpacing' in ctx) ctx.letterSpacing = '22px';
    ctx.fillText('UIU', canvas.width / 2, canvas.height / 2);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    return texture;
}

export function createCampus(palette, quality) {
    let lightBudget = quality && Number.isFinite(quality.pointLights) ? quality.pointLights : 6;
    const pointLights = [];
    const addPointLight = (x, y, z, distance) => {
        if (lightBudget <= 0) return null;
        lightBudget -= 1;
        const light = new THREE.PointLight(0xffc98a, 0, distance, 2);
        light.position.set(x, y, z);
        pointLights.push(light);
        return light;
    };

    const group = new THREE.Group();
    const materials = {};
    const facadeMaterials = [];
    const disposables = [];
    const track = (t) => (disposables.push(t), t);

    // Materials

    materials.concrete = track(new THREE.MeshStandardMaterial({
        color: palette.concrete.clone(), roughness: 0.94, metalness: 0,
    }));

    materials.shade = track(new THREE.MeshStandardMaterial({
        color: palette.concreteShade.clone(), roughness: 0.96, metalness: 0,
    }));

    materials.slot = track(new THREE.MeshStandardMaterial({
        color: palette.slot.clone(), roughness: 1, metalness: 0,
    }));

    materials.terracotta = track(new THREE.MeshStandardMaterial({
        color: palette.terracotta.clone(), roughness: 0.85, metalness: 0,
    }));

    materials.paving = track(new THREE.MeshStandardMaterial({
        color: palette.paving.clone(), roughness: 1, metalness: 0,
    }));

    // Garden materials.
    materials.hedge = track(new THREE.MeshStandardMaterial({
        color: palette.hedge.clone(), roughness: 1, metalness: 0, flatShading: true,
    }));
    materials.bloomWarm = track(new THREE.MeshStandardMaterial({
        color: palette.bloomWarm.clone(), roughness: 1, metalness: 0,
    }));
    materials.bloomCool = track(new THREE.MeshStandardMaterial({
        color: palette.bloomCool.clone(), roughness: 1, metalness: 0,
    }));
    materials.pond = track(new THREE.MeshStandardMaterial({
        color: palette.pondWater.clone(), roughness: 0.16, metalness: 0.35,
    }));
    materials.koi = track(new THREE.MeshStandardMaterial({
        color: palette.koi.clone(), roughness: 0.7, metalness: 0,
    }));
    materials.trunk = track(new THREE.MeshStandardMaterial({
        color: palette.trunk.clone(), roughness: 1, metalness: 0,
    }));
    materials.foliage = track(new THREE.MeshStandardMaterial({
        map: track(createLeafTexture()),
        color: palette.foliage.clone(), roughness: 1, metalness: 0, flatShading: true,
    }));

    // Roof kit and railings.
    materials.solar = track(new THREE.MeshStandardMaterial({
        color: palette.solar.clone(), roughness: 0.28, metalness: 0.55,
    }));
    materials.railMetal = track(new THREE.MeshStandardMaterial({
        color: palette.railMetal.clone(), roughness: 0.5, metalness: 0.6,
    }));
    materials.glass = track(new THREE.MeshStandardMaterial({
        color: palette.glassRail.clone(),
        transparent: true, opacity: 0.45, depthWrite: false,
        roughness: 0.1, metalness: 0.2,
    }));
    materials.interior = track(new THREE.MeshStandardMaterial({
        color: palette.slot.clone(),
        emissive: palette.interiorGlow.clone(),
        emissiveIntensity: palette.interiorGlowIntensity,
        roughness: 1, metalness: 0,
    }));

    const unitBox = track(new THREE.BoxGeometry(1, 1, 1));

    // Zone volumes. Two batches: the connectors take a different material.

    const isRecess = (zone) => zone.role === 'connector' || zone.role === 'stair';
    const solidZones = ZONES.filter((z) => !isRecess(z));
    const recessZones = ZONES.filter(isRecess);

    const buildVolumes = (list, material) => {
        const mesh = new THREE.InstancedMesh(unitBox, material, list.length);
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        mesh.frustumCulled = false;

        list.forEach((zone, i) => {
            const height = TOP - zone.base;
            place(
                mesh, i,
                zone.x, zone.base + height / 2, BACK + zone.thickness / 2,
                zone.width, height, zone.thickness
            );
        });

        mesh.instanceMatrix.needsUpdate = true;
        group.add(mesh);
    };

    materials.recess = track(new THREE.MeshStandardMaterial({
        color: palette.slot.clone(),
        emissiveMap: track(createConnectorGlowTexture(FLOORS)),
        emissive: palette.interiorGlow.clone(),
        emissiveIntensity: palette.interiorGlowIntensity,
        roughness: 1, metalness: 0,
    }));

    buildVolumes(solidZones, materials.concrete);
    buildVolumes(recessZones, materials.recess);

    for (const zone of recessZones) {
        const light = addPointLight(zone.x, TOP * 0.42, zone.front + 4.5, 34);
        if (light) group.add(light);
    }

    const voidZones = ZONES.filter((z) => z.hasVoid);
    const groundMass = new THREE.InstancedMesh(unitBox, materials.concrete, voidZones.length);
    groundMass.castShadow = true;
    groundMass.receiveShadow = true;
    groundMass.frustumCulled = false;
    voidZones.forEach((zone, i) => {
        place(
            groundMass, i,
            zone.x, GROUND_HEIGHT / 2, BACK + zone.thickness / 2,
            zone.width, GROUND_HEIGHT, zone.thickness
        );
    });
    groundMass.instanceMatrix.needsUpdate = true;
    group.add(groundMass);

    // Facades: one textured plane per zone, except the connectors.

    const textureCache = new Map();
    const texturesFor = (kind, rows) => {
        const key = `${kind}:${rows}`;
        if (!textureCache.has(key)) {
            const made = kind === 'curtain'
                ? createCurtainWallTextures(rows)
                : createFacadeTextures(rows);
            track(made.map);
            track(made.emissiveMap);
            textureCache.set(key, made);
        }
        return textureCache.get(key);
    };

    const facadeTime = { value: 0 };


    /** Injects a slow per-window on/off gate into a facade material. */
    const injectWindowGate = (material, rows) => {
        const grid = { value: new THREE.Vector2(MODULES_PER_TILE, rows) };

        material.onBeforeCompile = (shader) => {
            shader.uniforms.uFacadeTime = facadeTime;
            shader.uniforms.uWindowGrid = grid;

            shader.fragmentShader = shader.fragmentShader
                .replace('#include <common>', /* glsl */ `
                    #include <common>
                    uniform float uFacadeTime;
                    uniform vec2 uWindowGrid;

                    float ccCellHash(vec2 p) {
                        return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
                    }

                    float ccWindowGate(vec2 uv) {
                        vec2 cell = floor(uv * uWindowGrid);
                        float h = ccCellHash(cell);

                        // One re-roll per window every ~20 s, phase-offset by
                        // the cell hash.
                        float t = uFacadeTime * 0.05 + h * 37.0;
                        float i = floor(t);

                        float a = ccCellHash(vec2(i, h * 53.0));
                        float b = ccCellHash(vec2(i + 1.0, h * 53.0));

                        // Cross-fade over the last tenth of the interval.
                        float level = mix(a, b, smoothstep(0.9, 1.0, fract(t)));

                        // Threshold, low so most rooms are lit at any moment.
                        return smoothstep(0.20, 0.30, level);
                    }
                `)
                .replace('#include <emissivemap_fragment>', /* glsl */ `
                    #include <emissivemap_fragment>
                    totalEmissiveRadiance *= ccWindowGate(vEmissiveMapUv);
                `);
        };

        material.customProgramCacheKey = () => 'cc-window-gate';
    };

    for (const zone of ZONES) {
        if (isRecess(zone)) continue;

        const source = texturesFor(
            zone.role === 'centreFront' ? 'curtain' : 'punched',
            zone.wallFloors
        );

        const modules = windowsFor(zone);

        const width = zone.width - 1.6;   // 0.8 m margin each side, metres

        const zoneMap = source.map.clone();
        zoneMap.needsUpdate = true;
        zoneMap.repeat.set(modules / MODULES_PER_TILE, 1);
        track(zoneMap);

        const zoneGlow = source.emissiveMap.clone();
        zoneGlow.needsUpdate = true;
        zoneGlow.repeat.set(modules / MODULES_PER_TILE, 1);
        track(zoneGlow);

        const material = track(new THREE.MeshStandardMaterial({
            map: zoneMap,
            emissiveMap: zoneGlow,
            emissive: palette.windowEmissive.clone(),
            emissiveIntensity: palette.windowEmissiveIntensity,
            color: palette.facadeTint.clone(),
            roughness: 0.7,
            metalness: 0.05,
        }));

        injectWindowGate(material, zone.wallFloors);
        facadeMaterials.push(material);

        const wallHeight = zone.wallFloors * FLOOR_HEIGHT;
        const plane = new THREE.Mesh(track(new THREE.PlaneGeometry(width, wallHeight)), material);
        plane.position.set(zone.x, zone.wallBase + wallHeight / 2, zone.front + 0.35);
        plane.receiveShadow = true;
        group.add(plane);
    }

    // Floor bands: the projecting slab edge at every storey.
    const bandTotal = ZONES.reduce((sum, z) => sum + z.wallFloors + 1, 0);
    const bands = new THREE.InstancedMesh(unitBox, materials.concrete, bandTotal);
    bands.castShadow = true;
    bands.receiveShadow = true;
    bands.frustumCulled = false;

    let bandIndex = 0;
    for (const zone of ZONES) {
        for (let f = 0; f <= zone.wallFloors; f++) {
            const y = zone.wallBase + f * FLOOR_HEIGHT - 0.35;

            const deep = zone.hasVoid && f === 0;

            place(
                bands, bandIndex++,
                zone.x, y, zone.front + (deep ? 0.2 : 0.65),
                zone.width + 0.5, deep ? 1.1 : 0.7, deep ? 2.6 : 1.3
            );
        }
    }
    bands.instanceMatrix.needsUpdate = true;
    group.add(bands);

    const terraceEdge = new THREE.InstancedMesh(unitBox, materials.concrete, voidZones.length);
    terraceEdge.castShadow = true;
    terraceEdge.receiveShadow = true;
    terraceEdge.frustumCulled = false;
    voidZones.forEach((zone, i) => {
        place(
            terraceEdge, i,
            zone.x, zone.voidBase - 0.2, zone.front + 0.5,
            zone.width + 0.5, 0.75, 1.9
        );
    });
    terraceEdge.instanceMatrix.needsUpdate = true;
    group.add(terraceEdge);

    // Back-step returns: the exposed flank where the plan steps.
    const returns = [];

    ZONES.forEach((zone, i) => {
        for (const side of [-1, 1]) {
            const neighbour = ZONES[i + side];
            const behind = neighbour ? neighbour.front : BACK;
            const depth = zone.front - behind;
            if (depth <= 0.5) continue;

            let proud = depth + 0.25;
            let z = behind + proud / 2;

            // On a stair flank the panel stops short of the stair.
            if (zone.stairFlank === side && depth > STAIR_FLANK_RUN + 2) {
                proud = depth - STAIR_FLANK_RUN + 0.25;
                z = zone.front + 0.25 - proud / 2;
            }

            returns.push({
                x: zone.x + side * (zone.width / 2 - 0.4),
                z,
                base: zone.base,
                width: 1.2,
                depth: proud,
            });
        }
    });

    const returnPanels = new THREE.InstancedMesh(unitBox, materials.terracotta, returns.length);
    returnPanels.castShadow = true;
    returnPanels.receiveShadow = true;
    returnPanels.frustumCulled = false;
    returns.forEach((r, i) => {
        const height = TOP - 0.4 - r.base;
        place(returnPanels, i, r.x, r.base + height / 2, r.z, r.width, height, r.depth);
    });
    returnPanels.instanceMatrix.needsUpdate = true;
    group.add(returnPanels);

    // Ground floor: one shallow panel per zone, riding that zone's front face.
    const walledZones = ZONES;
    const groundPanels = new THREE.InstancedMesh(unitBox, materials.slot, walledZones.length);
    groundPanels.receiveShadow = true;
    groundPanels.frustumCulled = false;

    walledZones.forEach((zone, i) => {
        place(
            groundPanels, i,
            zone.x, (GROUND_HEIGHT - 0.7) / 2, zone.front + 0.30,
            zone.width - 1.0, GROUND_HEIGHT - 0.7, 0.5
        );
    });
    groundPanels.instanceMatrix.needsUpdate = true;
    group.add(groundPanels);

    const openZones = voidZones;
    const openSpan = openZones.reduce((sum, z) => sum + z.width, 0);
    const openCentreX = (openZones[0].x - openZones[0].width / 2 + openSpan / 2);

    const voidBase = openZones[0].voidBase;
    const lobbyHeight = openZones[0].base - voidBase;
    const lobbyWall = new THREE.Mesh(unitBox, materials.slot);
    lobbyWall.scale.set(openSpan, lobbyHeight, 3);
    lobbyWall.position.set(openCentreX, voidBase + lobbyHeight / 2, DEPTH.mid - 9);
    lobbyWall.receiveShadow = true;
    group.add(lobbyWall);

    // Terrace pendants: lamps on cords, with point lights at the lamp heads.

    const PENDANT_DROP = 2.7;   // cord length, metres
    const pendantsPerZone = 4;
    const pendantTotal = voidZones.length * pendantsPerZone;

    const cords = new THREE.InstancedMesh(unitBox, materials.slot, pendantTotal);
    const shades = new THREE.InstancedMesh(unitBox, materials.shade, pendantTotal);
    const bulbs = new THREE.InstancedMesh(unitBox, materials.interior, pendantTotal);
    [cords, shades, bulbs].forEach((m) => { m.frustumCulled = false; });

    let pendantIndex = 0;
    voidZones.forEach((zone) => {
        for (let f = 0; f < pendantsPerZone; f++) {
            const x = zone.x + (f / (pendantsPerZone - 1) - 0.5) * (zone.width - 1.8);
            const z = zone.front - 3.2;
            const headY = zone.wallBase - PENDANT_DROP;

            place(cords, pendantIndex, x, zone.wallBase - PENDANT_DROP / 2, z, 0.07, PENDANT_DROP, 0.07);
            place(shades, pendantIndex, x, headY + 0.16, z, 0.62, 0.2, 0.62);
            place(bulbs, pendantIndex, x, headY - 0.02, z, 0.44, 0.16, 0.44);

            if (f === 0) {
                const light = addPointLight(zone.x, headY - 0.3, z, 26);
                if (light) group.add(light);
            }
            pendantIndex += 1;
        }
    });
    cords.instanceMatrix.needsUpdate = true;
    shades.instanceMatrix.needsUpdate = true;
    bulbs.instanceMatrix.needsUpdate = true;
    group.add(cords, shades, bulbs);

    // Columns: two at the corners of each open centre portion, three elsewhere.
    const COLUMN = 1.1;   // column side, metres
    const columnsFor = (zone) => (zone.hasVoid ? 2 : 3);
    const columnTotal = ZONES.reduce((sum, z) => sum + columnsFor(z), 0);
    const columns = new THREE.InstancedMesh(unitBox, materials.concrete, columnTotal);
    columns.castShadow = true;
    columns.frustumCulled = false;

    let columnIndex = 0;
    for (const zone of ZONES) {
        const count = columnsFor(zone);

        // A centre column stands on the terrace slab; elsewhere on the ground.
        const foot = zone.hasVoid ? zone.voidBase : 0;
        const height = zone.wallBase - 0.35 - foot;

        for (let k = 0; k < count; k++) {
            const reach = zone.width / 2 - COLUMN / 2;
            const x = count === 1 ? zone.x : zone.x + (k / (count - 1) * 2 - 1) * reach;
            place(columns, columnIndex++, x, foot + height / 2, zone.front + 0.6, COLUMN, height, COLUMN);
        }
    }
    columns.instanceMatrix.needsUpdate = true;
    group.add(columns);

    // The open terrace: seating spread across all three centre zones.
    const voidCentreX = openCentreX;
    const benchRows = 2;
    const benchesPerRow = 5;
    const benchMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.bench.clone(), roughness: 0.9,
    }));
    const benches = new THREE.InstancedMesh(unitBox, benchMaterial, benchRows * benchesPerRow * 2);
    benches.castShadow = true;
    benches.receiveShadow = true;
    benches.frustumCulled = false;

    let benchIndex = 0;
    for (let r = 0; r < benchRows; r++) {
        for (let b = 0; b < benchesPerRow; b++) {
            const x = voidCentreX + (b - (benchesPerRow - 1) / 2) * (openSpan / (benchesPerRow + 1));
            const z = DEPTH.mid - 1 - r * 5;
            place(benches, benchIndex++, x, voidBase + 0.45, z, 2.0, 0.18, 0.6);
            place(benches, benchIndex++, x, voidBase + 0.24, z, 0.22, 0.48, 0.5);
        }
    }
    benches.instanceMatrix.needsUpdate = true;
    group.add(benches);

    // Zigzag stair in the right connector, ground to eleventh floor.

    const stairZone = zoneByKey.SC;
    const run = stairZone.width - 4.5;
    const rise = FLOOR_HEIGHT / 2;
    const flightLength = Math.sqrt(run * run + rise * rise);
    const flightRoll = Math.atan2(rise, run);
    const stairZ = stairZone.front + 1.8;

    const stairSteps = UPPER_FLOORS + 1;
    const stair = new THREE.InstancedMesh(unitBox, materials.shade, stairSteps * 4);
    stair.material = materials.slot;
    stair.castShadow = true;
    stair.receiveShadow = true;
    stair.frustumCulled = false;

    let stairIndex = 0;
    for (let f = 0; f < stairSteps; f++) {
        const base = f === 0 ? 0 : GROUND_HEIGHT + (f - 1) * FLOOR_HEIGHT;
        const storey = f === 0 ? GROUND_HEIGHT : FLOOR_HEIGHT;
        const halfRise = storey / 2;
        const roll = Math.atan2(halfRise, run);
        const length = Math.sqrt(run * run + halfRise * halfRise);

        placeTilted(stair, stairIndex++, stairZone.x, base + halfRise / 2, stairZ, length, 0.35, 2.6, roll);
        placeTilted(stair, stairIndex++, stairZone.x, base + storey - halfRise / 2, stairZ, length, 0.35, 2.6, -roll);

        place(stair, stairIndex++, stairZone.x - run / 2, base + halfRise, stairZ, 3.2, 0.35, 2.8);
        place(stair, stairIndex++, stairZone.x + run / 2, base + storey, stairZ, 3.2, 0.35, 2.8);
    }
    stair.instanceMatrix.needsUpdate = true;
    group.add(stair);

    // Stair on L2's right flank, second floor to top floor.

    const flankZone = ZONES.find((z) => z.stairFlank);
    if (flankZone) {
        const side = flankZone.stairFlank;
        const flankLanding = 1.3;   // landing depth at each end, metres
        const flankRun = STAIR_FLANK_RUN - 2 * flankLanding - 1.4;
        const flankBase = floorLevel(2);
        const flankSteps = Math.round((TOP_FLOOR - flankBase) / FLOOR_HEIGHT);

        const flankSlot = ZONES[ZONES.indexOf(flankZone) + side];
        const flankBehind = flankSlot.front;
        const flankZ = flankBehind + STAIR_FLANK_RUN / 2;
        const flankX = flankZone.x + side * (flankZone.width / 2 + 1.35);

        const flankStair = new THREE.InstancedMesh(unitBox, materials.slot, flankSteps * 4);
        flankStair.castShadow = true;
        flankStair.receiveShadow = true;
        flankStair.frustumCulled = false;

        const flankRail = new THREE.InstancedMesh(unitBox, materials.shade, flankSteps * 4);
        flankRail.castShadow = true;
        flankRail.receiveShadow = true;
        flankRail.frustumCulled = false;

        const RAIL_H = 1.1;

        let flankIndex = 0;
        let railIndex = 0;
        for (let f = 0; f < flankSteps; f++) {
            const base = flankBase + f * FLOOR_HEIGHT;
            const halfRise = FLOOR_HEIGHT / 2;
            const pitch = -Math.atan2(halfRise, flankRun);
            const length = Math.sqrt(flankRun * flankRun + halfRise * halfRise);
            const outerX = flankX + side * (2.6 / 2 + 0.18);
            const landingZ = flankZ + (flankRun + flankLanding) / 2;

            placeTiltedX(flankStair, flankIndex++, flankX, base + halfRise / 2, flankZ, 2.6, 0.35, length, pitch);
            placeTiltedX(flankStair, flankIndex++, flankX, base + FLOOR_HEIGHT - halfRise / 2, flankZ, 2.6, 0.35, length, -pitch);

            placeTiltedX(flankStair, flankIndex++, flankX, base + halfRise, flankZ - (flankRun + flankLanding) / 2, 2.8, 0.35, flankLanding, 0);
            placeTiltedX(flankStair, flankIndex++, flankX, base + FLOOR_HEIGHT, landingZ, 2.8, 0.35, flankLanding, 0);

            placeTiltedX(flankRail, railIndex++, outerX, base + halfRise / 2 + RAIL_H / 2, flankZ, 0.26, RAIL_H, length, pitch);
            placeTiltedX(flankRail, railIndex++, outerX, base + FLOOR_HEIGHT - halfRise / 2 + RAIL_H / 2, flankZ, 0.26, RAIL_H, length, -pitch);

            place(flankRail, railIndex++, flankX, base + halfRise + RAIL_H / 2, flankZ - (flankRun + flankLanding) / 2 - flankLanding / 2, 2.8, RAIL_H, 0.26);
            place(flankRail, railIndex++, flankX, base + FLOOR_HEIGHT + RAIL_H / 2, landingZ + flankLanding / 2, 2.8, RAIL_H, 0.26);
        }
        flankStair.instanceMatrix.needsUpdate = true;
        flankRail.instanceMatrix.needsUpdate = true;
        group.add(flankStair, flankRail);

        // The open bay the stair serves.

        const bayDepth = STAIR_FLANK_RUN - 1.0;
        const bayBase = flankBase - FLOOR_HEIGHT * 0.5;
        const bayHeight = TOP - bayBase;
        const bayX = flankZone.x + side * (flankZone.width / 2 + 0.075);

        const bayFace = new THREE.Mesh(unitBox, materials.slot);
        bayFace.scale.set(0.15, bayHeight, bayDepth);
        bayFace.position.set(bayX, bayBase + bayHeight / 2, flankZ);
        bayFace.receiveShadow = true;
        group.add(bayFace);

        const bayFloors = flankSteps + 1;
        const baySlabs = new THREE.InstancedMesh(unitBox, materials.concrete, bayFloors);
        baySlabs.castShadow = true;
        baySlabs.receiveShadow = true;
        baySlabs.frustumCulled = false;
        for (let f = 0; f < bayFloors; f++) {
            place(
                baySlabs, f,
                flankZone.x + side * (flankZone.width / 2 + 0.24), flankBase + f * FLOOR_HEIGHT - 0.3,
                flankZ, 0.48, 0.6, bayDepth
            );
        }
        baySlabs.instanceMatrix.needsUpdate = true;
        group.add(baySlabs);

        const bayLamps = new THREE.InstancedMesh(unitBox, materials.interior, bayFloors);
        bayLamps.frustumCulled = false;
        for (let f = 0; f < bayFloors; f++) {
            place(
                bayLamps, f,
                flankZone.x + side * (flankZone.width / 2 + 0.42), flankBase + f * FLOOR_HEIGHT - 0.78,
                flankZ, 0.16, 0.16, 1.8
            );
        }
        bayLamps.instanceMatrix.needsUpdate = true;
        group.add(bayLamps);

        const bayLight = addPointLight(
            flankZone.x + side * (flankZone.width / 2 + 1.6),
            flankBase + (TOP_FLOOR - flankBase) * 0.4,
            flankZ, 26
        );
        if (bayLight) group.add(bayLight);
    }

    // Terrace stair at C2's right-hand end: pavement up to the terrace.

    {
        const c2 = zoneByKey.C2;
        const treads = 14;
        const rise = GROUND_HEIGHT / treads;
        const going = 0.62;
        const stairWidth = 4.0;
        const stairX = c2.x + c2.width / 2 + 1.15;
        const headZ = c2.front + 1.6;
        const run = treads * going;

        const terraceStair = new THREE.InstancedMesh(unitBox, materials.shade, treads + 3);
        terraceStair.castShadow = true;
        terraceStair.receiveShadow = true;
        terraceStair.frustumCulled = false;

        let terraceIndex = 0;
        for (let s = 0; s < treads; s++) {
            const top = rise * (s + 1);
            const z = headZ + going * (treads - s) - going / 2;
            place(terraceStair, terraceIndex++, stairX, top / 2, z, stairWidth, top, going);
        }
        place(terraceStair, terraceIndex++, stairX, GROUND_HEIGHT / 2, headZ - 0.6, stairWidth, GROUND_HEIGHT, 1.2);

        const rake = -Math.atan2(GROUND_HEIGHT, run);
        const rakedLength = Math.sqrt(run * run + GROUND_HEIGHT * GROUND_HEIGHT);
        for (const sideX of [-1, 1]) {
            placeTiltedX(
                terraceStair, terraceIndex++,
                stairX + sideX * (stairWidth / 2 + 0.2),
                GROUND_HEIGHT / 2 + 0.55, headZ + run / 2,
                0.35, 1.1, rakedLength, rake
            );
        }
        terraceStair.instanceMatrix.needsUpdate = true;
        group.add(terraceStair);
    }

    // Central entrance

    const centre = zoneByKey.C2;

    const c1 = zoneByKey.C1;
    const deckX = (c1.x - c1.width / 2 + centre.x + centre.width / 2) / 2;
    const deckWidth = 10;
    const deckDepth = 8;
    const deckZ = centre.front - 1.5;
    const deckFront = deckZ + deckDepth / 2;

    const entranceSteps = 3;
    const stepRise = ENTRANCE_DECK / entranceSteps;
    const stepGoing = 0.5;

    const entrance = new THREE.InstancedMesh(unitBox, materials.shade, entranceSteps + 1);
    entrance.castShadow = true;
    entrance.receiveShadow = true;
    entrance.frustumCulled = false;

    let entranceIndex = 0;
    for (let s = 0; s < entranceSteps; s++) {
        const y = stepRise * (s + 1);
        const z = deckFront + stepGoing * (entranceSteps - s) - stepGoing / 2;
        place(entrance, entranceIndex++, deckX, y / 2, z, deckWidth - 1.2 - s * 0.5, y, stepGoing);
    }
    place(entrance, entranceIndex++, deckX, ENTRANCE_DECK / 2, deckZ, deckWidth, ENTRANCE_DECK, deckDepth);
    entrance.instanceMatrix.needsUpdate = true;
    group.add(entrance);

    // Courtyard gardens: two small trees, a koi pond with a stone rim,
    // benches down both sides, and dense flowers out to the front line.

    const plantClumps = [];
    const plantRandom = makeRandom(20030101);
    const BLOOMS_PER_PLANT = 3;

    /** Fills a rectangle with jittered, overlapping plants. */
    const scatterPlants = (x0, z0, w, d, spacing, baseY) => {
        const out = [];
        const cols = Math.max(1, Math.round(w / spacing));
        const rows = Math.max(1, Math.round(d / spacing));
        for (let r = 0; r < rows; r++) {
            for (let cIdx = 0; cIdx < cols; cIdx++) {
                const pw = 0.75 + plantRandom() * 0.75;
                const ph = 0.4 + plantRandom() * 0.5;
                const plant = {
                    x: x0 + (cIdx + 0.5) * (w / cols) + (plantRandom() - 0.5) * spacing * 0.52,
                    z: z0 + (r + 0.5) * (d / rows) + (plantRandom() - 0.5) * spacing * 0.52,
                    y: baseY,
                    w: pw,
                    h: ph,
                    leaf: plantRandom() < 0.55 ? 0 : 1,
                    blooms: [],
                };
                for (let b = 0; b < BLOOMS_PER_PLANT; b++) {
                    plant.blooms.push({
                        dx: (plantRandom() - 0.5) * pw * 0.7,
                        dz: (plantRandom() - 0.5) * pw * 0.7,
                        dy: ph * (0.62 + plantRandom() * 0.3),
                        size: 0.16 + plantRandom() * 0.1,
                        warm: plantRandom() < 0.55,
                    });
                }
                out.push(plant);
            }
        }
        return out;
    };

    const gardenZones = ZONES.filter(isRecess);
    {
        const bounds = gardenZones.map((zone) => {
            const i = ZONES.indexOf(zone);
            const flanks = [ZONES[i - 1], ZONES[i + 1]].filter(Boolean);
            const back = zone.front + 0.6;
            const front = Math.max(...flanks.map((f) => f.front)) - 0.5;
            const gateSide = flanks.findIndex((f) => f.stairFlank);
            return { zone, back, front, gate: gateSide >= 0 ? flanks[gateSide] : null };
        });

        // The pond is a trapezoid, wider at the edge facing the field.
        const POND_FRONT_W = 6.2;
        const POND_BACK_W = 3.4;
        const POND_D = 3.4;
        const POND_W = POND_FRONT_W; // widest point
        bounds.forEach((g) => { g.pondZ = g.back + POND_D / 2 + 1.6; });

        const halfF = POND_FRONT_W / 2;
        const halfB = POND_BACK_W / 2;
        const halfD = POND_D / 2;
        const waterGeometry = track(new THREE.BufferGeometry());
        waterGeometry.setAttribute('position', new THREE.Float32BufferAttribute([
            -halfF, 0, halfD,
            halfF, 0, halfD,
            halfB, 0, -halfD,
            -halfB, 0, -halfD,
        ], 3));
        waterGeometry.setAttribute('normal', new THREE.Float32BufferAttribute([
            0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0,
        ], 3));
        waterGeometry.setAttribute('uv', new THREE.Float32BufferAttribute([0, 0, 1, 0, 1, 1, 0, 1], 2));
        waterGeometry.setIndex([0, 1, 2, 0, 2, 3]);

        // Stone rim, one bar per edge, the slanted sides turned to the taper.
        const RIM_H = [0.50, 0.46, 0.44, 0.42];   // rim heights per edge, metres
        const rims = new THREE.InstancedMesh(unitBox, materials.paving, bounds.length * 4);
        rims.receiveShadow = true;
        rims.castShadow = true;
        rims.frustumCulled = false;

        let rimIndex = 0;
        bounds.forEach((g) => {
            place(rims, rimIndex++, g.zone.x, RIM_H[0] / 2, g.pondZ + halfD, POND_FRONT_W + 1.0, RIM_H[0], 1.0);
            place(rims, rimIndex++, g.zone.x, RIM_H[1] / 2, g.pondZ - halfD, POND_BACK_W + 1.0, RIM_H[1], 1.0);
            for (const sideSign of [-1, 1]) {
                const dx = sideSign * (halfB - halfF);
                const dz = -POND_D;
                const turn = Math.atan2(dx, dz);
                const length = Math.sqrt(dx * dx + dz * dz);
                const h = RIM_H[sideSign < 0 ? 2 : 3];
                placeTurned(
                    rims, rimIndex++,
                    g.zone.x + sideSign * (halfF + halfB) / 2, h / 2, g.pondZ,
                    1.0, h, length, turn
                );
            }
            const surface = new THREE.Mesh(waterGeometry, materials.pond);
            surface.position.set(g.zone.x, 0.34, g.pondZ);
            group.add(surface);
        });
        rims.instanceMatrix.needsUpdate = true;
        group.add(rims);

        const koiPerPond = 4;
        const koi = new THREE.InstancedMesh(unitBox, materials.koi, bounds.length * koiPerPond);
        koi.frustumCulled = false;
        let koiIndex = 0;
        bounds.forEach((g) => {
            for (let k = 0; k < koiPerPond; k++) {
                const along = ((k % 2) - 0.5) * (POND_D - 1.6);
                const t = 0.5 - along / POND_D;
                const reach = (POND_FRONT_W + (POND_BACK_W - POND_FRONT_W) * t) / 2 - 0.6;
                const across = (k / (koiPerPond - 1) - 0.5) * 2 * reach;
                place(koi, koiIndex++, g.zone.x + across, 0.37, g.pondZ + along, 0.7, 0.1, 0.22);
            }
        });
        koi.instanceMatrix.needsUpdate = true;
        group.add(koi);

        // Benches down both long sides of the pond, facing it.
        const benchPerSide = 2;
        const gardenBenches = new THREE.InstancedMesh(unitBox, benchMaterial, bounds.length * benchPerSide * 2 * 2);
        gardenBenches.castShadow = true;
        gardenBenches.frustumCulled = false;
        let gbIndex = 0;
        bounds.forEach((g) => {
            for (const sideSign of [-1, 1]) {
                for (let b = 0; b < benchPerSide; b++) {
                    const x = g.zone.x + sideSign * (POND_W / 2 + 1.9);
                    const z = g.pondZ + (b - (benchPerSide - 1) / 2) * 1.9;
                    place(gardenBenches, gbIndex++, x, 0.46, z, 0.6, 0.16, 1.5);
                    place(gardenBenches, gbIndex++, x, 0.25, z, 0.5, 0.5, 0.22);
                }
            }
        });
        gardenBenches.instanceMatrix.needsUpdate = true;
        group.add(gardenBenches);

        // Flowers: each plant is a green mound with three blooms on top.
        const clumps = [];
        bounds.forEach((g) => {
            const x0 = g.zone.x - g.zone.width / 2 + 0.7;
            const z0 = g.pondZ + POND_D / 2 + 1.8;
            clumps.push(...scatterPlants(x0, z0, g.zone.width - 1.4, g.front - z0, 1.05, 0));
        });

        plantClumps.push(...clumps);


        // Two small trees per garden, in the back corners.
        const treesPerGarden = 2;
        const LIMBS = 3;
        const LOBES = LIMBS + 1;
        const treeTotal = bounds.length * treesPerGarden;
        const trunkGeometry = track(new THREE.CylinderGeometry(0.1, 0.17, 1, 8));
        const limbGeometry = track(new THREE.CylinderGeometry(0.055, 0.1, 1, 6));
        const canopyGeometry = track(createFoliageGeometry(2.4));
        const trunks = new THREE.InstancedMesh(trunkGeometry, materials.trunk, treeTotal);
        const limbs = new THREE.InstancedMesh(limbGeometry, materials.trunk, treeTotal * LIMBS);
        const canopies = new THREE.InstancedMesh(canopyGeometry, materials.foliage, treeTotal * LOBES);
        [trunks, limbs, canopies].forEach((m) => { m.castShadow = true; m.frustumCulled = false; });

        let treeIndex = 0;
        bounds.forEach((g) => {
            for (let t = 0; t < treesPerGarden; t++) {
                const x = g.zone.x + (t * 2 - 1) * (g.zone.width / 2 - 1.7);
                const z = g.back + 1.1;
                const seed = treeIndex + 3;
                const rnd = (n) => {
                    const v = Math.sin(seed * 12.9898 + n * 78.233) * 43758.5453;
                    return v - Math.floor(v);
                };
                const height = 4.6 + rnd(0) * 1.2;
                const spread = 1.5 + rnd(1) * 0.5;
                const fork = height * (0.44 + rnd(2) * 0.14);

                place(trunks, treeIndex, x, (fork + 0.4) / 2, z, 1, fork + 0.4, 1);
                for (let l = 0; l < LIMBS; l++) {
                    const azimuth = (l / LIMBS) * Math.PI * 2 + rnd(l + 3) * 2.1;
                    const tilt = 0.34 + rnd(l + 7) * 0.66;
                    const length = (height - fork) * (0.75 + rnd(l + 11) * 0.5);
                    const dx = -Math.sin(tilt) * Math.cos(azimuth);
                    const dy = Math.cos(tilt);
                    const dz = Math.sin(tilt) * Math.sin(azimuth);
                    placeTurned(
                        limbs, treeIndex * LIMBS + l,
                        x + dx * length / 2, fork + dy * length / 2, z + dz * length / 2,
                        0.85, length, 0.85, azimuth
                    );
                    const r = spread * (0.42 + rnd(l + 15) * 0.28);
                    place(
                        canopies, treeIndex * LOBES + l,
                        x + dx * length * 0.95, fork + dy * length * 0.95 + r * 0.3, z + dz * length * 0.95,
                        r * 1.05, r * 0.82, r * 1.05
                    );
                }
                const rc = spread * (0.46 + rnd(19) * 0.24);
                place(
                    canopies, treeIndex * LOBES + LIMBS,
                    x, fork + (height - fork) * 0.6, z, rc, rc * 0.8, rc
                );
                treeIndex++;
            }
        });
        [trunks, limbs, canopies].forEach((m) => { m.instanceMatrix.needsUpdate = true; });
        group.add(trunks, limbs, canopies);

        // The gate into L2, beside the pond. Left-hand recess only.
        const gated = bounds.filter((g) => g.gate);
        if (gated.length) {
            const frames = new THREE.InstancedMesh(unitBox, materials.concrete, gated.length);
            const doors = new THREE.InstancedMesh(unitBox, materials.slot, gated.length);
            frames.castShadow = true;
            frames.receiveShadow = true;
            frames.frustumCulled = false;
            doors.frustumCulled = false;
            gated.forEach((g, i) => {
                const wallX = g.gate.x + Math.sign(g.zone.x - g.gate.x) * (g.gate.width / 2);
                const outward = Math.sign(g.zone.x - g.gate.x);
                place(frames, i, wallX + outward * 0.12, 1.35, g.pondZ, 0.24, 2.7, 2.1);
                place(doors, i, wallX + outward * 0.35, 1.1, g.pondZ, 0.22, 2.2, 1.5);
            });
            frames.instanceMatrix.needsUpdate = true;
            doors.instanceMatrix.needsUpdate = true;
            group.add(frames, doors);
        }
    }

    // Deferred until every plant is known, so the meshes can be sized once.
    const buildPlanting = () => {
        const clumps = plantClumps;
        const moundGeometry = track(new THREE.IcosahedronGeometry(1, 0));
        const bloomGeometry = track(new THREE.IcosahedronGeometry(1, 0));

        const leafMaterials = [materials.hedge, materials.foliage];
        const leafCounts = [0, 0];
        clumps.forEach((c) => { leafCounts[c.leaf] += 1; });
        const mounds = leafMaterials.map((material, k) => {
            const mesh = new THREE.InstancedMesh(moundGeometry, material, Math.max(1, leafCounts[k]));
            mesh.castShadow = true;
            mesh.receiveShadow = true;
            mesh.frustumCulled = false;
            return mesh;
        });

        let warmCount = 0;
        clumps.forEach((c) => c.blooms.forEach((b) => { if (b.warm) warmCount += 1; }));
        const coolCount = clumps.length * BLOOMS_PER_PLANT - warmCount;
        const bloomMeshes = [
            new THREE.InstancedMesh(bloomGeometry, materials.bloomWarm, Math.max(1, warmCount)),
            new THREE.InstancedMesh(bloomGeometry, materials.bloomCool, Math.max(1, coolCount)),
        ];
        bloomMeshes.forEach((m) => { m.frustumCulled = false; });

        const leafIndex = [0, 0];
        const bloomIndex = [0, 0];
        clumps.forEach((c) => {
            place(mounds[c.leaf], leafIndex[c.leaf]++, c.x, c.y + c.h * 0.5, c.z, c.w / 2, c.h * 0.62, c.w / 2);
            c.blooms.forEach((b) => {
                const k = b.warm ? 0 : 1;
                place(bloomMeshes[k], bloomIndex[k]++, c.x + b.dx, c.y + b.dy, c.z + b.dz, b.size, b.size * 0.8, b.size);
            });
        });
        leafIndex.forEach((written, k) => {
            if (written === 0) place(mounds[k], 0, 0, -50, 0, 0.01, 0.01, 0.01);
        });
        bloomIndex.forEach((written, k) => {
            if (written === 0) place(bloomMeshes[k], 0, 0, -50, 0, 0.01, 0.01, 0.01);
        });
        [...mounds, ...bloomMeshes].forEach((mesh) => {
            mesh.instanceMatrix.needsUpdate = true;
            group.add(mesh);
        });

        Object.freeze(plantClumps);
    };

    // Roof: a deck per zone, a kerb round its edge, and a rail on the kerb.

    const ROOF_DECK = 0.5;              // deck thickness
    const DECK_TOP = TOP + ROOF_DECK;   // what things stand on
    const KERB_W = 0.42;
    // Parapet heights per side (front, back, left, right), metres.
    const KERB_H = [1.02, 0.94, 0.98, 0.90];

    const decks = new THREE.InstancedMesh(unitBox, materials.concrete, ZONES.length * 5);
    const rails = new THREE.InstancedMesh(unitBox, materials.railMetal, ZONES.length * 4);
    decks.castShadow = true;
    decks.receiveShadow = true;
    decks.frustumCulled = false;
    rails.castShadow = true;
    rails.frustumCulled = false;

    let deckIndex = 0;
    let railIndex = 0;
    for (const zone of ZONES) {
        const depth = zone.thickness;
        const cz = BACK + depth / 2;

        place(decks, deckIndex++, zone.x, TOP + ROOF_DECK / 2, cz, zone.width, ROOF_DECK, depth);

        const bars = [
            [zone.x, cz + depth / 2 - KERB_W / 2, zone.width, KERB_W, 0],
            [zone.x, cz - depth / 2 + KERB_W / 2, zone.width, KERB_W, 1],
            [zone.x - zone.width / 2 + KERB_W / 2, cz, KERB_W, depth, 2],
            [zone.x + zone.width / 2 - KERB_W / 2, cz, KERB_W, depth, 3],
        ];
        for (const [bx, bz, bw, bd, k] of bars) {
            const h = KERB_H[k];
            place(decks, deckIndex++, bx, DECK_TOP + h / 2, bz, bw, h, bd);
            const alongX = k < 2;
            place(
                rails, railIndex++, bx, DECK_TOP + h + 0.24, bz,
                alongX ? bw : 0.13, 0.13, alongX ? 0.13 : bd
            );
        }
    }
    decks.instanceMatrix.needsUpdate = true;
    rails.instanceMatrix.needsUpdate = true;
    group.add(decks, rails);

    // Water tanks: three cylinders on stands.

    const tankSpots = [
        [zoneByKey.C1.x, DEPTH.mid - 3],
        [zoneByKey.L1.x - 1, DEPTH.mid - 19],
        [zoneByKey.R4.x, DEPTH.outer - 16],
    ];
    const tankGeometry = track(new THREE.CylinderGeometry(1, 1, 1, 12));
    const tankBodies = new THREE.InstancedMesh(tankGeometry, materials.slot, tankSpots.length * 2);
    const tankStands = new THREE.InstancedMesh(unitBox, materials.railMetal, tankSpots.length);
    [tankBodies, tankStands].forEach((m) => { m.castShadow = true; m.frustumCulled = false; });

    let tankIndex = 0;
    tankSpots.forEach(([x, z], i) => {
        const standH = 1.3;
        place(tankStands, i, x, DECK_TOP + standH / 2, z, 1.9, standH, 1.9);
        place(tankBodies, tankIndex++, x, DECK_TOP + standH + 1.05, z, 1.7, 2.1, 1.7);
        place(tankBodies, tankIndex++, x, DECK_TOP + standH + 2.2, z, 1.86, 0.22, 1.86);
    });
    tankBodies.instanceMatrix.needsUpdate = true;
    tankStands.instanceMatrix.needsUpdate = true;
    group.add(tankBodies, tankStands);

    // Solar panels, raised on frames.

    const PANEL_TILT = 0.91;      // ~52 degrees, facing the field
    const PANEL_W = 2.2;          // across the roof
    const PANEL_LEN = 1.55;       // up the rake
    const PANEL_RISE = PANEL_LEN * Math.sin(PANEL_TILT);
    const arrays = [
        ['R2', 2, 3, DEPTH.front - 5],
        ['R3', 4, 3, DEPTH.mid - 3],
        ['R4', 3, 3, DEPTH.outer - 3],
        ['L1', 2, 3, DEPTH.mid - 14],
        ['L2', 2, 3, DEPTH.front - 20],
    ];
    const panelCount = arrays.reduce((sum, a) => sum + a[1] * a[2], 0);
    const panels = new THREE.InstancedMesh(unitBox, materials.solar, panelCount);
    const panelFeet = new THREE.InstancedMesh(unitBox, materials.railMetal, panelCount * 2);
    panels.castShadow = true;
    panels.frustumCulled = false;
    panelFeet.castShadow = true;
    panelFeet.frustumCulled = false;

    let panelIndex = 0;
    let footIndex = 0;
    for (const [key, rows, perRow, z0] of arrays) {
        const zone = zoneByKey[key];
        const room = zone.width - 2.4 - PANEL_W;
        const span = Math.max(0, Math.min(room, (perRow - 1) * 2.9));
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < perRow; c++) {
                const x = zone.x + (perRow === 1 ? 0 : (c / (perRow - 1) - 0.5) * span);
                const z = z0 - r * 3.2;
                const mid = DECK_TOP + 0.75 + PANEL_RISE / 2;
                placeTiltedX(panels, panelIndex++, x, mid, z, PANEL_W, 0.1, PANEL_LEN, PANEL_TILT);
                place(panelFeet, footIndex++, x, DECK_TOP + 0.7, z - 0.48, 1.9, 1.4, 0.13);
                place(panelFeet, footIndex++, x, DECK_TOP + 0.26, z + 0.48, 1.9, 0.52, 0.13);
            }
        }
    }
    panels.instanceMatrix.needsUpdate = true;
    panelFeet.instanceMatrix.needsUpdate = true;
    group.add(panels, panelFeet);

    // Satellite dishes: plinth, mast, yoke, dish, rim, feed arm and horn.

    const DISH_TILT = 0.85;                    // ~49 degrees off vertical
    const dishUpY = Math.cos(DISH_TILT);       // dish axis after tilting about x
    const dishUpZ = Math.sin(DISH_TILT);
    const dishSpots = [
        [zoneByKey.R2.x + 5, BACK + 6],
        [zoneByKey.L2.x - 4, BACK + 7],
    ];

    const dishGeometry = track(new THREE.CylinderGeometry(1, 0.82, 1, 14));
    const dishFaces = new THREE.InstancedMesh(dishGeometry, materials.railMetal, dishSpots.length * 2);
    const dishSteel = new THREE.InstancedMesh(unitBox, materials.railMetal, dishSpots.length * 4);
    const dishBases = new THREE.InstancedMesh(unitBox, materials.concrete, dishSpots.length);
    [dishFaces, dishSteel, dishBases].forEach((m) => { m.castShadow = true; m.frustumCulled = false; });

    let faceIndex = 0;
    let steelIndex = 0;
    dishSpots.forEach(([x, z], i) => {
        const mastTop = DECK_TOP + 2.2;
        place(dishBases, i, x, DECK_TOP + 0.05, z, 1.7, 0.5, 1.7);
        place(dishSteel, steelIndex++, x, DECK_TOP + 1.2, z, 0.3, 2.0, 0.3);          // mast
        place(dishSteel, steelIndex++, x, mastTop + 0.3, z, 0.62, 0.62, 0.62);        // yoke

        const cx = x;
        const cy = mastTop + 1.15;
        const cz = z - 0.2;
        placeTiltedX(dishFaces, faceIndex++, cx, cy, cz, 2.3, 0.42, 2.3, DISH_TILT);
        placeTiltedX(dishFaces, faceIndex++, cx, cy + dishUpY * 0.2, cz + dishUpZ * 0.2, 2.5, 0.13, 2.5, DISH_TILT);
        placeTiltedX(dishSteel, steelIndex++, cx, cy + dishUpY * 0.8, cz + dishUpZ * 0.8, 0.15, 1.6, 0.15, DISH_TILT);
        place(dishSteel, steelIndex++, cx, cy + dishUpY * 1.55, cz + dishUpZ * 1.55, 0.38, 0.38, 0.38);
    });
    dishFaces.instanceMatrix.needsUpdate = true;
    dishSteel.instanceMatrix.needsUpdate = true;
    dishBases.instanceMatrix.needsUpdate = true;
    group.add(dishFaces, dishSteel, dishBases);

    // Roof planting, in raised planters.

    const planterSpecs = [
        ['L1', 6.0, 5.0, DEPTH.mid - 4],
        ['L2', 9.0, 5.0, DEPTH.front - 5],
        ['L2', 9.0, 4.0, DEPTH.front - 11],
        ['C3', 4.5, 4.0, DEPTH.mid - 5],
        ['R3', 5.0, 4.0, DEPTH.mid - 13],
    ];
    const planters = new THREE.InstancedMesh(unitBox, materials.concrete, planterSpecs.length);
    planters.castShadow = true;
    planters.receiveShadow = true;
    planters.frustumCulled = false;
    const PLANTER_H = 1.15;
    planterSpecs.forEach(([key, w, d, z], i) => {
        const zone = zoneByKey[key];
        const width = Math.min(w, zone.width - 2.0);
        place(planters, i, zone.x, DECK_TOP - 0.15 + PLANTER_H / 2, z, width, PLANTER_H, d);
        plantClumps.push(...scatterPlants(
            zone.x - width / 2 + 0.5, z - d / 2 + 0.5,
            width - 1.0, d - 1.0, 1.0,
            DECK_TOP + PLANTER_H - 0.15
        ));
    });
    planters.instanceMatrix.needsUpdate = true;
    group.add(planters);


    // Terrace balustrade: glass with a metal cap rail, along the open edge.

    {
        const glassPanes = new THREE.InstancedMesh(unitBox, materials.glass, voidZones.length);
        const capRails = new THREE.InstancedMesh(unitBox, materials.railMetal, voidZones.length);
        glassPanes.frustumCulled = false;
        capRails.castShadow = true;
        capRails.frustumCulled = false;
        voidZones.forEach((zone, i) => {
            const z = zone.front - 0.15;
            place(glassPanes, i, zone.x, zone.voidBase + 0.75, z, zone.width - 0.4, 1.15, 0.09);
            place(capRails, i, zone.x, zone.voidBase + 1.42, z, zone.width - 0.4, 0.24, 0.2);
        });
        glassPanes.instanceMatrix.needsUpdate = true;
        capRails.instanceMatrix.needsUpdate = true;
        group.add(glassPanes, capRails);
    }

    // Bike garage, butted onto R4's end wall with its front edge on R2's line.

    const anchor = zoneByKey.R4;
    const garageX = anchor.x + anchor.width / 2 + GARAGE_WIDTH / 2;
    const garageFront = DEPTH.front;
    const garageBack = anchor.front - 12;
    const garageDepth = garageFront - garageBack;
    const garageZ = garageBack + garageDepth / 2;

    const garageMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.paving.clone(), roughness: 1, metalness: 0,
    }));

    const garageSlab = new THREE.Mesh(unitBox, garageMaterial);
    garageSlab.scale.set(GARAGE_WIDTH, 0.3, garageDepth);
    garageSlab.position.set(garageX, 0.15, garageZ);
    garageSlab.receiveShadow = true;
    group.add(garageSlab);

    // Right-hand boundary: a planted strip, then the university's railing.

    const BED_WIDTH = 1.65;   // planted strip width, metres
    const bedX = garageX + GARAGE_WIDTH / 2 + BED_WIDTH / 2;
    const railX = bedX + BED_WIDTH / 2 + 0.7;

    const bedKerb = new THREE.Mesh(unitBox, materials.paving);
    bedKerb.scale.set(BED_WIDTH, 0.4, garageDepth);
    bedKerb.position.set(bedX, 0.2, garageZ);
    bedKerb.receiveShadow = true;
    group.add(bedKerb);

    plantClumps.push(...scatterPlants(
        bedX - BED_WIDTH / 2 + 0.25, garageBack + 0.6,
        BED_WIDTH - 0.5, garageDepth - 1.2, 0.85, 0.4
    ));

    {
        const railBack = garageBack - 2;
        const railFront = 172;
        const RAIL_H = 1.7;

        // Pedestrian gate between the garage and the football field.
        const GATE_W = 10;
        const GATE_H = 2.7;
        const gateCentre = 25.5;
        const gateZ0 = gateCentre - GATE_W / 2;
        const gateZ1 = gateCentre + GATE_W / 2;

        const postSpacing = 2.6;
        const railRuns = [[railBack, gateZ0 - 0.6], [gateZ1 + 0.6, railFront]];

        const postSlots = railRuns.map(([a, b]) => Math.floor((b - a) / postSpacing) + 1);
        const postCount = postSlots.reduce((sum, n) => sum + n, 0);
        const railPosts = new THREE.InstancedMesh(unitBox, materials.slot, postCount);
        const railBars = new THREE.InstancedMesh(unitBox, materials.slot, railRuns.length * 3);
        [railPosts, railBars].forEach((m) => { m.castShadow = true; m.frustumCulled = false; });

        let postIndex = 0;
        let barIndex = 0;
        railRuns.forEach(([a, b], run) => {
            for (let i = 0; i < postSlots[run]; i++) {
                place(railPosts, postIndex++, railX, RAIL_H / 2, a + i * postSpacing, 0.15, RAIL_H, 0.15);
            }
            const length = b - a;
            [1.62, 1.0, 0.38].forEach((y) => {
                place(railBars, barIndex++, railX, y, a + length / 2, 0.11, 0.11, length);
            });
        });
        railPosts.instanceMatrix.needsUpdate = true;
        railBars.instanceMatrix.needsUpdate = true;
        group.add(railPosts, railBars);

        const piers = new THREE.InstancedMesh(unitBox, materials.concrete, 4);
        piers.castShadow = true;
        piers.receiveShadow = true;
        piers.frustumCulled = false;
        [gateZ0, gateZ1].forEach((z, i) => {
            place(piers, i * 2, railX, 1.75, z, 0.95, 3.5, 0.95);
            place(piers, i * 2 + 1, railX, 3.62, z, 1.2, 0.26, 1.2);
        });
        piers.instanceMatrix.needsUpdate = true;
        group.add(piers);

        const leafSpan = GATE_W / 2 - 0.25;
        const barsPerLeaf = 13;
        const gateParts = new THREE.InstancedMesh(unitBox, materials.slot, 2 * (barsPerLeaf + 3));
        gateParts.castShadow = true;
        gateParts.frustumCulled = false;

        let gateIndex = 0;
        for (const side of [-1, 1]) {
            const leafCentre = gateCentre + side * (leafSpan / 2 + 0.125);
            [GATE_H - 0.12, GATE_H * 0.52, 0.16].forEach((y) => {
                place(gateParts, gateIndex++, railX, y, leafCentre, 0.16, 0.16, leafSpan);
            });
            for (let b = 0; b < barsPerLeaf; b++) {
                const z = leafCentre + (b / (barsPerLeaf - 1) - 0.5) * (leafSpan - 0.2);
                place(gateParts, gateIndex++, railX, GATE_H / 2, z, 0.12, GATE_H, 0.12);
            }
        }
        gateParts.instanceMatrix.needsUpdate = true;
        group.add(gateParts);
    }

    // Bikes on the slab: a body and a wheel pair each, tinted per instance.
    const bikeRows = 9;
    const bikesPerRow = 7;
    const bikeCount = bikeRows * bikesPerRow;

    const bikeMaterial = track(new THREE.MeshStandardMaterial({
        color: new THREE.Color('#ffffff'), roughness: 0.6, metalness: 0.15,
    }));
    const wheelMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.slot.clone(), roughness: 0.95,
    }));

    const bikeBodies = new THREE.InstancedMesh(unitBox, bikeMaterial, bikeCount);
    const bikeWheels = new THREE.InstancedMesh(unitBox, wheelMaterial, bikeCount);
    bikeBodies.castShadow = true;
    bikeBodies.frustumCulled = false;
    bikeWheels.frustumCulled = false;

    const bikeColours = ['#8f2f28', '#243b55', '#3c3f46', '#7a7f88', '#2f5140', '#9c6a24'];
    const bikeTint = new THREE.Color();

    let bikeIndex = 0;
    for (let r = 0; r < bikeRows; r++) {
        for (let b = 0; b < bikesPerRow; b++) {
            const x = garageX + (b - (bikesPerRow - 1) / 2) * (GARAGE_WIDTH / (bikesPerRow + 0.6));
            const z = garageBack + 2.4 + r * ((garageDepth - 5) / (bikeRows - 1));

            place(bikeBodies, bikeIndex, x, 1.25, z, 0.5, 0.6, 1.7);
            place(bikeWheels, bikeIndex, x, 0.74, z, 0.24, 0.68, 1.9);

            bikeTint.set(bikeColours[(r * bikesPerRow + b) % bikeColours.length]);
            bikeBodies.setColorAt(bikeIndex, bikeTint);
            bikeIndex++;
        }
    }
    bikeBodies.instanceMatrix.needsUpdate = true;
    bikeWheels.instanceMatrix.needsUpdate = true;
    if (bikeBodies.instanceColor) bikeBodies.instanceColor.needsUpdate = true;
    group.add(bikeBodies, bikeWheels);

    // Signage: red letters on a dark backing panel, high on R2.

    const signZone = zoneByKey.R2;
    const signY = TOP - 8;

    const backing = new THREE.Mesh(unitBox, materials.slot);
    backing.scale.set(signZone.width * 0.62, 8, 0.6);
    backing.position.set(signZone.x, signY, signZone.front + 1.95);
    group.add(backing);

    const signTexture = track(createSignTexture());
    const signMaterial = track(new THREE.MeshBasicMaterial({
        map: signTexture,
        transparent: true,
        toneMapped: false,
        depthWrite: false,
    }));

    const sign = new THREE.Mesh(track(new THREE.PlaneGeometry(signZone.width * 0.5, signZone.width * 0.25)), signMaterial);
    sign.position.set(signZone.x, signY, signZone.front + 2.35);
    group.add(sign);

    buildPlanting();

    return {
        group,
        materials,

        update(time) {
            facadeTime.value = time;
        },

        applyPalette(p) {
            materials.concrete.color.copy(p.concrete);
            materials.shade.color.copy(p.concreteShade);
            materials.slot.color.copy(p.slot);
            materials.terracotta.color.copy(p.terracotta);
            materials.paving.color.copy(p.paving);
            materials.hedge.color.copy(p.hedge);
            materials.bloomWarm.color.copy(p.bloomWarm);
            materials.bloomCool.color.copy(p.bloomCool);
            materials.pond.color.copy(p.pondWater);
            materials.koi.color.copy(p.koi);
            materials.trunk.color.copy(p.trunk);
            materials.foliage.color.copy(p.foliage);
            materials.solar.color.copy(p.solar);
            materials.railMetal.color.copy(p.railMetal);
            materials.glass.color.copy(p.glassRail);
            materials.interior.emissive.copy(p.interiorGlow);
            materials.interior.emissiveIntensity = p.interiorGlowIntensity * 5.5;
            for (const light of pointLights) {
                light.intensity = p.interiorGlowIntensity * 260;
            }
            materials.recess.emissive.copy(p.interiorGlow);
            materials.recess.emissiveIntensity = p.interiorGlowIntensity;
            materials.recess.color.copy(p.slot);
            benchMaterial.color.copy(p.bench);
            wheelMaterial.color.copy(p.slot);

            for (const material of facadeMaterials) {
                material.color.copy(p.facadeTint);
                material.emissive.copy(p.windowEmissive);
                material.emissiveIntensity = p.windowEmissiveIntensity;
            }
        },

        dispose() {
            disposables.forEach((d) => d.dispose && d.dispose());
        },
    };
}

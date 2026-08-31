/** The residential block across the canal, 500 to 800 m from the camera. */

import * as THREE from 'three';

/** Window bays across one texture tile, and how wide one bay is in metres. */
const BAYS = 6;
const BAY_WIDTH = 3.0;
const TILE_WIDTH = BAYS * BAY_WIDTH;

const FLOOR_HEIGHT = 3.1;
const GROUND_FLOOR = 3.8;

/** Facade texture size, and the flat corner patch the roof faces sample. */
const TEXTURE_SIZE = 512;
const ROOF_PATCH = 12;
/** Middle of that patch, in UV. v is flipped, so the top row is v = 1. */
const ROOF_UV = [(ROOF_PATCH / 2) / TEXTURE_SIZE, 1 - (ROOF_PATCH / 2) / TEXTURE_SIZE];

/** Total height of a building of `storeys` habitable floors. */
const heightOf = (storeys) => GROUND_FLOOR + (storeys - 1) * FLOOR_HEIGHT;

/**
 * x, z          centre of the footprint, in world space
 * w, d          footprint
 * storeys       4 to 8
 * rot           degrees about y
 * state         'done'   finished and occupied
 *               'rising' lower half clad, bare frame above
 *               'frame'  structure only, with a crane
 * tone          index into TONES
 *
 * Ordered nearest to furthest; a low quality tier takes a prefix of the list.
 */
const PLOTS = [
    { x: -292, z: -300, w: 18, d: 15, storeys: 6, rot: 7, state: 'done', tone: 0 },
    { x: -300, z: -352, w: 14, d: 12, storeys: 4, rot: -13, state: 'done', tone: 3 },
    { x: -286, z: -402, w: 21, d: 16, storeys: 8, rot: 3, state: 'frame', tone: 1 },
    { x: -334, z: -344, w: 15, d: 13, storeys: 5, rot: 19, state: 'done', tone: 5 },
    { x: -330, z: -398, w: 19, d: 15, storeys: 7, rot: -8, state: 'rising', tone: 4 },
    { x: -346, z: -452, w: 16, d: 14, storeys: 6, rot: 24, state: 'done', tone: 2 },
    { x: -372, z: -390, w: 13, d: 12, storeys: 4, rot: -18, state: 'done', tone: 6 },
    { x: -378, z: -448, w: 20, d: 17, storeys: 8, rot: 6, state: 'done', tone: 0 },
    { x: -408, z: -430, w: 15, d: 13, storeys: 5, rot: -25, state: 'done', tone: 7 },
    { x: -416, z: -492, w: 18, d: 15, storeys: 7, rot: 12, state: 'done', tone: 2 },
    { x: -448, z: -478, w: 16, d: 14, storeys: 6, rot: -5, state: 'rising', tone: 5 },
];

/** Paint colours, one per building. */
const TONES = [
    '#d9cdb4', '#c2ae95', '#b9c4b6', '#cfbfa6',
    '#c4aeaa', '#b4bcc6', '#d6c79f', '#bdb4a2',
];

function makeRandom(seed) {
    let s = seed >>> 0;
    return () => {
        s = (s * 1664525 + 1013904223) >>> 0;
        return s / 4294967296;
    };
}

/** A residential facade, `rows` floors tall and tiling horizontally only. */
function createHomeTextures(rows, seed) {
    const size = TEXTURE_SIZE;

    const colour = document.createElement('canvas');
    colour.width = size;
    colour.height = size;
    const c = colour.getContext('2d');

    const glow = document.createElement('canvas');
    glow.width = size;
    glow.height = size;
    const g = glow.getContext('2d');

    const cellW = size / BAYS;
    const cellH = size / rows;
    const random = makeRandom(seed);

    // Near-white base; each building's colour arrives as a vertex colour.
    c.fillStyle = '#e8e4da';
    c.fillRect(0, 0, size, size);
    g.fillStyle = '#000000';
    g.fillRect(0, 0, size, size);

    for (let row = 0; row < rows; row++) {
        const top = row * cellH;
        // flipY is on, so canvas row 0 is the top of the building.
        const ground = row === rows - 1;

        // Slab edge, drawn as a shadow line.
        c.fillStyle = 'rgba(0,0,0,0.18)';
        c.fillRect(0, top, size, Math.max(1, cellH * 0.06));

        for (let bay = 0; bay < BAYS; bay++) {
            const left = bay * cellW;
            const inset = cellW * 0.14;
            const x = left + inset;
            const w = cellW - inset * 2;

            if (ground) {
                // Shutters and gates, wider and darker than anything above.
                const h = cellH * 0.5;
                const y = top + cellH * 0.34;
                c.fillStyle = random() < 0.5 ? 'rgba(58,60,58,0.92)' : 'rgba(78,72,62,0.9)';
                c.fillRect(x - inset * 0.4, y, w + inset * 0.8, h);
                if (random() < 0.4) {
                    g.fillStyle = 'rgba(255,255,255,0.5)';
                    g.fillRect(x, y, w, h * 0.8);
                }
                continue;
            }

            const y = top + cellH * 0.26;
            const h = cellH * 0.52;

            if (random() < 0.38) {
                // Balcony: a recess with a solid parapet across the bottom.
                c.fillStyle = 'rgba(40,42,44,0.82)';
                c.fillRect(x - inset * 0.5, y, w + inset, h);
                c.fillStyle = 'rgba(206,200,188,0.95)';
                c.fillRect(x - inset * 0.5, y + h * 0.55, w + inset, h * 0.45);
                c.fillStyle = 'rgba(150,146,138,0.8)';
                c.fillRect(x - inset * 0.5, y + h * 0.52, w + inset, Math.max(1, h * 0.06));

                // A lit balcony is dimmer than a lit room.
                if (random() < 0.5) {
                    g.fillStyle = 'rgba(255,255,255,0.34)';
                    g.fillRect(x, y, w, h * 0.5);
                }
            } else {
                // Window with a security grille.
                c.fillStyle = 'rgba(46,54,58,0.9)';
                c.fillRect(x, y, w, h);
                c.fillStyle = 'rgba(190,188,178,0.45)';
                c.fillRect(x + w * 0.48, y, Math.max(1, w * 0.05), h);
                c.fillRect(x, y + h * 0.46, w, Math.max(1, h * 0.05));

                // Air conditioner on the sill.
                if (random() < 0.28) {
                    c.fillStyle = 'rgba(214,210,200,0.95)';
                    c.fillRect(x + w * 0.3, y + h, w * 0.42, cellH * 0.1);
                }

                if (random() < 0.55) {
                    g.fillStyle = `rgba(255,255,255,${0.5 + random() * 0.5})`;
                    g.fillRect(x, y, w, h);
                }
            }
        }
    }

    // Rain streaks down the whole elevation.
    for (let i = 0; i < 26; i++) {
        const x = random() * size;
        const w = 2 + random() * 7;
        const start = random() * size * 0.5;
        c.fillStyle = `rgba(96,92,80,${0.05 + random() * 0.09})`;
        c.fillRect(x, start, w, size - start);
    }

    // Plain patch in the top-left corner that the roof faces sample.
    c.fillStyle = '#cdc9be';
    c.fillRect(0, 0, ROOF_PATCH, ROOF_PATCH);
    g.fillStyle = '#000000';
    g.fillRect(0, 0, ROOF_PATCH, ROOF_PATCH);

    const map = new THREE.CanvasTexture(colour);
    map.colorSpace = THREE.SRGBColorSpace;
    map.wrapS = THREE.RepeatWrapping;
    map.wrapT = THREE.ClampToEdgeWrapping;
    map.anisotropy = 4;

    const emissiveMap = new THREE.CanvasTexture(glow);
    emissiveMap.colorSpace = THREE.SRGBColorSpace;
    emissiveMap.wrapS = THREE.RepeatWrapping;
    emissiveMap.wrapT = THREE.ClampToEdgeWrapping;
    emissiveMap.anisotropy = 4;

    return { map, emissiveMap };
}

/**
 * Rewrites a unit box's UVs so the facade tiles at a constant metric bay width.
 * Box faces are laid out four vertices each in the order +x, -x, +y, -y, +z, -z.
 */
function retileBox(geometry, w, d) {
    const uv = geometry.attributes.uv;
    if (!uv) return;
    const a = uv.array;
    const alongDepth = d / TILE_WIDTH;
    const alongWidth = w / TILE_WIDTH;

    for (let i = 0; i < 4; i++) {
        a[(0 + i) * 2] *= alongDepth;
        a[(4 + i) * 2] *= alongDepth;
        a[(16 + i) * 2] *= alongWidth;
        a[(20 + i) * 2] *= alongWidth;
    }

    // Roof and underside collapsed onto the flat patch.
    for (let i = 8; i < 16; i++) {
        a[i * 2] = ROOF_UV[0];
        a[i * 2 + 1] = ROOF_UV[1];
    }

    uv.needsUpdate = true;
}

/** Paints every vertex of a geometry one colour, for per-building tinting. */
function tintGeometry(geometry, colour) {
    const count = geometry.attributes.uv ? geometry.attributes.uv.count : 24;
    const data = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        data[i * 3] = colour.r;
        data[i * 3 + 1] = colour.g;
        data[i * 3 + 2] = colour.b;
    }
    geometry.setAttribute('color', new THREE.BufferAttribute(data, 3));
}

/**
 * The alpha ramp for the ground pad: solid in the middle, gone by the edge.
 * Greyscale, since an alphaMap is sampled from the green channel.
 */
function createPadTexture() {
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    const gradient = ctx.createRadialGradient(size / 2, size / 2, size * 0.10, size / 2, size / 2, size * 0.5);
    gradient.addColorStop(0, '#ffffff');
    gradient.addColorStop(0.6, '#d8d8d8');
    gradient.addColorStop(1, '#000000');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, size, size);

    const texture = new THREE.CanvasTexture(canvas);
    return texture;
}

export function createNeighbourhood(palette, quality) {
    const group = new THREE.Group();
    const disposables = [];
    const track = (t) => (disposables.push(t), t);

    const wanted = quality && quality.homes ? quality.homes : 9;
    const plots = PLOTS.slice(0, Math.max(4, Math.min(PLOTS.length, wanted)));

    // The ground they stand on

    const padMaterial = track(new THREE.MeshStandardMaterial({
        alphaMap: track(createPadTexture()),
        color: palette.surround.clone(),
        transparent: true,
        depthWrite: false,
        roughness: 1,
        metalness: 0,
    }));
    const pad = new THREE.Mesh(track(new THREE.PlaneGeometry(330, 380)), padMaterial);
    pad.rotation.x = -Math.PI / 2;
    // Clear of the canal, which ends at x = -260.
    pad.position.set(-380, 0.22, -420);
    pad.renderOrder = 1;
    group.add(pad);

    // Contact shadows: one soft quad per plot, offset to match the sun.

    const shadowMaterial = track(new THREE.MeshBasicMaterial({
        color: new THREE.Color('#0b1208'),
        alphaMap: track(createPadTexture()),
        transparent: true,
        depthWrite: false,
        opacity: 0.34,
        fog: false,
    }));

    // Shared materials

    // One material per floor count: the texture is as many rows tall as floors.
    const wallMaterials = new Map();
    const wallMaterialFor = (rows) => {
        if (!wallMaterials.has(rows)) {
            const made = createHomeTextures(rows, 470021 + rows * 7919);
            track(made.map);
            track(made.emissiveMap);
            wallMaterials.set(rows, track(new THREE.MeshStandardMaterial({
                map: made.map,
                emissiveMap: made.emissiveMap,
                color: palette.homeWall.clone(),
                emissive: palette.homeEmissive.clone(),
                emissiveIntensity: palette.homeEmissiveIntensity,
                vertexColors: true,
                roughness: 1,
                metalness: 0,
            })));
        }
        return wallMaterials.get(rows);
    };

    const roofMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.homeRoof.clone(), roughness: 1, metalness: 0,
    }));
    const tankMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.slot.clone(), roughness: 0.9, metalness: 0,
    }));
    const rawMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.homeConcrete.clone(), roughness: 1, metalness: 0,
    }));
    const craneMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.terracotta.clone(), roughness: 0.8, metalness: 0,
    }));

    const box = (w, h, d, material) => {
        const geometry = track(new THREE.BoxGeometry(w, h, d));
        return new THREE.Mesh(geometry, material);
    };

    // Lanes, boundary walls and outbuildings

    const unitBox = track(new THREE.BoxGeometry(1, 1, 1));
    const _wm = new THREE.Matrix4();
    const _wp = new THREE.Vector3();
    const _wq = new THREE.Quaternion();
    const _ws = new THREE.Vector3();
    const place = (mesh, index, x, y, z, sx, sy, sz) => {
        _wp.set(x, y, z);
        _ws.set(sx, sy, sz);
        _wq.identity();
        _wm.compose(_wp, _wq, _ws);
        mesh.setMatrixAt(index, _wm);
    };

    const laneMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.homeConcrete.clone(), roughness: 1, metalness: 0,
    }));
    const wallMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.homeRoof.clone(), roughness: 1, metalness: 0,
    }));

    {
        // From the bridgehead on the canal bank, back into the cluster.
        const laneFrom = { x: -292, z: -276 };
        const laneTo = { x: -470, z: -516 };
        const dx = laneTo.x - laneFrom.x;
        const dz = laneTo.z - laneFrom.z;
        const laneLength = Math.sqrt(dx * dx + dz * dz);
        const lane = new THREE.Mesh(track(new THREE.PlaneGeometry(7.5, laneLength)), laneMaterial);
        lane.rotation.x = -Math.PI / 2;
        lane.rotation.z = -Math.atan2(dx, dz);
        lane.position.set((laneFrom.x + laneTo.x) / 2, 0.26, (laneFrom.z + laneTo.z) / 2);
        lane.receiveShadow = true;
        group.add(lane);

        // A short spur off it.
        const spur = new THREE.Mesh(track(new THREE.PlaneGeometry(6, 96)), laneMaterial);
        spur.rotation.x = -Math.PI / 2;
        spur.rotation.z = -1.24;
        spur.position.set(-352, 0.27, -404);
        spur.receiveShadow = true;
        group.add(spur);
    }

    // Boundary walls: four low bars round each plot, turned with the building.
    {
        // Wall height in metres.
        const WALL_H = 1.7;
        plots.forEach((plot) => {
            const holder = new THREE.Group();
            holder.position.set(plot.x, 0, plot.z);
            holder.rotation.y = (plot.rot * Math.PI) / 180;
            group.add(holder);
            const pw = plot.w + 9;
            const pd = plot.d + 9;
            // Front, back, left, right.
            const bars = [
                [0, pd / 2, pw, 0.4],
                [0, -pd / 2, pw, 0.4],
                [-pw / 2, 0, 0.4, pd],
                [pw / 2, 0, 0.4, pd],
            ];
            const localWalls = new THREE.InstancedMesh(unitBox, wallMaterial, 4);
            localWalls.castShadow = true;
            localWalls.frustumCulled = false;
            bars.forEach(([bx, bz, bw, bd], i) => {
                place(localWalls, i, bx, WALL_H / 2, bz, bw, WALL_H, bd);
            });
            localWalls.instanceMatrix.needsUpdate = true;
            holder.add(localWalls);
        });
    }

    // Outbuildings: single-storey shops and sheds along the lane.
    {
        const sheds = [
            [-300, -318, 9, 6, 3.4, 12],
            [-318, -366, 7, 6, 3.0, -8],
            [-344, -418, 11, 7, 3.6, 20],
            [-378, -400, 8, 6, 3.2, -14],
            [-398, -470, 10, 7, 3.4, 6],
            [-436, -448, 8, 6, 3.0, 24],
            [-330, -292, 7, 5, 2.8, -20],
        ];
        sheds.forEach(([sx, sz, sw, sd, sh, rot], i) => {
            const holder = new THREE.Group();
            holder.position.set(sx, 0, sz);
            holder.rotation.y = (rot * Math.PI) / 180;
            group.add(holder);
            const body = new THREE.Mesh(unitBox, laneMaterial);
            body.scale.set(sw, sh, sd);
            body.position.y = sh / 2;
            body.castShadow = true;
            body.receiveShadow = true;
            holder.add(body);
            // A roof slab set proud on plan and sunk into the body.
            const roof = new THREE.Mesh(unitBox, wallMaterial);
            roof.scale.set(sw + 0.8, 0.4, sd + 0.8);
            roof.position.y = sh - 0.1;
            roof.castShadow = true;
            holder.add(roof);
        });
    }

    // The buildings

    plots.forEach((plot) => {
        const holder = new THREE.Group();
        holder.name = 'plot';
        holder.position.set(plot.x, 0, plot.z);
        holder.rotation.y = (plot.rot * Math.PI) / 180;
        group.add(holder);

        const { w, d, storeys } = plot;
        const height = heightOf(storeys);

        // Laid into the group, not the holder, so it is not rotated with the building.
        const spread = Math.max(w, d) + height * 1.35;
        const shadow = new THREE.Mesh(track(new THREE.PlaneGeometry(spread, spread)), shadowMaterial);
        shadow.rotation.x = -Math.PI / 2;
        shadow.position.set(plot.x + height * 0.36, 0.30, plot.z - height * 0.52);
        shadow.renderOrder = 2;
        group.add(shadow);
        const tone = new THREE.Color(TONES[plot.tone % TONES.length]);

        // Storeys that are clad: all of them, half, or none.
        const clad = plot.state === 'done' ? storeys
            : plot.state === 'rising' ? Math.max(2, Math.round(storeys / 2))
                : 0;

        if (clad > 0) {
            const cladHeight = heightOf(clad);
            // Set inside the frame's columns on a part-built block.
            const shrink = clad === storeys ? 0 : 0.3;
            const geometry = track(new THREE.BoxGeometry(w - shrink, cladHeight, d - shrink));
            retileBox(geometry, w - shrink, d - shrink);
            tintGeometry(geometry, tone);
            const body = new THREE.Mesh(geometry, wallMaterialFor(clad));
            body.position.y = cladHeight / 2;
            holder.add(body);
        }

        if (clad < storeys) {
            // Bare structure: slabs and columns, nothing between them.
            for (let k = 1; k <= storeys; k++) {
                const y = GROUND_FLOOR + (k - 1) * FLOOR_HEIGHT;
                if (y < heightOf(Math.max(clad, 1)) && clad > 0) continue;
                const slab = box(w, 0.34, d, rawMaterial);
                slab.position.y = y;
                holder.add(slab);
            }

            // Columns, set well inside the slab edge.
            for (const sx of [-1, 0, 1]) {
                if (sx === 0 && w < 16) continue;
                for (const sz of [-1, 1]) {
                    const column = box(0.85, height, 0.85, rawMaterial);
                    column.position.set(sx * (w / 2 - 0.9), height / 2, sz * (d / 2 - 0.9));
                    holder.add(column);
                }
            }
        }

        if (plot.state === 'frame') {
            // One crane: mast, jib and counterweight.
            const mastHeight = height + 17;
            const mast = box(1.1, mastHeight, 1.1, craneMaterial);
            mast.position.set(w / 2 + 4, mastHeight / 2, d / 2 + 3);
            holder.add(mast);

            const jib = box(46, 0.9, 0.9, craneMaterial);
            jib.position.set(w / 2 + 4 + 14, mastHeight - 1.2, d / 2 + 3);
            holder.add(jib);

            const counterweight = box(3.4, 2.2, 2.4, craneMaterial);
            counterweight.position.set(w / 2 + 4 - 10, mastHeight - 2.6, d / 2 + 3);
            holder.add(counterweight);
        }

        if (plot.state === 'done') {
            // Roof slab, dropped into the top of the building and set proud on plan.
            const parapet = box(w + 0.9, 1.7, d + 0.9, roofMaterial);
            parapet.position.y = height + 0.35;
            holder.add(parapet);

            // Stair head and water tank on the roof.
            const stair = box(w * 0.28, 2.9, d * 0.34, roofMaterial);
            stair.position.set(-w * 0.26, height + 2.4, d * 0.18);
            holder.add(stair);

            const tank = box(2.4, 2.0, 2.4, tankMaterial);
            tank.position.set(w * 0.28, height + 2.1, -d * 0.2);
            holder.add(tank);
        }
    });

    // Nothing here is inside the sun's shadow camera.
    group.traverse((child) => {
        child.castShadow = false;
        child.receiveShadow = false;
    });

    return {
        group,

        applyPalette(p) {
            for (const material of wallMaterials.values()) {
                material.color.copy(p.homeWall);
                material.emissive.copy(p.homeEmissive);
                material.emissiveIntensity = p.homeEmissiveIntensity;
            }
            roofMaterial.color.copy(p.homeRoof);
            tankMaterial.color.copy(p.slot);
            rawMaterial.color.copy(p.homeConcrete);
            craneMaterial.color.copy(p.terracotta);
            // Between the lawn and the gravel surround, weighted to the surround.
            padMaterial.color.copy(p.groundColor).lerp(p.surround, 0.85);
            laneMaterial.color.copy(p.homeConcrete);
            wallMaterial.color.copy(p.homeRoof);
            // Contact shadows track the cloud-shadow strength.
            shadowMaterial.opacity = p.cloudShadow * 0.82;
        },

        dispose() {
            disposables.forEach((disposable) => disposable.dispose && disposable.dispose());
        },
    };
}

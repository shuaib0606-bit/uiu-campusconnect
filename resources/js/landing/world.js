/**
 * Everything around the building: ground, the football pitch, animated grass,
 * the canal, the approach road, trees, the distant city and cloud shadows.
 *
 * The grass is one merged mesh with baked world positions, and the wind lives
 * entirely in its vertex shader.
 */

import * as THREE from 'three';

const GROUND_SIZE = 4200;   // metres, wide enough to run under the skyline

// Football pitch size and centre, in metres.
const PITCH_W = 130;
const PITCH_D = 84;
const PITCH_X = -18;
const PITCH_Z = 30;
const PITCH_SURROUND = 16;   // gravel margin, 8 m a side

/** FogExp2, so custom shaders match the fog three applies to standard ones. */
const fogChunk = /* glsl */ `
    vec3 applyFog(vec3 color, float depth, vec3 fogColor, float fogDensity) {
        float f = 1.0 - exp(-fogDensity * fogDensity * depth * depth);
        return mix(color, fogColor, clamp(f, 0.0, 1.0));
    }
`;

/** The same value noise the sky uses; the two must agree. */
const noiseChunk = /* glsl */ `
    float hash(vec2 p) {
        p = fract(p * vec2(123.34, 456.21));
        p += dot(p, p + 45.32);
        return fract(p.x * p.y);
    }

    float noise(vec2 p) {
        vec2 i = floor(p);
        vec2 f = fract(p);
        vec2 u = f * f * f * (f * (f * 6.0 - 15.0) + 10.0);
        float a = hash(i);
        float b = hash(i + vec2(1.0, 0.0));
        float c = hash(i + vec2(0.0, 1.0));
        float d = hash(i + vec2(1.0, 1.0));
        return mix(mix(a, b, u.x), mix(c, d, u.x), u.y);
    }

    float fbm(vec2 p) {
        float total = 0.0;
        float amplitude = 0.5;
        mat2 rot = mat2(0.80, 0.60, -0.60, 0.80);
        for (int i = 0; i < 5; i++) {
            total += amplitude * noise(p);
            p = rot * p * 2.02;
            amplitude *= 0.5;
        }
        return total;
    }
`;

/** Running-bond brick, for the footways. */
function createBrickTexture() {
    const size = 256;
    const rows = 16;
    const cols = 8;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#8a7a76';
    ctx.fillRect(0, 0, size, size);

    const bw = size / cols;
    const bh = size / rows;
    let seed = 1337;
    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;
        return seed / 4294967296;
    };

    for (let r = 0; r < rows; r++) {
        const offset = (r % 2) * (bw / 2);
        for (let c = -1; c < cols + 1; c++) {
            const x = c * bw + offset;
            const tone = 0.82 + rand() * 0.36;
            const v = (n) => Math.round(Math.min(255, n * tone));
            ctx.fillStyle = `rgb(${v(198)},${v(176)},${v(170)})`;
            ctx.fillRect(x + 1.2, r * bh + 1.2, bw - 2.4, bh - 2.4);
        }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.anisotropy = 8;
    return texture;
}

/**
 * The leaf texture: dappled light and shade over a crown mass. Drawn around
 * white and multiplied into the palette's foliage colour.
 */
export function createLeafTexture() {
    const size = 256;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#d8dad0';
    ctx.fillRect(0, 0, size, size);

    let seed = 90210;
    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;
        return seed / 4294967296;
    };

    const passes = [
        { n: 260, min: 3, span: 7, shade: [126, 134, 112], lo: 0.35, hi: 0.3 },
        { n: 320, min: 2, span: 6, shade: [232, 238, 222], lo: 0.40, hi: 0.4 },
        { n: 200, min: 1.5, span: 4, shade: [255, 255, 246], lo: 0.35, hi: 0.45 },
    ];

    for (const pass of passes) {
        for (let i = 0; i < pass.n; i++) {
            const cx = rand() * size;
            const cy = rand() * size;
            const rx = pass.min + rand() * pass.span;
            const ry = rx * (0.5 + rand() * 0.5);
            const alpha = pass.lo + rand() * pass.hi;
            const [cr, cg, cb] = pass.shade;
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(rand() * Math.PI);
            ctx.fillStyle = 'rgba(' + cr + ',' + cg + ',' + cb + ',' + alpha + ')';
            ctx.beginPath();
            ctx.ellipse(0, 0, rx, ry, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.anisotropy = 4;
    return texture;
}

export function createFoliageGeometry(seed) {
    const geometry = new THREE.IcosahedronGeometry(1, 1);
    const position = geometry.attributes && geometry.attributes.position;
    if (!position) return geometry;

    const a = position.array;
    for (let i = 0; i < a.length; i += 3) {
        const x = a[i];
        const y = a[i + 1];
        const z = a[i + 2];
        const length = Math.sqrt(x * x + y * y + z * z) || 1;
        const nx = x / length;
        const ny = y / length;
        const nz = z / length;

        const wave = (fx, fy, fz, phase) =>
            Math.sin(nx * fx + ny * fy + nz * fz + seed * 1.7 + phase);

        // Three octaves of displacement along each vertex's own direction.
        const bump = wave(2.1, 1.7, 2.6, 0) * 0.17
            + wave(4.3, 5.1, 3.7, 1.3) * 0.10
            + wave(8.9, 7.3, 9.7, 2.6) * 0.05;

        const r = 1 + bump;
        a[i] = nx * r;
        a[i + 1] = ny * r * 0.88;
        a[i + 2] = nz * r;
    }
    position.needsUpdate = true;
    if (geometry.computeVertexNormals) geometry.computeVertexNormals();
    return geometry;
}

const grassVertex = /* glsl */ `
    precision highp float;

    attribute float aPhase;   // per-blade randomiser
    attribute float aHeight;  // 0 at the root, 1 at the tip

    uniform float uTime;
    uniform float uWind;

    varying float vHeight;
    varying float vPhase;
    varying float vDepth;

    void main() {
        vHeight = aHeight;
        vPhase = aPhase;

        vec3 pos = position;

        // Bend from the root, at two frequencies.
        float sway = sin(uTime * 1.1 + aPhase * 6.2831 + pos.x * 0.06)
                   + sin(uTime * 0.47 + aPhase * 3.1 + pos.z * 0.045) * 0.5;

        float bend = pow(aHeight, 1.6) * uWind;
        pos.x += sway * bend;
        pos.z += sway * bend * 0.35;
        pos.y -= abs(sway) * bend * 0.12;

        vec4 mvPosition = modelViewMatrix * vec4(pos, 1.0);
        vDepth = -mvPosition.z;
        gl_Position = projectionMatrix * mvPosition;
    }
`;

const grassFragment = /* glsl */ `
    precision highp float;

    uniform vec3  uBase;
    uniform vec3  uTip;
    uniform vec3  uFog;
    uniform float uFogDensity;
    uniform float uLight;

    varying float vHeight;
    varying float vPhase;
    varying float vDepth;

    ${fogChunk}

    void main() {
        vec3 color = mix(uBase, uTip, vHeight);

        color *= 0.86 + fract(vPhase * 91.7) * 0.28;

        color *= mix(0.55, 1.0, smoothstep(0.0, 0.55, vHeight));
        color *= uLight;

        gl_FragColor = vec4(applyFog(color, vDepth, uFog, uFogDensity), 1.0);
    }
`;

const waterVertex = /* glsl */ `
    precision highp float;
    varying vec2 vUv;
    varying float vDepth;

    void main() {
        vUv = uv;
        vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
        vDepth = -mvPosition.z;
        gl_Position = projectionMatrix * mvPosition;
    }
`;

const waterFragment = /* glsl */ `
    precision highp float;

    uniform vec3  uColor;
    uniform vec3  uSky;
    uniform vec3  uFog;
    uniform float uFogDensity;
    uniform float uTime;
    uniform float uOpacity;
    uniform float uNight;

    varying vec2 vUv;
    varying float vDepth;

    ${fogChunk}

    void main() {
        float r = sin(vUv.x * 180.0 + uTime * 0.9)
                * sin(vUv.y * 40.0 - uTime * 0.6);
        float glint = smoothstep(0.55, 1.0, r);

        vec3 color = mix(uColor, uSky, 0.35 + glint * 0.4);

        // Moon glitter: a shimmering column running towards the viewer.
        float column = exp(-pow((vUv.x - 0.46) * 7.0, 2.0));
        float shimmer = pow(max(sin(vUv.y * 260.0 + uTime * 1.6)
                              * sin(vUv.y * 91.0 - uTime * 0.7), 0.0), 8.0);
        color += vec3(0.72, 0.79, 1.0) * column * shimmer * 0.9 * uNight;

        gl_FragColor = vec4(applyFog(color, vDepth, uFog, uFogDensity), uOpacity);
    }
`;

const poolVertex = /* glsl */ `
    precision highp float;
    varying vec3 vWorld;

    void main() {
        vec4 world = modelMatrix * vec4(position, 1.0);
        vWorld = world.xyz;
        gl_Position = projectionMatrix * viewMatrix * world;
    }
`;

const poolFragment = /* glsl */ `
    precision highp float;

    uniform vec3  uColor;
    uniform float uStrength;

    uniform float uLampZ;        // the amenity lamp row
    uniform float uLampSpacing;
    uniform float uLampSpan;
    uniform float uLampReach;

    uniform vec2  uMastCorner;   // pitch floodlight masts, one quadrant
    uniform float uMastX;        // the pitch centre, which is not the origin
    uniform float uMastZ;
    uniform float uMastReach;

    varying vec3 vWorld;

    void main() {
        if (uStrength <= 0.001) discard;

        vec2 p = vWorld.xz;

        // Amenity lamps: distance from the row, scalloped along it.
        float lane = exp(-pow((p.y - uLampZ) / uLampReach, 2.0));
        float along = 0.62 + 0.38 * cos((p.x / uLampSpacing) * 6.2831853);
        float ends = smoothstep(uLampSpan + 30.0, uLampSpan - 10.0, abs(p.x));
        float lamps = lane * along * ends;

        // Floodlight masts: folded into one quadrant about the pitch centre,
        // so a single falloff covers all four corners.
        vec2 q = abs(vec2(p.x - uMastX, p.y - uMastZ)) - uMastCorner;
        float floods = exp(-pow(length(q) / uMastReach, 2.0));

        // Weighted towards the masts.
        float light = clamp(lamps * 0.45 + floods * 1.0, 0.0, 1.0);

        gl_FragColor = vec4(uColor * light * uStrength, 1.0);
    }
`;

const shadowVertex = /* glsl */ `
    precision highp float;
    varying vec3 vWorld;
    varying float vDepth;

    void main() {
        vec4 world = modelMatrix * vec4(position, 1.0);
        vWorld = world.xyz;
        vec4 mvPosition = viewMatrix * world;
        vDepth = -mvPosition.z;
        gl_Position = projectionMatrix * mvPosition;
    }
`;

const shadowFragment = /* glsl */ `
    precision highp float;

    uniform float uTime;
    uniform float uCloudCover;
    uniform float uStrength;

    varying vec3 vWorld;
    varying float vDepth;

    ${noiseChunk}

    void main() {
        // The sky's cloud field, sampled in world space on the ground. The
        // scale assumes a cloud height of roughly 600 m.
        vec2 uv = vWorld.xz * 0.0005;
        vec2 drift = vec2(uTime * 0.060, uTime * 0.023);

        float lower = fbm(uv * 0.8 + drift);
        float upper = fbm(uv * 1.9 - drift * 1.7);
        float density = lower * 0.68 + upper * 0.32;

        float threshold = 0.62 - uCloudCover * 0.38;
        float mask = smoothstep(threshold - 0.06, threshold + 0.14, density);

        // Fade out with distance, where the fog would wash them away.
        mask *= smoothstep(1400.0, 500.0, vDepth);

        float shade = 1.0 - mask * uStrength;
        gl_FragColor = vec4(vec3(shade), 1.0);
    }
`;

/** One grass blade: five vertices, three triangles, tapering to a point. */
function pushBlade(target, x, z, height, width, lean, phase) {
    const { positions, heights, phases, indices } = target;
    const base = positions.length / 3;

    const dx = Math.cos(lean) * width;
    const dz = Math.sin(lean) * width;

    const verts = [
        [x - dx, 0, z - dz, 0.0],
        [x + dx, 0, z + dz, 0.0],
        [x - dx * 0.6, height * 0.55, z - dz * 0.6, 0.55],
        [x + dx * 0.6, height * 0.55, z + dz * 0.6, 0.55],
        [x, height, z, 1.0],
    ];

    for (const [vx, vy, vz, h] of verts) {
        positions.push(vx, vy, vz);
        heights.push(h);
        phases.push(phase);
    }

    indices.push(
        base + 0, base + 1, base + 2,
        base + 1, base + 3, base + 2,
        base + 2, base + 3, base + 4
    );
}

/** Low-frequency mottling for the open ground. */
function createGroundTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 512;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 512, 512);

    for (let i = 0; i < 700; i++) {
        const shade = 208 + Math.floor(Math.random() * 48);
        ctx.fillStyle = `rgba(${shade},${shade},${shade},0.22)`;
        ctx.beginPath();
        ctx.arc(Math.random() * 512, Math.random() * 512, 10 + Math.random() * 52, 0, Math.PI * 2);
        ctx.fill();
    }

    for (let i = 0; i < 130; i++) {
        ctx.fillStyle = `rgba(150,155,140,${0.10 + Math.random() * 0.14})`;
        ctx.beginPath();
        ctx.arc(Math.random() * 512, Math.random() * 512, 14 + Math.random() * 46, 0, Math.PI * 2);
        ctx.fill();
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(22, 22);
    texture.anisotropy = 8;
    return texture;
}

/** The pitch: mowing stripes and white markings. */
function createPitchTexture() {
    const canvas = document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 640;
    const ctx = canvas.getContext('2d');

    const stripes = 12;
    for (let i = 0; i < stripes; i++) {
        ctx.fillStyle = i % 2 === 0 ? '#ffffff' : '#efefef';
        ctx.fillRect((i * canvas.width) / stripes, 0, canvas.width / stripes + 1, canvas.height);
    }

    ctx.strokeStyle = 'rgba(255,255,255,0.85)';
    ctx.lineWidth = 4;

    const m = 26;
    ctx.strokeRect(m, m, canvas.width - m * 2, canvas.height - m * 2);

    ctx.beginPath();
    ctx.moveTo(canvas.width / 2, m);
    ctx.lineTo(canvas.width / 2, canvas.height - m);
    ctx.stroke();

    ctx.beginPath();
    ctx.arc(canvas.width / 2, canvas.height / 2, 78, 0, Math.PI * 2);
    ctx.stroke();

    // Penalty areas.
    const boxD = 150;
    const boxH = 300;
    ctx.strokeRect(m, (canvas.height - boxH) / 2, boxD, boxH);
    ctx.strokeRect(canvas.width - m - boxD, (canvas.height - boxH) / 2, boxD, boxH);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 8;
    return texture;
}

export function createWorld(palette, quality) {
    const group = new THREE.Group();
    const disposables = [];
    const track = (t) => (disposables.push(t), t);

    // The right-hand boundary: railing, footways, road and divider all key off
    // RAIL_X, which matches the railing built in campus.js.
    const RAIL_X = 58.7;
    const PAVE_W = 2.2;       // footway width, metres
    const PAVE_TOP = 0.34;    // footway top, metres above ground
    const DIVIDER_W = 2.0;    // central reservation width, metres
    const LANE_PAIR = 7.0;    // width of one pair of lanes, metres
    const ROAD_W = LANE_PAIR * 2 + DIVIDER_W;
    const ROAD_INNER = RAIL_X + 0.5 + PAVE_W;
    const roadX = ROAD_INNER + ROAD_W / 2;
    const ROAD_FAR = ROAD_INNER + ROAD_W;
    const ENTRY_Z = -104;     // z of the entrance road behind the buildings

    // Ground

    const groundMaterial = track(new THREE.MeshStandardMaterial({
        map: track(createGroundTexture()),
        color: palette.groundColor.clone(),
        roughness: 1,
        metalness: 0,
    }));

    const ground = new THREE.Mesh(track(new THREE.PlaneGeometry(GROUND_SIZE, GROUND_SIZE)), groundMaterial);
    ground.rotation.x = -Math.PI / 2;
    ground.receiveShadow = true;
    group.add(ground);

    // Pitch, lifted a few centimetres clear of the ground plane.

    const pitchTexture = track(createPitchTexture());
    const fieldMaterial = track(new THREE.MeshStandardMaterial({
        map: pitchTexture,
        color: palette.pitchColor.clone(),
        roughness: 1,
        metalness: 0,
    }));

    const field = new THREE.Mesh(track(new THREE.PlaneGeometry(PITCH_W, PITCH_D)), fieldMaterial);
    field.rotation.x = -Math.PI / 2;
    field.position.set(PITCH_X, 0.30, PITCH_Z);
    field.receiveShadow = true;
    group.add(field);

    // Paved ground: an apron in front of the building and a pitch surround.

    const pavingMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.paving.clone(), roughness: 1, metalness: 0,
    }));

    const surroundMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.surround.clone(), roughness: 1, metalness: 0,
    }));

    const surround = new THREE.Mesh(track(new THREE.PlaneGeometry(PITCH_W + PITCH_SURROUND, PITCH_D + PITCH_SURROUND)), surroundMaterial);
    surround.rotation.x = -Math.PI / 2;
    surround.position.set(PITCH_X, 0.20, PITCH_Z);
    surround.receiveShadow = true;
    group.add(surround);

    // Apron along the front of the building, out to the field.
    const apron = new THREE.Mesh(track(new THREE.PlaneGeometry(116, 42)), pavingMaterial);
    apron.rotation.x = -Math.PI / 2;
    apron.position.set(0.7, 0.26, -35);
    apron.receiveShadow = true;
    group.add(apron);

    const boxUnit = track(new THREE.BoxGeometry(1, 1, 1));

    // Shared instancing temporaries; the traffic rewrites matrices per frame.
    const matrix = new THREE.Matrix4();
    const pos = new THREE.Vector3();
    const quat = new THREE.Quaternion();
    const euler = new THREE.Euler();
    const scale = new THREE.Vector3();

    // A tree is a trunk that forks between 42% and 58% of its height into
    // limbs, each carrying crown masses, with one more mass over the fork.
    const LIMBS = 3;
    const LOBES = LIMBS * 2 + 1;   // two masses per limb, plus one at the fork
    const trunkGeometry = track(new THREE.CylinderGeometry(0.42, 0.72, 1, 8));
    const limbGeometry = track(new THREE.CylinderGeometry(0.16, 0.34, 1, 6));
    const LOBE_VARIANTS = 3;   // crown shapes, so neighbouring trees differ
    const lobeGeometries = [];
    for (let v = 0; v < LOBE_VARIANTS; v++) {
        lobeGeometries.push(track(createFoliageGeometry(v * 3.1 + 1)));
    }
    /** Trees whose index falls on variant `v`, out of `total`. */
    const variantShare = (total, v) => Math.floor((total - v + LOBE_VARIANTS - 1) / LOBE_VARIANTS);

    /**
     * Places one tree across three instanced meshes: the trunk at `index`,
     * LIMBS branches from `index * LIMBS`, LOBES crown masses from
     * `index * LOBES`.
     */
    const placeTree = (trunkMesh, limbMesh, lobeMesh, lobeBase, index, x, z, height, spread, seed) => {
        // Trunk thickness follows the tree's height.
        const girth = Math.max(0.16, height * 0.035) / 0.72;
        const rnd = (n) => {
            const v = Math.sin(seed * 12.9898 + n * 78.233) * 43758.5453;
            return v - Math.floor(v);
        };

        const fork = height * (0.42 + rnd(0) * 0.16);
        const trunkLen = fork + height * 0.1;
        pos.set(x, trunkLen / 2, z);
        scale.set(girth, trunkLen, girth);
        quat.identity();
        matrix.compose(pos, quat, scale);
        trunkMesh.setMatrixAt(index, matrix);

        const reach = height - fork;
        for (let l = 0; l < LIMBS; l++) {
            // Evenly spaced around the trunk, then jittered.
            const azimuth = (l / LIMBS) * Math.PI * 2 + rnd(l + 1) * 2.1;
            const tilt = 0.3 + rnd(l + 5) * 0.75;
            const length = reach * (0.75 + rnd(l + 9) * 0.55);

            // Euler (0, azimuth, tilt) takes the cylinder's +y axis here.
            const dx = -Math.sin(tilt) * Math.cos(azimuth);
            const dy = Math.cos(tilt);
            const dz = Math.sin(tilt) * Math.sin(azimuth);

            const thick = girth * (0.72 + rnd(l + 17) * 0.4);
            pos.set(x + dx * length / 2, fork + dy * length / 2, z + dz * length / 2);
            scale.set(thick, length, thick);
            euler.set(0, azimuth, tilt);
            quat.setFromEuler(euler);
            matrix.compose(pos, quat, scale);
            limbMesh.setMatrixAt(index * LIMBS + l, matrix);

            const along = [0.96, 0.62];
            const size = [0.34 + rnd(l + 13) * 0.2, 0.22 + rnd(l + 41) * 0.16];
            for (let m = 0; m < 2; m++) {
                const r = spread * size[m];
                const t = along[m];
                pos.set(
                    x + dx * length * t,
                    fork + dy * length * t + r * (m === 0 ? 0.3 : 0.45),
                    z + dz * length * t
                );
                scale.set(
                    r * (0.86 + rnd(l + 21 + m) * 0.34),
                    r * 0.8,
                    r * (0.86 + rnd(l + 25 + m) * 0.34)
                );
                euler.set(rnd(l + 29 + m) * 1.1, rnd(l + 33 + m) * Math.PI, rnd(l + 37 + m) * 1.1);
                quat.setFromEuler(euler);
                matrix.compose(pos, quat, scale);
                lobeMesh.setMatrixAt(lobeBase + l * 2 + m, matrix);
            }
        }

        const rc = spread * (0.34 + rnd(43) * 0.2);
        pos.set(x + spread * (rnd(43) - 0.5) * 0.3, fork + reach * (0.55 + rnd(45) * 0.25), z + spread * (rnd(47) - 0.5) * 0.3);
        scale.set(rc, rc * 0.8, rc);
        euler.set(rnd(49) * 1.1, rnd(51) * Math.PI, rnd(53) * 1.1);
        quat.setFromEuler(euler);
        matrix.compose(pos, quat, scale);
        lobeMesh.setMatrixAt(lobeBase + LIMBS * 2, matrix);
        quat.identity();
    };

    const _m = new THREE.Matrix4();
    const _p = new THREE.Vector3();
    const _q = new THREE.Quaternion();
    const _s = new THREE.Vector3();
    const placeBox = (mesh, index, x, y, z, sx, sy, sz) => {
        _p.set(x, y, z);
        _s.set(sx, sy, sz);
        _q.identity();
        _m.compose(_p, _q, _s);
        mesh.setMatrixAt(index, _m);
    };

    // Clipped hedge along the outer edge of the apron.
    const hedgeMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.hedge.clone(), roughness: 1, flatShading: true,
    }));
    const hedgeCount = 22;
    const hedges = new THREE.InstancedMesh(boxUnit, hedgeMaterial, hedgeCount);
    hedges.castShadow = true;
    hedges.receiveShadow = true;
    hedges.frustumCulled = false;
    for (let i = 0; i < hedgeCount; i++) {
        const x = -116 + i * 8;
        placeBox(hedges, i, x, 0.7, -13, 6.4, 1.4, 2.0);
    }
    hedges.instanceMatrix.needsUpdate = true;
    group.add(hedges);

    // Lamp posts along the apron. The heads carry the emissive.
    const lampCount = 9;
    const postMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.concreteShade.clone(), roughness: 0.9,
    }));
    const lampHeadMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.lampBody.clone(),
        emissive: palette.lampGlow.clone(),
        emissiveIntensity: 1,
        roughness: 0.6,
    }));

    const posts = new THREE.InstancedMesh(boxUnit, postMaterial, lampCount);
    const lampHeads = new THREE.InstancedMesh(boxUnit, lampHeadMaterial, lampCount);
    posts.castShadow = true;
    posts.frustumCulled = false;
    lampHeads.frustumCulled = false;
    for (let i = 0; i < lampCount; i++) {
        const x = -110 + i * 20;
        placeBox(posts, i, x, 3.6, -17, 0.45, 7.2, 0.45);
        placeBox(lampHeads, i, x, 7.5, -17, 1.5, 0.5, 1.1);
    }
    posts.instanceMatrix.needsUpdate = true;
    lampHeads.instanceMatrix.needsUpdate = true;
    group.add(posts, lampHeads);

    // Floodlight masts at the corners of the pitch.
    const mastHeight = 18;
    const mastX = PITCH_W / 2 + 3;        // 3 m clear of the touch line
    const mastZ = PITCH_D / 2 + 11;
    const masts = new THREE.InstancedMesh(boxUnit, postMaterial, 4);
    const mastHeads = new THREE.InstancedMesh(boxUnit, lampHeadMaterial, 4);
    masts.castShadow = true;
    masts.frustumCulled = false;
    mastHeads.frustumCulled = false;

    let mastIndex = 0;
    for (const sx of [-1, 1]) {
        for (const sz of [-1, 1]) {
            const x = PITCH_X + sx * mastX;
            const z = PITCH_Z + sz * mastZ;
            placeBox(masts, mastIndex, x, mastHeight / 2, z, 0.9, mastHeight, 0.9);
            placeBox(mastHeads, mastIndex, x, mastHeight + 1.2, z, 5.0, 1.6, 1.6);
            mastIndex++;
        }
    }
    masts.instanceMatrix.needsUpdate = true;
    mastHeads.instanceMatrix.needsUpdate = true;
    group.add(masts, mastHeads);

    // Pools of light on the ground, additively blended.
    const poolMaterial = track(new THREE.ShaderMaterial({
        vertexShader: poolVertex,
        fragmentShader: poolFragment,
        blending: THREE.AdditiveBlending,
        transparent: true,
        depthWrite: false,
        uniforms: {
            uColor: { value: palette.lampPool.clone() },
            uStrength: { value: palette.lampPoolStrength },
            uLampZ: { value: -17 },
            uLampSpacing: { value: 20 },
            uLampSpan: { value: 118 },
            uLampReach: { value: 24 },
            uMastCorner: { value: new THREE.Vector2(mastX, mastZ) },
            uMastX: { value: PITCH_X },
            uMastZ: { value: PITCH_Z },
            uMastReach: { value: 112 },
        },
    }));

    const pools = new THREE.Mesh(track(new THREE.PlaneGeometry(560, 460)), poolMaterial);
    pools.rotation.x = -Math.PI / 2;
    pools.position.set(0, 0.62, 20);
    pools.renderOrder = 4;
    group.add(pools);

    // Traffic: each car is a body, a cabin and four wheels, looping.

    const ROAD_CARS = 18;
    const ENTRY_CARS = 6;
    const carCount = ROAD_CARS + ENTRY_CARS;

    const carMaterial = track(new THREE.MeshStandardMaterial({
        color: new THREE.Color('#ffffff'), roughness: 0.5, metalness: 0.12,
    }));
    const cabinMaterial = track(new THREE.MeshStandardMaterial({
        color: new THREE.Color('#2b3138'), roughness: 0.25, metalness: 0.3,
    }));
    const wheelMaterial = track(new THREE.MeshStandardMaterial({
        color: new THREE.Color('#17181a'), roughness: 0.9,
    }));

    const carBodies = new THREE.InstancedMesh(boxUnit, carMaterial, carCount);
    const carCabins = new THREE.InstancedMesh(boxUnit, cabinMaterial, carCount);
    const carWheels = new THREE.InstancedMesh(boxUnit, wheelMaterial, carCount * 4);
    carBodies.castShadow = true;
    carBodies.frustumCulled = false;
    carCabins.frustumCulled = false;
    carWheels.frustumCulled = false;

    const carColours = ['#b8bcc4', '#8d939c', '#5c6673', '#9c3f34', '#2f4a63', '#c9c4bb', '#3f4b40'];
    const carTint = new THREE.Color();

    // Per car: direction, start, speed and loop extent.
    const MAIN_Z0 = -560;
    const MAIN_SPAN = 690;
    const ENTRY_X0 = -70;
    const ENTRY_SPAN = 150;
    const traffic = [];
    for (let i = 0; i < ROAD_CARS; i++) {
        const lane = i % 4;
        const towards = lane >= 2;
        traffic.push({
            axis: 'z',
            across: roadX + [-5.2, -1.9, 1.9, 5.2][lane],
            start: MAIN_Z0 + (i / ROAD_CARS) * MAIN_SPAN,
            dir: towards ? 1 : -1,
            speed: 15 + (i % 5) * 2.4,
            min: MAIN_Z0,
            span: MAIN_SPAN,
        });
    }
    for (let i = 0; i < ENTRY_CARS; i++) {
        traffic.push({
            axis: 'x',
            across: ENTRY_Z + (i % 2 ? 2.3 : -2.3),
            start: ENTRY_X0 + (i / ENTRY_CARS) * ENTRY_SPAN,
            dir: i % 2 ? 1 : -1,
            speed: 8 + (i % 3) * 1.6,
            min: ENTRY_X0,
            span: ENTRY_SPAN,
        });
    }

    traffic.forEach((car, i) => {
        carTint.set(carColours[i % carColours.length]);
        carBodies.setColorAt(i, carTint);
    });

    const layoutTraffic = (time) => {
        traffic.forEach((car, i) => {
            const travelled = car.start + car.dir * car.speed * time - car.min;
            const along = ((travelled % car.span) + car.span) % car.span + car.min;
            const alongZ = car.axis === 'z';
            const x = alongZ ? car.across : along;
            const z = alongZ ? along : car.across;
            const lx = alongZ ? 2.0 : 4.4;
            const lz = alongZ ? 4.4 : 2.0;

            placeBox(carBodies, i, x, 0.78, z, lx, 0.76, lz);
            placeBox(carCabins, i, x, 1.42, z - (alongZ ? 0.25 : 0), lx * 0.86, 0.62, lz * (alongZ ? 0.5 : 0.86));
            for (let w = 0; w < 4; w++) {
                const ox = (w % 2 ? 1 : -1) * (alongZ ? 0.92 : 1.5);
                const oz = (w < 2 ? -1 : 1) * (alongZ ? 1.5 : 0.92);
                placeBox(carWheels, i * 4 + w, x + ox, 0.34, z + oz, 0.34, 0.62, 0.62);
            }
        });
        carBodies.instanceMatrix.needsUpdate = true;
        carCabins.instanceMatrix.needsUpdate = true;
        carWheels.instanceMatrix.needsUpdate = true;
    };

    layoutTraffic(0);
    if (carBodies.instanceColor) carBodies.instanceColor.needsUpdate = true;
    group.add(carBodies, carCabins, carWheels);

    // Grass

    const bladeTarget = { positions: [], heights: [], phases: [], indices: [] };
    const bladeCount = quality.grassBlades;

    for (let i = 0; i < bladeCount; i++) {
        // Blades cover the field and its verges only, stopping at the footway.
        const GRASS_MAX_X = RAIL_X - PAVE_W - 1.6;
        let x = (Math.random() - 0.5) * 380;
        if (x > GRASS_MAX_X) {
            x = GRASS_MAX_X - (x - GRASS_MAX_X) * 0.9;
        }
        const z = -30 + Math.pow(Math.random(), 0.6) * 240;

        const height = 0.26 + Math.random() * 0.5;
        const width = 0.05 + Math.random() * 0.045;
        pushBlade(bladeTarget, x, z, height, width, Math.random() * Math.PI, Math.random());
    }

    const grassGeometry = track(new THREE.BufferGeometry());
    grassGeometry.setAttribute('position', new THREE.Float32BufferAttribute(bladeTarget.positions, 3));
    grassGeometry.setAttribute('aHeight', new THREE.Float32BufferAttribute(bladeTarget.heights, 1));
    grassGeometry.setAttribute('aPhase', new THREE.Float32BufferAttribute(bladeTarget.phases, 1));
    grassGeometry.setIndex(bladeTarget.indices);
    grassGeometry.computeBoundingSphere();

    const grassMaterial = track(new THREE.ShaderMaterial({
        vertexShader: grassVertex,
        fragmentShader: grassFragment,
        side: THREE.DoubleSide,
        uniforms: {
            uTime: { value: 0 },
            uWind: { value: 0.42 },
            uBase: { value: palette.grassBase.clone() },
            uTip: { value: palette.grassTip.clone() },
            uFog: { value: palette.fog.clone() },
            uFogDensity: { value: palette.fogDensity },
            uLight: { value: 1.0 },
        },
    }));

    group.add(new THREE.Mesh(grassGeometry, grassMaterial));

    // Cloud shadows: a multiply-blended sheet from the sky's noise field.

    const shadowMaterial = track(new THREE.ShaderMaterial({
        vertexShader: shadowVertex,
        fragmentShader: shadowFragment,
        blending: THREE.MultiplyBlending,
        transparent: true,
        premultipliedAlpha: true,
        depthWrite: false,
        uniforms: {
            uTime: { value: 0 },
            uCloudCover: { value: palette.cloudCover },
            uStrength: { value: palette.cloudShadow },
        },
    }));

    const cloudShadows = new THREE.Mesh(track(new THREE.PlaneGeometry(1600, 1600)), shadowMaterial);
    cloudShadows.rotation.x = -Math.PI / 2;
    cloudShadows.position.y = 1.6;
    cloudShadows.renderOrder = 2;
    group.add(cloudShadows);

    // Canal

    const waterMaterial = track(new THREE.ShaderMaterial({
        vertexShader: waterVertex,
        fragmentShader: waterFragment,
        transparent: true,
        uniforms: {
            uTime: { value: 0 },
            uColor: { value: palette.water.clone() },
            uSky: { value: palette.skyHorizon.clone() },
            uFog: { value: palette.fog.clone() },
            uFogDensity: { value: palette.fogDensity },
            uOpacity: { value: palette.waterOpacity },
            uNight: { value: 0 },
        },
    }));

    const CANAL_LENGTH = 1900;   // metres, long enough to fade into the fog
    const CANAL_Z = -30;
    const water = new THREE.Mesh(track(new THREE.PlaneGeometry(90, CANAL_LENGTH)), waterMaterial);
    water.rotation.x = -Math.PI / 2;
    water.position.set(-215, 0.45, CANAL_Z);
    water.renderOrder = 3;
    group.add(water);

    const bankMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.concreteShade.clone(), roughness: 1,
    }));
    const bank = new THREE.Mesh(track(new THREE.BoxGeometry(8, 3, CANAL_LENGTH)), bankMaterial);
    bank.position.set(-168, 1.2, CANAL_Z);
    bank.receiveShadow = true;
    bank.castShadow = true;
    group.add(bank);

    // The matching wall on the far side, set 2 m into the water's far edge.
    const farBank = new THREE.Mesh(track(new THREE.BoxGeometry(8, 3, CANAL_LENGTH)), bankMaterial);
    farBank.position.set(-262, 1.2, CANAL_Z);
    farBank.receiveShadow = true;
    group.add(farBank);

    // Canal bridge: one lane on piers, with a parapet down both sides.

    const BRIDGE_Z = -268;
    const BRIDGE_W = 9;            // one lane plus footways
    const BRIDGE_Y = 3.5;          // deck level, clear of the 2.7 m bank tops
    const bridgeX0 = -284;
    const bridgeX1 = -150;
    const bridgeSpan = bridgeX1 - bridgeX0;

    const deck = new THREE.Mesh(
        track(new THREE.BoxGeometry(bridgeSpan, 0.7, BRIDGE_W)), bankMaterial
    );
    deck.position.set((bridgeX0 + bridgeX1) / 2, BRIDGE_Y, BRIDGE_Z);
    deck.castShadow = true;
    deck.receiveShadow = true;
    group.add(deck);

    const pierCount = 4;   // piers across the 90 m of water
    const piers = new THREE.InstancedMesh(boxUnit, bankMaterial, pierCount);
    piers.castShadow = true;
    piers.frustumCulled = false;
    for (let i = 0; i < pierCount; i++) {
        const x = -252 + (i / (pierCount - 1)) * 74;
        placeBox(piers, i, x, (BRIDGE_Y - 0.35) / 2, BRIDGE_Z, 2.6, BRIDGE_Y - 0.35, 4.4);
    }
    piers.instanceMatrix.needsUpdate = true;
    group.add(piers);

    // Parapets, then the approach ramps down to the ground on each side.
    const bridgeRails = new THREE.InstancedMesh(boxUnit, bankMaterial, 2);
    bridgeRails.castShadow = true;
    bridgeRails.frustumCulled = false;
    [-1, 1].forEach((side, i) => {
        placeBox(
            bridgeRails, i,
            (bridgeX0 + bridgeX1) / 2, BRIDGE_Y + 0.85, BRIDGE_Z + side * (BRIDGE_W / 2 - 0.35),
            bridgeSpan, 1.0, 0.5
        );
    });
    bridgeRails.instanceMatrix.needsUpdate = true;
    group.add(bridgeRails);

    const approaches = new THREE.InstancedMesh(boxUnit, bankMaterial, 2);
    approaches.receiveShadow = true;
    approaches.frustumCulled = false;
    placeBox(approaches, 0, bridgeX0 - 11, BRIDGE_Y / 2, BRIDGE_Z, 24, BRIDGE_Y, BRIDGE_W);
    placeBox(approaches, 1, bridgeX1 + 11, BRIDGE_Y / 2, BRIDGE_Z, 24, BRIDGE_Y, BRIDGE_W);
    approaches.instanceMatrix.needsUpdate = true;
    group.add(approaches);

    // The boundary, the pavements and the main road, outwards from campus:
    //     brick footway | railing | brick footway | carriageway | divider |
    //     carriageway | brick footway
    // The railing itself is built in campus.js. Footways are raised slabs.

    const roadMaterial = track(new THREE.MeshStandardMaterial({
        color: new THREE.Color('#2f3033'), roughness: 0.95,
    }));
    const trunkMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.trunk.clone(), roughness: 1,
    }));
    const foliageMaterial = track(new THREE.MeshStandardMaterial({
        map: track(createLeafTexture()),
        color: palette.foliage.clone(), roughness: 1, flatShading: true,
    }));
    const brickMaterial = track(new THREE.MeshStandardMaterial({
        map: track(createBrickTexture()),
        color: palette.brick.clone(),
        roughness: 1,
        metalness: 0,
    }));

    const footway = (x, z, width, length) => {
        const geometry = track(new THREE.BoxGeometry(width, PAVE_TOP, length));
        const slab = new THREE.Mesh(geometry, brickMaterial);
        slab.position.set(x, PAVE_TOP / 2, z);
        slab.receiveShadow = true;
        slab.castShadow = true;
        group.add(slab);
        return slab;
    };

    // Campus side of the railing, along the length of the field.
    footway(RAIL_X - 0.5 - PAVE_W / 2, 36.5, PAVE_W, 139);
    footway(RAIL_X + 0.5 + PAVE_W / 2, -95, PAVE_W, 450);
    footway(ROAD_FAR + PAVE_W / 2, -95, PAVE_W, 450);

    const road = new THREE.Mesh(track(new THREE.PlaneGeometry(ROAD_W, 690)), roadMaterial);
    road.rotation.x = -Math.PI / 2;
    road.position.set(roadX, 0.15, -215);
    road.receiveShadow = true;
    group.add(road);

    const divider = new THREE.Mesh(track(new THREE.BoxGeometry(DIVIDER_W, 0.32, 690)), surroundMaterial);
    divider.position.set(roadX, 0.32, -215);
    divider.receiveShadow = true;
    group.add(divider);

    // The entrance road, off the main road and passing behind the buildings.

    const entry = new THREE.Mesh(track(new THREE.PlaneGeometry(154, 9)), roadMaterial);
    entry.rotation.x = -Math.PI / 2;
    entry.position.set(3, 0.19, ENTRY_Z);
    entry.receiveShadow = true;
    group.add(entry);

    // Street trees along all three footways, evenly spaced.

    const streetSpacing = 17;   // metres between trees
    const streetRows = [
        [RAIL_X - 0.85, -33, 105],
        [RAIL_X + 0.85, -180, 130],
        [ROAD_FAR + 0.9, -180, 130],
    ];
    const streetCount = streetRows.reduce(
        (sum, [x, z0, z1]) => {
            const n = Math.floor((z1 - z0) / streetSpacing) + 1;
            let kept = 0;
            for (let i = 0; i < n; i++) {
                const z = z0 + i * streetSpacing;
                if (!(x < ROAD_INNER && Math.abs(z - (-44.5)) < 9)) kept += 1;
            }
            return sum + kept;
        }, 0
    );
    const streetTrunks = new THREE.InstancedMesh(trunkGeometry, trunkMaterial, streetCount);
    const streetLimbs = new THREE.InstancedMesh(limbGeometry, trunkMaterial, streetCount * LIMBS);
    const streetCanopies = lobeGeometries.map((g, v) =>
        new THREE.InstancedMesh(g, foliageMaterial, Math.max(1, variantShare(streetCount, v) * LOBES)));
    const streetCursor = lobeGeometries.map(() => 0);
    [streetTrunks, streetLimbs, ...streetCanopies].forEach((m) => {
        m.castShadow = true;
        m.frustumCulled = false;
    });

    // Nothing is planted in front of the gate.
    const GATE_Z = -44.5;      // gate centre
    const GATE_CLEAR = 9;      // clearance either side, metres
    const blocksGate = (x, z) => x < ROAD_INNER && Math.abs(z - GATE_Z) < GATE_CLEAR;

    let streetIndex = 0;
    const streetPlaced = [];
    for (const [x, z0, z1] of streetRows) {
        const n = Math.floor((z1 - z0) / streetSpacing) + 1;
        for (let i = 0; i < n; i++) {
            const z = z0 + i * streetSpacing;
            if (blocksGate(x, z)) continue;
            streetPlaced.push([x, z, i]);
        }
    }

    for (const [x, z, i] of streetPlaced) {
        const height = 3.7 + (i % 3) * 0.4;
        const spread = 1.25 + (i % 2) * 0.2;
        const v = streetIndex % LOBE_VARIANTS;
        placeTree(
            streetTrunks, streetLimbs, streetCanopies[v], streetCursor[v],
            streetIndex, x, z, height, spread, streetIndex + 101
        );
        streetCursor[v] += LOBES;
        streetIndex += 1;
    }
    [streetTrunks, streetLimbs, ...streetCanopies].forEach((m) => { m.instanceMatrix.needsUpdate = true; });
    group.add(streetTrunks, streetLimbs, ...streetCanopies);

    // Divider columns and their wires

    const dividerLampCount = 16;
    const dividerPosts = new THREE.InstancedMesh(boxUnit, postMaterial, dividerLampCount * 2);
    const dividerHeads = new THREE.InstancedMesh(boxUnit, lampHeadMaterial, dividerLampCount * 2);
    const wireCount = (dividerLampCount - 1) * 3;
    const wires = new THREE.InstancedMesh(boxUnit, postMaterial, wireCount);
    dividerPosts.castShadow = true;
    dividerPosts.frustumCulled = false;
    dividerHeads.frustumCulled = false;
    wires.frustumCulled = false;

    const poleSpacing = 34;
    const poleZ0 = 90;
    const WIRE_Y = [8.7, 8.25, 7.8];
    let wireIndex = 0;
    for (let i = 0; i < dividerLampCount; i++) {
        const z = poleZ0 - i * poleSpacing;
        placeBox(dividerPosts, i * 2, roadX, 4.7, z, 0.36, 9.0, 0.36);
        placeBox(dividerPosts, i * 2 + 1, roadX, 8.95, z, 2.4, 0.16, 0.16);
        placeBox(dividerHeads, i * 2, roadX - 2.6, 8.4, z, 1.7, 0.45, 1.0);
        placeBox(dividerHeads, i * 2 + 1, roadX + 2.6, 8.4, z, 1.7, 0.45, 1.0);

        if (i < dividerLampCount - 1) {
            const span = poleSpacing;
            const midZ = z - span / 2;
            for (let w = 0; w < 3; w++) {
                const dx = [-1.05, 0, 1.05][w];
                placeBox(wires, wireIndex++, roadX + dx, WIRE_Y[w], midZ, 0.09, 0.09, span);
            }
        }
    }
    dividerPosts.instanceMatrix.needsUpdate = true;
    dividerHeads.instanceMatrix.needsUpdate = true;
    wires.instanceMatrix.needsUpdate = true;
    group.add(dividerPosts, dividerHeads, wires);

    // Distant city

    const cityCount = quality.city;
    const cityMaterial = track(new THREE.MeshStandardMaterial({
        color: palette.cityBuilding.clone(),
        emissive: palette.cityEmissive.clone(),
        emissiveIntensity: 1,
        roughness: 1,
    }));

    const city = new THREE.InstancedMesh(track(new THREE.BoxGeometry(1, 1, 1)), cityMaterial, cityCount);
    city.frustumCulled = false;


    for (let i = 0; i < cityCount; i++) {
        const band = i % 3;
        const x = -560 + Math.random() * 1400;
        const z = -1020 + band * 60 - Math.random() * 420;
        const height = 9 + Math.random() * (band === 0 ? 16 : 32);
        const w = 14 + Math.random() * 22;
        const d = 14 + Math.random() * 22;

        pos.set(x, height / 2, z);
        scale.set(w, height, d);
        matrix.compose(pos, quat, scale);
        city.setMatrixAt(i, matrix);
    }
    city.instanceMatrix.needsUpdate = true;
    group.add(city);

    // Trees

    const treeCount = quality.trees;
    const trunks = new THREE.InstancedMesh(trunkGeometry, trunkMaterial, treeCount);
    const limbs = new THREE.InstancedMesh(limbGeometry, trunkMaterial, treeCount * LIMBS);
    const canopies = lobeGeometries.map((g, v) =>
        new THREE.InstancedMesh(g, foliageMaterial, Math.max(1, variantShare(treeCount, v) * LOBES)));
    const canopyCursor = lobeGeometries.map(() => 0);
    [trunks, limbs, ...canopies].forEach((m) => { m.castShadow = true; m.frustumCulled = false; });

    const treePositions = [];

    for (let i = 0; i < treeCount; i++) {
        // Four bands: canal verge, road divider, rear screen, far bank.
        const band = i % 4;
        let x;
        let z;

        if (band === 0) {
            x = -160 + Math.random() * 24;
            z = -240 + Math.random() * 470;
        } else if (band === 1) {
            x = roadX - 1.0 + Math.random() * 2.0;
            z = -330 + Math.random() * 450;
        } else if (band === 2) {
            // Clear of the bank at -164, short of the boundary railing.
            x = -150 + Math.random() * 200;
            z = -200 - Math.random() * 130;
        } else {
            x = -286 - Math.random() * 190;
            z = -300 - Math.random() * 250;
        }

        const street = band === 1;
        const height = street ? 3.0 + Math.random() * 1.0 : 7 + Math.random() * 7;
        const spread = street ? 1.1 + Math.random() * 0.4 : 3.4 + Math.random() * 2.4;

        const variant = i % LOBE_VARIANTS;
        placeTree(trunks, limbs, canopies[variant], canopyCursor[variant], i, x, z, height, spread, i + 1);
        canopyCursor[variant] += LOBES;

        if (band !== 3) treePositions.push(new THREE.Vector3(x, height * 0.6, z));
    }

    [trunks, limbs, ...canopies].forEach((m) => { m.instanceMatrix.needsUpdate = true; });
    group.add(trunks, limbs, ...canopies);

    return {
        group,
        treePositions,

        update(time) {
            layoutTraffic(time);
            grassMaterial.uniforms.uTime.value = time;
            waterMaterial.uniforms.uTime.value = time;
            shadowMaterial.uniforms.uTime.value = time;
        },

        applyPalette(p) {
            groundMaterial.color.copy(p.groundColor);
            pavingMaterial.color.copy(p.paving);
            surroundMaterial.color.copy(p.surround);
            hedgeMaterial.color.copy(p.hedge);
            postMaterial.color.copy(p.concreteShade);
            roadMaterial.color.copy(p.paving).multiplyScalar(0.34);
            brickMaterial.color.copy(p.brick);
            lampHeadMaterial.color.copy(p.lampBody);
            lampHeadMaterial.emissive.copy(p.lampGlow);
            fieldMaterial.color.copy(p.pitchColor);
            bankMaterial.color.copy(p.concreteShade);
            trunkMaterial.color.copy(p.trunk);
            foliageMaterial.color.copy(p.foliage);
            cityMaterial.color.copy(p.cityBuilding);
            cityMaterial.emissive.copy(p.cityEmissive);

            grassMaterial.uniforms.uBase.value.copy(p.grassBase);
            grassMaterial.uniforms.uTip.value.copy(p.grassTip);
            grassMaterial.uniforms.uFog.value.copy(p.fog);
            grassMaterial.uniforms.uFogDensity.value = p.fogDensity;

            waterMaterial.uniforms.uColor.value.copy(p.water);
            waterMaterial.uniforms.uSky.value.copy(p.skyHorizon);
            waterMaterial.uniforms.uFog.value.copy(p.fog);
            waterMaterial.uniforms.uFogDensity.value = p.fogDensity;
            waterMaterial.uniforms.uOpacity.value = p.waterOpacity;
            waterMaterial.uniforms.uNight.value = p.starOpacity;

            poolMaterial.uniforms.uColor.value.copy(p.lampPool);
            poolMaterial.uniforms.uStrength.value = p.lampPoolStrength;

            shadowMaterial.uniforms.uCloudCover.value = p.cloudCover;
            shadowMaterial.uniforms.uStrength.value = p.cloudShadow;
        },

        dispose() {
            disposables.forEach((d) => d.dispose && d.dispose());
        },
    };
}

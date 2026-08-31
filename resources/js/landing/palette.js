/** Day and night colour definitions for the landing scene. */

import * as THREE from 'three';

const c = (hex) => new THREE.Color(hex);

export const DAY = {
    // Sky dome. `zenith` is straight up, `horizon` is at eye level.
    skyZenith: c('#2f6fc4'),
    skyHorizon: c('#cfe3f4'),
    cloudLight: c('#ffffff'),
    cloudDark: c('#b9c9da'),
    cloudCover: 0.42, // 0 = clear, 1 = overcast
    cloudOpacity: 1.0,

    // Key light. Position is a direction, scaled out to the shadow camera.
    sunColor: c('#fff2d6'),
    sunIntensity: 2.4,
    sunDirection: new THREE.Vector3(-0.45, 0.62, 0.64),

    // Direction the moon disc is drawn in, not the direction moonlight comes from.
    moonDirection: new THREE.Vector3(-0.527, 0.061, -0.903),

    // Light pollution on the horizon. Nothing by day.
    cityGlow: c('#000000'),

    hemiSky: c('#bcd9ff'),
    hemiGround: c('#63753f'),
    hemiIntensity: 0.6,

    ambient: c('#8fa8c4'),
    ambientIntensity: 0.35,

    fog: c('#c6dbee'),
    fogDensity: 0.00085,

    grassBase: c('#4c7a33'),
    grassTip: c('#8bb254'),
    groundColor: c('#5d8339'),
    // Multiplies the pitch texture, whose stripes are drawn as white and grey.
    pitchColor: c('#5e8a37'),
    // How far the cloud deck darkens the ground beneath it.
    cloudShadow: 0.42,

    // Building materials.
    concrete: c('#a9a89b'),
    concreteShade: c('#8a8a7d'),
    slot: c('#34342f'),
    terracotta: c('#b0522f'),
    // Multiplies the facade texture.
    facadeTint: c('#ffffff'),
    windowEmissive: c('#000000'),
    windowEmissiveIntensity: 0.0,

    water: c('#5f87a6'),
    waterOpacity: 0.85,
    trunk: c('#5b4634'),
    foliage: c('#3f6b2e'),
    cityBuilding: c('#8e939c'),
    cityEmissive: c('#000000'),

    // Hard landscaping: the apron and the gravel surround to the pitch.
    paving: c('#9c9b93'),
    surround: c('#8e8d7e'),
    hedge: c('#3d6330'),
    lampBody: c('#54585f'),
    lampGlow: c('#000000'),
    // Light the fittings throw on the ground. Nothing by day.
    lampPool: c('#000000'),
    lampPoolStrength: 0.0,
    // Timber seating in the open space under C2, and beside the ponds.
    bench: c('#7a6448'),

    // The two courtyard gardens in the recesses.
    bloomWarm: c('#b07a52'),
    bloomCool: c('#96708c'),
    pondWater: c('#4c7286'),
    koi: c('#e07a34'),

    // Roof kit.
    solar: c('#1c2740'),
    // The glass balustrade on the terrace and the pipe rails on the roof.
    glassRail: c('#cfe0e2'),
    railMetal: c('#6d7076'),
    // Footways either side of the boundary and along the far kerb.
    brick: c('#b09a95'),
    // Light from the terrace and the two connectors. Nothing by day.
    interiorGlow: c('#000000'),
    interiorGlowIntensity: 0.0,

    // The apartment blocks across the canal. Multiplies their vertex colours.
    homeWall: c('#ffffff'),
    homeEmissive: c('#000000'),
    homeEmissiveIntensity: 0.0,
    // Bare structure, parapets, stair heads and water tanks.
    homeConcrete: c('#93918a'),
    homeRoof: c('#9b988d'),

    starOpacity: 0.0,
    fireflyOpacity: 0.0,
    exposure: 1.0,
};

export const NIGHT = {
    skyZenith: c('#080d20'),
    skyHorizon: c('#1e2d55'),
    cloudLight: c('#4a5680'),
    cloudDark: c('#161d31'),
    cloudCover: 0.30, // 0 = clear, 1 = overcast
    cloudOpacity: 0.9,

    // Moonlight comes from the left and from in front, so z stays positive.
    sunColor: c('#b4c8ff'),
    sunIntensity: 1.5,
    sunDirection: new THREE.Vector3(-0.55, 0.52, 0.58),

    moonDirection: new THREE.Vector3(-0.527, 0.061, -0.903),

    // Amber stain on the horizon towards the city.
    cityGlow: c('#7a4418'),

    hemiSky: c('#3b4f7d'),
    hemiGround: c('#1e2739'),
    hemiIntensity: 0.82,

    ambient: c('#4c5b88'),
    ambientIntensity: 0.68,

    fog: c('#111a33'),
    fogDensity: 0.0013,

    grassBase: c('#26382a'),
    grassTip: c('#3f5836'),
    groundColor: c('#2c4026'),
    pitchColor: c('#31472a'),
    cloudShadow: 0.10,

    concrete: c('#626879'),
    concreteShade: c('#454b59'),
    slot: c('#15171d'),
    terracotta: c('#66301f'),
    // Darkens the facade texture after dark.
    facadeTint: c('#7c8492'),
    // Which rooms are lit comes from the emissive map baked in facade.js.
    windowEmissive: c('#ffcb84'),
    windowEmissiveIntensity: 1.8,

    water: c('#1b3050'),
    waterOpacity: 0.92,
    trunk: c('#231c16'),
    foliage: c('#16281a'),

    paving: c('#3f434c'),
    surround: c('#44403a'),
    hedge: c('#1c2e1b'),
    lampBody: c('#2a2d33'),
    lampGlow: c('#ffb457'),
    lampPool: c('#ffd9ac'),
    lampPoolStrength: 0.17,
    bench: c('#3a3227'),

    bloomWarm: c('#4a2c1e'),
    bloomCool: c('#3a2338'),
    pondWater: c('#1d3550'),
    koi: c('#4a301a'),

    solar: c('#0b1020'),
    glassRail: c('#31465c'),
    railMetal: c('#2f333b'),
    brick: c('#3a3234'),
    interiorGlow: c('#f2b47a'),
    interiorGlowIntensity: 0.3,
    cityBuilding: c('#101219'),
    // Averaged glow standing in for windows and streetlights on the skyline.
    cityEmissive: c('#241a0b'),

    homeWall: c('#6d768c'),
    homeEmissive: c('#ffc072'),
    homeEmissiveIntensity: 1.25,
    homeConcrete: c('#39404f'),
    homeRoof: c('#3c4250'),

    starOpacity: 1.0,
    fireflyOpacity: 1.0,
    exposure: 1.42,
};

/** Blends the two palettes into `out`. `t` is 0 for day, 1 for night. */
export function mixPalette(out, t) {
    for (const key of Object.keys(DAY)) {
        const a = DAY[key];
        const b = NIGHT[key];

        if (a instanceof THREE.Color) {
            if (!out[key]) out[key] = new THREE.Color();
            out[key].copy(a).lerp(b, t);
        } else if (a instanceof THREE.Vector3) {
            if (!out[key]) out[key] = new THREE.Vector3();
            out[key].copy(a).lerp(b, t);
        } else {
            out[key] = a + (b - a) * t;
        }
    }

    return out;
}

export function createPalette() {
    return mixPalette({}, 0);
}

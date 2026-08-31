/** Fireflies: one Points mesh, all motion in the vertex shader. */

import * as THREE from 'three';

const fireflyVertex = /* glsl */ `
    precision highp float;

    attribute vec3  aDrift;   // per-axis wander amplitude
    attribute float aPhase;
    attribute float aSize;

    uniform float uTime;

    varying float vGlow;

    void main() {
        vec3 pos = position;

        // A different rate per axis, so the path is an open Lissajous.
        float p = aPhase * 6.2831;
        pos.x += sin(uTime * 0.34 + p) * aDrift.x;
        pos.y += sin(uTime * 0.51 + p * 1.7) * aDrift.y;
        pos.z += cos(uTime * 0.28 + p * 0.6) * aDrift.z;

        // The blink: a high power on the sine leaves it dark between flares.
        float pulse = 0.5 + 0.5 * sin(uTime * 2.1 + p * 3.3);
        vGlow = pow(pulse, 4.0);

        vec4 mvPosition = modelViewMatrix * vec4(pos, 1.0);
        // Perspective sizing, clamped to 1 to 42 pixels.
        gl_PointSize = clamp(aSize * (320.0 / max(-mvPosition.z, 1.0)), 1.0, 42.0);
        gl_Position = projectionMatrix * mvPosition;
    }
`;

const fireflyFragment = /* glsl */ `
    precision highp float;

    uniform float uOpacity;
    uniform vec3  uColor;

    varying float vGlow;

    void main() {
        if (uOpacity <= 0.001) discard;

        float d = length(gl_PointCoord - vec2(0.5));

        // A hard core inside a wide soft halo.
        float core = smoothstep(0.16, 0.0, d);
        float halo = smoothstep(0.5, 0.0, d) * 0.45;

        float alpha = (core + halo) * vGlow * uOpacity;
        if (alpha <= 0.001) discard;

        gl_FragColor = vec4(uColor, alpha);
    }
`;

export function createFireflies(quality, treePositions) {
    const count = quality.fireflies;
    const positions = new Float32Array(count * 3);
    const drifts = new Float32Array(count * 3);
    const sizes = new Float32Array(count);
    const phases = new Float32Array(count);

    for (let i = 0; i < count; i++) {
        // Two thirds cluster near the tree line, the rest over the open field.
        let x;
        let y;
        let z;

        if (treePositions.length && i % 3 !== 0) {
            const anchor = treePositions[Math.floor(Math.random() * treePositions.length)];
            x = anchor.x + (Math.random() - 0.5) * 34;
            y = 1.5 + Math.random() * 9;
            z = anchor.z + (Math.random() - 0.5) * 34;
        } else {
            x = (Math.random() - 0.5) * 300;
            y = 0.8 + Math.random() * 7;
            z = -20 + Math.random() * 210;
        }

        positions[i * 3 + 0] = x;
        positions[i * 3 + 1] = y;
        positions[i * 3 + 2] = z;

        drifts[i * 3 + 0] = 2.5 + Math.random() * 6;
        drifts[i * 3 + 1] = 0.8 + Math.random() * 2.4;
        drifts[i * 3 + 2] = 2.5 + Math.random() * 6;

        sizes[i] = 4 + Math.random() * 5;
        phases[i] = Math.random();
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('aDrift', new THREE.BufferAttribute(drifts, 3));
    geometry.setAttribute('aSize', new THREE.BufferAttribute(sizes, 1));
    geometry.setAttribute('aPhase', new THREE.BufferAttribute(phases, 1));

    const material = new THREE.ShaderMaterial({
        vertexShader: fireflyVertex,
        fragmentShader: fireflyFragment,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        uniforms: {
            uTime: { value: 0 },
            uOpacity: { value: 0 },
            uColor: { value: new THREE.Color('#c6ff86') },
        },
    });

    const points = new THREE.Points(geometry, material);
    points.frustumCulled = false;

    return {
        points,
        update(time) {
            material.uniforms.uTime.value = time;
        },
        applyPalette(p) {
            material.uniforms.uOpacity.value = p.fireflyOpacity;
        },
        dispose() {
            geometry.dispose();
            material.dispose();
        },
    };
}

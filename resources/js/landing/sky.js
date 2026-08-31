/** Sky dome: gradient, clouds, sun, moon, stars and city glow in one shader. */

import * as THREE from 'three';

const vertexShader = /* glsl */ `
    varying vec3 vDirection;

    void main() {
        // The dome is centred on the camera, so local position is the view direction.
        vDirection = normalize(position);
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }
`;

const fragmentShader = /* glsl */ `
    precision highp float;

    varying vec3 vDirection;

    uniform vec3  uZenith;
    uniform vec3  uHorizon;
    uniform vec3  uCloudLight;
    uniform vec3  uCloudDark;
    uniform vec3  uSunDirection;
    uniform vec3  uSunColor;
    uniform vec3  uMoonDirection;
    uniform vec3  uCityGlow;
    uniform vec3  uCityDirection;
    uniform float uCloudCover;
    uniform float uCloudOpacity;
    uniform float uStarAmount;
    uniform float uTime;
    uniform float uNight;

    // Noise

    float hash(vec2 p) {
        p = fract(p * vec2(123.34, 456.21));
        p += dot(p, p + 45.32);
        return fract(p.x * p.y);
    }

    float hash13(vec3 p) {
        p = fract(p * 0.1031);
        p += dot(p, p.zyx + 31.32);
        return fract((p.x + p.y) * p.z);
    }

    vec3 hash33(vec3 p) {
        p = fract(p * vec3(0.1031, 0.1030, 0.0973));
        p += dot(p, p.yxz + 33.33);
        return fract((p.xxy + p.yxx) * p.zyx);
    }

    float noise(vec2 p) {
        vec2 i = floor(p);
        vec2 f = fract(p);
        // Quintic interpolation.
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
        // Rotating between octaves stops the noise lining up into grid streaks.
        mat2 rot = mat2(0.80, 0.60, -0.60, 0.80);

        for (int i = 0; i < 5; i++) {
            total += amplitude * noise(p);
            p = rot * p * 2.02;
            amplitude *= 0.5;
        }

        return total;
    }

    // Stars: one candidate per cell of a 3D lattice, only the ray's own cell tested.

    float starLayer(vec3 dir, float scale, float density, float rate) {
        vec3 p = dir * scale;
        vec3 cell = floor(p);
        vec3 f = fract(p) - 0.5;

        float h = hash13(cell);
        float present = step(density, h);

        vec3 centre = (hash33(cell + 11.0) - 0.5) * 0.7;
        float d = length(f - centre);

        // Magnitude: a few bright stars among many faint ones.
        float mag = (h - density) / max(1.0 - density, 0.001);

        // Two out-of-phase rates, so the twinkle has no visible rhythm.
        float tw = 0.5 + 0.5 * sin(uTime * rate * (0.6 + mag * 2.2) + h * 137.0);
        tw *= 0.75 + 0.25 * sin(uTime * rate * 2.7 + h * 61.0);

        float core = smoothstep(0.16, 0.0, d);
        float halo = smoothstep(0.34, 0.0, d) * 0.16 * mag;

        return (core * (0.35 + mag * 1.5) + halo) * present * (0.35 + 0.65 * tw);
    }

    void main() {
        vec3 dir = normalize(vDirection);
        float height = max(dir.y, 0.0);

        // Gradient
        vec3 sky = mix(uHorizon, uZenith, pow(height, 0.42));

        // Stars, drawn under the cloud deck.
        if (uStarAmount > 0.001) {
            // Two layers at different lattice scales.
            float s = starLayer(dir, 105.0, 0.845, 1.0)
                    + starLayer(dir, 48.0, 0.900, 0.7) * 1.4;
            // Fade into the horizon haze.
            s *= smoothstep(-0.01, 0.16, dir.y);
            sky += vec3(0.86, 0.90, 1.0) * s * uStarAmount;
        }

        // City glow: an amber stain on the horizon after dark.
        float cityAxis = max(dot(normalize(vec3(dir.x, 0.0, dir.z)), normalize(uCityDirection)), 0.0);
        float cityBand = pow(max(1.0 - abs(dir.y) * 7.0, 0.0), 2.0);
        sky += uCityGlow * pow(cityAxis, 3.0) * cityBand;

        // Sun
        float sunDot = max(dot(dir, normalize(uSunDirection)), 0.0);
        float sunCore = pow(sunDot, 900.0);
        float sunHalo = pow(sunDot, 12.0) * 0.35;
        sky += uSunColor * (sunCore * 2.0 + sunHalo) * (1.0 - uNight);

        // Moon. The disc is not drawn where the moonlight comes from.
        if (uNight > 0.001) {
            vec3 md = normalize(uMoonDirection);
            float moonDot = dot(dir, md);

            // Local basis on the disc, for the surface detail.
            vec3 tangent = normalize(cross(md, vec3(0.0, 1.0, 0.0)));
            vec3 bitangent = cross(md, tangent);
            // Noise frequency across a disc about a degree wide.
            vec2 surface = vec2(dot(dir, tangent), dot(dir, bitangent)) * 620.0;

            float maria = fbm(surface * 1.4 + 3.0);
            vec3 moonColor = mix(vec3(0.95, 0.96, 0.99), vec3(0.74, 0.77, 0.86), maria * 0.55);

            // Hard-edged disc of about 1.25 degrees radius.
            float disc = smoothstep(0.999740, 0.999805, moonDot);
            float glowNear = pow(max(moonDot, 0.0), 5000.0) * 0.55;
            float glowWide = pow(max(moonDot, 0.0), 260.0) * 0.11;

            sky += vec3(0.72, 0.80, 1.0) * (glowNear + glowWide) * uNight;
            sky = mix(sky, moonColor, disc * uNight);
        }

        // Clouds. The floor on dir.y guards the divide near the horizon.
        float denom = max(dir.y, 0.06);
        vec2 cloudUv = dir.xz / denom * 0.30;

        // Two layers drifting at different speeds, for parallax within the deck.
        vec2 drift = vec2(uTime * 0.060, uTime * 0.023);
        float lower = fbm(cloudUv * 0.8 + drift);
        float upper = fbm(cloudUv * 1.9 - drift * 1.7);
        float density = lower * 0.68 + upper * 0.32;

        // Five octaves of value noise rarely leave 0.25..0.72, so the threshold
        // is mapped into that range rather than 0..1.
        float threshold = 0.62 - uCloudCover * 0.38;
        float mask = smoothstep(threshold - 0.06, threshold + 0.14, density);

        // Shallow fade towards the horizon.
        mask *= smoothstep(0.0, 0.16, dir.y);
        mask *= uCloudOpacity;

        vec3 cloud = mix(uCloudDark, uCloudLight, smoothstep(threshold, threshold + 0.28, density));
        cloud += uSunColor * pow(sunDot, 6.0) * 0.25 * (1.0 - uNight);
        // Moonlit silver on the cloud edge facing the moon.
        cloud += vec3(0.55, 0.62, 0.85) * pow(max(dot(dir, normalize(uMoonDirection)), 0.0), 8.0) * 0.3 * uNight;

        sky = mix(sky, cloud, clamp(mask, 0.0, 1.0));

        gl_FragColor = vec4(sky, 1.0);
    }
`;

export function createSky(palette) {
    const geometry = new THREE.SphereGeometry(1, 32, 20);

    const material = new THREE.ShaderMaterial({
        vertexShader,
        fragmentShader,
        side: THREE.BackSide,
        depthWrite: false,
        fog: false,
        uniforms: {
            uZenith: { value: palette.skyZenith.clone() },
            uHorizon: { value: palette.skyHorizon.clone() },
            uCloudLight: { value: palette.cloudLight.clone() },
            uCloudDark: { value: palette.cloudDark.clone() },
            uSunDirection: { value: palette.sunDirection.clone() },
            uSunColor: { value: palette.sunColor.clone() },
            uMoonDirection: { value: palette.moonDirection.clone() },
            uCityGlow: { value: palette.cityGlow.clone() },
            uCityDirection: { value: new THREE.Vector3(0.85, 0, -0.53) },
            uCloudCover: { value: palette.cloudCover },
            uCloudOpacity: { value: palette.cloudOpacity },
            uStarAmount: { value: palette.starOpacity },
            uTime: { value: 0 },
            uNight: { value: 0 },
        },
    });

    const mesh = new THREE.Mesh(geometry, material);
    // Dome radius in metres; it rides along with the camera.
    mesh.scale.setScalar(3000);
    mesh.renderOrder = -1000;
    mesh.frustumCulled = false;
    mesh.matrixAutoUpdate = false;

    return {
        mesh,
        material,

        update(time, camera) {
            material.uniforms.uTime.value = time;
            // Keep the dome centred on the camera.
            mesh.position.copy(camera.position);
            mesh.updateMatrix();
        },

        applyPalette(p, night) {
            const u = material.uniforms;
            u.uZenith.value.copy(p.skyZenith);
            u.uHorizon.value.copy(p.skyHorizon);
            u.uCloudLight.value.copy(p.cloudLight);
            u.uCloudDark.value.copy(p.cloudDark);
            u.uSunDirection.value.copy(p.sunDirection);
            u.uSunColor.value.copy(p.sunColor);
            u.uMoonDirection.value.copy(p.moonDirection);
            u.uCityGlow.value.copy(p.cityGlow);
            u.uCloudCover.value = p.cloudCover;
            u.uCloudOpacity.value = p.cloudOpacity;
            u.uStarAmount.value = p.starOpacity;
            u.uNight.value = night;
        },

        dispose() {
            geometry.dispose();
            material.dispose();
        },
    };
}

/** Landing scene: renderer, camera, lights, frame loop and day/night blend. */

import * as THREE from 'three';

import { createPalette, mixPalette } from './palette.js';
import { createSky } from './sky.js';
import { createCampus, CAMPUS_HEIGHT, CAMPUS_SPAN } from './campus.js';
import { createWorld } from './world.js';
import { createNeighbourhood } from './neighbourhood.js';
import { createFireflies } from './night.js';

const TRANSITION_MS = 2200;

const QUALITY = {
    high: { grassBlades: 34000, trees: 70, city: 80, homes: 11, fireflies: 300, pointLights: 6, shadowMap: 2048, maxDpr: 2.0, shadows: true },
    medium: { grassBlades: 15000, trees: 48, city: 55, homes: 9, fireflies: 180, pointLights: 6, shadowMap: 1024, maxDpr: 1.5, shadows: true },
    // Low tier runs with no point lights.
    low: { grassBlades: 4500, trees: 26, city: 24, homes: 6, fireflies: 80, pointLights: 0, shadowMap: 512, maxDpr: 1.0, shadows: false },
};

/** Picks a quality tier from the device hints the browser exposes. */
export function detectQuality() {
    if (typeof navigator === 'undefined') return 'low';

    const ua = navigator.userAgent || '';
    const mobile = /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
    const memory = navigator.deviceMemory || (mobile ? 2 : 8);
    const cores = navigator.hardwareConcurrency || (mobile ? 4 : 8);

    if (mobile && (memory <= 4 || cores <= 4)) return 'low';
    if (mobile) return 'medium';
    if (memory <= 4 || cores <= 4) return 'medium';

    return 'high';
}

const easeInOutCubic = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

export function createScene(canvas, options = {}) {
    const tier = options.quality || detectQuality();
    const quality = QUALITY[tier] || QUALITY.medium;

    const reducedMotion = typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Renderer

    const renderer = new THREE.WebGLRenderer({
        canvas,
        antialias: tier !== 'low',
        alpha: false,
        powerPreference: 'high-performance',
        preserveDrawingBuffer: false,
    });

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, quality.maxDpr));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;

    if (quality.shadows) {
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFShadowMap;
    }

    // Scene graph

    const scene = new THREE.Scene();
    const palette = createPalette();

    scene.fog = new THREE.FogExp2(palette.fog.getHex(), palette.fogDensity);

    // 34 degree vertical field; near and far planes in metres.
    const camera = new THREE.PerspectiveCamera(34, 1, 8, 4000);

    const sky = createSky(palette);
    scene.add(sky.mesh);

    const campus = createCampus(palette, quality);
    campus.group.position.set(0, 0, -70);
    scene.add(campus.group);

    const world = createWorld(palette, quality);
    scene.add(world.group);

    // Apartment blocks on the far bank.
    const neighbourhood = createNeighbourhood(palette, quality);
    scene.add(neighbourhood.group);

    const fireflies = createFireflies(quality, world.treePositions);
    scene.add(fireflies.points);

    // Lights

    const sun = new THREE.DirectionalLight(palette.sunColor.getHex(), palette.sunIntensity);
    sun.position.copy(palette.sunDirection).multiplyScalar(400);

    if (quality.shadows) {
        sun.castShadow = true;
        sun.shadow.mapSize.set(quality.shadowMap, quality.shadowMap);
        // Half-extent of the shadow frustum, in metres.
        const d = 230;
        sun.shadow.camera.left = -d;
        sun.shadow.camera.right = d;
        sun.shadow.camera.top = d;
        sun.shadow.camera.bottom = -d;
        sun.shadow.camera.near = 20;
        sun.shadow.camera.far = 900;
        sun.shadow.bias = -0.0007;
        sun.shadow.normalBias = 0.6;
    }

    scene.add(sun);
    scene.add(sun.target);
    sun.target.position.set(0, 20, -70);

    const hemi = new THREE.HemisphereLight(
        palette.hemiSky.getHex(),
        palette.hemiGround.getHex(),
        palette.hemiIntensity
    );
    scene.add(hemi);

    const ambient = new THREE.AmbientLight(palette.ambient.getHex(), palette.ambientIntensity);
    scene.add(ambient);

    // Camera framing

    // Look-at point and camera offset from it, in metres.
    const target = new THREE.Vector3(0, 18, -60);
    const baseOffset = new THREE.Vector3(39, 29, 181);
    const offset = new THREE.Vector3();
    const UP = new THREE.Vector3(0, 1, 0);

    // Aim offset from the target, in metres.
    const aim = new THREE.Vector3();
    const AIM_X = -12;
    const AIM_Y = 0;

    const pointer = { x: 0, y: 0 };
    const smoothed = { x: 0, y: 0 };

    function updateCamera(time) {
        // Azimuth drift in radians, under ten degrees end to end.
        const drift = reducedMotion ? 0 : Math.sin(time * 0.055) * 0.085;

        smoothed.x += (pointer.x - smoothed.x) * 0.045;
        smoothed.y += (pointer.y - smoothed.y) * 0.045;

        offset.copy(baseOffset);
        offset.applyAxisAngle(UP, drift + smoothed.x * 0.07);
        offset.y += smoothed.y * 14 + (reducedMotion ? 0 : Math.sin(time * 0.08) * 2.5);

        camera.position.copy(target).add(offset);
        aim.set(target.x + AIM_X, target.y + AIM_Y, target.z);
        camera.lookAt(aim);
    }

    // Day / night

    let nightValue = 0;
    let nightFrom = 0;
    let nightTo = 0;
    let transitionStart = 0;
    let transitioning = false;

    function applyPalette() {
        mixPalette(palette, nightValue);

        sky.applyPalette(palette, nightValue);
        campus.applyPalette(palette);
        world.applyPalette(palette);
        neighbourhood.applyPalette(palette);
        fireflies.applyPalette(palette);

        sun.color.copy(palette.sunColor);
        sun.intensity = palette.sunIntensity;
        sun.position.copy(palette.sunDirection).multiplyScalar(400);

        hemi.color.copy(palette.hemiSky);
        hemi.groundColor.copy(palette.hemiGround);
        hemi.intensity = palette.hemiIntensity;

        ambient.color.copy(palette.ambient);
        ambient.intensity = palette.ambientIntensity;

        scene.fog.color.copy(palette.fog);
        scene.fog.density = palette.fogDensity;

        renderer.toneMappingExposure = palette.exposure;
    }

    applyPalette();

    function setTheme(isNight, immediate = false) {
        const goal = isNight ? 1 : 0;
        if (goal === nightTo && !immediate) return;

        if (immediate || reducedMotion) {
            nightValue = goal;
            nightFrom = goal;
            nightTo = goal;
            transitioning = false;
            applyPalette();
            requestRender();
            return;
        }

        nightFrom = nightValue;
        nightTo = goal;
        transitionStart = performance.now();
        transitioning = true;
        ensureRunning();
    }

    // Frame loop

    // Per-frame delta is clamped to 0.1 s.
    let elapsed = 0;
    let lastFrameAt = 0;

    function advanceTime() {
        const now = performance.now();
        const delta = lastFrameAt ? Math.min((now - lastFrameAt) / 1000, 0.1) : 0;
        lastFrameAt = now;
        elapsed += delta;
        return elapsed;
    }

    let frameId = null;
    let running = false;
    let visible = true;
    let onScreen = true;
    let disposed = false;

    function renderFrame() {
        const time = advanceTime();

        if (transitioning) {
            const progress = Math.min((performance.now() - transitionStart) / TRANSITION_MS, 1);
            nightValue = nightFrom + (nightTo - nightFrom) * easeInOutCubic(progress);
            applyPalette();
            if (progress >= 1) transitioning = false;
        }

        if (!reducedMotion) {
            sky.update(time, camera);
            world.update(time);
            campus.update(time);
            fireflies.update(time);
        } else {
            sky.update(0, camera);
            campus.update(0);
        }

        updateCamera(time);
        renderer.render(scene, camera);
    }

    function tick() {
        if (disposed) return;
        frameId = requestAnimationFrame(tick);
        renderFrame();
    }

    function ensureRunning() {
        if (disposed || running) return;
        if (!visible || !onScreen) return;
        if (reducedMotion && !transitioning) return;

        running = true;
        lastFrameAt = 0;
        tick();
    }

    function stop() {
        running = false;
        if (frameId !== null) {
            cancelAnimationFrame(frameId);
            frameId = null;
        }
        lastFrameAt = 0;
    }

    /** Draws exactly one frame. */
    function requestRender() {
        if (disposed) return;
        if (running) return;
        renderFrame();
    }

    // Sizing

    function resize() {
        if (disposed) return;

        const parent = canvas.parentElement || canvas;
        const width = parent.clientWidth || window.innerWidth;
        const height = parent.clientHeight || window.innerHeight;

        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, quality.maxDpr));
        renderer.setSize(width, height, false);
        camera.aspect = width / Math.max(height, 1);
        camera.updateProjectionMatrix();

        requestRender();
    }

    resize();

    // Listeners, all torn down in dispose()

    const onResize = () => resize();
    window.addEventListener('resize', onResize, { passive: true });

    const onVisibility = () => {
        visible = document.visibilityState === 'visible';
        if (visible) ensureRunning();
        else stop();
    };
    document.addEventListener('visibilitychange', onVisibility);

    const onPointerMove = (event) => {
        if (reducedMotion) return;
        pointer.x = (event.clientX / window.innerWidth) * 2 - 1;
        pointer.y = -((event.clientY / window.innerHeight) * 2 - 1);
    };
    window.addEventListener('pointermove', onPointerMove, { passive: true });

    // Stop rendering once the canvas scrolls away.
    let observer = null;
    if (typeof IntersectionObserver !== 'undefined') {
        observer = new IntersectionObserver((entries) => {
            onScreen = entries.some((e) => e.isIntersecting);
            if (onScreen) ensureRunning();
            else stop();
        }, { threshold: 0.01 });
        observer.observe(canvas);
    }

    const onContextLost = (event) => {
        // Without preventDefault the context never restores.
        event.preventDefault();
        stop();
        if (typeof options.onContextLost === 'function') options.onContextLost();
    };
    const onContextRestored = () => {
        applyPalette();
        resize();
        ensureRunning();
    };
    canvas.addEventListener('webglcontextlost', onContextLost, false);
    canvas.addEventListener('webglcontextrestored', onContextRestored, false);

    ensureRunning();
    if (reducedMotion) requestRender();

    return {
        tier,
        reducedMotion,
        setTheme,
        resize,

        dispose() {
            disposed = true;
            stop();

            window.removeEventListener('resize', onResize);
            window.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('visibilitychange', onVisibility);
            canvas.removeEventListener('webglcontextlost', onContextLost);
            canvas.removeEventListener('webglcontextrestored', onContextRestored);
            if (observer) observer.disconnect();

            sky.dispose();
            campus.dispose();
            world.dispose();
            neighbourhood.dispose();
            fireflies.dispose();
            renderer.dispose();
        },
    };
}

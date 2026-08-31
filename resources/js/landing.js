/** Landing page entry point. Loads the 3D scene in its own chunk after paint. */

/** Probes for a usable WebGL context. */
function hasWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return !!(
            window.WebGLRenderingContext
            && (canvas.getContext('webgl2') || canvas.getContext('webgl'))
        );
    } catch (e) {
        return false;
    }
}

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function boot() {
    const canvas = document.getElementById('cc-landing-canvas');
    if (!canvas) return;

    const stage = canvas.parentElement;

    if (!hasWebGL()) {
        if (stage) stage.dataset.fallback = 'true';
        return;
    }

    import('./landing/scene.js')
        .then(({ createScene }) => {
            let scene;

            try {
                scene = createScene(canvas, {
                    onContextLost: () => {
                        canvas.style.opacity = '0';
                        if (stage) stage.dataset.fallback = 'true';
                    },
                });
            } catch (e) {
                if (stage) stage.dataset.fallback = 'true';
                return;
            }

            scene.setTheme(isDark(), true);

            // Two rAFs: fade in only once the first frame is composited.
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    canvas.style.opacity = '1';
                    if (stage) stage.dataset.ready = 'true';
                });
            });

            // The scene follows the `dark` class on the document element.
            const observer = new MutationObserver(() => scene.setTheme(isDark()));
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            });

            window.addEventListener('pagehide', () => {
                observer.disconnect();
                scene.dispose();
            }, { once: true });
        })
        .catch(() => {
            if (stage) stage.dataset.fallback = 'true';
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

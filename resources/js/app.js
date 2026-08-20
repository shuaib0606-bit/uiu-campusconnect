import Alpine from 'alpinejs';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.content;
}

/**
 * Theme handling — persisted in a plain cookie (read server-side by
 * ShareThemePreference) so there's no flash of the wrong theme on load.
 */
function readCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
}

window.setTheme = function setTheme(theme) {
    applyTheme(theme);
    document.cookie = `cc_theme=${theme}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    window.axios.post('/theme', { theme }).catch(() => {});
};

window.currentTheme = function currentTheme() {
    return readCookie('cc_theme') || (document.documentElement.classList.contains('dark') ? 'dark' : 'light');
};

/**
 * Lightweight AJAX + toast layer so in-list actions (react, save, join,
 * follow, ...) update in place instead of doing a full page reload — a
 * full reload resets scroll position and collapses any open Alpine state
 * (expanded comment threads, open menus) elsewhere on the page.
 */
window.ccPost = async function ccPost(url, data = {}) {
    try {
        const res = await window.axios.post(url, data);
        return res.data && typeof res.data === 'object' ? res.data : {};
    } catch (e) {
        return { error: true };
    }
};

window.ccToast = function ccToast(message, tone = 'default') {
    window.dispatchEvent(new CustomEvent('cc-toast', { detail: { message, tone } }));
};

document.addEventListener('alpine:init', () => {
    // Generic toggle (react, save, join, follow, ...): tracks its own
    // active/count state and posts to the server without navigating.
    Alpine.data('ccToggle', (config) => ({
        active: !!config.active,
        count: config.count ?? null,
        busy: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            const data = await window.ccPost(config.url, config.payload || {});
            this.busy = false;
            if (data.error) {
                window.ccToast(config.errorMessage || 'Something went wrong. Try again.', 'error');
                return;
            }
            this.active = 'active' in data ? data.active : !this.active;
            if ('count' in data) this.count = data.count;
            if (data.message) window.ccToast(data.message, 'success');
        },
    }));

    // One-shot action (block, mark read, delete, ...): posts and reports
    // success/failure via toast, no local toggle state to track.
    Alpine.data('ccAction', (config) => ({
        busy: false,
        done: false,
        async run() {
            if (this.busy) return;
            this.busy = true;
            const data = await window.ccPost(config.url, config.payload || {});
            this.busy = false;
            if (data.error) {
                window.ccToast(config.errorMessage || 'Something went wrong. Try again.', 'error');
                return;
            }
            this.done = true;
            if (data.message) window.ccToast(data.message, 'success');
            if (config.onDone) config.onDone(data);
        },
    }));
});

window.Alpine = Alpine;
Alpine.start();

/**
 * The live connection, when there is one.
 *
 * Reverb is a second process. It can be off, it can be starting, it can
 * die halfway through a class. So nothing here throws when it is absent:
 * `window.Echo` is either a working connection or undefined, and the chat
 * checks which and polls instead.
 *
 * Exported as a promise because the Echo and Pusher libraries are a
 * couple of hundred kilobytes that no page except a group chat needs.
 * They load on demand.
 */

let connecting = null;

/** Whether a socket server was configured at build time. */
export function liveConfigured() {
    return Boolean(import.meta.env.VITE_REVERB_APP_KEY);
}

/**
 * Resolves with an Echo instance, or with null when live updates are not
 * available. Never rejects - a missing socket server is an ordinary
 * state, not an error.
 */
export function connectLive() {
    if (!liveConfigured()) return Promise.resolve(null);
    if (connecting) return connecting;

    connecting = Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ])
        .then(([{ default: Echo }, { default: Pusher }]) => {
            window.Pusher = Pusher;

            const echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
                wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
                enabledTransports: ['ws', 'wss'],
                // One attempt. If the server is not there, fall back to
                // polling rather than retrying into a dead port forever.
                activityTimeout: 30000,
                pongTimeout: 10000,
            });

            window.Echo = echo;

            return echo;
        })
        .catch(() => null);

    return connecting;
}

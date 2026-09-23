import { connectLive, liveConfigured } from './echo';

/**
 * Group chat, as an Alpine component.
 *
 * Two ways for a message to arrive and one way to draw it. The socket is
 * preferred; polling is what happens when there is no socket, and also
 * what fills the gap after a socket drops and comes back. Both paths hand
 * `push()` the same object, because both come from the same PHP method.
 *
 * Nothing here assumes Reverb is running. That is the point: a class
 * conversation that quietly stops updating is worse than one that updates
 * three seconds late, and a websocket server is one more process that can
 * be down.
 */
export default function groupChat({ groupId, endpoint, canSend, meId }) {
    return {
        messages: [],
        draft: '',
        sending: false,
        loading: true,
        error: null,

        /** 'live' when a socket is carrying messages, 'polling' otherwise. */
        mode: 'polling',

        lastId: 0,
        timer: null,
        echo: null,

        canSend,
        meId,

        /** Set while the reader is scrolled up through older messages. */
        readingBack: false,

        onStateChange: null,

        /**
         * Alpine calls this by itself for any x-data object that has an
         * init() method. It must NOT also be named in x-init, or it runs
         * twice - two first loads, two socket listeners, two timers.
         */
        async init() {
            await this.load();
            this.$nextTick(() => this.scrollDown(true));

            // Polling starts at once and at full speed. The socket, if it
            // comes up, slows it down - never the other way round, so a
            // socket that never arrives costs nothing.
            this.startPolling(4000);

            if (liveConfigured()) {
                const echo = await connectLive();

                if (echo) this.goLive(echo);
            }
        },

        /**
         * Subscribes, and claims "live" only once the server has said yes.
         *
         * A private channel is authorised by a round trip to
         * /broadcasting/auth that happens after .private() returns, so the
         * earlier version - which set the dot straight after subscribing -
         * could show green for a subscription that had in fact been
         * refused. The dot is the one thing a user reads to trust the
         * chat, so it waits for the answer.
         */
        goLive(echo) {
            const channel = echo.private(`group.${groupId}`);

            channel
                .listen('.message.sent', (payload) => this.push(payload.message))
                .subscribed(() => {
                    this.echo = channel;
                    this.mode = 'live';

                    // Keep a slow poll underneath. A socket reconnecting
                    // can drop a message without saying so; a lazy check
                    // every twenty seconds closes that gap for nothing.
                    this.startPolling(20000);

                    // And catch up on anything sent while connecting.
                    this.load(true);
                })
                .error(() => {
                    echo.leave(`group.${groupId}`);
                    this.echo = null;
                    this.mode = 'polling';
                    this.startPolling(4000);
                });

            // If the socket server goes away mid-conversation, say so and
            // speed polling back up. When it returns, pusher-js resubscribes
            // on its own and the subscribed() callback above fires again.
            const connection = echo.connector?.pusher?.connection;

            if (connection) {
                this.onStateChange = ({ current }) => {
                    if (current !== 'connected' && this.mode === 'live') {
                        this.mode = 'polling';
                        this.startPolling(4000);
                    }
                };

                connection.bind('state_change', this.onStateChange);
            }
        },

        /** Alpine calls this when the panel closes and the component is torn down. */
        destroy() {
            clearInterval(this.timer);

            const connection = window.Echo?.connector?.pusher?.connection;

            if (connection && this.onStateChange) {
                connection.unbind('state_change', this.onStateChange);
            }

            if (this.echo) {
                window.Echo?.leave(`group.${groupId}`);
                this.echo = null;
            }
        },

        startPolling(every) {
            clearInterval(this.timer);

            this.timer = setInterval(() => {
                // Nothing to gain from polling a tab nobody is looking at.
                if (document.visibilityState === 'visible') this.load(true);
            }, every);
        },

        async load(incremental = false) {
            try {
                const url = incremental && this.lastId
                    ? `${endpoint}?after=${this.lastId}`
                    : endpoint;

                const { data } = await window.axios.get(url);

                data.messages.forEach((message) => this.push(message));

                this.canSend = data.can_send;
                this.error = null;
            } catch (e) {
                // A failed poll is not worth shouting about - the next one
                // is four seconds away. A failed first load is.
                if (! incremental) this.error = 'Could not load the conversation.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Adds a message, or replaces one already held.
         *
         * Replacement matters: the sender gets their own line back from
         * the POST while the socket may also deliver it, and a deletion
         * arrives as the same id with `deleted` set.
         */
        push(message) {
            const at = this.messages.findIndex((m) => m.id === message.id);
            const isNew = at === -1;

            if (isNew) {
                this.messages.push(message);
                this.messages.sort((a, b) => a.id - b.id);
            } else {
                this.messages.splice(at, 1, message);
            }

            if (message.id > this.lastId) this.lastId = message.id;

            // Follow the conversation down - unless the reader has scrolled
            // up to read something older, in which case yanking them to the
            // bottom every few seconds would make that impossible. Their
            // own messages always scroll, because they just sent them.
            if (isNew && (! this.readingBack || this.mine(message))) {
                this.$nextTick(() => this.scrollDown(true));
            }
        },

        /** Bound to the scroller's scroll event. */
        trackScroll() {
            const box = this.$refs.scroller;

            if (! box) return;

            // Within 80px of the bottom counts as "at the bottom", so a
            // pixel of drift does not stop the chat following along.
            this.readingBack = box.scrollHeight - box.scrollTop - box.clientHeight > 80;
        },

        async send() {
            const body = this.draft.trim();

            if (! body || this.sending || ! this.canSend) return;

            this.sending = true;
            this.draft = '';

            try {
                const { data } = await window.axios.post(endpoint, { body });
                this.push(data.message);
                this.error = null;
            } catch (e) {
                // Put the text back rather than losing what they typed.
                this.draft = body;
                this.error = e.response?.status === 429
                    ? 'Slow down a moment.'
                    : 'That message did not send.';
            } finally {
                this.sending = false;
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        async remove(id) {
            try {
                const { data } = await window.axios.delete(`${endpoint}/${id}`);
                this.push(data.message);
            } catch (e) {
                this.error = 'Could not remove that message.';
            }
        },

        /**
         * Whether this message continues the one above it: same person,
         * same day, within five minutes. Runs of one person's messages
         * then show one name and one avatar instead of repeating both.
         */
        continues(index) {
            if (index === 0) return false;

            const previous = this.messages[index - 1];
            const current = this.messages[index];

            if (previous.user_id !== current.user_id) return false;
            if (previous.day !== current.day) return false;

            return (new Date(current.at) - new Date(previous.at)) < 5 * 60 * 1000;
        },

        /** The first message of a day carries a date separator. */
        startsDay(index) {
            return index === 0 || this.messages[index - 1].day !== this.messages[index].day;
        },

        mine(message) {
            return message.user_id === this.meId;
        },

        scrollDown(force = false) {
            const box = this.$refs.scroller;

            if (! box || (this.readingBack && ! force)) return;

            box.scrollTop = box.scrollHeight;
            this.readingBack = false;
        },
    };
}

<?php

/**
 * The campus assistant.
 *
 * Three providers sit behind one interface and are tried in the order
 * listed below. Falling through happens on failure only - a timeout, a
 * rate limit, a 5xx - never on a poor answer, because nothing here can
 * judge that. The order is configuration rather than code so it can be
 * changed while the site is running, and so development can start on
 * whichever key arrived first.
 */
return [

    /*
     * The whole feature switches off from here. With no key configured
     * for any provider in the order, the assistant reports itself
     * unavailable rather than failing halfway through a request.
     */
    'enabled' => env('AI_ENABLED', true),

    /*
     * Tried left to right. Names must match the keys in 'providers'.
     */
    'order' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_PROVIDER_ORDER', 'groq,grok,gemini,deepseek'))
    ))),

    'providers' => [

        /*
         * Read the next two entries carefully: groq and grok are
         * different companies whose names differ by one letter.
         *
         *   groq - Groq, api.groq.com. Runs open-weight models on their
         *          own hardware. Very fast. Keys begin gsk_.
         *   grok - xAI's model, api.x.ai. Keys begin xai-.
         *
         * Putting one company's key against the other's entry produces a
         * 401 that looks like a dead key rather than a typo, so
         * tools/check-ai-keys.sh checks each prefix and says so.
         */
        'groq' => [
            'driver' => 'openai',
            'key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            // Groq retires model names faster than the other vendors, and
            // the assistant needs one that supports tool calling. If this
            // starts returning 404, the check script lists what is live.
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        ],

        /*
         * The same Groq key against the smaller model.
         *
         * Groq meters tokens per minute per model, so this has its own
         * budget rather than sharing the 120b one. That makes it a real
         * fallback for the case that actually happens here - the big
         * model's minute being spent - and it costs nothing and needs no
         * second account. It is a weaker model, so it sits below the
         * large one and only ever answers when the large one is busy.
         */
        'groq-small' => [
            'driver' => 'openai',
            'key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('GROQ_SMALL_MODEL', 'openai/gpt-oss-20b'),
        ],

        'grok' => [
            'driver' => 'openai',
            'key' => env('GROK_API_KEY'),
            'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
            'model' => env('GROK_MODEL', 'grok-3'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        ],

        'deepseek' => [
            'driver' => 'openai',
            'key' => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        ],

        /*
         * OpenRouter: one account in front of many vendors' models,
         * several of them free. Added because Gemini's project-level
         * block is a Google-side problem with no quick fix, and a chain
         * with one working link is not a chain.
         *
         * It needed no new code - OpenRouter speaks the same OpenAI
         * dialect as Groq and DeepSeek, so this config entry is the whole
         * integration. Keys begin sk-or-v1-.
         */
        'openrouter' => [
            'driver' => 'openai',
            'key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            // Free models come and go and must support tool calling. If
            // this 404s, tools/check-ai-keys.sh lists what is live.
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-oss-120b:free'),
        ],
    ],

    /*
     * Seconds to wait on one provider before giving up and trying the
     * next. Low enough that falling through all three still answers
     * inside a reasonable wait.
     */
    'timeout' => (int) env('AI_TIMEOUT', 20),

    /*
     * How many times the model may call tools and be handed the results
     * before it must answer. Each round trip is another request, so this
     * caps both latency and spend. Four covers the deepest real chain
     * (find my courses, find the teacher, read the group, read notices).
     */
    'max_tool_rounds' => (int) env('AI_MAX_TOOL_ROUNDS', 4),

    /*
     * Ceiling on the answer. Long enough for a paragraph and a short
     * list, short enough that a runaway generation costs little.
     */
    'max_tokens' => (int) env('AI_MAX_TOKENS', 650),

    /*
     * Low but not zero: campus answers should be repeatable, and the
     * assistant is not meant to be inventive.
     */
    'temperature' => (float) env('AI_TEMPERATURE', 0.3),

    /*
     * Per-user limits. The window is a minute.
     */
    'rate_limit' => [
        'per_minute' => (int) env('AI_RATE_PER_MINUTE', 8),
        'per_day' => (int) env('AI_RATE_PER_DAY', 150),
    ],

    /*
     * The longest question accepted. A very long message is either an
     * accident or an attempt to bury an instruction in the middle of a
     * wall of text, and neither deserves an API call.
     */
    'max_question_length' => (int) env('AI_MAX_QUESTION', 1200),

    /*
     * How many previous turns of a conversation are replayed to the
     * model. Older turns fall off; the audit trail keeps all of them.
     */
    'history_turns' => (int) env('AI_HISTORY_TURNS', 4),

    /*
     * What the assistant calls itself. Students see a product, not a
     * vendor - the same convention every assistant built on a hosted
     * model follows.
     */
    'persona_name' => env('AI_PERSONA_NAME', 'UIU CampusConnect AI'),
];

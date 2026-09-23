## 3.5 The campus assistant and the chat server
Two features need more than php artisan serve, and both are built to
degrade rather than fail when the extra piece is missing.

**The assistant** needs at least one API key. They go in .env and
nowhere else - the file is ignored by version control:

AI_PROVIDER_ORDER=groq,openrouter,groq-small,gemini,grok,deepseek\
GROQ_API_KEY=\
OPENROUTER_API_KEY=

Providers with no key are skipped, so one key is enough to start. Before
debugging the assistant itself, check the keys:

bash tools/check-ai-keys.sh

It asks each provider for one word and reports what came back, without
ever printing a key, and separates a rejected key (401), an empty
account (402), a retired model name (404) and a busy free tier (429) -
four failures that look identical from inside the browser.

**Class chat** works with nothing extra: it polls for new messages every
few seconds. To make it live, set BROADCAST_CONNECTION=reverb in .env and
run the websocket server in a third terminal:

php artisan reverb:start

If that server stops, chat carries on by polling and says so on screen.

**Proving the assistant cannot leak** takes one command and no API key:

php artisan ai:audit

Section 8.22 explains what it checks and why a test that uses no model
is the stronger test.

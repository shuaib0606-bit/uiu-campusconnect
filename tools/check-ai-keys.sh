#!/usr/bin/env bash
#
# Asks each provider for one word, and reports what came back.
#
# Run this before debugging the assistant itself. It separates "the key
# is wrong" from "my code is wrong", which are otherwise easy to confuse
# because both surface as the same unhelpful error in the browser.
#
#   bash tools/check-ai-keys.sh
#
# Keys are read from .env and never printed. Anything key-shaped in a
# provider's error message is masked before it reaches the screen.

set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

if [ ! -f .env ]; then
    echo "No .env here. Run this from the project folder."
    exit 1
fi

get() { grep -m1 "^$1=" .env | cut -d= -f2- | tr -d '"'"'"'\r'; }

scrub() { sed -E 's/(AIza|xai-|gsk_|sk-|AQ\.)[A-Za-z0-9_.-]+/<key hidden>/g'; }

# Model names come out of config/ai.php so this script and the
# application can never drift apart on what is being tested.
#
# Deliberately not sed: macOS ships BSD sed, which does not understand
# \s, so a pattern written on Linux fails silently here and hands back
# the whole line instead of the capture. grep -o has no such split.
model_for() {
    local from_env
    from_env=$(grep -m1 "^$1=" .env 2>/dev/null | cut -d= -f2- | tr -d '"'"'"'\r')

    if [ -n "$from_env" ]; then echo "$from_env"; return; fi

    grep -m1 "$1" config/ai.php | grep -oE "'[^']+'\)" | tr -d "')"
}

# groq keys start gsk_, grok keys start xai-. Swapping those two is the
# single easiest mistake to make here and produces a 401 that reads like
# a dead key, so it is worth catching before any request is sent.
#
# Only that swap is reported. An earlier version also complained when a
# key did not match its vendor's usual prefix, which was a mistake: it
# called a perfectly good Gemini key wrong because Google had moved to a
# format this script had never seen. Vendors change their key formats
# whenever they like, and the request itself is the real test - a 401
# means the key is bad and a 404 means it is fine but the model moved.
prefix_warning() {
    local key="$1" slot="$2"

    case "$key$slot" in
        gsk_*grok) echo "     ⚠️  that is a Groq key (gsk_) in the Grok slot. GROQ_API_KEY and GROK_API_KEY are swapped." ;;
        xai-*groq) echo "     ⚠️  that is a Grok/xAI key (xai-) in the Groq slot. GROQ_API_KEY and GROK_API_KEY are swapped." ;;
        *) return 1 ;;
    esac

    return 0
}

report() {
    local name="$1" code="$2" body="$3"

    case "$code" in
        200) echo "  ✅ $name works." ;;
        000) echo "  ❌ $name — could not connect. Check your internet." ;;
        401|403) echo "  ❌ $name — key rejected (HTTP $code). Wrong key, or not activated yet." ;;
        402) echo "  ❌ $name — payment required. The account needs credit." ;;
        404) echo "  ❌ $name — model not found. The model name in config/ai.php is wrong or retired." ;;
        429) echo "  ⚠️  $name — rate limited right now, but the key is valid." ;;
        *)   echo "  ❌ $name — HTTP $code." ;;
    esac

    if [ "$code" != "200" ] && [ -n "$body" ]; then
        echo "     $(echo "$body" | scrub | tr '\n' ' ' | cut -c1-240)"
    fi
}

# On a 404 the useful next thing is the list of models that do exist.
list_models() {
    local key="$1" url="$2"

    echo "     Models this key can actually use:"
    curl -s -m 20 -H "Authorization: Bearer $key" "$url" \
        | tr ',' '\n' | grep -o '"id":"[^"]*"' | cut -d'"' -f4 | sort -u \
        | head -12 | sed 's/^/       /'
}

openai_style() {
    local label="$1" key="$2" url="$3" model="$4" slot="$5" models_url="${6:-}"

    if [ -z "$key" ]; then echo "  ⏭  $label — no key set, will be skipped."; return; fi

    local out code body
    out=$(curl -s -w $'\n%{http_code}' -m 30 \
        -H "Authorization: Bearer $key" -H 'Content-Type: application/json' \
        -d "{\"model\":\"$model\",\"messages\":[{\"role\":\"user\",\"content\":\"Reply with the single word: ok\"}],\"max_tokens\":10}" \
        "$url")
    code=$(printf '%s' "$out" | tail -n1)
    body=$(printf '%s' "$out" | sed '$d')

    report "$label" "$code" "$body"
    prefix_warning "$key" "$slot" || true

    if [ "$code" = "404" ] && [ -n "$models_url" ]; then
        list_models "$key" "$models_url"
    fi
}

echo "Checking the providers in order. Keys are never shown."
echo

openai_style "Groq ($(model_for GROQ_MODEL))" "$(get GROQ_API_KEY)" \
    "https://api.groq.com/openai/v1/chat/completions" "$(model_for GROQ_MODEL)" \
    "groq" "https://api.groq.com/openai/v1/models"

openai_style "Groq small ($(model_for GROQ_SMALL_MODEL))" "$(get GROQ_API_KEY)" \
    "https://api.groq.com/openai/v1/chat/completions" "$(model_for GROQ_SMALL_MODEL)" \
    "groq" "https://api.groq.com/openai/v1/models"

openai_style "Grok / xAI ($(model_for GROK_MODEL))" "$(get GROK_API_KEY)" \
    "https://api.x.ai/v1/chat/completions" "$(model_for GROK_MODEL)" \
    "grok" "https://api.x.ai/v1/models"

GEM=$(get GEMINI_API_KEY)
GEM_MODEL=$(model_for GEMINI_MODEL)
if [ -z "$GEM" ]; then
    echo "  ⏭  Gemini — no key set, will be skipped."
else
    OUT=$(curl -s -w $'\n%{http_code}' -m 30 \
        -H "x-goog-api-key: $GEM" -H 'Content-Type: application/json' \
        -d '{"contents":[{"role":"user","parts":[{"text":"Reply with the single word: ok"}]}]}' \
        "https://generativelanguage.googleapis.com/v1beta/models/${GEM_MODEL}:generateContent")
    report "Gemini ($GEM_MODEL)" "$(printf '%s' "$OUT" | tail -n1)" "$(printf '%s' "$OUT" | sed '$d')"
fi

openai_style "DeepSeek ($(model_for DEEPSEEK_MODEL))" "$(get DEEPSEEK_API_KEY)" \
    "https://api.deepseek.com/v1/chat/completions" "$(model_for DEEPSEEK_MODEL)" \
    "deepseek" "https://api.deepseek.com/v1/models"

openai_style "OpenRouter ($(model_for OPENROUTER_MODEL))" "$(get OPENROUTER_API_KEY)" \
    "https://openrouter.ai/api/v1/chat/completions" "$(model_for OPENROUTER_MODEL)" \
    "openrouter" "https://openrouter.ai/api/v1/models"

echo
echo "Provider order: $(get AI_PROVIDER_ORDER)"
echo "One working provider is enough. The rest are there for when it is not."

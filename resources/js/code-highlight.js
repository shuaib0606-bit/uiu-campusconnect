/**
 * Syntax highlighting for code posts.
 *
 * Only the languages the composer offers are registered, rather than all ~190
 * highlight.js ships with: the full bundle is close to a megabyte, and this
 * set is a fraction of it. Anything posted as plain text is left alone.
 *
 * Loaded as its own chunk, on demand, the first time a code block appears -
 * most people scrolling the feed never see one, and they should not pay for
 * the parser.
 */

import hljs from 'highlight.js/lib/core';

import bash from 'highlight.js/lib/languages/bash';
import c from 'highlight.js/lib/languages/c';
import cpp from 'highlight.js/lib/languages/cpp';
import css from 'highlight.js/lib/languages/css';
import java from 'highlight.js/lib/languages/java';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';

// Keys match PostController::CODE_LANGUAGES. `html` is xml's grammar.
const LANGUAGES = {
    bash,
    c,
    cpp,
    css,
    html: xml,
    java,
    javascript,
    json,
    php,
    python,
    sql,
    typescript,
};

for (const [name, definition] of Object.entries(LANGUAGES)) {
    hljs.registerLanguage(name, definition);
}

/**
 * Colours every unhighlighted code block under `root`.
 *
 * Safe to call repeatedly: a block is marked once it has been done, so the
 * feed appending more posts only costs the new ones.
 */
export function highlight(root = document) {
    const blocks = root.querySelectorAll('pre code[data-language]:not([data-highlighted])');

    for (const block of blocks) {
        const language = block.dataset.language;

        // Plain text, or a language we do not carry: leave the text as it is
        // rather than letting auto-detection guess and colour it wrongly.
        if (!language || !LANGUAGES[language]) {
            block.dataset.highlighted = 'skipped';
            continue;
        }

        try {
            const { value } = hljs.highlight(block.textContent, {
                language,
                ignoreIllegals: true,
            });

            block.innerHTML = value;
        } catch (e) {
            // A grammar that throws leaves the original text in place.
        }

        block.dataset.highlighted = 'yes';
    }
}

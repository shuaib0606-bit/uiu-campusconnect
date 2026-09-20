"""Blade structure checker.

Counts the directives that open and close a block, and the custom <x-...>
components, and reports any that do not balance. A Blade file with a missing
@endif renders as a white screen with a cryptic message, and the error points
at the compiled cache file rather than the source, so catching it here saves
a lot of hunting.

Usage:  python3 check_blade.py path/to/file.blade.php [more files...]
Exit:   0 when every file balances, 1 otherwise.
"""
import re
import sys

# Directives that open a block, and the directive that closes each.
PAIRS = {
    'if': 'endif',
    'unless': 'endunless',
    'isset': 'endisset',
    'empty': 'endempty',
    'auth': 'endauth',
    'guest': 'endguest',
    'foreach': 'endforeach',
    'forelse': 'endforelse',
    'for': 'endfor',
    'while': 'endwhile',
    'switch': 'endswitch',
    'php': 'endphp',
    'section': 'endsection',
    'push': 'endpush',
    'once': 'endonce',
    'error': 'enderror',
    'verbatim': 'endverbatim',
    'fragment': 'endfragment',
}

# @forelse closes with @endforelse, but @empty inside it is not @endempty.
OPEN = re.compile(r'@(' + '|'.join(PAIRS) + r')\s*[\(\s]')
CLOSE = re.compile(r'@(' + '|'.join(PAIRS.values()) + r')\b')
COMMENT = re.compile(r'\{\{--.*?--\}\}', re.S)

# <x-foo ...> ... </x-foo>. Scanned rather than matched with a regex, because
# Blade attribute values routinely contain a PHP arrow (:user="$post->user"),
# and any [^>]* pattern stops dead on the > in that arrow and then mistakes a
# self-closing tag for an opening one.
COMPONENT_NAME = re.compile(r'<(/?)x-([a-z0-9.\-:]+)')


def component_tags(src):
    """Yields (name, self_closing, line) for every <x-...> tag."""
    for m in COMPONENT_NAME.finditer(src):
        closing = m.group(1) == '/'
        name = m.group(2)
        i = m.end()
        quote = None

        # Walk to this tag's own closing >, ignoring anything inside quotes.
        while i < len(src):
            c = src[i]

            if quote:
                if c == quote:
                    quote = None
            elif c in '"\'':
                quote = c
            elif c == '>':
                break

            i += 1

        selfclose = i > 0 and src[i - 1] == '/'

        yield name, closing, selfclose, src.count('\n', 0, m.start()) + 1


def check(path):
    src = open(path, encoding='utf-8').read()

    # Blade comments can legally contain unbalanced text.
    src = COMMENT.sub('', src)

    problems = []
    stack = []

    # Walk opens and closes in document order so the report can name the
    # directive that was left hanging rather than just the count.
    events = []
    for m in OPEN.finditer(src):
        events.append((m.start(), 'open', m.group(1)))
    for m in CLOSE.finditer(src):
        events.append((m.start(), 'close', m.group(1)))
    events.sort()

    for pos, kind, name in events:
        line = src.count('\n', 0, pos) + 1

        if kind == 'open':
            # @empty inside @forelse is a separator, not a block of its own.
            if name == 'empty' and stack and stack[-1][0] == 'forelse':
                continue
            stack.append((name, line))
            continue

        if not stack:
            problems.append(f'line {line}: @{name} with nothing open')
            continue

        opener, opened_at = stack[-1]

        if PAIRS[opener] == name:
            stack.pop()
        else:
            problems.append(
                f'line {line}: @{name} closes @{opener} opened on line {opened_at}'
            )
            stack.pop()

    for opener, opened_at in stack:
        problems.append(f'line {opened_at}: @{opener} is never closed')

    # Components.
    stack = []

    for name, closing, selfclose, line in component_tags(src):
        if selfclose:
            continue

        if closing:
            if not stack:
                problems.append(f'line {line}: </x-{name}> with nothing open')
            elif stack[-1][0] != name:
                opener, opened_at = stack.pop()
                problems.append(
                    f'line {line}: </x-{name}> closes <x-{opener}> opened on line {opened_at}'
                )
            else:
                stack.pop()
        else:
            stack.append((name, line))

    for name, line in stack:
        problems.append(f'line {line}: <x-{name}> is never closed')

    return problems


def main(paths):
    bad = 0

    for path in paths:
        problems = check(path)

        if problems:
            bad += 1
            print(f'FAIL {path}')
            for problem in problems:
                print(f'  {problem}')
        else:
            print(f'ok {path}')

    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

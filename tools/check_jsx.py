"""JSX structure checker.

Nothing here compiles JSX; npm is blocked in the authoring environment, so a
missing </div> would otherwise only surface when the build runs on the other
machine. This does two passes:

  1. Bracket balance outside strings, template literals, regexes and comments.
  2. Tag balance, treating a whole attribute block as one unit so that
     style={{ ... }} cannot swallow the tag it belongs to.

Known limit: an apostrophe in JSX text ("don't") reads as an unterminated
string. Write &apos; - which is what react/no-unescaped-entities wants anyway.

Usage:  python3 check_jsx.py path/to/File.jsx [more files...]
Exit:   0 when every file balances, 1 otherwise.
"""
import sys

VOID = {
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
    'link', 'meta', 'param', 'source', 'track', 'wbr',
}

PAIRS = {')': '(', ']': '[', '}': '{'}


def strip_code(src):
    """Returns the source with strings, comments and regexes blanked out.

    Blanked rather than removed, so every reported offset still lines up with
    the real file.
    """
    out = []
    i = 0
    n = len(src)

    while i < n:
        c = src[i]

        # Line comment
        if c == '/' and i + 1 < n and src[i + 1] == '/':
            while i < n and src[i] != '\n':
                out.append(' ')
                i += 1
            continue

        # Block comment
        if c == '/' and i + 1 < n and src[i + 1] == '*':
            while i < n and not (src[i] == '*' and i + 1 < n and src[i + 1] == '/'):
                out.append('\n' if src[i] == '\n' else ' ')
                i += 1
            out.append('  ')
            i += 2
            continue

        # String or template literal
        if c in '"\'`':
            quote = c
            out.append(' ')
            i += 1

            while i < n:
                if src[i] == '\\':
                    out.append('  ')
                    i += 2
                    continue

                if src[i] == quote:
                    out.append(' ')
                    i += 1
                    break

                out.append('\n' if src[i] == '\n' else ' ')
                i += 1
            else:
                return None, f'unterminated {quote} string'

            continue

        out.append(c)
        i += 1

    return ''.join(out), None


def check_brackets(code):
    stack = []

    for i, c in enumerate(code):
        if c in '([{':
            stack.append((c, i))
        elif c in ')]}':
            if not stack:
                return f'line {code.count(chr(10), 0, i) + 1}: stray {c}'

            opener, at = stack.pop()

            if opener != PAIRS[c]:
                return (
                    f'line {code.count(chr(10), 0, i) + 1}: {c} closes {opener} '
                    f'opened on line {code.count(chr(10), 0, at) + 1}'
                )

    if stack:
        opener, at = stack[-1]
        return f'line {code.count(chr(10), 0, at) + 1}: {opener} is never closed'

    return None


def scan_tags(code):
    """Walks JSX tags, skipping each attribute block as a single unit."""
    stack = []
    i = 0
    n = len(code)

    while i < n:
        if code[i] != '<':
            i += 1
            continue

        j = i + 1

        if j >= n:
            break

        closing = code[j] == '/'

        if closing:
            j += 1

        # Fragment: <> or </>
        if j < n and code[j] == '>':
            if closing:
                if not stack or stack[-1][0] != '<>':
                    return f'line {code.count(chr(10), 0, i) + 1}: </> with no matching <>'
                stack.pop()
            else:
                stack.append(('<>', code.count('\n', 0, i) + 1))

            i = j + 1
            continue

        # Name
        start = j
        while j < n and (code[j].isalnum() or code[j] in '._-'):
            j += 1

        name = code[start:j]

        if not name or not (name[0].isalpha() or name[0] == '_'):
            i += 1
            continue

        # Walk to the closing > of this tag, counting braces so an attribute
        # value such as {{ a: 1 }} or {cond && <X />} is stepped over whole.
        depth = 0
        while j < n:
            if code[j] == '{':
                depth += 1
            elif code[j] == '}':
                depth -= 1
            elif code[j] == '>' and depth == 0:
                break
            j += 1

        if j >= n:
            return f'line {code.count(chr(10), 0, i) + 1}: <{name} has no closing >'

        selfclose = not closing and code[j - 1] == '/'
        line = code.count('\n', 0, i) + 1

        if closing:
            if not stack:
                return f'line {line}: </{name}> with nothing open'

            opener, opened_at = stack.pop()

            if opener != name:
                return f'line {line}: </{name}> closes <{opener}> opened on line {opened_at}'

        elif not selfclose and name not in VOID:
            # Matched case-sensitively on purpose. HTML void elements are
            # lowercase; a React component is capitalised, so <Link> is a
            # component that must be closed while <link> is not.
            stack.append((name, line))

        i = j + 1

    if stack:
        name, line = stack[-1]
        return f'line {line}: <{name}> is never closed'

    return None


def check(path):
    src = open(path, encoding='utf-8').read()

    code, err = strip_code(src)

    if err:
        return err

    return check_brackets(code) or scan_tags(code)


def main(paths):
    bad = 0

    for path in paths:
        problem = check(path)

        if problem:
            bad += 1
            print(f'FAIL {path}')
            print(f'  {problem}')
        else:
            print(f'ok {path}')

    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))

# Checkers

Standing in for a build. The environment these changes are written in has no
working npm and no database, so nothing is compiled or executed before it
reaches this machine. These catch the classes of mistake that would otherwise
only appear when somebody clicks the wrong button.

Each was negative-tested against deliberately broken files *and* run against
the whole working project. Both of those matter: the first proves it can fail,
the second proves it does not cry wolf. Both checkers had a real bug that only
the second kind of run exposed.

    python3 tools/check_blade.py $(find resources/views -name "*.blade.php")
    python3 tools/check_jsx.py resources/js/Layouts/*.jsx resources/js/Pages/*/*.jsx resources/js/Components/*.jsx
    APP_ROOT=$(pwd) php tools/check_calls.php

| Tool | Catches | Known limit |
| --- | --- | --- |
| `check_blade.py` | `@if`/`@foreach`/`@forelse` and `<x-component>` balance | none known |
| `check_jsx.py` | Bracket and JSX tag balance | an apostrophe in JSX text reads as an unterminated string - write `&apos;` |
| `check_calls.php` | `$this->foo()` that nothing declares | a class extending something outside `app/` is skipped, since its parent cannot be resolved |

What they do **not** catch: anything needing a parser or a running app - a hook
called conditionally, a bad import path, a Tailwind class that does not exist,
or a controller writing to a column the model will not accept. That last one
produced a real bug in this project, and only clicking the button found it.

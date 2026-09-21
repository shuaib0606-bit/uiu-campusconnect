#!/usr/bin/env python3
"""
Builds docs/UIU-Real-Data-Sources.pdf from sources.md.

The four data tables in that document are not written out by hand: they are
read from the seeding command through gen_sources.php, so the document lists
exactly the rows the database gets. If somebody adds a club to the command,
the next build of this PDF has it.

    python3 build_sources.py

Needs pandoc, php, and LibreOffice for the PDF.
"""

import json
import os
import re
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SOURCE = os.path.join(HERE, 'sources.md')
BUILD = os.path.join(HERE, '.sources-build.md')
DOCX = os.path.join(HERE, 'UIU-Real-Data-Sources.docx')
PDF = os.path.join(HERE, 'UIU-Real-Data-Sources.pdf')
REFERENCE = os.path.join(HERE, 'reference.docx')
SEEDER = os.path.join(HERE, '..', 'src', 'app', 'Console', 'Commands', 'SeedUiuData.php')

PAGE_BREAK = (
    '```{=openxml}\n'
    '<w:p><w:r><w:br w:type="page"/></w:r></w:p>\n'
    '```'
)

TYPE_LABELS = {
    'exam': 'Exams', 'routine': 'Class routines', 'payment': 'Payments',
    'admission': 'Admission', 'scholarship': 'Scholarship',
    'transport': 'Transport', 'department': 'Department',
    'section': 'Sections', 'academic': 'Academic', 'university': 'University',
}

MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
          'August', 'September', 'October', 'November', 'December']


def seed_data():
    """The seeding command's own arrays, as JSON."""
    result = subprocess.run(['php', os.path.join(HERE, 'gen_sources.php'), SEEDER],
                            capture_output=True, text=True)

    if result.returncode != 0:
        sys.exit(result.stderr.strip() or 'gen_sources.php failed')

    return json.loads(result.stdout)


def pretty_date(iso):
    year, month, day = iso.split('-')
    return f'{int(day)} {MONTHS[int(month) - 1]} {year}'


def slug_to_name(slug, clubs):
    """The club's printed name, from the slug the seeder refers to it by."""
    for name, *_ in clubs:
        if re.sub(r'[^a-z0-9]+', '-', name.lower()).strip('-') == slug:
            return name

    return slug


def table(headers, rows, widths):
    """A pandoc multiline table, matching the rest of the documentation."""
    import textwrap

    widths = [max(w, len(h) + 4) for w, h in zip(widths, headers)]
    total = sum(widths) + len(widths) - 1

    def row_text(cells):
        columns = []

        for cell, width in zip(cells, widths):
            lines = textwrap.wrap(str(cell), width, break_long_words=False,
                                  break_on_hyphens=False) or ['']
            columns.append(lines)

        height = max(len(c) for c in columns)
        out = []

        for n in range(height):
            parts = [(c[n] if n < len(c) else '').ljust(w)
                     for c, w in zip(columns, widths)]
            out.append(' '.join(parts).rstrip())

        return '\n  '.join(out)

    lines = ['  ' + '-' * total,
             '  ' + row_text([f'**{h}**' for h in headers]),
             '  ' + ' '.join('-' * w for w in widths)]

    for i, row in enumerate(rows):
        if i:
            lines.append('')
        lines.append('  ' + row_text(row))

    lines.append('  ' + '-' * total)

    return '\n'.join(lines)


def club_table(data):
    rows = [[name, category, facebook]
            for name, category, _description, facebook in data['CLUBS']]

    return table(['Club', 'Filed under', 'Facebook page'], rows, [46, 14, 26])


def notice_table(data):
    rows = []

    for title, date, kind, priority, urgent, _slug in data['NOTICES']:
        label = TYPE_LABELS.get(kind, kind)
        rows.append([pretty_date(date), title,
                     label + (' · urgent' if urgent else '')])

    return table(['Published', 'Title', 'Filed under'], rows, [16, 52, 20])


def event_table(data):
    rows = []

    for title, start, end, _category, slug, _source in data['EVENTS']:
        when = pretty_date(start)

        if end != start:
            when += ' to ' + pretty_date(end)

        rows.append([title, when, slug_to_name(slug, data['CLUBS']) if slug else 'University'])

    return table(['Event', 'Dates as published', 'Organised by'], rows, [42, 24, 26])


def upcoming_table(data):
    rows = [[title, slug_to_name(slug, data['CLUBS']), f'about {days} days from seeding']
            for title, slug, days, _category in data['UPCOMING']]

    return table(['Programme (real name)', 'Club', 'Date shown (not real)'],
                 rows, [38, 34, 24])


def main():
    data = seed_data()

    replacements = {
        '<<<CLUBTABLE>>>': club_table(data),
        '<<<NOTICETABLE>>>': notice_table(data),
        '<<<EVENTTABLE>>>': event_table(data),
        '<<<UPCOMINGTABLE>>>': upcoming_table(data),
        '<<<PAGEBREAK>>>': PAGE_BREAK,
    }

    with open(SOURCE, encoding='utf-8') as handle:
        text = handle.read()

    for marker, block in replacements.items():
        if marker != '<<<PAGEBREAK>>>' and marker not in text:
            sys.exit(f'{marker} is missing from sources.md')

        text = text.replace(marker, block)

    with open(BUILD, 'w', encoding='utf-8') as handle:
        handle.write(text)

    if not shutil.which('pandoc'):
        sys.exit('pandoc is not installed.')

    subprocess.run(['pandoc', BUILD, '-o', DOCX,
                    f'--reference-doc={REFERENCE}',
                    f'--resource-path={HERE}'], check=True)

    print(f'Wrote {os.path.basename(DOCX)}')

    soffice = shutil.which('soffice') or shutil.which('libreoffice')

    if soffice:
        subprocess.run([soffice, '--headless', '--convert-to', 'pdf',
                        '--outdir', HERE, DOCX],
                       check=True, capture_output=True)
        print(f'Wrote {os.path.basename(PDF)}')

    print(f"{len(data['CLUBS'])} clubs, {len(data['NOTICES'])} notices, "
          f"{len(data['EVENTS'])} events, {len(data['UPCOMING'])} programmes")


if __name__ == '__main__':
    main()

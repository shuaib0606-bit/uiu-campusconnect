#!/usr/bin/env python3
"""
Turns docs/full.md into the Word file and the PDF.

full.md is the prose. It carries two markers that plain markdown has no
spelling for:

    <<<PAGEBREAK>>>          start the next chapter on a fresh page
    <<<INCLUDE name.md>>>    splice in docs/generated/name.md

The include markers are how the reference appendices stay true: they are not
in full.md at all, they are produced by gen_appendices.py straight from the
source tree, and this script pulls the current version in every build.

    python3 gen_appendices.py && python3 build.py

Needs pandoc. Makes the PDF with LibreOffice if it is installed, so the two
files always say the same thing.
"""

import os
import re
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
FULL = os.path.join(HERE, 'full.md')
BUILD = os.path.join(HERE, 'build.md')
GENERATED = os.path.join(HERE, 'generated')

# A pristine copy, kept apart from the output so a build never restyles
# itself from its own previous run.
REFERENCE = os.path.join(HERE, 'reference.docx')
DOCX = os.path.join(HERE, 'UIU-CampusConnect-System-Documentation.docx')
PDF = os.path.join(HERE, 'UIU-CampusConnect-System-Documentation.pdf')

# Word has no markdown for "new page", so it goes in as the XML Word itself
# uses. Pandoc passes a raw openxml block through untouched.
PAGE_BREAK = (
    '```{=openxml}\n'
    '<w:p><w:r><w:br w:type="page"/></w:r></w:p>\n'
    '```'
)

INCLUDE = re.compile(r'^<<<INCLUDE (.+?)>>>$')


def headings():
    """
    Every chapter and section title, in order, as they will be printed.

    The generated appendices are spliced in at build time, so their headings
    are not in full.md; the include markers are followed here too, or the
    contents page would stop at the last hand-written chapter.
    """
    found = []

    def walk(path):
        with open(path, encoding='utf-8') as handle:
            for line in handle:
                include = INCLUDE.match(line.rstrip('\n'))

                if include:
                    part = os.path.join(GENERATED, include.group(1))

                    if os.path.exists(part):
                        walk(part)

                    continue

                if line.startswith('# ') or line.startswith('## '):
                    found.append((line.count('#', 0, 3), line.lstrip('# ').strip()))

    walk(FULL)

    return found


def contents(pages=None):
    """
    The contents page, as a pandoc simple table so the page numbers line up
    in a right-hand column.

    It is written out rather than left to Word's TOC field, because that field
    is an instruction that only Word runs: in a PDF made by anything else the
    contents page comes out blank. Page numbers are filled on the second pass
    (see build_once); on the first they are dashes, which occupy the same one
    line per entry, so the contents page is the same length both times and
    nothing shifts under it.
    """
    # A pipe table. Pandoc only works out column widths for a table whose
    # source is wider than 72 characters - narrower than that and both
    # columns come out of Word collapsed into one - so the rule is drawn
    # deliberately wide.
    indent = '\u00a0' * 4

    rows = ['| Section | Page |',
            '|:' + '-' * 78 + '|' + '-' * 6 + ':|']

    for level, title in headings():
        label = f'**{title}**' if level == 1 else indent + title
        number = str(pages.get(title, '')) if pages else '-'
        rows.append(f'| {label} | {number} |')

    return '\n'.join(rows)


def assemble(pages=None):
    out = []
    included = []

    with open(FULL, encoding='utf-8') as handle:
        for line in handle:
            line = line.rstrip('\n')

            if line == '<<<PAGEBREAK>>>':
                out.append(PAGE_BREAK)
                continue

            if line == '<<<CONTENTS>>>':
                out.append(contents(pages))
                continue

            match = INCLUDE.match(line)
            if match:
                name = match.group(1)
                path = os.path.join(GENERATED, name)

                if not os.path.exists(path):
                    sys.exit(f'Missing {path}. Run gen_appendices.py first.')

                with open(path, encoding='utf-8') as part:
                    out.append(part.read().rstrip('\n'))

                included.append(name)
                continue

            out.append(line)

    with open(BUILD, 'w', encoding='utf-8') as handle:
        handle.write('\n'.join(out) + '\n')

    return included


def ensure_base_styles(path):
    """
    Fills in the Word styles the reference document never defined.

    The reference carries the house look - the fonts, the terracotta
    headings, the running footer - but it was written by hand and defines
    only seventeen styles. It has no Normal, no BodyText, no Compact and no
    table style, which are the ones pandoc puts on ordinary paragraphs and on
    the inside of every table cell. With nothing to apply, Word and
    LibreOffice fall back, and every table in the document renders as one
    collapsed column: the stack table, the route table and all the rest come
    out as a list of single words.

    So any style pandoc's own default reference defines and this one does
    not is copied in. Ours win where both define a style, which is what keeps
    the look. Idempotent: once the styles are there this does nothing.
    """
    import re
    import shutil as _shutil
    import zipfile

    with zipfile.ZipFile(path) as source:
        styles = source.read('word/styles.xml').decode('utf-8')

    mine = set(re.findall(r'w:styleId="([^"]+)"', styles))

    if 'Normal' in mine and 'Compact' in mine:
        return

    default = subprocess.run(['pandoc', '--print-default-data-file',
                              'reference.docx'], capture_output=True).stdout

    scratch = path + '.default'

    with open(scratch, 'wb') as handle:
        handle.write(default)

    with zipfile.ZipFile(scratch) as source:
        theirs = source.read('word/styles.xml').decode('utf-8')

    os.remove(scratch)

    adding = []

    for block in re.findall(r'<w:style [^>]*>.*?</w:style>', theirs, re.S):
        style_id = re.search(r'w:styleId="([^"]+)"', block)

        if style_id and style_id.group(1) not in mine:
            # The reference's own docDefaults set the font and colour, so the
            # incoming styles must not carry pandoc's.
            block = re.sub(r'<w:rFonts [^>]*/>', '', block)
            adding.append(block)

    if not adding:
        return

    patched = styles.replace('</w:styles>', ''.join(adding) + '</w:styles>')
    temporary = path + '.tmp'

    with zipfile.ZipFile(path) as source:
        with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as target:
            for item in source.infolist():
                data = source.read(item.filename)

                if item.filename == 'word/styles.xml':
                    data = patched.encode('utf-8')

                target.writestr(item, data)

    _shutil.move(temporary, path)
    print(f'  added {len(adding)} missing styles to reference.docx')


def force_field_update(path):
    """
    Tells Word and LibreOffice to rebuild the table of contents when the file
    is opened.

    Pandoc writes the contents page as a TOC *field* - an instruction to work
    the list out - rather than as text, and it takes settings.xml from the
    reference document, which has no updateFields flag. Without the flag
    nothing ever runs the instruction, so the document opens with the heading
    "Contents" above nothing at all. This adds it.
    """
    import shutil as _shutil
    import zipfile

    flag = '<w:updateFields w:val="true"/>'
    temporary = path + '.tmp'

    with zipfile.ZipFile(path) as source:
        settings = source.read('word/settings.xml').decode('utf-8')

        if flag in settings:
            return

        # It belongs directly inside <w:settings>, and the schema wants it
        # among the first elements, so it goes at the front.
        opening = settings.index('>', settings.index('<w:settings')) + 1
        patched = settings[:opening] + flag + settings[opening:]

        with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as target:
            for item in source.infolist():
                data = source.read(item.filename)

                if item.filename == 'word/settings.xml':
                    data = patched.encode('utf-8')

                target.writestr(item, data)

    _shutil.move(temporary, path)


def run(command):
    result = subprocess.run(command, capture_output=True, text=True)

    if result.returncode != 0:
        sys.exit(result.stderr.strip() or f'{command[0]} failed')

    return result


def build_once(pages, soffice):
    """One pass: assemble, convert to docx, and convert that to PDF."""
    included = assemble(pages)

    run(['pandoc', BUILD, '-o', DOCX,
         f'--reference-doc={REFERENCE}',
         # Figure paths in full.md are relative to docs/, so pandoc is told
         # where to look rather than depending on where it was run from -
         # which is how every figure silently went missing once already.
         f'--resource-path={HERE}'])

    missing = [line for line in open(BUILD, encoding='utf-8')
               if line.startswith('![](') and not os.path.exists(
                   os.path.join(HERE, line[4:].split(')')[0]))]

    if missing:
        sys.exit('Figures not found:\n' + ''.join(missing))

    force_field_update(DOCX)

    if soffice:
        run([soffice, '--headless', '--convert-to', 'pdf', '--outdir', HERE, DOCX])

    return included


def body_starts_with():
    """The first sentence of chapter 1, used to find where the body begins."""
    with open(FULL, encoding='utf-8') as handle:
        seen_chapter = False

        for line in handle:
            if line.startswith('# '):
                seen_chapter = True
                continue

            if seen_chapter and line.strip():
                return line.strip()[:40]

    return None


def page_numbers():
    """
    Which page each heading landed on, read back out of the PDF.

    pdftotext splits pages on form feeds, so the page a title is on is the
    index of the chunk it appears in. The search starts at the first page of
    the body: the contents page lists every heading itself, so scanning from
    page one would give every entry the contents page's own number.
    """
    text = subprocess.run(['pdftotext', '-layout', PDF, '-'],
                          capture_output=True, text=True).stdout
    chunks = text.split('\f')

    sentinel = body_starts_with()
    first = next((i for i, chunk in enumerate(chunks) if sentinel in chunk), 0)

    pages = {}
    at = first

    for _, title in headings():
        for number in range(at, len(chunks)):
            # A whole-line match: Word wraps nothing in a heading, and this
            # stops a title quoted mid-paragraph from claiming the entry.
            if any(line.strip() == title for line in chunks[number].splitlines()):
                pages[title] = number + 1
                at = number
                break

    return pages


def main():
    if not shutil.which('pandoc'):
        sys.exit('pandoc is not installed.')

    ensure_base_styles(REFERENCE)

    soffice = shutil.which('soffice') or shutil.which('libreoffice')

    if not soffice or not shutil.which('pdftotext'):
        print('No LibreOffice or pdftotext - building once, contents unnumbered.')
        build_once(None, soffice)
        print(f"\nWrote {os.path.basename(DOCX)}")
        return 0

    # First pass lays the document out; second pass puts the page numbers it
    # produced onto the contents page. The contents page is the same height
    # both times, so nothing moves between them.
    print('Pass 1 of 2: laying out')
    included = build_once(None, soffice)

    pages = page_numbers()
    print(f'  found {len(pages)} of {len(headings())} headings')

    print('Pass 2 of 2: numbering the contents page')
    build_once(pages, soffice)

    print(f'\nAssembled from {len(included)} generated parts')
    size = os.path.getsize(DOCX) / 1e6
    print(f'Wrote {os.path.basename(DOCX)} ({size:.1f} MB)')
    print(f'Wrote {os.path.basename(PDF)}')
    return 0


if __name__ == '__main__':
    sys.exit(main())

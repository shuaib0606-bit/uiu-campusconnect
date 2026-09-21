#!/usr/bin/env python3
"""
Builds docs/UIU-CampusConnect-Demo-Script.pdf from script.md.

Separate from build.py because this document is read at arm's length on a
tablet while somebody talks to a camera, not at a desk. It uses
script-reference.docx, which is the house reference with the body type
raised from 10pt to 12.5pt, and it has no contents page - a presenter
scrolls, they do not look things up.

    python3 build_script.py
"""

import os
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SOURCE = os.path.join(HERE, 'script.md')
BUILD = os.path.join(HERE, '.script-build.md')
DOCX = os.path.join(HERE, 'UIU-CampusConnect-Demo-Script.docx')
PDF = os.path.join(HERE, 'UIU-CampusConnect-Demo-Script.pdf')
REFERENCE = os.path.join(HERE, 'script-reference.docx')

PAGE_BREAK = (
    '```{=openxml}\n'
    '<w:p><w:r><w:br w:type="page"/></w:r></w:p>\n'
    '```'
)


def main():
    if not os.path.exists(REFERENCE):
        sys.exit('script-reference.docx is missing.')

    with open(SOURCE, encoding='utf-8') as handle:
        text = handle.read()

    text = text.replace('<<<PAGEBREAK>>>', PAGE_BREAK)

    with open(BUILD, 'w', encoding='utf-8') as handle:
        handle.write(text)

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


if __name__ == '__main__':
    main()

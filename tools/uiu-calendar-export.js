// Exports UIU's academic calendars from www.uiu.ac.bd as uiu-calendars.json,
// for `php artisan uiu:import-calendars --file=...`.
//
// Runs in your own browser, on UIU's site, as an ordinary visitor: it reads
// the calendar list and the ~10 calendar pages once, then shows a button to
// save the file. See uiu-calendar-export.md for how to install it as a bookmark.
(async () => {
    if (location.hostname !== 'www.uiu.ac.bd') {
        alert('Open https://www.uiu.ac.bd/academics/calendar/ first, then click this bookmark again.');
        return;
    }

    const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
    const html = (s) => new DOMParser().parseFromString(s, 'text/html');

    const list = await fetch('/wp-json/wp/v2/calendars?per_page=100&_fields=slug,title,date,link').then((r) => r.json());
    const out = [];

    for (const c of list) {
        const doc = html(await fetch(c.link).then((r) => r.text()));

        const rows = [...doc.querySelectorAll('.calender-table tr')]
            .map((tr) => [...tr.querySelectorAll('td')])
            .filter((td) => td.length >= 3)
            .map((td) => {
                const ps = [...td[2].querySelectorAll('p')];
                const items = (ps.length ? ps : [td[2]])
                    .map((p) => ({ t: clean(p.textContent), b: !!p.querySelector('strong,b') }))
                    .filter((i) => i.t);
                return [clean(td[0].textContent), clean(td[1].textContent), items];
            });

        const notes = [...doc.querySelectorAll('.calender-extra-details')].map((box) => {
            const blocks = [...box.querySelectorAll('p,li')];
            return (blocks.length ? blocks : [box]).map((b) => clean(b.textContent)).filter(Boolean).join('\n\n');
        }).join('\n\n');

        out.push({ slug: c.slug, title: clean(html(c.title.rendered).body.textContent), date: c.date, link: c.link, rows, notes });
    }

    // Browsers only allow a download straight from a click, so offer a button.
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(out)], { type: 'application/json' }));
    a.download = 'uiu-calendars.json';
    a.textContent = 'Download uiu-calendars.json (' + out.length + ' calendars)';
    a.style.cssText = 'position:fixed;top:20px;left:20px;z-index:99999;padding:16px 22px;background:#c15f3c;color:#fff;font:600 16px system-ui;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.25)';
    a.onclick = () => setTimeout(() => a.remove(), 500);
    document.body.appendChild(a);
})();

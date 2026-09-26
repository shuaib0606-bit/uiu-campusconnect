# Exporting UIU's calendars from the browser

`php artisan uiu:import-calendars` reads the calendars straight from uiu.ac.bd,
but the site's bot protection (Imunify360) refuses requests that do not come
from a browser. When the command says so, do this instead. It takes a minute
and only needs doing when UIU publishes or revises a calendar.

## One-time setup: add the bookmark

1. Show the bookmarks bar (Brave/Chrome: Cmd+Shift+B).
2. Right-click the bar, choose **Add page...** (or **Add bookmark**).
3. Name: `UIU calendars`. URL: paste the whole line below.

```
javascript:(async () => { if (location.hostname !== 'www.uiu.ac.bd') { alert('Open https://www.uiu.ac.bd/academics/calendar/ first, then click this bookmark again.'); return; } const clean = (s) => (s || '').replace(/\s+/g, ' ').trim(); const html = (s) => new DOMParser().parseFromString(s, 'text/html'); const list = await fetch('/wp-json/wp/v2/calendars?per_page=100&_fields=slug,title,date,link').then((r) => r.json()); const out = []; for (const c of list) { const doc = html(await fetch(c.link).then((r) => r.text())); const rows = [...doc.querySelectorAll('.calender-table tr')] .map((tr) => [...tr.querySelectorAll('td')]) .filter((td) => td.length >= 3) .map((td) => { const ps = [...td[2].querySelectorAll('p')]; const items = (ps.length ? ps : [td[2]]) .map((p) => ({ t: clean(p.textContent), b: !!p.querySelector('strong,b') })) .filter((i) => i.t); return [clean(td[0].textContent), clean(td[1].textContent), items]; }); const notes = [...doc.querySelectorAll('.calender-extra-details')].map((box) => { const blocks = [...box.querySelectorAll('p,li')]; return (blocks.length ? blocks : [box]).map((b) => clean(b.textContent)).filter(Boolean).join('\n\n'); }).join('\n\n'); out.push({ slug: c.slug, title: clean(html(c.title.rendered).body.textContent), date: c.date, link: c.link, rows, notes }); } const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([JSON.stringify(out)], { type: 'application/json' })); a.download = 'uiu-calendars.json'; a.textContent = 'Download uiu-calendars.json (' + out.length + ' calendars)'; a.style.cssText = 'position:fixed;top:20px;left:20px;z-index:99999;padding:16px 22px;background:#c15f3c;color:#fff;font:600 16px system-ui;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.25)'; a.onclick = () => setTimeout(() => a.remove(), 500); document.body.appendChild(a); })();
```

(The readable version is `tools/uiu-calendar-export.js`.)

## Each time

1. Open <https://www.uiu.ac.bd/academics/calendar/>.
2. Click the **UIU calendars** bookmark. After a few seconds an orange
   **Download uiu-calendars.json** button appears at the top left; click it.
3. Import it:

```
php artisan uiu:import-calendars --file=~/Downloads/uiu-calendars.json
```

Calendars that have not changed are left alone, revised ones are updated, new
ones are added. Delete the downloaded file afterwards if you like.

For a permanent fix, UIU's IT office can whitelist the server's address so the
command can fetch the calendars itself.

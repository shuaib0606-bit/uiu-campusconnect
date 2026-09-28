# Run the CampusConnect Selenium tests on your Mac

You already have what you need:
- Python 3.9.6 ✓
- `selenium` 4.36 ✓
- A Chromium browser — **Chrome or Brave both work** (Selenium downloads the
  matching driver automatically the first time).

## What this suite does

- **Guest checks** (logged out): landing page, sign-in form, and that
  protected pages redirect you to sign-in.
- **For each account you list** (multi-role in one run): logs in, then
  - loads every core module page (feed, clubs, events, marketplace, lost &
    found, groups, assistant, calendar, deadlines, notices, network, messages,
    notifications, search, profile, settings),
  - **crawls your live data** and opens *every* club, event, marketplace item,
    lost-&-found item, group, post, and profile it can find,
  - checks role-gated pages (admin, moderation, administration, approvals)
    respond without a server error,
  - runs the **CGPA / retake / tuition tools with exact expected math**.
- **Optional write tests** (only with `CC_WRITE=1`): react, save, and
  create-then-delete a post. Off by default.

It writes `results_local/report.html` with a screenshot for every test.

## 1. Start your app

Make sure the site is running at **http://127.0.0.1:8000**:

```bash
php artisan serve      # from your project folder, with XAMPP MySQL running
```

## 2. List your accounts (for multi-role testing)

Copy the template and fill in real logins:

```bash
cp accounts.example.json accounts.json
# then edit accounts.json — put in the email + password for each role
```

`accounts.json` looks like:

```json
[
  { "label": "Student (Arpon)",    "email": "you@uiu...",  "password": "..." },
  { "label": "Faculty (Dr Suman)", "email": "...",         "password": "..." },
  { "label": "Registrar",          "email": "...",         "password": "..." },
  { "label": "Admin",              "email": "...",         "password": "..." }
]
```

Give each account a **`"role"`**: `student`, `faculty`, `registrar` or `admin`.
The suite uses it to check every restricted page against who *should* be able
to open it. Without it, it guesses from the label, and marks the access checks
"?" if it can't tell.

List as many or as few as you like. **No `accounts.json`?** The script just
asks for one login at the prompt (or run public-only by pressing Enter).

> `accounts.json` holds passwords — keep it on your machine, don't commit it.

## 3. Run

```bash
CC_BROWSER=brave python3 run_local.py
```

## 4. Read the results

```
results_local/report.html     <- open this (grouped by role, screenshots)
results_local/results.json    <- raw results
results_local/shots/*.png
```

## Reading the access-control matrix

The top of the report has a table of every restricted page against every
account. Each cell shows **Allowed** or **Blocked (403)**:

- **✓ green**: that's what your code's rules say should happen. A student
  getting *Blocked (403)* on the admin panel is correct, and counts as a pass.
- **✗ LEAK, red**: someone got into a page they shouldn't. This is a failure.
- **✗, red, Blocked**: someone was kept out of a page they should reach.

Every page now also checks its real HTTP status, so a 403 or 404 on a normal
page or on a crawled record is a failure, never a silent pass.

## Safe against your real data?

**Yes, by default.** Everything is read-only — it loads pages and runs the
(client-side) calculators. It does not change your database.

**Write tests are opt-in.** Turn them on with `CC_WRITE=1` to also test
posting/reacting/saving. They try to clean up after themselves (e.g. create a
post then delete it), but because they touch real records, only use write mode
against a scratch/test database or when you're OK with that:

```bash
CC_WRITE=1 CC_BROWSER=brave python3 run_local.py
```

Each write test is locked to one specific post (by its id), so it can never
touch a different post, and it confirms its cleanup by reloading the page:

- **React / Save** — toggles on, toggles back off, then reloads to confirm the
  post is back exactly as it was.
- **Create post** — posts `E2E autotest post HHMMSS`, confirms it appears,
  deletes it with that post's own Delete button, reloads to confirm it's gone.

Note: CampusConnect **soft-deletes** posts (your `destroy` sets
`is_deleted = 1` rather than removing the row) — the same thing your own
"Delete post" button does. So after a write run the test post is hidden
everywhere, but a flagged row stays in the `posts` table. If a cleanup ever
fails, the report names the exact post to remove by hand.

## Options

```bash
CC_BASE_URL="http://localhost:8000"   # different app URL
CC_BROWSER=brave | chrome             # pick the browser
CC_BINARY="/path/to/Browser"          # exact browser binary
CC_HEADLESS=1                         # no visible window
CC_WRITE=1                            # include write/interaction tests
CC_MAX_PER_CAT=50                     # crawl up to N pages per category per role
```

The crawl is capped at 30 pages per category per role by default so runs stay
quick — raise `CC_MAX_PER_CAT` to cover more (or every) record.

## How many tests will run?

It depends on your data — the crawl grows with it. On the test copy (7 clubs,
11 events, 16 marketplace items, 15 lost-&-found items, up to 13 groups, posts
and 25 profiles) it ran **~123 tests per account → 497 tests for 4 roles**
(all passing), plus 3 more per account in write mode. Your live database has
more records, so expect more.

## Troubleshooting

- **"login failed — these credentials do not match"** — wrong email/password.
  Use exactly what you type to sign in normally. To see valid emails:
  `/Applications/XAMPP/xamppfiles/bin/mysql -u root uiu_campusconnect_laravel -e "SELECT email, role FROM users LIMIT 20;"`
- **Lots of connection errors** — the app isn't running at `CC_BASE_URL`.
- **"Could not start the browser"** — make sure Chrome/Brave is installed;
  Selenium Manager handles the driver.

# UIU CampusConnect — Selenium E2E Test Summary

- **Result:** 48/48 passed (100%), 0 failed, 0 errored, 0 skipped
- **Browser:** Chrome 148.0.7778.97 via chromedriver (W3C WebDriver)
- **Target:** http://127.0.0.1:8010 (local test copy of the app)
- **Run at:** 2026-09-28T08:48:10 · **Duration:** 59.2s

Every test signs in through the test-only login shim as the relevant role (student = Arpon, faculty = Dr. Suman Ahmmed, registrar, admin), drives a real Chrome browser, and captures a screenshot.

## Public — 3/3 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Landing page loads with hero + 3D scene | guest | ✅ pass | 0.36s |  |
| Sign-in page renders login form | guest | ✅ pass | 0.36s |  |
| Feed redirects unauthenticated users to sign-in | guest | ✅ pass | 0.32s |  |

## Feed — 5/5 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Student feed loads with posts | student | ✅ pass | 0.87s |  |
| Feed composer is present | student | ✅ pass | 0.68s |  |
| Student can publish a post | student | ✅ pass | 2.72s |  |
| Student can react to a post | student | ✅ pass | 3.81s |  |
| Opening a post detail page works | student | ✅ pass | 0.89s |  |

## Student Tools — 3/3 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| CGPA calculator computes UIU GPA correctly | student | ✅ pass | 5.48s |  |
| Retake estimator tab renders | student | ✅ pass | 1.97s |  |
| Tuition estimator tab renders | student | ✅ pass | 2.04s |  |

## Modules — 22/22 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Clubs index lists clubs | student | ✅ pass | 0.45s |  |
| Events index lists events | student | ✅ pass | 0.42s |  |
| Marketplace index lists items | student | ✅ pass | 0.42s |  |
| Lost & Found index loads | student | ✅ pass | 0.48s |  |
| Groups index loads | student | ✅ pass | 0.43s |  |
| Assistant page loads | student | ✅ pass | 0.37s |  |
| Academic calendar loads | student | ✅ pass | 0.5s |  |
| Deadlines page loads | student | ✅ pass | 0.49s |  |
| Notices board loads | student | ✅ pass | 0.38s |  |
| Network page loads | student | ✅ pass | 0.6s |  |
| Messages page loads | student | ✅ pass | 0.41s |  |
| Notifications page loads | student | ✅ pass | 0.4s |  |
| Own profile page loads | student | ✅ pass | 0.6s |  |
| Profile settings page loads | student | ✅ pass | 0.4s |  |
| Academic settings page loads | student | ✅ pass | 0.17s |  |
| Club detail page loads | student | ✅ pass | 0.44s |  |
| Event detail page loads | student | ✅ pass | 0.35s |  |
| Marketplace item detail loads | student | ✅ pass | 0.5s |  |
| Lost & Found item detail loads | student | ✅ pass | 0.32s |  |
| Group detail page loads | student | ✅ pass | 0.61s |  |
| Another user's profile loads | student | ✅ pass | 0.48s |  |
| Search returns a results page | student | ✅ pass | 0.43s |  |

## Chat — 1/1 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Student can post in a group chat | student | ✅ pass | 2.5s |  |

## Faculty — 4/4 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Faculty account signs in to feed | faculty | ✅ pass | 0.94s |  |
| Faculty can open Administration | faculty | ✅ pass | 0.38s |  |
| Faculty can open Moderation queue | faculty | ✅ pass | 0.15s |  |
| Faculty sees a group they can manage | faculty | ✅ pass | 0.31s |  |

## Registrar — 4/4 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Registrar account signs in to feed | registrar | ✅ pass | 0.8s |  |
| Registrar can open Notices | registrar | ✅ pass | 0.39s |  |
| Registrar can open Administration | registrar | ✅ pass | 0.56s |  |
| Registrar can open user approvals | registrar | ✅ pass | 0.23s |  |

## Admin — 4/4 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Admin panel (Filament) loads | admin | ✅ pass | 1.84s |  |
| Admin users resource lists | admin | ✅ pass | 0.78s |  |
| Admin clubs resource lists | admin | ✅ pass | 0.59s |  |
| Admin events resource lists | admin | ✅ pass | 0.55s |  |

## Access control — 2/2 passed

| Test | Role | Status | Time | Notes |
|------|------|--------|------|-------|
| Student is blocked from the admin panel | student | ✅ pass | 1.16s |  |
| Student is blocked from user approvals | student | ✅ pass | 0.15s |  |

## What went wrong

Nothing — all tests passed. No application errors, no server 500s, no role/permission leaks, and no severe browser-console errors were surfaced during the run.

## Notes

- Tests ran against a local SQLite test copy of the app, not the production MySQL database, so no live data was modified.
- A few tests intentionally create data (a post, a chat message) to exercise write paths; these live only in the test copy.
- `selenium` (PyPI) is blocked by the sandbox egress policy, so the suite talks to chromedriver directly over the W3C WebDriver HTTP protocol — the same wire protocol the Selenium library uses.
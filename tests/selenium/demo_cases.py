#!/usr/bin/env python3
"""
UIU CampusConnect — a short, visible Selenium run for demos.

Runs a handful of test cases from run_local.py (the full suite), one per
feature area, with a pause after every page so people can follow along:

  guest   sign-in form renders · feed redirects logged-out visitors
  student signs in · feed loads · admin panel is blocked · CGPA math ·
          create a post, see it, delete it
  admin   signs in · admin panel opens

Accounts come from accounts.json (same file as run_local.py); the first
"student" and the first "admin" entries are used.

  CC_DEMO_PACE    seconds to pause after each page (default 1.0)
  CC_DRIVER       path to chromedriver (optional; Selenium finds one itself)
  CC_CHROME_ARGS  extra Chrome switches, space separated (optional)
Everything else (CC_BASE_URL, CC_BROWSER, CC_BINARY) as in run_local.py.
"""
import os, sys, time, json
import run_local as S
from selenium import webdriver
from selenium.webdriver.chrome.service import Service

PACE = float(os.environ.get("CC_DEMO_PACE", "1.0"))

# pause after every page load so the browser can be followed on screen
_goto = S.App.goto
def _slow_goto(self, path):
    _goto(self, path)
    time.sleep(PACE)
S.App.goto = _slow_goto


def pick(tests, title):
    for t in tests:
        if t[0] == title:
            return t[-1]
    raise SystemExit(f"test not found in run_local.py: {title}")


def build_driver():
    opts = S.Options()
    opts.add_argument("--window-size=1440,950")
    opts.add_argument("--disable-search-engine-choice-screen")
    for a in (os.environ.get("CC_CHROME_ARGS") or "").split():
        opts.add_argument(a)
    if os.environ.get("CC_BINARY"):
        opts.binary_location = os.environ["CC_BINARY"]
    drv = os.environ.get("CC_DRIVER")
    return webdriver.Chrome(options=opts, service=Service(drv) if drv else None)


def main():
    accts = S.load_accounts()
    student = next((a for a in accts if S.role_of(a) == "student"), None)
    admin = next((a for a in accts if S.role_of(a) == "admin"), None)
    print(f"Selenium demo run against {S.BASE}\n", flush=True)
    driver = build_driver()
    driver.set_window_position(0, 0)
    app = S.App(driver)
    t0 = time.time()

    def run(role, cat, title, fn):
        S.run_one(app, role, cat, title, fn)
        sys.stdout.flush()
        time.sleep(PACE * 0.6)

    print("== Guest (logged out) ==", flush=True)
    for title in ("Sign-in page renders the login form",
                  "Feed redirects logged-out visitors to sign-in"):
        run("guest", "Public", title, pick(S.GUEST_TESTS, title))

    if student:
        print("\n== Student ==", flush=True)
        app.login(student["email"], student["password"]); time.sleep(PACE)
        S.record(driver, "Student", "Login", "Sign in as a student", "pass", "", 0.0)
        sys.stdout.flush()
        run("Student", "Module pages", "Feed", pick(S.AUTH_TESTS, "Feed"))
        run("Student", "Access control", "Admin panel is blocked for students",
            S.gated_test("Admin panel (Filament)", "/admin", {"admin"}, "student", "Student"))
        title = "CGPA — A(3cr) + B(3cr) = 3.50 over 6 cr"
        run("Student", "Student tools", title, pick(S.TOOL_TESTS, title))
        title = "Create a post, verify it appears, delete it, verify it's gone"
        run("Student", "Interactions (write)", title, pick(S.WRITE_TESTS, title))

    if admin:
        print("\n== Admin ==", flush=True)
        app.logout()
        app.login(admin["email"], admin["password"]); time.sleep(PACE)
        S.record(driver, "Admin", "Login", "Sign in as an admin", "pass", "", 0.0)
        sys.stdout.flush()
        run("Admin", "Access control", "Admin panel opens for admins",
            S.gated_test("Admin panel (Filament)", "/admin", {"admin"}, "admin", "Admin"))

    driver.quit()
    res = S.RESULTS
    passed = sum(r["status"] == "pass" for r in res)
    print(f"\n{passed}/{len(res)} passed in {time.time() - t0:.1f}s", flush=True)


if __name__ == "__main__":
    main()

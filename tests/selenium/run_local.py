#!/usr/bin/env python3
"""
UIU CampusConnect — comprehensive local Selenium suite (run on your Mac).

WHAT IT DOES
  * GUEST checks (logged out): landing, sign-in form, auth guards.
  * For EACH account you list (multi-role): logs in and then
      - loads every core module page,
      - CRAWLS your live data and opens every club / event / marketplace item /
        lost-&-found item / group / post / profile it can find,
      - checks role-gated pages respond without a server error,
      - exercises the CGPA / retake / tuition tools with exact expected math,
      - (optional) runs interaction tests that create + clean up data.
  * Writes results_local/report.html with a screenshot for every test.

SAFE BY DEFAULT: everything is READ-ONLY unless you pass CC_WRITE=1. Read-only
tests never change your database.

QUICK START
  1.  Start your app so it's at http://127.0.0.1:8000  (php artisan serve).
  2.  Copy accounts.example.json -> accounts.json and fill in your logins.
      (No file? It falls back to asking for one login at the prompt.)
  3.  CC_BROWSER=brave python3 run_local.py
  4.  Open results_local/report.html

ENV OPTIONS
  CC_BASE_URL     app URL (default http://127.0.0.1:8000)
  CC_BROWSER      brave | chrome           CC_BINARY  full path to the browser
  CC_HEADLESS=1   run without a visible window
  CC_WRITE=1      also run write/interaction tests (create + delete data)
  CC_MAX_PER_CAT  max crawled pages per category per role (default 30)
"""
import os, re, sys, time, json, html, base64, getpass, datetime, traceback

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.common.exceptions import WebDriverException

HERE = os.path.dirname(os.path.abspath(__file__))
BASE = os.environ.get("CC_BASE_URL", "http://127.0.0.1:8000").rstrip("/")
HEADLESS = os.environ.get("CC_HEADLESS") == "1"
WRITE = os.environ.get("CC_WRITE") == "1"
MAX_PER_CAT = int(os.environ.get("CC_MAX_PER_CAT", "30"))
OUT = os.path.join(HERE, "results_local")
SHOTS = os.path.join(OUT, "shots")
os.makedirs(SHOTS, exist_ok=True)

ERROR_MARKERS = ["Whoops, something went wrong", "Server Error", "SQLSTATE",
                 "Symfony\\Component", "Fatal error", "Parse error",
                 "Call to undefined", "Undefined variable", "Class \"",
                 "ParseError", "TypeError :", "RuntimeException"]

# UIU grade points, mirrored from config/grading.php (for exact-math checks)
GP = {"A": 4.00, "A-": 3.67, "B+": 3.33, "B": 3.00, "B-": 2.67, "C+": 2.33,
      "C": 2.00, "C-": 1.67, "D+": 1.33, "D": 1.00, "F": 0.00}


class SkipTest(Exception):
    pass


class Fail(Exception):
    pass


def ok(cond, msg):
    if not cond:
        raise Fail(msg)


def path_of(href):
    if not href:
        return None
    href = href.split("#")[0]
    if href.startswith(BASE):
        href = href[len(BASE):]
    if href.startswith("http://") or href.startswith("https://"):
        return None  # external
    return href or "/"


class App:
    def __init__(self, driver):
        self.d = driver
        self.logged_in = False

    def goto(self, path):
        self.d.get(BASE + path)
        WebDriverWait(self.d, 20).until(
            lambda x: x.execute_script("return document.readyState") == "complete")
        self.last_status, self.last_msg = self.page_status()

    def page_status(self):
        """HTTP status of the page now showing, plus Laravel's error message.
        Uses the Navigation Timing API; falls back to reading Laravel's
        '403 | Forbidden' style error page."""
        code = self.d.execute_script(
            "try{let n=performance.getEntriesByType('navigation')[0];"
            "return (n&&n.responseStatus)||0}catch(e){return 0}") or 0
        text = self.body().strip()
        msg = ""
        m = re.match(r"^\s*([45]\d\d)\s*[|\n]+\s*(.*)$", text[:400], re.S)
        if m and len(text) < 400:
            if not code or code < 400:
                code = int(m.group(1))
            msg = " ".join(m.group(2).split())[:160]
        return int(code or 200), msg

    def ok_status(self):
        """Fail unless the page itself loaded (2xx/3xx)."""
        c, m = getattr(self, "last_status", 200), getattr(self, "last_msg", "")
        ok(c < 400, f"HTTP {c}" + (f" — {m}" if m else "") + f" ({self.d.current_url})")

    def body(self):
        return self.d.execute_script(
            "return document.body ? document.body.innerText : ''") or ""

    def exists(self, css):
        return len(self.d.find_elements(By.CSS_SELECTOR, css)) > 0

    def wait_text(self, s, timeout=6):
        end, s = time.time() + timeout, s.lower()
        while time.time() < end:
            if s in self.body().lower():
                return True
            time.sleep(0.2)
        return False

    def no_error_page(self):
        src = self.d.page_source
        for m in ERROR_MARKERS:
            ok(m not in src, f"error page detected (marker: {m!r})")

    def not_login(self):
        ok("/signin" not in self.d.current_url and "/login" not in self.d.current_url,
           f"redirected to sign-in ({self.d.current_url})")

    def console_errors(self):
        try:
            logs = self.d.get_log("browser")
        except Exception:
            return []
        out = []
        for e in logs:
            if e.get("level") == "SEVERE" and "favicon" not in e.get("message", ""):
                out.append(e.get("message", "")[:300])
        return out

    def hrefs(self, regex):
        # Links are usually absolute (http://host/clubs/x); match on the PATH,
        # same-origin only.
        return self.d.execute_script(
            "let re=new RegExp(arguments[0]);"
            "return [...new Set([...document.querySelectorAll('a[href]')].map(a=>{"
            "  try{let u=new URL(a.getAttribute('href'),location.href);"
            "      return u.origin===location.origin?u.pathname:null}catch(e){return null}"
            "}).filter(p=>p&&re.test(p)))];", regex)

    def login(self, email, password):
        self.logged_in = False
        self.goto("/signin")
        fields = self.d.find_elements(By.CSS_SELECTOR,
                                      "input[type=email], input[name=email]")
        if not fields:            # still logged in as someone else: log out, retry
            self.logout()
            self.goto("/signin")
            fields = self.d.find_elements(By.CSS_SELECTOR,
                                          "input[type=email], input[name=email]")
        pw = self.d.find_elements(By.CSS_SELECTOR, "input[type=password]")
        ok(fields and pw, "could not find email/password fields on /signin")
        fields[0].clear(); fields[0].send_keys(email)
        pw[0].clear(); pw[0].send_keys(password)
        submitted = False
        try:
            form = pw[0].find_element(By.XPATH, "./ancestor::form")
            btns = form.find_elements(
                By.CSS_SELECTOR, "button[type=submit], input[type=submit], button")
            if btns:
                btns[-1].click(); submitted = True
        except Exception:
            pass
        if not submitted:
            pw[0].send_keys(Keys.RETURN)
        WebDriverWait(self.d, 20).until(
            lambda x: x.execute_script("return document.readyState") == "complete")
        time.sleep(1.2)
        if "/signin" in self.d.current_url or "/login" in self.d.current_url:
            body = self.body()
            m = re.search(r"(these credentials do not match[^\n]*|"
                          r"the provided credentials[^\n]*|[^\n]*must be a valid email[^\n]*)",
                          body, re.I)
            reason = m.group(1).strip()[:120] if m else \
                "form submitted but stayed on sign-in (check email/password)"
            raise Fail(f"login failed — {reason}")
        self.logged_in = True

    def is_guest(self):
        """True when the server treats this browser as logged out."""
        self.goto("/feed")
        cur = self.d.current_url
        return "/signin" in cur or "/login" in cur

    def logout(self):
        """Log out through the site's own Logout (POST /logout), which ends
        the session on the server. Deleting cookies alone is not enough: the
        feed polls in the background and can hand the old session cookie back."""
        js = (
            "let f=[...document.querySelectorAll('form')]"
            ".find(x=>(x.getAttribute('action')||'').endsWith('/logout'));"
            "if(f){f.submit();return 'form'}"
            "let t=document.querySelector('meta[name=csrf-token]');"
            "if(t){let g=document.createElement('form');g.method='POST';g.action='/logout';"
            "let i=document.createElement('input');i.type='hidden';i.name='_token';"
            "i.value=t.content;g.appendChild(i);document.body.appendChild(g);g.submit();return 'built'}"
            "return 'none'")
        for _ in range(3):
            try:
                if self.is_guest():
                    break
                self.d.execute_script(js)
                time.sleep(1.2)
                self.d.delete_all_cookies()
            except Exception:
                time.sleep(0.5)
        self.logged_in = False


# ---------------------------------------------------------------- static tests
GUEST_TESTS, AUTH_TESTS, TOOL_TESTS, WRITE_TESTS = [], [], [], []


def guest(title):
    def d(fn): GUEST_TESTS.append((title, fn)); return fn
    return d


def auth(title, category):
    def d(fn): AUTH_TESTS.append((title, category, fn)); return fn
    return d


def tool(title):
    def d(fn): TOOL_TESTS.append((title, fn)); return fn
    return d


def write(title):
    def d(fn): WRITE_TESTS.append((title, fn)); return fn
    return d


# ===== GUEST =====
@guest("Landing page loads with hero / 3D scene")
def _(a):
    a.goto("/"); a.no_error_page()
    ok("campusconnect" in a.d.title.lower() or a.wait_text("UIU"),
       f"unexpected landing (title={a.d.title!r})")


@guest("Sign-in page renders the login form")
def _(a):
    a.goto("/signin"); a.no_error_page()
    ok(a.exists("input[type=email], input[name=email]"), "no email field")
    ok(a.exists("input[type=password]"), "no password field")


@guest("Feed redirects logged-out visitors to sign-in")
def _(a):
    a.goto("/feed")
    ok("/signin" in a.d.current_url or "/login" in a.d.current_url,
       "guest not redirected away from /feed")


@guest("Forgot-password page renders")
def _(a):
    a.goto("/forgot-password"); a.no_error_page()
    # CampusConnect asks for an identifier (email or student ID), not a bare email.
    ok(a.exists("[name=identifier], input[type=email], input[name=email]"),
       "no identifier/email field on the reset-request page")


@guest("Protected module redirects guest (clubs)")
def _(a):
    a.goto("/clubs")
    ok("/signin" in a.d.current_url or "/login" in a.d.current_url,
       "guest not redirected from /clubs")


# ===== AUTH: module pages (all roles should load these) =====
def _page(path, needle=None):
    def fn(a):
        a.goto(path); a.no_error_page(); a.not_login(); a.ok_status()
        if needle:
            ok(a.wait_text(needle) or a.exists("main"), f"expected {needle!r} on {path}")
    return fn


for _t, _p, _n in [
    ("Feed", "/feed", None), ("Clubs index", "/clubs", "Club"),
    ("Events index", "/events", "Event"),
    ("Marketplace index", "/marketplace", "arket"),
    ("Lost & Found index", "/lost-found", "ost"),
    ("Groups index", "/groups", "roup"),
    ("Assistant", "/assistant", "ssistant"),
    ("Academic calendar", "/calendar", "alendar"),
    ("Deadlines", "/deadlines", "eadline"),
    ("Notices board", "/notices", "otice"),
    ("Network", "/network", "etwork"),
    ("Messages", "/messages", "essage"),
    ("Notifications", "/notifications", "otification"),
    ("Search results", "/search?q=uiu", None),
    ("Own profile (/me)", "/me", None),
    ("Profile settings", "/settings/profile", "rofile"),
    ("Password settings", "/settings/password", "assword"),
]:
    AUTH_TESTS.append((_t, "Module pages", _page(_p, _n)))


# ===== AUTH: role-gated pages — checked against who SHOULD get in =====
# Mirrors the rules in your code:
#   isAdmin()                      -> /admin, /moderation, /user-approvals, /password-requests
#   faculty, officials, admins (students blocked)            -> /administration
#   academic profile is "for faculty and university staff" -> /settings/academic
ROLES = ("student", "faculty", "registrar", "admin")
GATED = [
    ("Admin panel (Filament)",  "/admin",             {"admin"}),
    ("Moderation queue",        "/moderation",        {"admin"}),
    ("User approvals",          "/user-approvals",    {"admin"}),
    ("Password reset requests", "/password-requests", {"admin"}),
    ("Administration",          "/administration",    {"faculty", "registrar", "admin"}),
    ("Academic settings",       "/settings/academic", {"faculty", "registrar"}),
    ("Connection requests",     "/connect-requests",  set(ROLES)),
]
ACCESS = {}   # path -> {account label: {...}}  (drives the access matrix)


def role_of(acct):
    r = (acct.get("role") or "").lower().strip()
    if r == "official":
        r = "registrar"
    if r in ROLES:
        return r
    text = f"{acct.get('label','')} {acct.get('email','')}".lower()
    for key, role in [("admin", "admin"), ("registrar", "registrar"),
                      ("official", "registrar"), ("faculty", "faculty"),
                      ("teacher", "faculty"), ("student", "student")]:
        if key in text:
            return role
    return None


def gated_test(title, path, allowed, role, label):
    def fn(a):
        a.goto(path)
        a.no_error_page()                      # a crash is always a failure
        code, msg = a.last_status, a.last_msg
        cur = path_of(a.d.current_url) or ""
        got_in = code < 400 and not cur.startswith(("/signin", "/login")) \
            and (cur.split("?")[0].rstrip("/") == path.rstrip("/")
                 or cur.startswith(path.rstrip("/") + "/"))
        how = f"allowed (HTTP {code})" if got_in else \
            (f"denied — HTTP {code}" + (f": {msg}" if msg else "")) if code >= 400 else \
            f"denied — redirected to {cur}"
        expected = None if role is None else (role in allowed)
        ACCESS.setdefault(path, {})[label] = {
            "title": title, "allowed": got_in, "expected": expected,
            "code": code, "how": how}
        if expected is None:
            raise SkipTest(f"{how} — add \"role\" to this account in accounts.json "
                           "to check it against the rules")
        if got_in and not expected:
            raise Fail(f"ACCESS LEAK: a {role} can open {path} but shouldn't. {how}")
        if not got_in and expected:
            raise Fail(f"a {role} should be able to open {path} but was {how}")
        # correct either way: record what happened so the report shows it
        a.note = f"{'Allowed' if got_in else 'Correctly blocked'} for {role} — {how}"
    return fn


# ===== TOOLS (student feed rail) — exact math =====
def open_tools(a):
    a.goto("/feed"); time.sleep(0.4)
    clicked = a.d.execute_script(
        "let t=[...document.querySelectorAll('.rail-tile')]"
        ".find(b=>b.innerText.trim().startsWith('Tools'));"
        "if(t){t.click();return true}return false;")
    if not clicked:
        raise SkipTest("no student Tools tile for this account")
    time.sleep(0.7)


def tools_data(a):
    """Return the Alpine component data handle presence + a setter/getter."""
    return a.d.execute_script(
        "let el=[...document.querySelectorAll('[x-data]')]"
        ".find(e=>(e.getAttribute('x-data')||'').trim().startsWith('ccTools'));"
        "return el && window.Alpine ? true : false;")


def set_courses_read(a, courses):
    a.d.execute_script(
        "let el=[...document.querySelectorAll('[x-data]')]"
        ".find(e=>(e.getAttribute('x-data')||'').trim().startsWith('ccTools'));"
        "let d=window.Alpine.$data(el); d.tool='cgpa'; d.courses=arguments[0];", courses)
    time.sleep(0.35)
    gpa = a.d.find_elements(By.CSS_SELECTOR, "[x-text='termGpa']")[0].text.strip()
    cr = a.d.find_elements(By.CSS_SELECTOR, "[x-text='termCredits']")[0].text.strip()
    return gpa, cr


@tool("CGPA — default courses (A / A- / B+ over 9 cr) = 3.67")
def _(a):
    open_tools(a)
    ok(a.exists("[x-text='termGpa']"), "CGPA panel did not open")
    gpa = a.d.find_elements(By.CSS_SELECTOR, "[x-text='termGpa']")[0].text.strip()
    cr = a.d.find_elements(By.CSS_SELECTOR, "[x-text='termCredits']")[0].text.strip()
    ok(gpa == "3.67" and cr == "9", f"expected 3.67 / 9, got {gpa} / {cr}")


@tool("CGPA — A(3cr) + F(3cr) = 2.00 over 6 cr")
def _(a):
    open_tools(a)
    ok(tools_data(a), "Alpine ccTools not reachable")
    gpa, cr = set_courses_read(a, [{"credit": 3, "grade": "A"}, {"credit": 3, "grade": "F"}])
    ok(gpa == "2.00" and cr == "6", f"expected 2.00 / 6, got {gpa} / {cr}")


@tool("CGPA — A(3cr) + B(3cr) = 3.50 over 6 cr")
def _(a):
    open_tools(a)
    ok(tools_data(a), "Alpine ccTools not reachable")
    gpa, cr = set_courses_read(a, [{"credit": 3, "grade": "A"}, {"credit": 3, "grade": "B"}])
    ok(gpa == "3.50" and cr == "6", f"expected 3.50 / 6, got {gpa} / {cr}")


@tool("CGPA — weighted A(4cr) + C(1cr) = 3.60 over 5 cr")
def _(a):
    open_tools(a)
    ok(tools_data(a), "Alpine ccTools not reachable")
    gpa, cr = set_courses_read(a, [{"credit": 4, "grade": "A"}, {"credit": 1, "grade": "C"}])
    # (16 + 2) / 5 = 3.60
    ok(gpa == "3.60" and cr == "5", f"expected 3.60 / 5, got {gpa} / {cr}")


@tool("Tuition estimator math (12cr @ 6500 + 1000, 50% waiver)")
def _(a):
    open_tools(a)
    ok(tools_data(a), "Alpine ccTools not reachable")
    vals = a.d.execute_script(
        "let el=[...document.querySelectorAll('[x-data]')]"
        ".find(e=>(e.getAttribute('x-data')||'').trim().startsWith('ccTools'));"
        "let d=window.Alpine.$data(el); d.tool='tuition';"
        "d.tCredits=12; d.tPerCredit=6500; d.tOther=1000; d.tWaiver=0;"
        "let base={tuition:d.tuition, net0:d.netCost};"
        "d.tWaiver=50; base.waiver=d.waiverAmount; base.net50=d.netCost; return base;")
    ok(abs(vals["tuition"] - 78000) < 0.5, f"tuition should be 78000, got {vals['tuition']}")
    ok(abs(vals["net0"] - 79000) < 0.5, f"net (no waiver) should be 79000, got {vals['net0']}")
    ok(abs(vals["waiver"] - 39000) < 0.5, f"50% waiver should be 39000, got {vals['waiver']}")
    ok(abs(vals["net50"] - 40000) < 0.5, f"net (50% waiver) should be 40000, got {vals['net50']}")


@tool("Retake estimator moves CGPA in the right direction")
def _(a):
    open_tools(a)
    ok(tools_data(a), "Alpine ccTools not reachable")
    res = a.d.execute_script(
        "let el=[...document.querySelectorAll('[x-data]')]"
        ".find(e=>(e.getAttribute('x-data')||'').trim().startsWith('ccTools'));"
        "let d=window.Alpine.$data(el); d.tool='retake';"
        "d.rCgpa=3.00; d.rCredits=30; d.rCourseCredit=3;"
        "d.rOld='F'; d.rNew='A'; let up=d.retakeCgpa;"
        "d.rOld='A'; d.rNew='F'; let down=d.retakeCgpa;"
        "return {up:parseFloat(up), down:parseFloat(down)};")
    ok(res["up"] >= 3.00, f"improving F→A should not lower CGPA (got {res['up']})")
    ok(res["down"] <= 3.00, f"worsening A→F should not raise CGPA (got {res['down']})")


# ===== WRITE (opt-in, self-cleaning, verified) =====
# Every write test is scoped to ONE post card (by data-post-id) and verifies
# its own cleanup by reloading, so it can never touch a different post.

def _first_card_id(a):
    return a.d.execute_script(
        "let c=document.querySelector('[data-post-id]');"
        "return c?c.getAttribute('data-post-id'):null;")


@write("Reaction toggles on, then back off (same post)")
def _(a):
    a.goto("/feed"); time.sleep(0.6)
    pid = _first_card_id(a)
    if not pid:
        raise SkipTest("no posts on the feed")
    js_state = ("let c=document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');"
                "let r=c&&c.querySelector('[x-data^=\"ccReactions\"]');"
                "return r&&window.Alpine?!!window.Alpine.$data(r).active:null;")
    js_click = ("let c=document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');"
                "let b=c&&c.querySelector('.reaction-btn');if(b){b.click();return true}return false;")
    before = a.d.execute_script(js_state, pid)
    if before is None:
        raise SkipTest("reaction control not found on the post card")
    ok(a.d.execute_script(js_click, pid), "could not click the reaction button")
    time.sleep(1.0)
    mid = a.d.execute_script(js_state, pid)
    ok(mid != before, f"reaction state did not change (before={before}, after={mid})")
    a.d.execute_script(js_click, pid); time.sleep(1.0)
    # verify the server kept the original state after a reload
    a.goto("/feed"); time.sleep(0.6)
    after = a.d.execute_script(js_state, pid)
    ok(after == before, f"reaction not restored after reload (was {before}, now {after})")


@write("Save post toggles on, then back off (same post)")
def _(a):
    a.goto("/feed"); time.sleep(0.6)
    pid = _first_card_id(a)
    if not pid:
        raise SkipTest("no posts on the feed")
    js_state = ("let c=document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');"
                "return c&&window.Alpine?!!window.Alpine.$data(c).saved:null;")
    js_click = ("let c=document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');"
                "let b=c&&[...c.querySelectorAll('button')].find(x=>/save/i.test(x.innerText));"
                "if(b){b.click();return true}return false;")
    before = a.d.execute_script(js_state, pid)
    if before is None:
        raise SkipTest("save control not found")
    ok(a.d.execute_script(js_click, pid), "could not click Save post")
    time.sleep(1.0)
    ok(a.d.execute_script(js_state, pid) != before, "saved state did not change")
    a.d.execute_script(js_click, pid); time.sleep(1.0)
    a.goto("/feed"); time.sleep(0.6)
    after = a.d.execute_script(js_state, pid)
    ok(after == before, f"save not restored after reload (was {before}, now {after})")


@write("Create a post, verify it appears, delete it, verify it's gone")
def _(a):
    a.goto("/feed")
    marker = "E2E autotest post " + datetime.datetime.now().strftime("%H%M%S")
    a.d.execute_script(
        "let el=document.querySelector('[x-data^=\"ccComposer\"]');"
        "if(el&&window.Alpine){try{window.Alpine.$data(el).open=true}catch(e){}}")
    time.sleep(0.5)
    ta = next((t for t in a.d.find_elements(By.CSS_SELECTOR, "textarea") if t.is_displayed()), None)
    if not ta:
        raise SkipTest("composer textarea not reachable")
    ta.click(); ta.send_keys(marker); time.sleep(0.3)
    posted = a.d.execute_script(
        "let b=[...document.querySelectorAll('button')]"
        ".find(x=>['post','share','publish'].includes(x.innerText.trim().toLowerCase()));"
        "if(b){b.click();return true}return false;")
    ok(posted, "no Post button found")
    ok(a.wait_text(marker, 10), "created post did not appear")
    # locate THIS post's card by its text, then read its id
    a.goto("/feed"); time.sleep(0.6)
    pid = a.d.execute_script(
        "let c=[...document.querySelectorAll('[data-post-id]')]"
        ".find(x=>x.innerText.includes(arguments[0]));"
        "return c?c.getAttribute('data-post-id'):null;", marker)
    ok(pid, "created post not found as a post card after reload")
    # submit that card's own DELETE form
    submitted = a.d.execute_script(
        "let c=document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');"
        "let f=c&&[...c.querySelectorAll('form')]"
        ".find(x=>(x.getAttribute('action')||'').endsWith('/posts/'+arguments[0])"
        " && x.querySelector('input[name=_method][value=DELETE]'));"
        "if(f){f.submit();return true}return false;", pid)
    if not submitted:
        raise Fail(f"post #{pid} created, but its delete form was not found — "
                   f"delete the post '{marker}' manually")
    time.sleep(1.5)
    a.goto("/feed"); time.sleep(0.6)
    still = a.d.execute_script(
        "return !!document.querySelector('[data-post-id=\"'+arguments[0]+'\"]');", pid)
    ok(not still, f"post #{pid} still present after delete — remove '{marker}' manually")


# ---------------------------------------------------------------- crawl
CRAWL = [
    ("Clubs", "/clubs", r"^/clubs/[^/]+$", r"^/clubs$"),
    ("Events", "/events", r"^/events/\d+", r"^/events$"),
    ("Marketplace", "/marketplace", r"^/marketplace/\d+", r"^/marketplace$"),
    ("Lost & Found", "/lost-found", r"^/lost-found/\d+", r"^/lost-found$"),
    ("Groups", "/groups", r"^/groups/[^/]+$", r"^/groups$"),
    ("Posts", "/feed", r"^/posts/\d+", None),
    ("Profiles", "/network", r"^/profile/[^/]+$", None),
]


def discover(a):
    found = {}
    for label, index, item_re, skip_re in CRAWL:
        try:
            a.goto(index)
            time.sleep(0.4)
            urls = []
            for h in a.hrefs(item_re):
                p = path_of(h)
                if not p:
                    continue
                if skip_re and re.match(skip_re, p):
                    continue
                if p not in urls:
                    urls.append(p)
            # posts: also scan feed profiles
            found[label] = urls[:MAX_PER_CAT]
        except Exception:
            found[label] = []
    return found


# ---------------------------------------------------------------- runner
def load_accounts():
    p = os.path.join(HERE, "accounts.json")
    if os.path.exists(p):
        try:
            data = json.load(open(p))
            accts = [x for x in data if x.get("email") and x.get("password")]
            if accts:
                return accts
            print("  accounts.json has no usable entries; falling back to prompt.")
        except Exception as e:
            print(f"  could not read accounts.json ({e}); falling back to prompt.")
    # fallback: single prompt
    env_e, env_p = os.environ.get("CC_EMAIL"), os.environ.get("CC_PASSWORD")
    if env_e and env_p:
        return [{"label": env_e, "email": env_e, "password": env_p}]
    print("\nNo accounts.json found. Enter one login to test authenticated pages,")
    print("or press Enter to run only the public pages.\n")
    e = input("  Email (blank to skip): ").strip()
    if not e:
        return []
    pw = getpass.getpass("  Password (hidden): ")
    return [{"label": e, "email": e, "password": pw}]


def build_driver():
    opts = Options()
    if HEADLESS:
        opts.add_argument("--headless=new")
    opts.add_argument("--window-size=1440,950")
    opts.add_argument("--disable-search-engine-choice-screen")
    opts.add_experimental_option("excludeSwitches", ["enable-automation"])
    opts.set_capability("goog:loggingPrefs", {"browser": "ALL"})
    binary = os.environ.get("CC_BINARY")
    if not binary:
        choice = (os.environ.get("CC_BROWSER") or "").lower()
        chrome = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
        brave = "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"
        binary = brave if choice == "brave" else chrome if choice == "chrome" \
            else (chrome if os.path.exists(chrome) else brave if os.path.exists(brave) else None)
    if binary and os.path.exists(binary):
        opts.binary_location = binary
        print(f"  using browser: {binary}")
    return webdriver.Chrome(options=opts)


RESULTS = []


def record(driver, role, category, title, status, detail, dur):
    tid = re.sub(r"[^a-z0-9]+", "-", f"{role}-{title}".lower()).strip("-")[:60]
    shot = os.path.join(SHOTS, tid + ".png")
    try:
        driver.save_screenshot(shot)
    except Exception:
        shot = None
    RESULTS.append({"id": tid, "role": role, "category": category, "title": title,
                    "status": status, "detail": detail, "duration": dur,
                    "shot": os.path.basename(shot) if shot else None})
    icon = {"pass": "PASS", "fail": "FAIL", "error": "ERR ", "skip": "skip"}[status]
    print(f"    [{icon}] {title}  ({dur}s)"
          + (f"\n           -> {detail.splitlines()[0]}" if detail else ""))


def run_one(app, role, category, title, fn):
    t0 = time.time()
    status, detail = "pass", ""
    app.note = ""
    try:
        fn(app)
        detail = app.note
    except SkipTest as e:
        status, detail = "skip", str(e)
    except Fail as e:
        status, detail = "fail", str(e)
    except Exception as e:
        status, detail = "error", f"{e}\n{traceback.format_exc()[-400:]}"
    record(app.d, role, category, title, status, detail, round(time.time() - t0, 2))


def main():
    accounts = load_accounts()
    print(f"\nTarget: {BASE}")
    print(f"Accounts: {len(accounts)}   Write tests: {'ON' if WRITE else 'off'}   "
          f"Max crawl/category: {MAX_PER_CAT}\n")
    started = datetime.datetime.now()
    try:
        driver = build_driver()
    except WebDriverException as e:
        print("Could not start the browser via Selenium:\n ", str(e).splitlines()[0])
        print("Make sure Chrome or Brave is installed (Selenium fetches the driver).")
        sys.exit(1)
    app = App(driver)

    # ---- GUEST (once, logged out) ----
    print("== Guest (logged out) ==")
    app.logout()
    for title, fn in GUEST_TESTS:
        run_one(app, "guest", "Public", title, fn)

    # ---- per account ----
    for acct in accounts:
        label = acct.get("label") or acct["email"]
        print(f"\n== {label} ==")
        app.logout()  # sign the previous account out, or /signin just redirects
        try:
            app.login(acct["email"], acct["password"])
            print(f"    logged in as {acct['email']}")
        except Fail as e:
            record(driver, label, "Login", f"Sign in as {label}", "fail", str(e), 0.0)
            continue
        record(driver, label, "Login", f"Sign in as {label}", "pass", "", 0.0)

        for title, category, fn in AUTH_TESTS:
            run_one(app, label, category, title, fn)

        role = role_of(acct)
        shown = role or 'unknown - add "role" to this account in accounts.json'
        print(f"    role for access checks: {shown}")
        for title, path, allowed in GATED:
            run_one(app, label, "Access control", title,
                    gated_test(title, path, allowed, role, label))

        for title, fn in TOOL_TESTS:
            run_one(app, label, "Student tools", title, fn)

        # crawl live data — a record that is linked but returns 403/404 fails
        found = discover(app)
        for cat, urls in found.items():
            for u in urls:
                run_one(app, label, f"Detail pages: {cat}",
                        f"{cat[:-1] if cat.endswith('s') else cat} — {u}",
                        (lambda url: (lambda a: (a.goto(url), a.no_error_page(),
                                                 a.not_login(), a.ok_status())))(u))

        if WRITE:
            print("    -- write/interaction tests (create + clean up data) --")
            for title, fn in WRITE_TESTS:
                run_one(app, label, "Interactions (write)", title, fn)

    driver.quit()
    dur = round((datetime.datetime.now() - started).total_seconds(), 1)
    summary = {
        "generated_at": datetime.datetime.now().isoformat(timespec="seconds"),
        "duration_s": dur, "base_url": BASE, "write_mode": WRITE,
        "accounts": [a.get("label") or a["email"] for a in accounts],
        "total": len(RESULTS),
        "passed": sum(r["status"] == "pass" for r in RESULTS),
        "failed": sum(r["status"] == "fail" for r in RESULTS),
        "errored": sum(r["status"] == "error" for r in RESULTS),
        "skipped": sum(r["status"] == "skip" for r in RESULTS),
        "access": ACCESS,
        "results": RESULTS,
    }
    json.dump(summary, open(os.path.join(OUT, "results.json"), "w"), indent=2)
    write_html(summary)
    print(f"\n{summary['passed']}/{summary['total']} passed, {summary['failed']} failed, "
          f"{summary['errored']} errored, {summary['skipped']} skipped in {dur}s")
    print(f"Report: {os.path.join(OUT, 'report.html')}")


def write_html(s):
    COL = {"pass": "#16a34a", "fail": "#dc2626", "error": "#b91c1c", "skip": "#a16207"}
    # group by role, then category
    roles = {}
    for r in s["results"]:
        roles.setdefault(r["role"], {}).setdefault(r["category"], []).append(r)
    blocks = []
    for role, cats in roles.items():
        rp = sum(i["status"] == "pass" for c in cats.values() for i in c)
        rt = sum(len(c) for c in cats.values())
        blocks.append(f'<h2 class="role">{html.escape(str(role))} '
                      f'<span class="cnt">{rp}/{rt}</span></h2>')
        for cat, items in cats.items():
            p = sum(i["status"] == "pass" for i in items)
            blocks.append(f'<h3>{html.escape(cat)} <span class="cnt">{p}/{len(items)}</span></h3>')
            for r in items:
                uri = None
                if r["shot"]:
                    fp = os.path.join(SHOTS, r["shot"])
                    if os.path.exists(fp):
                        uri = "data:image/png;base64," + base64.b64encode(open(fp, "rb").read()).decode()
                shot = f'<div class="shot"><img loading="lazy" src="{uri}"></div>' if uri else ""
                det = f'<div class="detail {r["status"]}">{html.escape(r["detail"])}</div>' if r["detail"] else ""
                blocks.append(f'''<div class="card"><div class="hd">
<span class="badge" style="background:{COL[r['status']]}">{r['status'].upper()}</span>
<span class="ttl">{html.escape(r['title'])}</span>
<span class="meta">{r['duration']}s</span></div>{det}{shot}</div>''')
    # ---- access matrix: who could open what, and was that right? ----
    matrix = ""
    acc = s.get("access") or {}
    if acc:
        labels = list(dict.fromkeys(l for per in acc.values() for l in per))
        head = "".join(f"<th>{html.escape(l)}</th>" for l in labels)
        rows_m = []
        for path, per in acc.items():
            title = next(iter(per.values()))["title"]
            cells = []
            for l in labels:
                c = per.get(l)
                if not c:
                    cells.append("<td>—</td>"); continue
                word = "Allowed" if c["allowed"] else f"Blocked ({c['code']})" \
                    if c["code"] >= 400 else "Blocked (redirect)"
                cls = "unk" if c["expected"] is None else \
                    "good" if c["expected"] == c["allowed"] else "bad"
                mark = {"good": "✓", "bad": "✗ LEAK" if c["allowed"] else "✗", "unk": "?"}[cls]
                cells.append(f'<td class="{cls}" title="{html.escape(c["how"])}">{word} {mark}</td>')
            rows_m.append(f"<tr><th>{html.escape(title)}<br><code>{html.escape(path)}</code></th>{''.join(cells)}</tr>")
        matrix = (f'<h2 class="role">Access control matrix</h2>'
                  f'<p class="sub">Who could open each restricted page. ✓ = matches your code\'s rules · '
                  f'✗ = wrong · ? = role not set in accounts.json. Hover a cell for details.</p>'
                  f'<div class="mx"><table><tr><th>Page</th>{head}</tr>{"".join(rows_m)}</table></div>')
    pct = round(100 * s["passed"] / s["total"]) if s["total"] else 0
    doc = f'''<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CampusConnect Local Selenium Report</title><style>
:root{{--bg:#0b1020;--card:#151b2e;--ink:#e7ecf5;--sub:#9aa6bf;--line:#252d44}}
*{{box-sizing:border-box}}body{{margin:0;font:15px/1.55 -apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink)}}
.wrap{{max-width:1040px;margin:0 auto;padding:26px 18px 80px}}h1{{font-size:24px;margin:0 0 6px}}
.sub{{color:var(--sub);font-size:13px}}.stats{{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0 6px}}
.stat{{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 16px;min-width:84px}}
.stat b{{display:block;font-size:22px}}.stat span{{color:var(--sub);font-size:11px;text-transform:uppercase}}
.bar{{height:10px;border-radius:6px;background:#dc2626;overflow:hidden;margin-top:6px}}.bar>i{{display:block;height:100%;width:{pct}%;background:#16a34a}}
h2.role{{margin:34px 0 6px;font-size:19px;border-bottom:2px solid var(--line);padding-bottom:6px}}
h3{{margin:20px 0 10px;font-size:15px;color:#c7d2fe}}h3 .cnt,.role .cnt{{color:var(--sub);font-weight:500;font-size:12.5px}}
.card{{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:12px 14px;margin:9px 0}}
.hd{{display:flex;align-items:center;gap:10px;flex-wrap:wrap}}.badge{{color:#fff;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px}}
.ttl{{font-weight:600;word-break:break-word}}.meta{{color:var(--sub);font-size:12.5px;margin-left:auto}}
.detail{{margin:10px 0 4px;padding:8px 12px;border-left:3px solid #16a34a;border-radius:6px;background:#10261a;color:#c9f2d8;font-size:13px;white-space:pre-wrap;font-family:ui-monospace,monospace}}
.detail.fail,.detail.error{{background:#3a1116;border-left-color:#dc2626;color:#ffd9dc}}.detail.skip{{background:#2a230d;border-left-color:#a16207;color:#f0e0b8}}
.shot{{margin-top:10px}}.shot img{{width:100%;border:1px solid var(--line);border-radius:8px;display:block}}
.mx{{overflow-x:auto}}.mx table{{border-collapse:collapse;width:100%;font-size:13px;margin-top:8px}}
.mx th,.mx td{{border:1px solid var(--line);padding:7px 9px;text-align:left;vertical-align:top}}
.mx th{{background:var(--card);font-weight:600}}.mx td.good{{background:#10261a;color:#bdf0cf}}
.mx td.bad{{background:#4a1118;color:#ffd0d5;font-weight:700}}.mx td.unk{{background:#2a230d;color:#f0e0b8}}</style></head><body><div class="wrap">
<h1>UIU CampusConnect — Local Selenium Report</h1>
<div class="sub">Target {html.escape(s["base_url"])} · {html.escape(s["generated_at"])} · {s["duration_s"]}s · write mode {"ON" if s["write_mode"] else "off"} · roles: {html.escape(", ".join(s["accounts"]) or "guest only")}</div>
<div class="stats">
<div class="stat"><b>{s["total"]}</b><span>Total</span></div>
<div class="stat"><b style="color:#22c55e">{s["passed"]}</b><span>Passed</span></div>
<div class="stat"><b style="color:#f87171">{s["failed"]}</b><span>Failed</span></div>
<div class="stat"><b style="color:#f87171">{s["errored"]}</b><span>Errored</span></div>
<div class="stat"><b style="color:#eab308">{s["skipped"]}</b><span>Skipped</span></div>
<div class="stat"><b>{pct}%</b><span>Pass rate</span></div></div>
<div class="bar"><i></i></div>{matrix}{''.join(blocks)}
<footer style="margin-top:34px;color:var(--sub);font-size:12px;text-align:center">Read-only unless write mode is on · screenshots captured live per test.</footer>
</div></body></html>'''
    open(os.path.join(OUT, "report.html"), "w").write(doc)


if __name__ == "__main__":
    main()

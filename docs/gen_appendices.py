#!/usr/bin/env python3
"""
Regenerates the reference appendices straight from the source tree.

The appendices are lists of things that exist - routes, tables, controllers,
pages, commands. Every one of them went stale between the last version of the
document and this one, because they were written by hand. So they are not
written by hand any more: this script reads the code and emits the markdown,
and `python3 gen_appendices.py` is the only way they are ever updated.

Where a list needs prose next to it - what a controller is *for* - the prose
lives in a map below, keyed by the same name the code uses. Anything found in
the code with no entry in the map is emitted with a loud TODO, so a new
controller cannot slip into the document without a description.

Output: docs/generated/*.md, which assemble_full.py splices into full.md.
"""

import os
import re
import sys
import textwrap

SRC = os.environ.get('SRC', os.path.join(os.path.dirname(__file__), '..', 'src'))
OUT = os.path.join(os.path.dirname(__file__), 'generated')

MISSING = []


def read(path):
    with open(os.path.join(SRC, path), encoding='utf-8') as handle:
        return handle.read()


def describe(kind, key, mapping):
    """The prose for one row, or a TODO that is impossible to miss."""
    if key in mapping:
        return mapping[key]
    MISSING.append(f'{kind}: {key}')
    return 'TODO - no description'


# --------------------------------------------------------------------------
# Pandoc multiline tables
# --------------------------------------------------------------------------

def table(headers, rows, widths):
    """
    A pandoc multiline table, the same shape as the ones already in the
    document, so the reference docx styles them identically.

    `widths` are character counts; pandoc turns their proportions into the
    column widths in Word, so they are a layout instruction, not padding.
    """
    # A header that overflows its column would push the next one sideways and
    # pandoc reads the columns off the dashes, so widen rather than wrap.
    widths = [max(w, len(h) + 4) for w, h in zip(widths, headers)]

    total = sum(widths) + len(widths) - 1
    out = ['  ' + '-' * total]
    out.append('  ' + row_text([f'**{h}**' for h in headers], widths))
    out.append('  ' + ' '.join('-' * w for w in widths))

    for i, row in enumerate(rows):
        if i:
            out.append('')
        out.append('  ' + row_text(row, widths))

    out.append('  ' + '-' * total)
    return '\n'.join(out) + '\n'


def row_text(cells, widths):
    """One row, wrapping any cell too wide for its column onto more lines."""
    columns = []

    for cell, width in zip(cells, widths):
        text = str(cell)
        # Never break a code-ish token: a wrapped route or file path is worse
        # than a column that runs a little wide.
        lines = textwrap.wrap(text, width, break_long_words=False,
                              break_on_hyphens=False) or ['']
        columns.append(lines)

    height = max(len(c) for c in columns)
    lines = []

    for n in range(height):
        parts = []
        for column, width in zip(columns, widths):
            piece = column[n] if n < len(column) else ''
            parts.append(piece.ljust(width))
        lines.append(' '.join(parts).rstrip())

    return '\n  '.join(lines)


def write(name, text):
    os.makedirs(OUT, exist_ok=True)
    with open(os.path.join(OUT, name), 'w', encoding='utf-8') as handle:
        handle.write(text)
    print(f'  {name}')


# --------------------------------------------------------------------------
# Appendix A - routes
# --------------------------------------------------------------------------

ROUTE = re.compile(
    r"Route::(?P<verb>get|post|put|patch|delete)\(\s*"
    r"'(?P<url>[^']*)'\s*,\s*"
    r"(?:\[(?P<class>[A-Za-z]+)::class\s*,\s*'(?P<method>\w+)'\]"
    r"|(?P<invokable>[A-Za-z]+)::class)"
    r"(?P<tail>.*?);",
    re.S,
)

NAME = re.compile(r"->name\('([^']+)'\)")


def routes():
    source = read('routes/web.php')

    # The class short names in the route file are aliases; the use statements
    # say which folder each one really lives in, which is what the reader needs
    # when they go looking for the file.
    folders = {}
    for line in source.splitlines():
        match = re.match(r"use App\\Http\\Controllers\\(.+);", line.strip())
        if match:
            path = match.group(1).replace('\\', '/')
            folders[path.split('/')[-1]] = path

    rows = []
    for match in ROUTE.finditer(source):
        klass = match.group('class') or match.group('invokable')
        method = match.group('method') or '__invoke'
        named = NAME.search(match.group('tail'))

        rows.append([
            match.group('verb').upper(),
            match.group('url') or '/',
            named.group(1) if named else '-',
            f"{folders.get(klass, klass)}@{method}",
        ])

    body = (
        '# Appendix A. Route reference\n\n'
        f'Every URL in the application, in the order it appears in\n'
        f'routes/web.php - {len(rows)} in all. The Filament panel registers its own\n'
        'routes under /admin and is not listed here.\n\n'
        'Routes above the first authenticated group are open to guests;\n'
        'everything from /feed onwards sits inside the auth middleware group.\n\n'
    )

    return body + table(
        ['Method', 'URL', 'Route name', 'Handler'],
        rows,
        [8, 44, 32, 40],
    )


# --------------------------------------------------------------------------
# Appendix B.2 - controllers
# --------------------------------------------------------------------------

CONTROLLERS = {
    'Auth/LoginController': 'Sign in and sign out',
    'PasswordResetRequestController': 'Account recovery, and the administrator review of it',
    'UserApprovalController': 'Approving, rejecting and suspending accounts',
    'FeedController': 'The feed, its six tabs, their ranking and the infinite scroll',
    'PostController': 'Creating, editing, deleting, saving, hiding, sharing, reporting and voting, and serving private document downloads',
    'CommentController': 'Comments, comment reactions and comment reports',
    'ReactionController': 'Post reactions',
    'ProfileController': 'Viewing and editing profiles, and profile reports',
    'SettingsController': 'Password changes',
    'ThemeController': 'The light and dark preference cookie',
    'NetworkController': 'The people directory, connections, following, blocking, muting',
    'ConnectionRequestController': 'Student applications to connect with staff',
    'MessageController': 'The inbox, conversations and sending',
    'UserSearchController': 'The people lookup behind mention autocomplete',
    'SearchController': 'Global search',
    'NotificationController': 'The notification centre',
    'ClubController': 'Club directory, club pages, membership, officer nominations and panel succession',
    'EventController': 'Events, registration, interest and check-in',
    'NoticeController': 'University notices, including the course notices that reach enrolled students',
    'CourseSectionController': 'The trimester\'s course sections, their teachers and their class groups',
    'GroupController': 'All three kinds of group, their walls, materials, notices and files',
    'AssignmentController': 'Setting coursework, attaching handouts, handing in and collecting submissions',
    'GroupChatController': 'Class chat: history, sending, removal, and the polling fallback for the socket',
    'AssistantController': 'The campus assistant page, its conversations and the question endpoint',
    'MarketplaceController': 'Marketplace listings',
    'LostFoundController': 'Lost and found items and claims',
    'MediaController': 'Pictures attached to events, listings, clubs and notices',
    'AdminCenterController': 'The administrative home page',
    'ModerationController': 'The report review queue',
    'Controller': None,  # the abstract base class, not a feature
}


def controllers():
    found = []
    base = os.path.join(SRC, 'app/Http/Controllers')

    for root, _, files in os.walk(base):
        for name in sorted(files):
            if not name.endswith('Controller.php'):
                continue
            rel = os.path.relpath(os.path.join(root, name), base)
            found.append(rel[:-4].replace(os.sep, '/'))

    rows = []
    for key in sorted(found, key=lambda k: (k.count('/') == 0, k)):
        text = describe('controller', key, CONTROLLERS)
        if text is None:
            continue
        rows.append([key, text])

    return ('## B.2 Controllers\n' + table(['Controller', 'Feature'], rows, [32, 46]))


# --------------------------------------------------------------------------
# Appendix B.3 - models
# --------------------------------------------------------------------------

MODEL_GROUPS = [
    ('Accounts', ['User', 'UserProfile', 'PasswordResetRequest', 'ConnectionApplication']),
    ('Social graph', ['Friendship', 'UserBlock', 'UserMute', 'Message', 'MessageBlock']),
    ('Posts', ['Post', 'PostMedia', 'PostLink', 'PostEditHistory', 'PostReaction',
               'PostSave', 'PostShare', 'HiddenPost', 'PostView', 'PollOption',
               'PollVote', 'Mention']),
    ('Comments', ['Comment', 'CommentLike']),
    ('Academic records', ['Trimester', 'CourseSection', 'CourseEnrollment']),
    ('Coursework', ['Assignment', 'AssignmentAttachment', 'AssignmentSubmission']),
    ('Clubs and events', ['Club', 'ClubMembershipRequest', 'ClubRoleRequest',
                          'ClubPanelChange', 'ClubPanelMember', 'Event', 'EventCheckin']),
    ('Notices', ['Notice', 'NoticeAttachment']),
    ('Groups and boards', ['CommunityGroup', 'CommunityGroupFile', 'GroupMessage',
                           'MarketplaceItem', 'LostFoundItem', 'LostFoundClaim']),
    ('Campus assistant', ['AiConversation', 'AiMessage']),
    ('Pictures', ['Media']),
    ('Moderation', ['Report', 'CommentReport', 'ProfileReport', 'ModerationNote',
                    'AuditLog', 'Notification']),
    ('Retired', ['SectionAnnouncement']),
]

SUPPORT = {
    'AcademicCalendar': 'Trimester dates, and the deadlines card that reads them',
    'ClubPanel': 'Panel succession: who holds what now, and applying a whole new panel at once',
    'CoverImage': 'Picking and setting the cover picture of a gallery',
    'Gallery': 'Assembling a set of pictures for a page',
    'GroupSync': 'The one place a course section or club becomes a group and its membership',
    'ImageResizer': 'Thumbnails and size limits for uploaded pictures',
    'LinkPreview': 'Fetching the title, description and image behind a posted link',
    'Mentions': 'Finding @names in text and turning them into mention rows',
    'Notifier': 'Creating notifications without every controller knowing the shape of one',
    'ShuttleSchedule': 'The bus timetable behind the shuttle card',
}


def models():
    base = os.path.join(SRC, 'app/Models')
    found = {f[:-4] for f in os.listdir(base) if f.endswith('.php')}

    placed = {m for _, group in MODEL_GROUPS for m in group}
    for name in sorted(found - placed):
        MISSING.append(f'model not grouped: {name}')

    rows = []
    for title, group in MODEL_GROUPS:
        present = [m for m in group if m in found]
        if present:
            rows.append([title, ', '.join(present)])

    body = '## B.3 Models by table group\n' + table(['Group', 'Models'], rows, [22, 56])

    support = os.path.join(SRC, 'app/Support')
    helpers = sorted(f[:-4] for f in os.listdir(support) if f.endswith('.php'))
    rows = [[f'{name}.php', describe('support class', name, SUPPORT)] for name in helpers]

    body += (
        '\nApp\\Support holds the classes that are not models but are not\n'
        'controllers either - the rules several controllers share.\n\n'
        + table(['Support class', 'What it decides'], rows, [22, 56])
    )

    return body


# --------------------------------------------------------------------------
# Appendix B.4 - Blade views
# --------------------------------------------------------------------------

VIEW_FOLDERS = {
    'auth': 'Sign in, account recovery',
    'feed': 'The feed, a single post, and the composer, post and comment partials',
    'profile': 'Profile page, edit profile',
    'messages': 'Inbox, conversation, disabled notice',
    'clubs': 'Club page',
    'events': 'Event page',
    'notices': 'Notice page',
    'groups': 'Group page, its wall, materials and notices',
    'marketplace': 'Single listing',
    'lost-found': 'Single item',
    'search': 'Search results',
    'components': 'The page layout, icons, gallery, messenger widget, deadlines card, shuttle card',
}

# Folders whose screens moved to React. The files are still on disk but no
# controller renders them; see 11.2.
DEAD_FOLDERS = ['admin', 'administration', 'connect-requests', 'network',
                'notifications', 'settings']


def views():
    base = os.path.join(SRC, 'resources/views')
    folders = sorted(f for f in os.listdir(base)
                     if os.path.isdir(os.path.join(base, f)))

    rows = []
    for name in folders:
        if name in DEAD_FOLDERS:
            continue
        rows.append([name, describe('view folder', name, VIEW_FOLDERS)])

    body = '## B.4 Blade views\n' + table(['Folder', 'Pages'], rows, [22, 56])

    still_there = [f for f in DEAD_FOLDERS if f in folders]
    if still_there:
        body += (
            '\nSuperseded by React pages, still on disk and rendered by nothing:\n'
            + ', '.join(still_there) + '.\n'
        )

    return body


# --------------------------------------------------------------------------
# Appendix B.5 - React pages
# --------------------------------------------------------------------------

PAGES = {
    'Pages/Network/Index.jsx': 'People directory',
    'Pages/ConnectRequests/Index.jsx': 'Connection applications, and club officer nominations awaiting the nominee',
    'Pages/Clubs/Index.jsx': 'Club directory',
    'Pages/Events/Index.jsx': 'Event list',
    'Pages/Groups/Index.jsx': 'Group directory, split by class, club and community',
    'Pages/Notices/Index.jsx': 'Notice board',
    'Pages/Notifications/Index.jsx': 'Notification centre',
    'Pages/Marketplace/Index.jsx': 'Marketplace listings',
    'Pages/LostFound/Index.jsx': 'Lost and found board',
    'Pages/Admin/Center.jsx': 'Administrative home page',
    'Pages/Admin/UserApprovals.jsx': 'Account approval queue',
    'Pages/Admin/Moderation.jsx': 'Report review queue',
    'Pages/Admin/PasswordRequests.jsx': 'Password recovery queue',
    'Pages/Administration/Index.jsx': 'Course sections for a trimester',
    'Pages/Settings/Password.jsx': 'Account settings',
    'Pages/Assistant/Index.jsx': 'The campus assistant: conversation, suggestions and recent threads',
    'chat.js': 'Class chat component: live over the socket, polling when there is none',
    'echo.js': 'Loads the websocket client on demand, and reports honestly when there is no server',
    'Layouts/AppLayout.jsx': 'Sidebar, header, flash banner, mobile bar',
    'Components/Icon.jsx': 'The same icon set the Blade pages use',
    'Components/UserAvatar.jsx': 'Photo or initials chip',
    'Components/Pagination.jsx': 'Pager matching the Blade one',
    'Components/icons.js': 'The icon path data',
    'inertia.jsx': 'Entry point that mounts the pages',
}


def pages():
    base = os.path.join(SRC, 'resources/js')
    found = []

    for root, _, files in os.walk(base):
        for name in sorted(files):
            if not name.endswith(('.jsx', '.js')):
                continue
            rel = os.path.relpath(os.path.join(root, name), base).replace(os.sep, '/')
            # app.js, landing.js and the landing/ scene modules are plain
            # browser JavaScript, not Inertia pages; chapter 9 covers them.
            if rel.startswith('landing') or rel in ('app.js', 'code-highlight.js'):
                continue
            found.append(rel)

    order = {name: i for i, name in enumerate(PAGES)}
    found.sort(key=lambda n: order.get(n, 999))

    rows = [[name, describe('page', name, PAGES)] for name in found]

    return (
        '## B.5 React pages\n'
        'Every file below is under resources/js. A page is resolved by the name\n'
        'the controller passes to Inertia::render.\n\n'
        + table(['File', 'Screen'], rows, [34, 44])
    )


# --------------------------------------------------------------------------
# Appendix B.6 - console commands
# --------------------------------------------------------------------------

def commands():
    base = os.path.join(SRC, 'app/Console/Commands')
    rows = []

    for name in sorted(os.listdir(base)):
        if not name.endswith('.php'):
            continue

        source = open(os.path.join(base, name), encoding='utf-8').read()

        signature = re.search(r"\$signature\s*=\s*'(.*?)'", source, re.S)
        description = re.search(r"\$description\s*=\s*'(.*?)'", source, re.S)

        if not signature:
            continue

        # The signature carries its arguments across several lines; the command
        # name is the first token of it.
        whole = ' '.join(signature.group(1).split())
        rows.append([whole.split(' ')[0], description.group(1) if description else ''])

    # Setting the university up comes before running it day to day, which is
    # the order somebody reads this table in.
    order = ['uiu:trimester', 'uiu:import-faculty', 'uiu:import-enrolments',
             'uiu:sync-club-groups', 'uiu:archive-course-groups',
             'uiu:secure-group-files', 'uiu:demo-content', 'uiu:wipe-demo']
    rows.sort(key=lambda r: order.index(r[0]) if r[0] in order else 99)

    return (
        '## B.6 Console commands\n'
        'Run with php artisan. The ones that write take --dry-run first.\n\n'
        + table(['Command', 'Purpose'], rows, [30, 48])
    )


# --------------------------------------------------------------------------
# Appendix C - database tables
# --------------------------------------------------------------------------

GROUPS = [
    ('Accounts and access', [
        'users', 'user_profiles', 'password_reset_requests',
        'connection_applications', 'sessions', 'password_reset_tokens',
    ]),
    ('The social graph', [
        'friendships', 'user_follows', 'user_blocks', 'user_mutes',
        'messages', 'message_blocks',
    ]),
    ('Posts', [
        'posts', 'post_media', 'post_links', 'post_edit_history',
        'post_reactions', 'post_saves', 'post_shares', 'post_views',
        'hidden_posts', 'poll_options', 'poll_votes', 'mentions',
    ]),
    ('Comments', ['comments', 'comment_likes']),
    ('Academic records', [
        'trimesters', 'course_sections', 'course_enrollments',
    ]),
    ('Coursework', [
        'assignments', 'assignment_attachments', 'assignment_submissions',
    ]),
    ('Clubs and events', [
        'clubs', 'club_members', 'club_membership_requests',
        'club_role_requests', 'club_panel_changes', 'club_panel_members',
        'events', 'event_registrations', 'event_interests', 'event_checkins',
    ]),
    ('Notices', ['notices', 'notice_attachments', 'notice_reads']),
    ('Groups and boards', [
        'community_groups', 'community_group_members', 'community_group_files',
        'group_messages', 'marketplace_items', 'lost_found_items', 'lost_found_claims',
    ]),
    ('Campus assistant', ['ai_conversations', 'ai_messages']),
    ('Pictures', ['media']),
    ('Moderation and the trail', [
        'reports', 'comment_reports', 'profile_reports', 'moderation_notes',
        'audit_logs', 'notifications',
    ]),
]

# Tables Laravel itself owns, and the one table left over from a superseded
# feature. Listing them keeps the count in this appendix honest against the
# database without pretending they are part of the design.
FRAMEWORK = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs']
RETIRED = {'section_announcements': 'Superseded by course_sections; see 11.2'}


def tables():
    created = set()
    for name in sorted(os.listdir(os.path.join(SRC, 'database/migrations'))):
        source = open(os.path.join(SRC, 'database/migrations', name),
                      encoding='utf-8').read()
        created |= set(re.findall(r"Schema::create\('([a-z_]+)'", source))

    placed = {t for _, group in GROUPS for t in group} | set(FRAMEWORK) | set(RETIRED)
    stray = sorted(created - placed)
    unknown = sorted(placed - created - set(FRAMEWORK))

    for name in stray:
        MISSING.append(f'table not grouped: {name}')
    for name in unknown:
        MISSING.append(f'table grouped but never created: {name}')

    rows = []
    for title, group in GROUPS:
        present = [t for t in group if t in created]
        if present:
            rows.append([title, ', '.join(present)])

    if stray:
        rows.append(['Ungrouped - fix gen_appendices.py', ', '.join(stray)])

    body = (
        '# Appendix C. Database tables\n\n'
        f'The {len(created)} tables the migrations create, by the part of the system\n'
        'that owns them. Chapter 7 explains the design; this is the index.\n\n'
        + table(['Group', 'Tables'], rows, [26, 62])
    )

    body += (
        '\nFramework tables: ' + ', '.join(FRAMEWORK) + '.\n\n'
        + ''.join(f'Retired: {name} - {why.lower()}.\n'
                  for name, why in RETIRED.items())
    )

    return body


# --------------------------------------------------------------------------

def main():
    print('Writing appendices from', os.path.abspath(SRC))
    write('appendix_a.md', routes())
    write('b2_controllers.md', controllers())
    write('b3_models.md', models())
    write('b4_views.md', views())
    write('b5_pages.md', pages())
    write('b6_commands.md', commands())
    write('appendix_c.md', tables())

    if MISSING:
        print('\nNeeds attention:')
        for item in MISSING:
            print('  -', item)
        return 1

    print('\nAll rows described.')
    return 0


if __name__ == '__main__':
    sys.exit(main())

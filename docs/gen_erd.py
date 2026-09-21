#!/usr/bin/env python3
"""
Draws the entity-relationship figures.

The original diagrams were made with a tool that is not in this repository, so
when the schema grew - the academic tables, the club panel tables, post_links,
notice_reads - the figures could not be brought up to date and the document
would have been describing tables its own pictures did not show.

This draws them instead, from a spec in this file, in the same house style:
terracotta headers, PK and FK marked in the margin, a crow's foot at the many
end of each relation. A table that changes is changed here and the figure is
rebuilt; there is nothing to redraw by hand.

    python3 gen_erd.py

Writes docs/media/erd/*.png, which full.md references.
"""

import os

import matplotlib
matplotlib.use('Agg')

import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'media', 'erd')

# Sampled from the existing figures, so a new one sits beside an old one
# without looking like it came from somewhere else.
HEADER = '#c15f3c'
STRIPE = '#fdfaf8'
BORDER = '#ddd0c9'
TEXT = '#6b7a86'
STRONG = '#25303a'
KEY = '#9c4629'
LINE = '#b9a79f'

ROW = 0.42          # height of one column row
HEAD = 0.52         # height of the header bar
PAD = 0.22          # left and right padding inside a box
FONT = 9.5


class Table:
    """One box: a name, and a list of (column, type, marker) rows."""

    def __init__(self, name, x, y, width, columns):
        self.name = name
        self.x = x
        self.y = y                # top edge
        self.width = width
        self.columns = columns

    @property
    def height(self):
        return HEAD + ROW * len(self.columns)

    @property
    def bottom(self):
        return self.y - self.height

    def anchor(self, side, offset=0.5):
        """A point on one edge: offset is 0..1 along that edge."""
        if side == 'left':
            return self.x, self.y - HEAD - (self.height - HEAD) * offset
        if side == 'right':
            return self.x + self.width, self.y - HEAD - (self.height - HEAD) * offset
        if side == 'top':
            return self.x + self.width * offset, self.y
        return self.x + self.width * offset, self.bottom

    def draw(self, ax):
        ax.add_patch(Rectangle(
            (self.x, self.y - HEAD), self.width, HEAD,
            facecolor=HEADER, edgecolor='none', zorder=3))

        ax.text(self.x + PAD, self.y - HEAD / 2, self.name,
                va='center', ha='left', color='white',
                fontsize=FONT + 0.5, fontweight='bold', zorder=4)

        for i, (column, kind, marker) in enumerate(self.columns):
            top = self.y - HEAD - ROW * i

            ax.add_patch(Rectangle(
                (self.x, top - ROW), self.width, ROW,
                facecolor='white' if i % 2 else STRIPE,
                edgecolor=BORDER, linewidth=0.6, zorder=3))

            ax.text(self.x + PAD, top - ROW / 2, column,
                    va='center', ha='left', zorder=4, fontsize=FONT,
                    color=STRONG if marker and 'PK' in marker else TEXT,
                    fontweight='bold' if marker and 'PK' in marker else 'normal')

            if marker:
                # Right-aligned into its own column, clear of the type, so
                # "PK FK" never runs into "bigint".
                ax.text(self.x + self.width - PAD - 1.85, top - ROW / 2, marker,
                        va='center', ha='right', color=KEY, zorder=4,
                        fontsize=FONT - 1.5, fontweight='bold')

            ax.text(self.x + self.width - PAD, top - ROW / 2, kind,
                    va='center', ha='right', color=TEXT, zorder=4,
                    fontsize=FONT - 0.5)


def crow(ax, point, towards):
    """The three-pronged 'many' end, opening back along the line."""
    (x, y), (tx, ty) = point, towards
    dx, dy = tx - x, ty - y
    length = (dx * dx + dy * dy) ** 0.5 or 1
    dx, dy = dx / length, dy / length
    px, py = -dy, dx
    size = 0.30

    for spread in (-1, 0, 1):
        ax.plot([x, x + dx * size + px * spread * size * 0.95],
                [y, y + dy * size + py * spread * size * 0.95],
                color=LINE, linewidth=1.1, zorder=2, solid_capstyle='round')


def tick(ax, point, towards):
    """The single bar at the 'one' end."""
    (x, y), (tx, ty) = point, towards
    dx, dy = tx - x, ty - y
    length = (dx * dx + dy * dy) ** 0.5 or 1
    dx, dy = dx / length, dy / length
    px, py = -dy, dx
    cx, cy = x + dx * 0.24, y + dy * 0.24

    ax.plot([cx - px * 0.15, cx + px * 0.15],
            [cy - py * 0.15, cy + py * 0.15],
            color=LINE, linewidth=1.3, zorder=2)


def relate(ax, one, many, elbow=None):
    """
    One row on the left, many rows on the right. `elbow` is an optional
    mid-point the line bends through, for the runs that would otherwise cross
    a box.
    """
    points = [one, elbow, many] if elbow else [one, many]

    ax.plot([p[0] for p in points], [p[1] for p in points],
            color=LINE, linewidth=1.1, zorder=1, solid_capstyle='round')

    tick(ax, one, points[1])
    crow(ax, many, points[-2])


def render(name, tables, relations, size):
    fig, ax = plt.subplots(figsize=size, dpi=170)

    for table in tables.values():
        table.draw(ax)

    for one, many, *rest in relations:
        relate(ax, one, many, rest[0] if rest else None)

    # Fit the frame to the boxes rather than to the figure size, so nothing
    # is ever cropped when a table gains a column.
    xs = [t.x for t in tables.values()] + [t.x + t.width for t in tables.values()]
    ys = [t.y for t in tables.values()] + [t.bottom for t in tables.values()]

    # Elbows sit outside the boxes on purpose, so they count too.
    for relation in relations:
        for point in relation:
            xs.append(point[0])
            ys.append(point[1])

    left, right = min(xs) - 0.6, max(xs) + 0.6
    bottom, top = min(ys) - 0.5, max(ys) + 0.5

    ax.set_xlim(left, right)
    ax.set_ylim(bottom, top)
    ax.set_aspect('equal')
    ax.axis('off')
    fig.patch.set_facecolor('white')

    os.makedirs(OUT, exist_ok=True)
    path = os.path.join(OUT, name)
    fig.savefig(path, bbox_inches='tight', pad_inches=0.12, facecolor='white')
    plt.close(fig)
    print('  ', os.path.relpath(path, os.path.dirname(OUT)))


# --------------------------------------------------------------------------
# Figure: the academic record
# --------------------------------------------------------------------------

def academic():
    t = {
        'trimesters': Table('trimesters', 0, 0, 6.4, [
            ('id', 'bigint', 'PK'),
            ('code', 'varchar(30)', ''),
            ('name', 'varchar(120)', ''),
            ('starts_on', 'date', ''),
            ('ends_on', 'date', ''),
        ]),
        'sections': Table('course_sections', 0, -4.2, 6.4, [
            ('id', 'bigint', 'PK'),
            ('trimester_id', 'bigint', 'FK'),
            ('course_code', 'varchar(30)', ''),
            ('course_title', 'varchar(190)', ''),
            ('section_name', 'varchar(30)', ''),
            ('schedule_pattern', 'varchar(60)', ''),
            ('start_time', 'time', ''),
            ('end_time', 'time', ''),
            ('room', 'varchar(120)', ''),
            ('seat_limit', 'int', ''),
            ('source', 'varchar(20)', ''),
        ]),
        'enrolments': Table('course_enrollments', 8.6, -4.2, 6.6, [
            ('id', 'bigint', 'PK'),
            ('section_id', 'bigint', 'FK'),
            ('user_id', 'bigint', 'FK'),
            ('role', 'varchar(20)', ''),
            ('status', 'varchar(20)', ''),
            ('enrolled_at', 'timestamp', ''),
            ('dropped_at', 'timestamp', ''),
            ('source', 'varchar(20)', ''),
        ]),
        'users': Table('users', 8.6, 0, 6.6, [
            ('id', 'bigint', 'PK'),
            ('full_name', 'varchar(150)', ''),
            ('student_id', 'varchar(30)', ''),
            ('employee_id', 'varchar(30)', ''),
            ('role', 'enum', ''),
        ]),
        'groups': Table('community_groups', 0, -11.4, 6.4, [
            ('id', 'bigint', 'PK'),
            ('kind', 'varchar(20)', ''),
            ('source_id', 'bigint', ''),
            ('slug', 'varchar(190)', ''),
            ('owner_id', 'bigint', 'FK'),
            ('posting_mode', 'varchar(20)', ''),
            ('visibility', 'varchar(20)', ''),
            ('archived_at', 'timestamp', ''),
        ]),
        'members': Table('community_group_members', 8.6, -11.4, 6.6, [
            ('group_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('role', 'varchar(20)', ''),
            ('joined_at', 'timestamp', ''),
        ]),
    }

    r = [
        (t['trimesters'].anchor('bottom', 0.3), t['sections'].anchor('top', 0.3)),
        (t['sections'].anchor('right', 0.12), t['enrolments'].anchor('left', 0.18)),
        (t['users'].anchor('bottom', 0.5), t['enrolments'].anchor('top', 0.5)),
        (t['sections'].anchor('bottom', 0.3), t['groups'].anchor('top', 0.3)),
        (t['groups'].anchor('right', 0.25), t['members'].anchor('left', 0.25)),
        (t['users'].anchor('right', 0.8), t['members'].anchor('right', 0.8),
         (16.6, -6.0)),
    ]

    render('academic.png', t, r, (10.4, 8.4))


# --------------------------------------------------------------------------
# Figure: how a club's leadership changes
# --------------------------------------------------------------------------

def club_panel():
    t = {
        'clubs': Table('clubs', 0, 0, 6.2, [
            ('id', 'bigint', 'PK'),
            ('name', 'varchar(150)', ''),
            ('slug', 'varchar(190)', ''),
            ('moderator_id', 'bigint', 'FK'),
            ('status', 'varchar(30)', ''),
        ]),
        'members': Table('club_members', 0, -4.0, 6.2, [
            ('club_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('club_role', 'varchar(50)', ''),
            ('can_post_events', 'boolean', ''),
            ('joined_at', 'timestamp', ''),
        ]),
        'users': Table('users', 9.0, 0, 6.2, [
            ('id', 'bigint', 'PK'),
            ('full_name', 'varchar(150)', ''),
            ('role', 'enum', ''),
        ]),
        'roles': Table('club_role_requests', 9.0, -3.2, 6.2, [
            ('id', 'bigint', 'PK'),
            ('club_id', 'bigint', 'FK'),
            ('nominee_id', 'bigint', 'FK'),
            ('nominator_id', 'bigint', 'FK'),
            ('club_role', 'varchar(80)', ''),
            ('status', 'varchar(20)', ''),
            ('decided_by', 'bigint', 'FK'),
            ('decided_at', 'timestamp', ''),
        ]),
        'changes': Table('club_panel_changes', 0, -8.4, 6.2, [
            ('id', 'bigint', 'PK'),
            ('club_id', 'bigint', 'FK'),
            ('proposed_by', 'bigint', 'FK'),
            ('note', 'varchar(500)', ''),
            ('previous_panel', 'json', ''),
            ('status', 'varchar(20)', ''),
            ('decided_by', 'bigint', 'FK'),
            ('decided_at', 'timestamp', ''),
        ]),
        'seats': Table('club_panel_members', 9.0, -8.9, 6.2, [
            ('id', 'bigint', 'PK'),
            ('panel_change_id', 'bigint', 'FK'),
            ('user_id', 'bigint', 'FK'),
            ('club_role', 'varchar(80)', ''),
        ]),
    }

    r = [
        (t['clubs'].anchor('bottom', 0.3), t['members'].anchor('top', 0.3)),
        (t['users'].anchor('left', 0.5), t['clubs'].anchor('right', 0.5)),
        (t['users'].anchor('bottom', 0.35), t['roles'].anchor('top', 0.35)),
        (t['clubs'].anchor('right', 0.9), t['roles'].anchor('left', 0.12)),
        (t['clubs'].anchor('left', 0.9), t['changes'].anchor('left', 0.1),
         (-1.6, -5.4)),
        (t['changes'].anchor('right', 0.25), t['seats'].anchor('left', 0.35)),
        (t['users'].anchor('right', 0.95), t['seats'].anchor('right', 0.55),
         (16.6, -6.4)),
    ]

    render('club-panel.png', t, r, (10.4, 8.4))


# --------------------------------------------------------------------------
# Figure: a post and the things hanging off it
# --------------------------------------------------------------------------

def posts():
    t = {
        'posts': Table('posts', 5.0, 0, 6.6, [
            ('id', 'bigint', 'PK'),
            ('user_id', 'bigint', 'FK'),
            ('club_id', 'bigint', 'FK'),
            ('group_id', 'bigint', 'FK'),
            ('body', 'text', ''),
            ('post_type', 'varchar(20)', ''),
            ('code_language', 'varchar(30)', ''),
            ('visibility', 'varchar(20)', ''),
            ('is_deleted', 'boolean', ''),
        ]),
        'media': Table('post_media', -2.6, -1.2, 6.4, [
            ('id', 'bigint', 'PK'),
            ('post_id', 'bigint', 'FK'),
            ('path', 'varchar(500)', ''),
            ('disk', 'varchar(30)', ''),
            ('original_name', 'varchar(190)', ''),
            ('type', 'varchar(20)', ''),
            ('mime_type', 'varchar(120)', ''),
            ('size', 'bigint', ''),
            ('position', 'int', ''),
        ]),
        'links': Table('post_links', 12.8, -1.2, 6.4, [
            ('id', 'bigint', 'PK'),
            ('post_id', 'bigint', 'FK'),
            ('url', 'varchar(2048)', ''),
            ('title', 'varchar(190)', ''),
            ('description', 'text', ''),
            ('image_url', 'varchar(2048)', ''),
            ('site_name', 'varchar(120)', ''),
        ]),
        'options': Table('poll_options', -2.6, -7.4, 6.4, [
            ('id', 'bigint', 'PK'),
            ('post_id', 'bigint', 'FK'),
            ('label', 'varchar(190)', ''),
            ('position', 'int', ''),
        ]),
        'votes': Table('poll_votes', -2.6, -10.6, 6.4, [
            ('post_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('option_id', 'bigint', 'FK'),
        ]),
        'reactions': Table('post_reactions', 5.0, -7.4, 6.6, [
            ('post_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('reaction', 'varchar(20)', ''),
        ]),
        'views': Table('post_views', 5.0, -10.2, 6.6, [
            ('post_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('seen_at', 'timestamp', ''),
        ]),
        'comments': Table('comments', 12.8, -7.4, 6.4, [
            ('id', 'bigint', 'PK'),
            ('post_id', 'bigint', 'FK'),
            ('user_id', 'bigint', 'FK'),
            ('parent_id', 'bigint', 'FK'),
            ('body', 'text', ''),
            ('is_deleted', 'boolean', ''),
        ]),
    }

    r = [
        (t['posts'].anchor('left', 0.2), t['media'].anchor('right', 0.2)),
        (t['posts'].anchor('right', 0.2), t['links'].anchor('left', 0.2)),
        (t['posts'].anchor('bottom', 0.18), t['options'].anchor('top', 0.5),
         (0.6, -6.6)),
        (t['options'].anchor('bottom', 0.4), t['votes'].anchor('top', 0.4)),
        (t['posts'].anchor('bottom', 0.45), t['reactions'].anchor('top', 0.45)),
        (t['reactions'].anchor('bottom', 0.7), t['views'].anchor('top', 0.7)),
        (t['posts'].anchor('bottom', 0.8), t['comments'].anchor('top', 0.5),
         (15.6, -6.6)),
    ]

    render('posts.png', t, r, (11.6, 8.6))


# --------------------------------------------------------------------------
# Figure: a group, its wall and its shelf
# --------------------------------------------------------------------------

def groups():
    t = {
        'groups': Table('community_groups', 4.4, 0, 6.8, [
            ('id', 'bigint', 'PK'),
            ('kind', 'varchar(20)', ''),
            ('source_id', 'bigint', ''),
            ('name', 'varchar(150)', ''),
            ('slug', 'varchar(190)', ''),
            ('owner_id', 'bigint', 'FK'),
            ('posting_mode', 'varchar(20)', ''),
            ('visibility', 'varchar(20)', ''),
            ('archived_at', 'timestamp', ''),
        ]),
        'members': Table('community_group_members', -3.4, -0.8, 7.0, [
            ('group_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('role', 'varchar(20)', ''),
            ('joined_at', 'timestamp', ''),
        ]),
        'files': Table('community_group_files', -3.4, -5.0, 7.0, [
            ('id', 'bigint', 'PK'),
            ('group_id', 'bigint', 'FK'),
            ('uploaded_by', 'bigint', 'FK'),
            ('kind', 'varchar(20)', ''),
            ('title', 'varchar(190)', ''),
            ('path', 'varchar(500)', ''),
            ('disk', 'varchar(30)', ''),
            ('size', 'bigint', ''),
        ]),
        'notices': Table('notices', 12.4, -0.8, 6.6, [
            ('id', 'bigint', 'PK'),
            ('group_id', 'bigint', 'FK'),
            ('issuer_id', 'bigint', 'FK'),
            ('title', 'varchar(190)', ''),
            ('notice_type', 'varchar(80)', ''),
            ('audience', 'varchar(80)', ''),
            ('is_urgent', 'boolean', ''),
            ('status', 'varchar(30)', ''),
        ]),
        'reads': Table('notice_reads', 12.4, -6.2, 6.6, [
            ('notice_id', 'bigint', 'PK FK'),
            ('user_id', 'bigint', 'PK FK'),
            ('read_at', 'timestamp', ''),
        ]),
        'posts': Table('posts', 4.4, -6.4, 6.8, [
            ('id', 'bigint', 'PK'),
            ('group_id', 'bigint', 'FK'),
            ('user_id', 'bigint', 'FK'),
            ('body', 'text', ''),
            ('post_type', 'varchar(20)', ''),
        ]),
    }

    r = [
        (t['groups'].anchor('left', 0.2), t['members'].anchor('right', 0.3)),
        (t['groups'].anchor('left', 0.7), t['files'].anchor('right', 0.2)),
        (t['groups'].anchor('right', 0.2), t['notices'].anchor('left', 0.2)),
        (t['notices'].anchor('bottom', 0.4), t['reads'].anchor('top', 0.4)),
        (t['groups'].anchor('bottom', 0.4), t['posts'].anchor('top', 0.4)),
    ]

    render('groups.png', t, r, (11.6, 7.2))


def main():
    print('Drawing entity-relationship figures')
    academic()
    club_panel()
    posts()
    groups()


if __name__ == '__main__':
    main()

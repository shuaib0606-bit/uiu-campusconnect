**UIU CampusConnect**

Real Data Sources

Every club, notice and event now in the system, and where it came from

September 2026

<<<PAGEBREAK>>>

# 1. What this document is for

The sample data this project shipped with was invented. Six clubs that do
not exist, ten events nobody held, and eight notices nobody published.
That was enough to make the screens look occupied while the system was
being built, and not enough to show what the system is for.

This document records the replacement of that sample data with real
material taken from United International University's own public pages,
and it exists so that one question can always be answered: **is this row
real, and where did it come from?**

Three categories are used throughout, and nothing is allowed to sit
between them.

  --------------------------------------------------------------------------------
  **Category**       **Meaning**
  ------------------ -------------------------------------------------------------
  Sourced            Taken from a public UIU page. The title, the date and the
                     wording are the university's. The address it came from is
                     recorded next to it and stored in the row itself.

  Assigned           A real thing that the university does not publish the
                     detail of - which member of staff advises a club, for
                     instance. The surrounding fact is real; the specific value
                     is one this project chose, and is marked here.

  Dated by us        A real, named programme whose next date has not been
                     announced. The name is the university's; the date is a
                     placeholder, and the event says so on its own page.
  --------------------------------------------------------------------------------

Nothing in the system is invented and presented as sourced. Where a
detail could not be found it was left empty rather than filled in - the
clubs, for example, have no meeting time or room, because UIU does not
publish them.

## 1.1 How the data gets in

One command, which can be run again safely:

    php artisan uiu:seed-uiu-data --dry-run
    php artisan uiu:seed-uiu-data

It lives in app/Console/Commands/SeedUiuData.php, and every table in this
document is also a constant in that file. The command and this document
are meant to be read together: if they ever disagree, the command is what
the database has.

Clubs are matched by slug and notices and events by title, so a second run
updates the rows rather than duplicating them.

<<<PAGEBREAK>>>

# 2. Clubs

**Source:** https://www.uiu.ac.bd/campus-life/clubs-forums/
**Retrieved:** 21 September 2026
**Category:** Sourced (name, description, Facebook page) · Assigned
(category, moderator)

All 24 clubs, forums and societies UIU lists are now in the system. The
six invented ones are archived rather than deleted, because events, posts
and membership rows point at them; they no longer appear anywhere in the
interface.

The description of each club is shortened from the university's own
wording, and the club's Facebook address is stored with it, so anybody can
check the row against the source.

<<<CLUBTABLE>>>

## 2.1 What was assigned rather than sourced

**Category.** UIU lists its clubs as one flat list. The system groups
them - Technology, Culture, Career, Academic, Sports, Community - so the
directory can be filtered. The grouping is this project's.

**Moderator.** UIU does not publish which member of staff advises each
club. Each club is given a moderator round-robin from the faculty and
office accounts already in the database. Replacing one is a super
administrator's job, on the club page.

**Meeting time and place.** Not published, so left empty. The club page
simply omits the line.

**Logos.** The university's club logos could not be collected: they are
on Facebook pages and in image files this project has no way to fetch,
and they belong to the clubs. Every club therefore shows the generated
initials badge until somebody uploads the real one. Two ways to do that:

-   On the club page, hover the logo and choose **Change logo** - open to
    the moderator, the student panel, and a super administrator.

-   Or put the files in storage/app/seed-logos/ named after the club's
    slug - uiu-debate-club.png, uiu-robotics-club.png and so on - and run
    the command again. It picks up anything it finds and leaves clubs
    without a file alone.

<<<PAGEBREAK>>>

# 3. Notices

**Source:** https://www.uiu.ac.bd/notice/
**Retrieved:** 21 September 2026
**Category:** Sourced (title, date) · Assigned (type, priority)

Twelve notices, with the titles and dates the university board carries.
Each row's body states the publication date and links to the full notice,
so the system points at the original rather than copying it - the
attachments, the tables and the signatures stay where they belong.

The row's created_at is set to the real publication date, not the import
date, so the board sorts the way the university's does.

<<<NOTICETABLE>>>

## 3.1 What was assigned rather than sourced

**Type and priority.** The system files notices under ten types and three
priorities so they can be filtered. UIU's board does neither. The mapping
here is this project's reading of each title: an exam schedule is urgent,
a holiday notice is not.

**Audience.** Every imported notice is addressed to everyone. UIU's
notices are often narrower than that in practice, but the board does not
state the audience in a way that could be read automatically.

**Bodies.** The full text is not copied. Each notice carries its date and
its address, and the reader follows the link.

<<<PAGEBREAK>>>

# 4. Events

**Source:** https://www.uiu.ac.bd/event/
**Retrieved:** 21 September 2026
**Category:** Sourced (title, dates, club)

Twelve events with the titles and dates from the university's events
page. Most are in the past: that page is an archive, and this project is
not in a position to know what has not been announced yet.

<<<EVENTTABLE>>>

## 4.1 The upcoming programmes, and why they are different

An events page showing only things that already happened is not much of a
demonstration. So seven real, named club programmes were added with dates
this trimester.

**Every name below is real** - each one is the signature programme its
club names in its own description on the university page. **No date below
is real.** UIU has not announced this term's edition of any of them. Each
event says so in its own description on its page in the system, in those
words, so nobody reading the site is misled.

<<<UPCOMINGTABLE>>>

<<<PAGEBREAK>>>

# 5. Academic calendar and departments

## 5.1 Trimester

**Source:** https://www.uiu.ac.bd/academics/calendar/
**Category:** Sourced

The system previously carried an invented trimester. It now carries the
real one covering today:

  -----------------------------------------------------------------------
  **Field**                **Value**
  ------------------------ ----------------------------------------------
  Name                     Summer 2026

  Code                     2026-SUM

  First teaching day       4 July 2026

  Last teaching day        22 October 2026
  -----------------------------------------------------------------------

This is the undergraduate calendar. UIU publishes separate calendars for
graduate programmes and for the Bachelor of Pharmacy, which runs on
semesters rather than trimesters - Fall 2026 for B.Pharm. runs 3 August
2026 to 10 January 2027. The system holds one trimester at a time, so the
undergraduate one was taken.

The dates matter beyond looking right: the class groups count down to
twenty days after the last teaching day and then go read-only, so the
countdown a student sees is now measured against the real end of term.

## 5.2 Departments

**Source:** https://www.uiu.ac.bd/academics/
**Category:** Sourced

UIU's four schools and one institute, and the departments under them, are
the names the system now uses for the department field on an account and
for the Department tab in the feed.

  -----------------------------------------------------------------------
  **School**               **Departments**
  ------------------------ ----------------------------------------------
  School of Science and    Computer Science and Engineering, Data Science,
  Engineering              Electrical and Electronic Engineering, Civil
                           Engineering

  School of Business and   Business Administration, Accounting and
  Economics                Information Systems, Economics

  School of Humanities     English, Environment and Development Studies,
  and Social Sciences      Media Studies and Journalism

  School of Life Sciences  Pharmacy, Biotechnology and Genetic
                           Engineering

  Institute of Natural     (serves the schools above)
  Sciences                 
  -----------------------------------------------------------------------

<<<PAGEBREAK>>>

# 6. What is still invented, and deliberately so

Not everything in a demonstration should be real, and two things in
particular must not be.

**The people.** Every account on the system is invented - the students,
the faculty, the registrar desk. Real students' names, photographs,
student numbers and departments are personal data, and putting them into
a project database without their consent would be wrong whatever the
project was for. The accounts live on the demo.uiu.test domain precisely
so that one command can find and remove all of them.

**The conversation.** Posts, comments, private messages, marketplace
listings and lost property are all invented. They belong to the invented
people, and attributing a marketplace listing or a lost phone to a real
student would be a small fabrication with a real subject.

Two further things are invented because there is nothing public to copy:
club membership and panel designations, and the enrolment roster behind
the class groups. Section 7 covers what would replace the latter.

# 7. What would make this better

This is the list worth reading if the project continues.

## 7.1 The roster API

The single largest improvement. The class groups, enrolment, the
Department and My Batch feeds and the academic deadlines all currently
run off a CSV that somebody exports by hand. Everything downstream of it
already goes through one class, App\\Support\\GroupSync, so an API from
the university would replace the CSV reader and nothing else. Until then
the roster is the one part of the academic side that cannot be real.

## 7.2 Club logos and cover art

Described in 2.1. The blocker is not the code - the upload works, and the
command picks files up from a folder - it is having the files. The
quickest route is to ask each club for its logo, or to save them from the
Facebook pages listed in section 2 and drop them into
storage/app/seed-logos/.

## 7.3 A notice feed rather than a copy

The notices currently link back to uiu.ac.bd. A better version would read
the university's notice page on a schedule and keep itself in step - the
titles, dates and links are all in a consistent shape on that page, so a
small importer running nightly would work. That also removes the need to
re-run a seeding command whenever the board changes.

## 7.4 Real club officers

The panel handover feature is built and works, but the panels in the
system are invented. Real panel lists exist - the clubs publish them on
their own pages - and importing even two or three would make the feature
demonstrate itself instead of needing to be explained.

## 7.5 Permission to use the university's marks

The name, the logo and the club logos belong to UIU. For a course project
this is not usually raised, but if the system is ever shown outside the
course or deployed anywhere public, that permission is worth having in
writing first.

<<<PAGEBREAK>>>

# Appendix. Every source used

  --------------------------------------------------------------------------------
  **What it provided**                     **Address**
  ---------------------------------------- ---------------------------------------
  24 clubs, forums and societies: names,    https://www.uiu.ac.bd/campus-life/
  descriptions, Facebook pages, signature   clubs-forums/
  programmes                                

  12 notices: titles, dates, links          https://www.uiu.ac.bd/notice/

  12 events: titles, dates, organising      https://www.uiu.ac.bd/event/
  clubs                                     

  Academic calendar: the Summer 2026        https://www.uiu.ac.bd/academics/
  trimester dates, and the B.Pharm.         calendar/
  semester                                  

  Schools and departments                   https://www.uiu.ac.bd/academics/

  Campus life overview                      https://www.uiu.ac.bd/campus-life/
                                            student-life/
  --------------------------------------------------------------------------------

All six were retrieved on 21 September 2026. Where a page changes after
that date, the rows in the system keep the values recorded here until the
command is run again.

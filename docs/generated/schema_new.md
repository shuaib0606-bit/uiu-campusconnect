## 7.9 Coursework, class chat and the assistant
Six tables were added with the three newest features. Each pair follows
a rule already used elsewhere in the schema: one row per thing a person
does, owned by that person, and deleted with the thing it belongs to.

assignments hangs off community_groups rather than course_sections,
because the group is where a class already lives and where membership
is already checked. due_at is nullable, since some work has no fixed
deadline, and allow_late decides whether a passed deadline refuses new
work or only flags it. status is open or closed; closing stops new
submissions without touching any already handed in.

assignment_attachments and assignment_submissions have nearly the same
columns - a path, an original name, a size, a type - and are
deliberately two tables anyway, because they answer opposite questions
about who may read them. An attachment is a handout from the teacher to
the whole class. A submission is one student's work for the teacher and
nobody else. Keeping them apart puts that difference in the schema,
where no query can forget it, rather than in a kind column every
download would have to remember to check.

assignment_submissions is unique on (assignment_id, user_id): handing in
again replaces the row. is_late is written once, from the clock at the
moment the file arrives, and never recomputed - so moving a deadline
afterwards cannot retroactively change who was late.

group_messages is chat, kept out of posts on purpose. A post carries
media, links, polls, reactions, an edit history and a visibility
audience; a chat line is a sentence and a timestamp that arrives far more
often and is read oldest-first. Removed messages keep their row with
is_deleted set, so the conversation around them still reads.

ai_conversations and ai_messages hold the assistant's threads. The
second doubles as the audit trail: every answer records which provider
produced it, which tools it called, how many rounds it took, how long it
took, and whether the output guard had to remove anything.

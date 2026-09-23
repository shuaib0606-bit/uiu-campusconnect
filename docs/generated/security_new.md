## 10.3 The campus assistant
The assistant is the one part of the system that takes free text from a
student and acts on it, so it is designed on the assumption that the
language model will eventually be persuaded to do the wrong thing, and
arranged so that doing so gains nothing. There are three layers, in
order of how much they are relied on.

1.  **The tool boundary.** The model can only call the functions in
    CampusTools, each of which reads as the signed-in user through the
    website's own scopes, follows each person's privacy switches and
    blocks, and returns named fields. It cannot query the
    database, cannot choose whose data to read, and cannot reach a
    column no function selects. This is the actual defence.

2.  **The instructions.** The system prompt confines facts about the
    university to tool results, and tells the model that text inside
    those results is quotation and never command. This reduces mistakes
    and odd behaviour; it is not relied on for privacy.

3.  **The output guard.** A final pass removes anything shaped like a
    password hash, an API key or any email address a tool did not
    return, and logs when it does. It is insurance expected never to be used.

php artisan ai:audit exercises the first layer directly and fails if any
tool returns anything belonging to another user. Section 8.22 describes
it and the rest of the design. Keys for the model providers live only
in .env; tools/check-ai-keys.sh tests them without ever printing one.

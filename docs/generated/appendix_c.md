# Appendix C. Database tables

The 67 tables the migrations create, by the part of the system
that owns them. Chapter 7 explains the design; this is the index.

  -----------------------------------------------------------------------------------------
  **Group**                  **Tables**
  -------------------------- --------------------------------------------------------------
  Accounts and access        users, user_profiles, password_reset_requests,
                             connection_applications, sessions, password_reset_tokens

  The social graph           friendships, user_follows, user_blocks, user_mutes, messages,
                             message_blocks

  Posts                      posts, post_media, post_links, post_edit_history,
                             post_reactions, post_saves, post_shares, post_views,
                             hidden_posts, poll_options, poll_votes, mentions

  Comments                   comments, comment_likes

  Academic records           trimesters, course_sections, course_enrollments

  Coursework                 assignments, assignment_attachments, assignment_submissions

  Clubs and events           clubs, club_members, club_membership_requests,
                             club_role_requests, club_panel_changes, club_panel_members,
                             events, event_registrations, event_interests, event_checkins

  Notices                    notices, notice_attachments, notice_reads

  Groups and boards          community_groups, community_group_members,
                             community_group_files, group_messages, marketplace_items,
                             lost_found_items, lost_found_claims

  Campus assistant           ai_conversations, ai_messages

  Pictures                   media

  Moderation and the trail   reports, comment_reports, profile_reports, moderation_notes,
                             audit_logs, notifications
  -----------------------------------------------------------------------------------------

Framework tables: cache, cache_locks, jobs, job_batches, failed_jobs.

Retired: section_announcements - superseded by course_sections; see 11.2.

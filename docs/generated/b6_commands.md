## B.6 Console commands
Run with php artisan. The ones that write take --dry-run first.

  -------------------------------------------------------------------------------
  **Command**                    **Purpose**
  ------------------------------ ------------------------------------------------
  uiu:trimester                  Create or update a trimester

  uiu:import-faculty             Create or update faculty accounts from a CSV

  uiu:import-enrolments          Create course sections and enrolments from a
                                 CSV, and sync the class groups

  uiu:sync-club-groups           Create or refresh the group behind each active
                                 club

  uiu:archive-course-groups      Archive class groups whose trimester ended more
                                 than the grace period ago

  uiu:secure-group-files         Move group files off the publicly readable disk

  uiu:demo-content               Create sample students, clubs, events and
                                 listings for testing

  uiu:wipe-demo                  Remove all seeded content and all non-admin
                                 accounts

  ai:audit                       Prove the campus assistant cannot return
                                 anything the asker may not see

  uiu:seed-uiu-data              Replace the sample clubs, notices and events
                                 with real UIU ones
  -------------------------------------------------------------------------------

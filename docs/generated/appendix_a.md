# Appendix A. Route reference

Every URL in the application, in the order it appears in
routes/web.php - 138 in all. The Filament panel registers its own
routes under /admin and is not listed here.

Routes above the first authenticated group are open to guests;
everything from /feed onwards sits inside the auth middleware group.

  ---------------------------------------------------------------------------------------------------------------------------------
  **Method** **URL**                                      **Route name**                   **Handler**
  ---------- -------------------------------------------- -------------------------------- ----------------------------------------
  GET        /                                            welcome                          FeedController@welcome

  POST       /theme                                       theme.update                     ThemeController@__invoke

  GET        /signin                                      login                            Auth/LoginController@create

  POST       /signin                                      login.attempt                    Auth/LoginController@store

  GET        /forgot-password                             password.request                 PasswordResetRequestController@create

  POST       /forgot-password                             password.request.store           PasswordResetRequestController@store

  POST       /logout                                      logout                           Auth/LoginController@destroy

  GET        /feed                                        feed.index                       FeedController@index

  GET        /feed/more                                   feed.more                        FeedController@more

  POST       /feed/seen                                   feed.seen                        FeedController@markSeen

  POST       /posts                                       posts.store                      PostController@store

  GET        /posts/media/{media}                         posts.media.download             PostController@downloadMedia

  GET        /posts/{post}                                posts.show                       PostController@show

  PUT        /posts/{post}                                posts.update                     PostController@update

  DELETE     /posts/{post}                                posts.destroy                    PostController@destroy

  POST       /posts/{post}/save                           posts.save                       PostController@toggleSave

  POST       /posts/{post}/hide                           posts.hide                       PostController@hide

  POST       /posts/{post}/share                          posts.share                      PostController@share

  POST       /posts/{post}/report                         posts.report                     PostController@report

  POST       /posts/{post}/react                          posts.react                      ReactionController@store

  POST       /posts/{post}/vote                           posts.vote                       PostController@vote

  POST       /posts/{post}/comments                       comments.store                   CommentController@store

  PUT        /comments/{comment}                          comments.update                  CommentController@update

  DELETE     /comments/{comment}                          comments.destroy                 CommentController@destroy

  POST       /comments/{comment}/react                    comments.react                   CommentController@react

  POST       /comments/{comment}/report                   comments.report                  CommentController@report

  GET        /me                                          profile.me                       ProfileController@me

  GET        /profile/{user}                              profile.show                     ProfileController@show

  GET        /settings/profile                            profile.edit                     ProfileController@edit

  PUT        /settings/profile                            profile.update                   ProfileController@update

  POST       /profile/{user}/report                       profile.report                   ProfileController@report

  GET        /settings/password                           settings.password                SettingsController@passwordForm

  PUT        /settings/password                           settings.password.update         SettingsController@updatePassword

  GET        /network                                     network.index                    NetworkController@index

  POST       /network/{user}/request                      network.request                  NetworkController@request

  POST       /network/{user}/accept                       network.accept                   NetworkController@accept

  POST       /network/{user}/decline                      network.decline                  NetworkController@decline

  POST       /network/{user}/remove                       network.remove                   NetworkController@remove

  POST       /network/{user}/follow                       network.follow                   NetworkController@follow

  POST       /network/{user}/block                        network.block                    NetworkController@block

  POST       /network/{user}/unblock                      network.unblock                  NetworkController@unblock

  POST       /network/{user}/mute                         network.mute                     NetworkController@mute

  GET        /connect-requests                            connect-requests.index           ConnectionRequestController@index

  POST       /connect-requests/{user}                     connect-requests.apply           ConnectionRequestController@apply

  POST       /connect-requests/{application}/review       connect-requests.review          ConnectionRequestController@review

  POST       /connect-requests/{application}/cancel       connect-requests.cancel          ConnectionRequestController@cancel

  POST       /connect-requests/{application}/suspend-student connect-requests.suspend-student ConnectionRequestController@suspendStudent

  GET        /messages                                    messages.index                   MessageController@index

  GET        /messages/{user}                             messages.show                    MessageController@show

  POST       /messages/{user}                             messages.store                   MessageController@store

  GET        /clubs                                       clubs.index                      ClubController@index

  GET        /clubs/{club:slug}                           clubs.show                       ClubController@show

  POST       /clubs/{club}/request                        clubs.request                    ClubController@requestMembership

  POST       /clubs/{club}/requests/{membershipRequest}/review clubs.review-request             ClubController@reviewRequest

  POST       /clubs/{club}/leave                          clubs.leave                      ClubController@leave

  POST       /clubs/{club}/panel                          clubs.panel.propose              ClubController@proposePanel

  POST       /clubs/{club}/panel/{panelChange}/review     clubs.panel.review               ClubController@reviewPanel

  POST       /clubs/{club}/panel/{panelChange}/cancel     clubs.panel.cancel               ClubController@cancelPanel

  PUT        /clubs/{club}/moderator                      clubs.moderator                  ClubController@changeModerator

  POST       /clubs/{club}/logo                           clubs.logo                       ClubController@updateLogo

  DELETE     /clubs/{club}/logo                           clubs.logo.remove                ClubController@removeLogo

  POST       /clubs/{club}/officers                       clubs.officers.nominate          ClubController@nominateOfficer

  POST       /clubs/{club}/officers/{roleRequest}/review  clubs.officers.review            ClubController@reviewOfficer

  POST       /clubs/{club}/officers/{roleRequest}/cancel  clubs.officers.cancel            ClubController@cancelOfficer

  GET        /events                                      events.index                     EventController@index

  GET        /events/{event}                              events.show                      EventController@show

  POST       /events                                      events.store                     EventController@store

  POST       /events/{event}/register                     events.register                  EventController@register

  POST       /events/{event}/unregister                   events.unregister                EventController@unregister

  POST       /events/{event}/interest                     events.interest                  EventController@interest

  POST       /events/{event}/checkin                      events.checkin                   EventController@checkin

  GET        /notices                                     notices.index                    NoticeController@index

  POST       /notices                                     notices.store                    NoticeController@store

  GET        /notices/{notice}                            notices.show                     NoticeController@show

  GET        /assistant                                   assistant.index                  AssistantController@index

  POST       /assistant                                   assistant.ask                    AssistantController@ask

  GET        /assistant/{conversation}                    assistant.show                   AssistantController@index

  DELETE     /assistant/{conversation}                    assistant.destroy                AssistantController@destroy

  GET        /administration                              administration.index             CourseSectionController@index

  POST       /administration                              administration.store             CourseSectionController@store

  PUT        /administration/{section}                    administration.update            CourseSectionController@update

  GET        /developer-admin                             admin-center.index               AdminCenterController@index

  GET        /password-requests                           password-requests.index          PasswordResetRequestController@index

  POST       /password-requests/{passwordRequest}/review  password-requests.review         PasswordResetRequestController@review

  GET        /user-approvals                              user-approvals.index             UserApprovalController@index

  POST       /user-approvals/{target}/review              user-approvals.review            UserApprovalController@review

  GET        /moderation                                  moderation.index                 ModerationController@index

  POST       /moderation/posts/{report}/review            moderation.posts.review          ModerationController@reviewPost

  POST       /moderation/comments/{commentReport}/review  moderation.comments.review       ModerationController@reviewComment

  POST       /moderation/profiles/{profileReport}/review  moderation.profiles.review       ModerationController@reviewProfile

  GET        /groups                                      groups.index                     GroupController@index

  POST       /groups                                      groups.store                     GroupController@store

  GET        /groups/{group:slug}                         groups.show                      GroupController@show

  POST       /groups/{group}/join                         groups.join                      GroupController@join

  POST       /groups/{group}/leave                        groups.leave                     GroupController@leave

  PUT        /groups/{group}                              groups.update                    GroupController@update

  DELETE     /groups/{group}                              groups.destroy                   GroupController@destroy

  POST       /groups/{group}/members/{user}/approve       groups.members.approve           GroupController@approveMember

  DELETE     /groups/{group}/members/{user}               groups.members.remove            GroupController@removeMember

  DELETE     /groups/{group}/files/{file}                 groups.files.delete              GroupController@deleteFile

  POST       /groups/{group}/publish                      groups.publish                   GroupController@publish

  POST       /groups/{group}/notices                      groups.notices.store             GroupController@publishNotice

  DELETE     /groups/{group}/notices/{notice}             groups.notices.delete            GroupController@deleteNotice

  POST       /groups/{group}/posting-mode                 groups.posting-mode              GroupController@togglePosting

  GET        /groups/{group}/files/{file}                 groups.files.download            GroupController@downloadFile

  POST       /groups/{group}/assignments                  assignments.store                AssignmentController@store

  PUT        /groups/{group}/assignments/{assignment}     assignments.update               AssignmentController@update

  DELETE     /groups/{group}/assignments/{assignment}     assignments.destroy              AssignmentController@destroy

  POST       /groups/{group}/assignments/{assignment}/toggle assignments.toggle               AssignmentController@toggle

  POST       /groups/{group}/assignments/{assignment}/submit assignments.submit               AssignmentController@submit

  DELETE     /groups/{group}/assignments/{assignment}/submission assignments.withdraw             AssignmentController@withdraw

  GET        /groups/{group}/assignments/{assignment}/submissions/{submission} assignments.download             AssignmentController@download

  GET        /groups/{group}/chat                         groups.chat.index                GroupChatController@index

  POST       /groups/{group}/chat                         groups.chat.store                GroupChatController@store

  DELETE     /groups/{group}/chat/{message}               groups.chat.destroy              GroupChatController@destroy

  POST       /groups/{group}/assignments/{assignment}/attachments assignments.attach               AssignmentController@attach

  DELETE     /groups/{group}/assignments/{assignment}/attachments/{attachment} assignments.detach               AssignmentController@detach

  GET        /groups/{group}/assignments/{assignment}/attachments/{attachment} assignments.attachment           AssignmentController@downloadAttachment

  GET        /marketplace                                 marketplace.index                MarketplaceController@index

  POST       /marketplace                                 marketplace.store                MarketplaceController@store

  GET        /marketplace/{item}                          marketplace.show                 MarketplaceController@show

  POST       /marketplace/{item}/sold                     marketplace.sold                 MarketplaceController@markSold

  GET        /lost-found                                  lost-found.index                 LostFoundController@index

  POST       /lost-found                                  lost-found.store                 LostFoundController@store

  GET        /lost-found/{item}                           lost-found.show                  LostFoundController@show

  POST       /lost-found/{item}/claim                     lost-found.claim                 LostFoundController@claim

  POST       /lost-found/{item}/resolve                   lost-found.resolve               LostFoundController@resolve

  POST       /lost-found/{item}/reopen                    lost-found.reopen                LostFoundController@reopen

  POST       /lost-found/claims/{claim}/review            lost-found.claims.review         LostFoundController@reviewClaim

  POST       /media/{media}/cover                         media.cover                      MediaController@cover

  DELETE     /media/{media}                               media.destroy                    MediaController@destroy

  POST       /media/{type}/{id}                           media.store                      MediaController@store

  GET        /search                                      search.index                     SearchController@index

  GET        /users/search                                users.search                     UserSearchController@__invoke

  GET        /notifications                               notifications.index              NotificationController@index

  POST       /notifications/read-all                      notifications.read-all           NotificationController@markAllRead

  POST       /notifications/{notification}/read           notifications.read               NotificationController@read

  GET        /notifications/{notification}/go             notifications.go                 NotificationController@go
  ---------------------------------------------------------------------------------------------------------------------------------

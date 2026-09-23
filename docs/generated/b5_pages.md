## B.5 React pages
Every file below is under resources/js. A page is resolved by the name
the controller passes to Inertia::render.

  -------------------------------------------------------------------------------
  **File**                           **Screen**
  ---------------------------------- --------------------------------------------
  Pages/Network/Index.jsx            People directory

  Pages/ConnectRequests/Index.jsx    Connection applications, and club officer
                                     nominations awaiting the nominee

  Pages/Clubs/Index.jsx              Club directory

  Pages/Events/Index.jsx             Event list

  Pages/Groups/Index.jsx             Group directory, split by class, club and
                                     community

  Pages/Notices/Index.jsx            Notice board

  Pages/Notifications/Index.jsx      Notification centre

  Pages/Marketplace/Index.jsx        Marketplace listings

  Pages/LostFound/Index.jsx          Lost and found board

  Pages/Admin/Center.jsx             Administrative home page

  Pages/Admin/UserApprovals.jsx      Account approval queue

  Pages/Admin/Moderation.jsx         Report review queue

  Pages/Admin/PasswordRequests.jsx   Password recovery queue

  Pages/Administration/Index.jsx     Course sections for a trimester

  Pages/Settings/Password.jsx        Account settings

  Pages/Assistant/Index.jsx          The campus assistant: conversation,
                                     suggestions and recent threads

  chat.js                            Class chat component: live over the socket,
                                     polling when there is none

  echo.js                            Loads the websocket client on demand, and
                                     reports honestly when there is no server

  Layouts/AppLayout.jsx              Sidebar, header, flash banner, mobile bar

  Components/Icon.jsx                The same icon set the Blade pages use

  Components/UserAvatar.jsx          Photo or initials chip

  Components/Pagination.jsx          Pager matching the Blade one

  Components/icons.js                The icon path data

  inertia.jsx                        Entry point that mounts the pages
  -------------------------------------------------------------------------------

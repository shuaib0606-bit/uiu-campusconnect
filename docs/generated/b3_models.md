## B.3 Models by table group
  -------------------------------------------------------------------------------
  **Group**              **Models**
  ---------------------- --------------------------------------------------------
  Accounts               User, UserProfile, PasswordResetRequest,
                         ConnectionApplication

  Social graph           Friendship, UserBlock, UserMute, Message, MessageBlock

  Posts                  Post, PostMedia, PostLink, PostEditHistory,
                         PostReaction, PostSave, PostShare, HiddenPost, PostView,
                         PollOption, PollVote, Mention

  Comments               Comment, CommentLike

  Academic records       Trimester, CourseSection, CourseEnrollment

  Coursework             Assignment, AssignmentAttachment, AssignmentSubmission

  Clubs and events       Club, ClubMembershipRequest, ClubRoleRequest,
                         ClubPanelChange, ClubPanelMember, Event, EventCheckin

  Notices                Notice, NoticeAttachment

  Groups and boards      CommunityGroup, CommunityGroupFile, GroupMessage,
                         MarketplaceItem, LostFoundItem, LostFoundClaim

  Campus assistant       AiConversation, AiMessage

  Pictures               Media

  Moderation             Report, CommentReport, ProfileReport, ModerationNote,
                         AuditLog, Notification

  Retired                SectionAnnouncement
  -------------------------------------------------------------------------------

App\Support holds the classes that are not models but are not
controllers either - the rules several controllers share.

  -------------------------------------------------------------------------------
  **Support class**      **What it decides**
  ---------------------- --------------------------------------------------------
  AcademicCalendar.php   Trimester dates, and the deadlines card that reads them

  ClubPanel.php          Panel succession: who holds what now, and applying a
                         whole new panel at once

  CoverImage.php         Picking and setting the cover picture of a gallery

  Gallery.php            Assembling a set of pictures for a page

  GroupSync.php          The one place a course section or club becomes a group
                         and its membership

  ImageResizer.php       Thumbnails and size limits for uploaded pictures

  LinkPreview.php        Fetching the title, description and image behind a
                         posted link

  Mentions.php           Finding @names in text and turning them into mention
                         rows

  Notifier.php           Creating notifications without every controller knowing
                         the shape of one

  ShuttleSchedule.php    The bus timetable behind the shuttle card
  -------------------------------------------------------------------------------

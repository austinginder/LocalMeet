# ![](img/LocalMeet-logo.png)

## Self-starting local meetup groups

LocalMeet is an open source meetup platform built as a WordPress plugin. It turns a WordPress site into a full-featured, self-hosted alternative to Meetup.com — with groups, events, RSVPs, email notifications, and a single-page app frontend.

> **Note:** LocalMeet takes over the entire frontend of your WordPress site. Only install on a dedicated WordPress instance.

### Features

**Groups**
- Create and manage meetup groups with Markdown descriptions and custom slugs
- Invite-based group creation — admins send invites with a group allowance, recipients can start their own groups
- Member directory with join/leave tracking and CSV export
- Per-group email notification preferences (members can mute notifications or one-click unsubscribe)
- Configurable reply-to address for group emails
- Search across all groups

**Events**
- Create events with name, date/time, end time, location, description, and cover image
- Recurring events — weekly, biweekly, or monthly with automatic instance generation
- Event capacity limits with RSVP enforcement
- Cancel events with member notifications
- Event search within a group
- Markdown support for descriptions and summaries
- iCal calendar attachments via spatie/icalendar-generator

**RSVPs**
- Logged-in users toggle going/not going instantly
- Anonymous users RSVP via email verification — no account required upfront
- Capacity checks enforced at RSVP time for both flows

**Comments**
- Members can post, edit, and delete comments on events (Markdown supported)
- Comment moderation — organizers can approve or reject comments
- Organizer notifications on new comments

**Email notifications**
- Event announcements to group members
- Freeform group notices
- Organizer alerts when members join, leave, or comment
- Member notifications when a group is deleted
- All verification flows (RSVP, join group, create group) use secure email tokens

**Saved locations**
- Groups can maintain a library of reusable locations (name, address, notes)
- Select from saved locations when creating events

**Media**
- Organizers can upload cover images for events
- Per-user media library

**Accounts**
- Email-based signup with automatic account creation
- Password set/reset flows
- Profile management (name, email, avatar via Gravatar)
- First-time password prompt for users created through email verification

**Security**
- Parameterized SQL queries throughout (no raw string interpolation)
- Rate limiting on login, password reset, RSVP, group creation, announcements, and join requests
- Explicit permission callbacks on every REST endpoint
- Proper HTTP methods (POST for mutations, DELETE for deletions)
- Secure token generation with `random_bytes()`

**Frontend**
- Single-page app built with Alpine.js
- Server-side prerendering for SEO
- Responsive design with mobile support
- Paginated groups, events, and comments

### Requirements

- PHP 7.4+
- WordPress 5.0+
- A dedicated WordPress installation (LocalMeet overrides the site frontend)

### Installation

1. Upload the `localmeet` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Run the database migration: `LocalMeet\DB::upgrade()`

### License

[MIT](license.txt)

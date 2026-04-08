# Changelog

## v2 — April 8, 2026

### New Features

- **Invite system** — Admins can invite users via email with a configurable group allowance, letting invited users create their own groups without admin intervention. New `Invites` model and REST endpoints for creating, listing, and accepting invites.
- **Recurring events** — Events can be created with weekly, biweekly, or monthly recurrence rules. A new `Recurrence` class generates up to 8 future instances automatically on creation.
- **Event cancellation** — Organizers can cancel events (soft-delete via `cancelled_at` timestamp) with an optional notice sent to group members.
- **Event capacity** — Events support an optional attendee cap. RSVP is blocked when the event is full, with checks enforced for both logged-in and email-verified attendees.
- **Event end times** — Events now support an end time (`event_end_at`) in addition to the start time.
- **Event images** — Organizers can attach a cover image to events. New media upload and media library endpoints let subscribers upload images.
- **Saved locations** — Groups can manage a library of reusable locations (name, address, notes) via new `Locations` model and CRUD endpoints.
- **Group notices** — Organizers can send freeform email notices to all group members.
- **Group and event search** — New search endpoints for groups (`/groups/search`) and events within a group (`/group/{id}/events/search`) with `LIKE`-based full-text matching.
- **Member export** — Organizers can export group members as CSV (first name, last name, email, status, join/leave dates).
- **Email notification preferences** — Members can mute email notifications per group via an in-app toggle or one-click unsubscribe link. New `email_notifications` column on the members table.
- **Comment moderation** — Comments now carry a `status` field (approved/pending). Organizers can approve or reject comments. Pending comments are hidden from non-owners.
- **Organizer notifications** — Organizers receive email notifications when a new member joins, a member leaves, or a new comment is posted on their event.
- **Group deletion notifications** — Members are emailed when a group they belong to is deleted.
- **My Groups endpoint** — Logged-in users can fetch their group memberships via the `myGroups` command.
- **Current user endpoint** — New `currentUser` command on the login route returns the authenticated user without requiring a sign-in flow.
- **Pagination** — Groups list, event lists (upcoming/past), and comments all support server-side pagination with `page`, `per_page`, `events_per_page`, and `comments_per_page` parameters.
- **First/last name on profile** — Account updates now save `first_name` and `last_name` fields.
- **Start Group page** — New `/start-group` route and rewrite rules.
- **Admin invites page** — New `/admin/invites` route for managing invites.

### Security

- **SQL injection prevention** — Rewrote the entire `DB` class to use `$wpdb->prepare()` with parameterized queries across all methods (`where`, `upcoming`, `past`, `all`, `fetch`, `mine`, `select`, `valid_check`, `where_compare`, `search`). Eliminated all raw string interpolation in SQL.
- **Rate limiting** — New `RateLimiter` class using WordPress transients. Applied to login, password reset, RSVP, group creation, announcements, notices, and join requests.
- **Permission callbacks** — Every REST route now has an explicit `permission_callback`. Authenticated-only actions use `is_user_logged_in`, public routes use `__return_true`.
- **Proper HTTP methods** — Delete endpoints changed from `GET` to `DELETE`. Announce endpoint changed from `GET` to `POST`.
- **Ownership checks fixed** — Replaced broken `! $user->user_id() == $group->owner_id` (always true due to operator precedence) with correct `$user->user_id() != $group->owner_id` across all permission checks.
- **Token generation** — Replaced `openssl_random_pseudo_bytes(16)` with `random_bytes(32)` for all verification tokens.
- **Member token verification** — Replaced `md5()` hash comparison with `wp_hash()` for member leave/get tokens.
- **Username generation** — New users get a clean username derived from their email prefix instead of using the full email address as a login.
- **Admin bar and dashboard lockdown** — Non-admin users are redirected away from `wp-admin` and the admin bar is hidden for subscribers.
- **Group creation permissions** — Group creation is now gated by invite-based allowance instead of requiring admin role.
- **Comment membership check** — Commenting now requires active group membership, not just being logged in.
- **Input validation** — Event creation validates required fields (name, date, time) and rejects past dates. Sort columns and orders are whitelisted in DB queries.

### Improvements

- **Structured locations** — Event locations are stored as JSON (`name` + `address`) instead of a plain string, with backward-compatible parsing of legacy values.
- **Event update change detection** — When an event's time or location changes, the API returns a suggested notice subject/message for the organizer to send.
- **Slug regeneration** — Event slugs are regenerated when the event name changes during an update.
- **Mailer consolidation** — Inline `wp_mail()` calls throughout the codebase replaced with static methods on the `Mailer` class (`send_rsvp_verification`, `send_group_verification`, `send_member_join_verification`, `send_notice`, `send_invite`, `notify_organizer_new_member`, `notify_organizer_member_left`, `notify_organizer_new_comment`, `notify_members_group_deleted`).
- **Attendee display names** — Falls back to `display_name` when first/last name is empty. Strips `user_id` from attendee responses.
- **Attendee self-identification** — Attendee lists include an `is_me` flag for the current user.
- **Account class** — Expanded with password setting (with validation), password-needed check, and group membership listing.
- **DB class** — Added `count_where`, `count_upcoming`, `count_past`, `count_all`, and `search` methods. `where`, `upcoming`, and `past` accept `$limit` and `$offset` parameters.
- **Group fetch** — Returns `upcoming_total`, `past_total`, `is_member`, `email_notifications`, and `can_create_group` fields.
- **Group request bugfix** — Fixed `$request->group_request_id` referencing wrong variable (was `$request`, should be `$r`).
- **Comment delete permission fix** — Changed `||` to `&&` so non-admin users can delete their own comments.
- **Database schema v9** — Added `event_end_at`, `capacity`, `recurrence_rule`, `recurrence_parent_id`, `image_id`, `cancelled_at` to events table. Added `email_notifications` and `left_at` to members table. Added `status` to comments table. New `localmeet_locations` and `localmeet_invites` tables.

---

## v1 — April 6, 2022

Initial release of LocalMeet — a self-starting local meetups plugin for WordPress.

### Core Features

- **Groups** — Create, update, and delete meetup groups with Markdown descriptions, custom slugs, and email reply-to settings.
- **Events** — Create, update, and delete events within groups. Events have a name, date/time, location, description, summary (Markdown), and unique slug.
- **RSVP** — Logged-in users can mark going/not going. Anonymous users can RSVP via email verification.
- **Email verification flows** — Token-based email verification for group creation, joining groups, and RSVPing to events.
- **Members** — Users join and leave groups. Membership tracked with active status.
- **Event comments** — Members can post, edit, and delete comments on events with Markdown support.
- **Event announcements** — Organizers can send email announcements about events to group members.
- **iCal support** — Calendar file generation via `spatie/icalendar-generator`.
- **Password reset** — Users can request a password reset via email.
- **Profiles** — User profile page with avatar, display name, and email. Password-not-set prompt for users created via email verification.
- **Server-side prerender** — Basic server prerendering for SEO and initial page loads.
- **404 page** — Custom not-found page.
- **SPA routing** — Vue.js + Vuetify single-page app with client-side routing for groups, events, profiles, and find-group pages.
- **REST API** — Full set of WordPress REST endpoints under `localmeet/v1/` for all CRUD operations.
- **Custom database tables** — Dedicated tables for events, groups, members, attendees, comments, organizations, and request queues.
- **Mailer class** — Centralized email sending with HTML templates and iCal attachments.

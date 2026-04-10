<?php

namespace LocalMeet;

class Mailer {

    static public function send( $email, $subject, $content, $extra_headers = [], $attachments = [] ) {
        $headers = array_merge( [ "Content-Type: text/html; charset=UTF-8" ], $extra_headers );
        wp_mail( $email, $subject, $content, $headers, $attachments );
    }

    static public function action_button_public( $url, $text ) {
        return self::action_button( $url, $text );
    }

    static public function send_email_with_layout_public( $to, $subject, $headline, $subheadline, $main_content_html, $extra_headers = [], $attachments = [] ) {
        self::send_email_with_layout( $to, $subject, $headline, $subheadline, $main_content_html, $extra_headers, $attachments );
    }

    private static function action_button( $url, $text ) {
        $brand_color = '#2849c5';
        return "
            <div style='text-align: center; margin: 30px 0;'>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 6px; background-color: {$brand_color};'>
                            <a href='{$url}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 6px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 14px 32px; text-decoration: none;'>{$text}</a>
                        </td>
                    </tr>
                </table>
            </div>
        ";
    }

    private static function send_email_with_layout( $to, $subject, $headline, $subheadline, $main_content_html, $extra_headers = [], $attachments = [] ) {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url();
        $logo_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'img/LocalMeet-logo.png';

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #4a5568;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td style='padding: 40px 20px; text-align: center;'>

                        <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); overflow: hidden;'>
                            <tr>
                                <td style='padding: 40px; text-align: center; border-bottom: 1px solid #edf2f7;'>
                                    <h1 style='margin: 0 0 10px; font-size: 22px; font-weight: 700; color: #2d3748;'>{$headline}</h1>
                                    <p style='margin: 0; font-size: 15px; color: #718096;'>{$subheadline}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px; text-align: left; font-size: 15px; line-height: 1.6; color: #4a5568;'>
                                    {$main_content_html}
                                </td>
                            </tr>
                        </table>

                        <div style='margin-top: 30px; text-align: center;'>
                            <a href='{$site_url}'><img src='{$logo_url}' alt='{$site_name}' style='max-height: 60px; width: auto;'></a>
                        </div>

                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";

        self::send( $to, $subject, $message, $extra_headers, $attachments );
    }

    static public function send_group_verification( $email, $group_name, $verify_url ) {
        $button = self::action_button( $verify_url, 'Verify and Create Group' );
        $content = "
            <p>You're almost ready to start your group <strong>{$group_name}</strong>.</p>
            <p>Click the button below to verify your email and create the group.</p>
            {$button}
            <p style='font-size: 13px; color: #a0aec0;'>If you didn't request this, you can safely ignore this email.</p>
        ";
        self::send_email_with_layout( $email, "Verify your group: {$group_name}", 'Verify Your New Group', $group_name, $content );
    }

    static public function send_member_join_verification( $email, $group_name, $verify_url ) {
        $button = self::action_button( $verify_url, 'Confirm Joining Group' );
        $content = "
            <p>Thanks for your interest in joining <strong>{$group_name}</strong>.</p>
            <p>Click the button below to confirm your membership.</p>
            {$button}
            <p style='font-size: 13px; color: #a0aec0;'>If you didn't request this, you can safely ignore this email.</p>
        ";
        self::send_email_with_layout( $email, "Join group: {$group_name}", 'Confirm Group Membership', $group_name, $content );
    }

    static public function send_rsvp_verification( $email, $event_name, $verify_url ) {
        $button = self::action_button( $verify_url, 'Confirm Your RSVP' );
        $content = "
            <p>Thanks for your interest in attending <strong>{$event_name}</strong>.</p>
            <p>Click the button below to confirm your attendance.</p>
            {$button}
            <p style='font-size: 13px; color: #a0aec0;'>If you didn't request this, you can safely ignore this email.</p>
        ";
        self::send_email_with_layout( $email, "RSVP: {$event_name}", 'Confirm Your RSVP', $event_name, $content );
    }

    static public function send_invite( $email, $group_allowance, $accept_url ) {
        $button     = self::action_button( $accept_url, 'Accept Invitation' );
        $group_word = $group_allowance == 1 ? 'group' : 'groups';
        $content    = "
            <p>You've been invited to LocalMeet with permission to create <strong>{$group_allowance} {$group_word}</strong>.</p>
            <p>Click the button below to accept your invitation and get started.</p>
            {$button}
        ";
        self::send_email_with_layout( $email, "You're invited to LocalMeet", "You're Invited", 'Start your own meetup group', $content );
    }

    static public function notify_organizer_new_member( $group_id, $member_user_id ) {
        $group = ( new Groups )->get( $group_id );
        if ( ! $group || empty( $group->owner_id ) ) return;
        $owner = get_userdata( $group->owner_id );
        if ( ! $owner ) return;
        $member  = get_userdata( $member_user_id );
        $name    = trim( "{$member->first_name} {$member->last_name}" ) ?: $member->display_name;
        $members = ( new Members )->where( [ 'group_id' => $group_id, 'active' => 1 ] );
        $count   = $members ? count( $members ) : 0;
        $group_url = home_url( "/group/{$group->slug}" );
        $button    = self::action_button( $group_url, 'View Group' );
        $content   = "
            <p><strong>{$name}</strong> just joined your group <strong>{$group->name}</strong>.</p>
            <p>Your group now has <strong>{$count} member" . ( $count !== 1 ? 's' : '' ) . "</strong>.</p>
            {$button}
        ";
        self::send_email_with_layout( $owner->user_email, "New member: {$name} joined {$group->name}", 'New Member', $group->name, $content );
    }

    static public function notify_members_group_deleted( $group, $members ) {
        if ( empty( $members ) ) return;
        $site_name = get_bloginfo( 'name' );
        foreach ( $members as $member ) {
            $user = get_userdata( $member->user_id );
            if ( ! $user ) continue;
            $greeting = $user->first_name ?: $user->display_name;
            $content  = "
                <p>Hi {$greeting},</p>
                <p>The group <strong>{$group->name}</strong> has been removed by its organizer.</p>
                <p>You are no longer a member of this group. If you believe this was a mistake, please reach out to the group organizer.</p>
            ";
            self::send_email_with_layout( $user->user_email, "{$group->name} has been removed", 'Group Removed', $group->name, $content );
        }
    }

    static public function notify_organizer_new_comment( $event_id, $comment_text, $commenter_user_id ) {
        $event = ( new Events )->get( $event_id );
        if ( ! $event ) return;
        $group = ( new Groups )->get( $event->group_id );
        if ( ! $group || empty( $group->owner_id ) ) return;
        // Don't notify if the organizer is the one commenting
        if ( (int) $group->owner_id === (int) $commenter_user_id ) return;
        $owner = get_userdata( $group->owner_id );
        if ( ! $owner ) return;
        $commenter = get_userdata( $commenter_user_id );
        $name      = trim( "{$commenter->first_name} {$commenter->last_name}" ) ?: $commenter->display_name;
        $excerpt   = mb_substr( wp_strip_all_tags( $comment_text ), 0, 200 );

        $event_url = home_url( "/group/{$group->slug}/{$event->slug}" );
        $button    = self::action_button( $event_url, 'View Event' );
        $content   = "
            <p><strong>{$name}</strong> commented on <strong>{$event->name}</strong>:</p>
            <blockquote style='border-left: 3px solid #d1d5db; padding-left: 12px; margin: 16px 0; color: #6b7280;'>{$excerpt}</blockquote>
            {$button}
        ";
        self::send_email_with_layout( $owner->user_email, "New comment on {$event->name}", 'New Comment', "{$event->name} - {$group->name}", $content );
    }

    static public function notify_organizer_member_left( $group_id, $member_user_id ) {
        $group = ( new Groups )->get( $group_id );
        if ( ! $group || empty( $group->owner_id ) ) return;
        // Don't notify if the organizer is the one leaving
        if ( (int) $group->owner_id === (int) $member_user_id ) return;
        $owner  = get_userdata( $group->owner_id );
        if ( ! $owner ) return;
        $member = get_userdata( $member_user_id );
        if ( ! $member ) return;
        $name   = trim( "{$member->first_name} {$member->last_name}" ) ?: $member->display_name;

        $members = ( new Members )->where( [ 'group_id' => $group_id, 'active' => 1 ] );
        $count   = $members ? count( $members ) : 0;

        $group_url = home_url( "/group/{$group->slug}" );
        $button    = self::action_button( $group_url, 'View Group' );
        $content   = "
            <p><strong>{$name}</strong> has left your group <strong>{$group->name}</strong>.</p>
            <p>Your group now has <strong>{$count} member" . ( $count !== 1 ? 's' : '' ) . "</strong>.</p>
            {$button}
        ";
        self::send_email_with_layout( $owner->user_email, "{$name} left {$group->name}", 'Member Left', $group->name, $content );
    }

    static public function send_notice( $group_id, $subject, $message_html, $event_id = null ) {
        $group   = new Group( $group_id );
        $members = $group->members();
        $group_data = $group->fetch();
        $reply_to = "Reply-To: {$group_data->reply_to_name} <{$group_data->reply_to_email}>";

        foreach ( $members as $member ) {
            if ( ! Members::wants_email( $member->user_id, $group_id ) ) {
                continue;
            }
            $user = get_userdata( $member->user_id );
            if ( ! $user ) continue;

            $token         = $member->member_id . "-" . wp_hash( $member->created_at );
            $leave_link    = home_url() . "/group/{$group_data->slug}/leave?token={$token}";
            $mute_link     = home_url() . "/wp-json/localmeet/v1/member/mute/{$token}";
            $member_footer = str_replace( "[leave_group]", $leave_link, $group_data->email_footer );
            $member_footer = str_replace( "[mute_emails]", $mute_link, $member_footer );

            $greeting = $user->first_name ?: $user->display_name ?: 'there';
            $content  = "
                <p>Hi {$greeting},</p>
                {$message_html}
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0;'>
                    {$member_footer}
                </div>
            ";
            self::send_email_with_layout( $member->email, $subject, $subject, $group_data->name, $content, [ $reply_to ] );
        }
    }

    static public function build_announcement( $event_id ) {
        if ( ! function_exists( 'get_home_path' ) ) {
            include_once ABSPATH . '/wp-admin/includes/file.php';
        }

        $event      = ( new Event( $event_id ) )->fetch();
        $group      = new Group( $event->group_id );
        $group_data = $group->fetch();
        $home_path  = get_home_path();
        $path       = "{$home_path}invites/{$event->event_id}/";

        if ( ! is_dir( $path ) ) {
            mkdir( $path, 0755, true );
        }

        if ( ! empty( $event->event_end_at ) ) {
            $ends_at = new \DateTime( $event->event_end_at );
        } else {
            $ends_at = new \DateTime( $event->event_at );
            date_add( $ends_at, date_interval_create_from_date_string( '1 hour 30 minutes' ) );
        }

        $wp_timezone = get_option( 'timezone_string' ) ?: 'America/New_York';
        $tz          = new \DateTimeZone( $wp_timezone );

        $start      = new \Eluceo\iCal\Domain\ValueObject\DateTime( \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $event->event_at ), false );
        $end        = new \Eluceo\iCal\Domain\ValueObject\DateTime( \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $ends_at->format( "Y-m-d H:i:s" ) ), false );

        $organizer = new \Eluceo\iCal\Domain\ValueObject\Organizer(
            new \Eluceo\iCal\Domain\ValueObject\EmailAddress( $group_data->reply_to_email ),
            $group_data->reply_to_name,
        );

        $meetup = ( new \Eluceo\iCal\Domain\Entity\Event() )
            ->setSummary( $event->name )
            ->setOrganizer( $organizer )
            ->setOccurrence(
                new \Eluceo\iCal\Domain\ValueObject\TimeSpan( $start, $end )
            );

        $location_data = json_decode( $event->location );
        if ( $location_data && is_object( $location_data ) ) {
            $parts = array_filter( [ $location_data->name ?? '', $location_data->address ?? '' ] );
            if ( $parts ) {
                $meetup->setLocation( new \Eluceo\iCal\Domain\ValueObject\Location( implode( ', ', $parts ) ) );
            }
        }

        $calendar = new \Eluceo\iCal\Domain\Entity\Calendar( [ $meetup ] );
        $timeZone = ( new \Eluceo\iCal\Domain\Entity\TimeZone( "timezone" ) )::createFromPhpDateTimeZone(
            $tz,
            new \DateTimeImmutable( $event->event_at, $tz ),
            new \DateTimeImmutable( $ends_at->format( "Y-m-d H:i:s" ), $tz ),
        );
        $calendar->addTimeZone( $timeZone );

        $componentFactory = new \Eluceo\iCal\Presentation\Factory\CalendarFactory();
        $generated_ics    = $componentFactory->createCalendar( $calendar );

        file_put_contents( "{$path}invite.ics", $generated_ics );

        $event_link  = home_url() . "/group/{$group_data->slug}/{$event->slug}";
        $event_at    = date( 'F jS', strtotime( $event->event_at ) );
        $when        = date( 'l, F jS Y \a\t g:i a', strtotime( $event->event_at ) );
        if ( ! empty( $event->event_end_at ) ) {
            $when .= ' - ' . date( 'g:i a', strtotime( $event->event_end_at ) );
        }
        $subject     = "{$event->name} on {$event_at}";
        $attachments = [ "{$path}invite.ics" ];
        $reply_to    = "Reply-To: {$group_data->reply_to_name} <{$group_data->reply_to_email}>";

        $location_name    = '';
        $location_address = '';
        $location_data    = json_decode( $event->location );
        if ( $location_data && is_object( $location_data ) ) {
            $location_name    = $location_data->name ?? '';
            $location_address = $location_data->address ?? '';
        }

        $location_html = '';
        if ( $location_name ) {
            $location_html .= "<tr><td style='padding: 4px 0; color: #718096; font-size: 14px; width: 24px; vertical-align: top;'>&#128205;</td><td style='padding: 4px 0; font-size: 15px; color: #2d3748; font-weight: 600;'>{$location_name}</td></tr>";
        }
        if ( $location_address ) {
            $location_html .= "<tr><td style='padding: 0; width: 24px;'></td><td style='padding: 0 0 4px; font-size: 13px; color: #718096;'>{$location_address}</td></tr>";
        }

        $image_html = '';
        if ( ! empty( $event->image_url ) ) {
            $image_html = "<img src='{$event->image_url}' alt='" . esc_attr( $event->name ) . "' style='width: 100%; max-width: 600px; height: auto; border-radius: 8px; margin-bottom: 20px; display: block;'>";
        }

        $content_body = "
            {$image_html}
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f7fafc; border-radius: 8px; margin-bottom: 24px;'>
                <tr>
                    <td style='padding: 20px 24px;'>
                        <table role='presentation' border='0' cellpadding='0' cellspacing='0'>
                            <tr>
                                <td style='padding: 4px 0; color: #718096; font-size: 14px; width: 24px; vertical-align: top;'>&#128197;</td>
                                <td style='padding: 4px 0; font-size: 15px; color: #2d3748; font-weight: 600;'>{$when}</td>
                            </tr>
                            {$location_html}
                        </table>
                    </td>
                </tr>
            </table>
            <p>Hi {greeting_name},</p>
            {$event->description}
            {rsvp_button}
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0;'>
                <a href='{mute_link}' style='color: #a0aec0;'>Unsubscribe</a> &middot; <a href='{leave_link}' style='color: #a0aec0;'>Leave group</a>
            </div>
        ";

        return [
            'subject'      => $subject,
            'content_body' => $content_body,
            'attachments'  => $attachments,
            'reply_to'     => $reply_to,
            'event'        => $event,
            'group_data'   => $group_data,
        ];
    }

    static public function announce_event( $event_id ) {
        $announcement = self::build_announcement( $event_id );
        $group        = new Group( $announcement['event']->group_id );
        $members      = $group->members();
        $group_data   = $announcement['group_data'];

        foreach ( $members as $member ) {
            $member_record = ( new Members )->where( [ "user_id" => $member->user_id, "group_id" => $announcement['event']->group_id, "active" => 1 ] );
            if ( ! empty( $member_record ) && property_exists( $member_record[0], 'email_notifications' ) && $member_record[0]->email_notifications == 0 ) {
                continue;
            }

            $token         = $member->member_id . "-" . wp_hash( $member->created_at );
            $leave_link    = home_url() . "/group/{$group_data->slug}/leave?token={$token}";
            $mute_link     = home_url() . "/wp-json/localmeet/v1/member/mute/{$token}";

            $full_name     = trim( "{$member->first_name} {$member->last_name}" );
            $greeting_name = $member->first_name ?: ( $full_name ?: 'there' );

            $rsvp_link   = home_url() . "/group/{$group_data->slug}/{$announcement['event']->slug}/rsvp?token={$token}";
            $rsvp_button = self::action_button( $rsvp_link, 'RSVP to Attend' );

            $content = str_replace( '{greeting_name}', $greeting_name, $announcement['content_body'] );
            $content = str_replace( '{rsvp_button}', $rsvp_button, $content );
            $content = str_replace( '{leave_link}', $leave_link, $content );
            $content = str_replace( '{mute_link}', $mute_link, $content );

            self::send_email_with_layout(
                $member->email,
                $announcement['subject'],
                $announcement['event']->name,
                $group_data->name,
                $content,
                [ $announcement['reply_to'] ],
                $announcement['attachments']
            );
        }
    }

    static public function announce_event_preview( $event_id, $email ) {
        $announcement  = self::build_announcement( $event_id );
        $current_user  = wp_get_current_user();
        $greeting_name = $current_user->first_name ?: 'there';

        $event_link   = home_url() . "/group/{$announcement['group_data']->slug}/{$announcement['event']->slug}";
        $rsvp_button  = self::action_button( $event_link, 'RSVP to Attend' );

        $content = str_replace( '{greeting_name}', $greeting_name, $announcement['content_body'] );
        $content = str_replace( '{rsvp_button}', $rsvp_button, $content );
        $content = str_replace( '{leave_link}', '#preview-leave', $content );
        $content = str_replace( '{mute_link}', '#preview-mute', $content );

        self::send_email_with_layout(
            $email,
            '[PREVIEW] ' . $announcement['subject'],
            $announcement['event']->name,
            $announcement['group_data']->name,
            $content,
            [ $announcement['reply_to'] ],
            $announcement['attachments']
        );
    }

    static public function send_password_reset( $email, $user_login, $reset_url ) {
        $site_name = get_bloginfo( 'name' );
        $button    = self::action_button( $reset_url, 'Reset Password' );
        $content   = "
            <p>Someone has requested a password reset for the account associated with <strong>{$user_login}</strong>.</p>
            <p>Click the button below to choose a new password:</p>
            {$button}
            <p style='font-size: 13px; color: #a0aec0;'>If you didn't request this, you can safely ignore this email. Your password will not be changed.</p>
        ";
        self::send_email_with_layout( $email, "{$site_name} - Password Reset", 'Reset Your Password', $site_name, $content );
    }

    static public function send_role_notification( $email, $group_name, $role, $group_slug = '' ) {
        $site_name = get_bloginfo( 'name' );
        $group_url = home_url( "/group/{$group_slug}" );
        $label     = $role === 'organizer' ? 'organizer' : 'manager';
        $subject   = "{$site_name} - You've been made {$label} of {$group_name}";
        $content   = "<p>You've been promoted to <strong>{$label}</strong> of <strong>{$group_name}</strong>.</p>";
        if ( $role === 'organizer' ) {
            $content .= "<p>As organizer, you have full control over the group, its events, and its members.</p>";
        } else {
            $content .= "<p>As a manager, you can create and manage events, send announcements, and moderate comments.</p>";
        }
        $content .= self::action_button( $group_url, "Go to {$group_name}" );
        self::send_email_with_layout( $email, $subject, $group_name, $site_name, $content );
    }
}

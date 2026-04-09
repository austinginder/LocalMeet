<?php

namespace LocalMeet;

class EmailQueue {

    const BATCH_SIZE = 20;

    private static function progress_key( $type, $id ) {
        return "localmeet_email_{$type}_{$id}_progress";
    }

    private static function data_key( $type, $id ) {
        return "localmeet_email_{$type}_{$id}_data";
    }

    static function get_progress( $type, $id ) {
        return get_transient( self::progress_key( $type, $id ) );
    }

    static function cleanup( $type, $id ) {
        delete_transient( self::progress_key( $type, $id ) );
        delete_transient( self::data_key( $type, $id ) );
    }

    static function start_announcement( $event_id ) {
        $announcement = Mailer::build_announcement( $event_id );
        $group        = new Group( $announcement['event']->group_id );
        $members      = $group->members();
        $group_data   = $announcement['group_data'];

        // Filter to subscribed members only
        $recipients = [];
        foreach ( $members as $member ) {
            if ( Members::wants_email( $member->user_id, $announcement['event']->group_id ) ) {
                $recipients[] = $member;
            }
        }

        $total = count( $recipients );
        if ( $total === 0 ) {
            return [ 'sent' => 0, 'total' => 0 ];
        }

        set_transient( self::data_key( 'announce', $event_id ), [
            'announcement' => [
                'subject'      => $announcement['subject'],
                'content_body' => $announcement['content_body'],
                'attachments'  => $announcement['attachments'],
                'reply_to'     => $announcement['reply_to'],
                'event_name'   => $announcement['event']->name,
                'event_slug'   => $announcement['event']->slug,
                'group_name'   => $group_data->name,
                'group_slug'   => $group_data->slug,
            ],
            'recipients' => $recipients,
        ], HOUR_IN_SECONDS );

        set_transient( self::progress_key( 'announce', $event_id ), [
            'status' => 'sending',
            'sent'   => 0,
            'total'  => $total,
        ], HOUR_IN_SECONDS );

        wp_schedule_single_event( time(), 'localmeet_send_email_batch', [ 'announce', $event_id, 0 ] );
        spawn_cron();

        return [ 'total' => $total ];
    }

    static function start_notice( $group_id, $subject, $message_html ) {
        $group      = new Group( $group_id );
        $members    = $group->members();
        $group_data = $group->fetch();
        $reply_to   = "Reply-To: {$group_data->reply_to_name} <{$group_data->reply_to_email}>";

        $recipients = [];
        foreach ( $members as $member ) {
            if ( Members::wants_email( $member->user_id, $group_id ) ) {
                $recipients[] = $member;
            }
        }

        $total = count( $recipients );
        if ( $total === 0 ) {
            return [ 'sent' => 0, 'total' => 0 ];
        }

        $job_id = $group_id . '_' . time();

        set_transient( self::data_key( 'notice', $job_id ), [
            'subject'      => $subject,
            'message_html' => $message_html,
            'reply_to'     => $reply_to,
            'group_name'   => $group_data->name,
            'group_slug'   => $group_data->slug,
            'group_id'     => $group_id,
            'email_footer' => $group_data->email_footer,
            'recipients'   => $recipients,
        ], HOUR_IN_SECONDS );

        set_transient( self::progress_key( 'notice', $job_id ), [
            'status' => 'sending',
            'sent'   => 0,
            'total'  => $total,
        ], HOUR_IN_SECONDS );

        wp_schedule_single_event( time(), 'localmeet_send_email_batch', [ 'notice', $job_id, 0 ] );
        spawn_cron();

        return [ 'total' => $total, 'job_id' => $job_id ];
    }

    static function process_batch( $type, $id, $offset ) {
        $job_data = get_transient( self::data_key( $type, $id ) );
        $progress = get_transient( self::progress_key( $type, $id ) );

        if ( ! $job_data || ! $progress ) {
            return;
        }

        if ( $type === 'announce' ) {
            self::process_announcement_batch( $id, $job_data, $progress, $offset );
        } elseif ( $type === 'notice' ) {
            self::process_notice_batch( $id, $job_data, $progress, $offset );
        }
    }

    private static function process_announcement_batch( $event_id, $job_data, $progress, $offset ) {
        $recipients   = array_slice( $job_data['recipients'], $offset, self::BATCH_SIZE );
        $announcement = $job_data['announcement'];

        foreach ( $recipients as $member ) {
            $token         = $member->member_id . "-" . wp_hash( $member->created_at );
            $leave_link    = home_url() . "/group/{$announcement['group_slug']}/leave?token={$token}";
            $mute_link     = home_url() . "/wp-json/localmeet/v1/member/mute/{$token}";

            $full_name     = trim( "{$member->first_name} {$member->last_name}" );
            $greeting_name = $member->first_name ?: ( $full_name ?: 'there' );

            $rsvp_link   = home_url() . "/group/{$announcement['group_slug']}/{$announcement['event_slug']}/rsvp?token={$token}";
            $rsvp_button = Mailer::action_button_public( $rsvp_link, 'RSVP to Attend' );

            $content = str_replace( '{greeting_name}', $greeting_name, $announcement['content_body'] );
            $content = str_replace( '{rsvp_button}', $rsvp_button, $content );
            $content = str_replace( '{leave_link}', $leave_link, $content );
            $content = str_replace( '{mute_link}', $mute_link, $content );

            Mailer::send_email_with_layout_public(
                $member->email,
                $announcement['subject'],
                $announcement['event_name'],
                $announcement['group_name'],
                $content,
                [ $announcement['reply_to'] ],
                $announcement['attachments']
            );

            $progress['sent']++;
            set_transient( self::progress_key( 'announce', $event_id ), $progress, HOUR_IN_SECONDS );
        }

        $next_offset = $offset + self::BATCH_SIZE;
        if ( $next_offset < $progress['total'] ) {
            wp_schedule_single_event( time(), 'localmeet_send_email_batch', [ 'announce', $event_id, $next_offset ] );
            spawn_cron();
        } else {
            $progress['status'] = 'complete';
            set_transient( self::progress_key( 'announce', $event_id ), $progress, HOUR_IN_SECONDS );
            delete_transient( self::data_key( 'announce', $event_id ) );

            $time_now = current_time( 'mysql' );
            ( new Events )->update( [ 'announced_at' => $time_now ], [ 'event_id' => $event_id ] );
        }
    }

    private static function process_notice_batch( $job_id, $job_data, $progress, $offset ) {
        $recipients = array_slice( $job_data['recipients'], $offset, self::BATCH_SIZE );

        foreach ( $recipients as $member ) {
            $user = get_userdata( $member->user_id );
            if ( ! $user ) {
                $progress['sent']++;
                set_transient( self::progress_key( 'notice', $job_id ), $progress, HOUR_IN_SECONDS );
                continue;
            }

            $token         = $member->member_id . "-" . wp_hash( $member->created_at );
            $leave_link    = home_url() . "/group/{$job_data['group_slug']}/leave?token={$token}";
            $mute_link     = home_url() . "/wp-json/localmeet/v1/member/mute/{$token}";
            $member_footer = str_replace( "[leave_group]", $leave_link, $job_data['email_footer'] );
            $member_footer = str_replace( "[mute_emails]", $mute_link, $member_footer );

            $greeting = $user->first_name ?: $user->display_name ?: 'there';
            $content  = "
                <p>Hi {$greeting},</p>
                {$job_data['message_html']}
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0;'>
                    {$member_footer}
                </div>
            ";

            Mailer::send_email_with_layout_public(
                $member->email,
                $job_data['subject'],
                $job_data['subject'],
                $job_data['group_name'],
                $content,
                [ $job_data['reply_to'] ]
            );

            $progress['sent']++;
            set_transient( self::progress_key( 'notice', $job_id ), $progress, HOUR_IN_SECONDS );
        }

        $next_offset = $offset + self::BATCH_SIZE;
        if ( $next_offset < $progress['total'] ) {
            wp_schedule_single_event( time(), 'localmeet_send_email_batch', [ 'notice', $job_id, $next_offset ] );
            spawn_cron();
        } else {
            $progress['status'] = 'complete';
            set_transient( self::progress_key( 'notice', $job_id ), $progress, HOUR_IN_SECONDS );
            delete_transient( self::data_key( 'notice', $job_id ) );
        }
    }
}

<?php

namespace LocalMeet;

class Mailer {

    public function announce_event( $event_id ) {
        if ( ! function_exists( 'get_home_path' ) ) {
            include_once ABSPATH . '/wp-admin/includes/file.php';
        }
        $event      = ( new Event( $event_id ) )->fetch();
        $group      = ( new Group( $event->group_id ) );
        $members    = $group->members();
        $group_data = $group->fetch();
        $home_path  = get_home_path();
        $path       = "{$home_path}invites/{$event->event_id}/";
        
        if ( ! is_dir( $path ) ) {
            mkdir ( $path );
        }

        $ends_at = new \DateTime( $event->event_at );
        date_add( $ends_at, date_interval_create_from_date_string( '1 hour 30 minutes' ) );

        $start      = new \Eluceo\iCal\Domain\ValueObject\DateTime(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $event->event_at), false);
        $end        = new \Eluceo\iCal\Domain\ValueObject\DateTime(\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ends_at->format("Y-m-d h:m:s")), false);
        $occurrence = new \Eluceo\iCal\Domain\ValueObject\TimeSpan($start, $end);

       $organizer = new \Eluceo\iCal\Domain\ValueObject\Organizer(
            new \Eluceo\iCal\Domain\ValueObject\EmailAddress( $group_data->reply_to_email ), 
            $group_data->reply_to_name,
        );

        $meetup = (new \Eluceo\iCal\Domain\Entity\Event())
            ->setSummary( $event->name )
            ->setOrganizer($organizer)
            ->setOccurrence(
                new \Eluceo\iCal\Domain\ValueObject\TimeSpan( $start, $end )
            );
        
        $calendar = new \Eluceo\iCal\Domain\Entity\Calendar( [$meetup] );
        $timeZone = ( new \Eluceo\iCal\Domain\Entity\TimeZone( "timezone" ) )::createFromPhpDateTimeZone( 
            new \DateTimeZone('America/New_York'),
            new \DateTimeImmutable( $event->event_at, new \DateTimeZone('America/New_York') ),
            new \DateTimeImmutable( $ends_at->format("Y-m-d h:m:s"), new \DateTimeZone('America/New_York') ),
        );
        $calendar->addTimeZone($timeZone);
        
        $componentFactory = new \Eluceo\iCal\Presentation\Factory\CalendarFactory();
        $generated_ics    = $componentFactory->createCalendar($calendar);

        file_put_contents( "{$path}invite.ics", $generated_ics );
        $event_link  = home_url() . "/group/{$group->fetch()->slug}/{$event->slug}";
        $event_at    = date('F jS', strtotime( $event->event_at ) );
        $when        = date('l, F jS Y \a\t g:i a', strtotime( $event->event_at ) );
        $subject     = "{$event->name} on $event_at";
        $attachments = [ "{$path}invite.ics" ];
        $reply_to    = "Reply-To: {$group_data->reply_to_name} <{$group_data->reply_to_email}>";
        $headers     = [ 'Content-Type: text/html; charset=UTF-8', $reply_to ];
        $event_intro = "<a href=\"$event_link\" style='padding: 12px 32px; font-size: 18px;background-color: #eee!important;border-color: #eee!important;margin: 10px;display: inline-block;color: black;text-decoration: none;'><span style='font-weight:bold;'>$event->name</span><br />$when<br>🗺️ $event->location<br><small>$group_data->name</small></a><br /><br />";
        $rsvp        = '<a href="'.$event_link.'" style="padding: 12px 32px; font-size: 18px;background-color: #eee!important;border-color: #eee!important;margin: 10px;display: inline-block;color: black;text-decoration: none;font-weight: bold;">RSVP TO ATTEND EVENT</a>';

        foreach( $members as $member ) {
            $token         = $member->member_id . "-" . md5( $member->created_at );
            $leave_link    = home_url() . "/group/{$group->fetch()->slug}/leave?token=$token";
            $member_footer = str_replace( "[leave_group]", $leave_link, $group_data->email_footer );
            $body          = "$event_intro {$member->first_name}, <br />$event->description $rsvp $member_footer";
            wp_mail( $member->email, $subject, $body, $headers, $attachments );
        }

        return;
    }

}
<?php

namespace LocalMeet;
use Spatie\IcalendarGenerator\Components\Calendar as SpatieCalendar;
use Spatie\IcalendarGenerator\Components\Event as SpatieEvent;

class Mailer {

    public function announce_event( $event_id ) {
        $event     = ( new Event( $event_id ) )->fetch();
        $home_path = get_home_path();
        $path      = "{$home_path}invites/{$event->event_id}/";
        if ( ! is_dir( $path ) ) {
            mkdir ( $path );
        }

        $ends_at = new \DateTime($event->event_at, new \DateTimeZone('America/New_York')); 
        date_add( $ends_at, date_interval_create_from_date_string('1 hour 30 minutes'));

        $generated_ics = SpatieCalendar::create( $event->name )
            ->event( SpatieEvent::create( $event->name )
                ->startsAt( new \DateTime( $event->event_at, new \DateTimeZone('America/New_York') ) )
                ->endsAt( $ends_at )
            )
            ->get();

        file_put_contents( "{$path}invite.ics", $generated_ics );
        echo "Generated {$path}invite.ics";

        $members     = ( new Group( $event->group_id ) )->members();
        $event_at    = date('D M jS Y \a\t h:ia', strtotime( $event->event_at ) );
        $subject     = "{$event->name} on $event_at";
        $attachments = [ "{$path}invite.ics" ];
        $headers     = [ 'Content-Type: text/html; charset=UTF-8' ];

        foreach( $members as  $member ) {
            $body    = "{$member->first_name}, <br />$event->description";
            wp_mail( $member->email, $subject, $body, $headers, $attachments );
        }

        return;
    }

}
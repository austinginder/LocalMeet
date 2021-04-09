<?php

namespace LocalMeet;

class Event {

    public function __construct( $event_id = "" ) {
        $this->event_id = $event_id;  
    }

    public function fetch() {
        $event    = ( new Events )->get( $this->event_id );
        $time_now = date("Y-m-d H:i:s");
        if ( $event->event_at > $time_now ) {
            $event->status = "upcoming";
        }
        if ( $event->event_at < $time_now ) {
            $event->status = "past";
        }
        $event->description_raw = $event->description;
        $event->description     = ( new \Parsedown )->text( $event->description );
        $event->attendees       = self::going(); 
        $event->attendees_not   = self::not_going();
        return $event;
    }

    public function going() {
        $attendees = ( new Attendees )->where( [ "event_id" => $this->event_id, "going" => 1 ] );
        foreach ( $attendees as $key => $attendee ) {
            $user              = get_userdata( $attendee->user_id );
            $attendee->name    = "{$user->first_name} {$user->last_name}";
            $attendee->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            $attendees[ $key ] = $attendee;
        }
        array_multisort( 
            array_column( $attendees, 'description' ), SORT_DESC,
            array_column( $attendees, 'name' ), SORT_ASC,
            $attendees
        );
        return $attendees;
    }
	
    public function not_going() {
        $attendees             = ( new Attendees )->where( [ "event_id" => $this->event_id, "going" => 0 ] );
        foreach ( $attendees as $key => $attendee ) {
            $user              = get_userdata( $attendee->user_id );
            $attendee->name    = "{$user->first_name} {$user->last_name}";
            $attendee->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            $attendees[ $key ] = $attendee;
        }
        array_multisort( 
            array_column( $attendees, 'description' ), SORT_DESC,
            array_column( $attendees, 'name' ), SORT_ASC,
            $attendees
        );
    }
    
    public function announce() {
        ( new Mailer )->announce_event( $this->event_id );
    }
}
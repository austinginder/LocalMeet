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
        $event->summary_raw     = $event->summary;
        $event->summary         = ( new \Parsedown )->text( $event->summary );
        $event->attendees       = self::going(); 
        $event->attendees_not   = self::not_going();
        $event->comments        = self::comments();
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
        return $attendees;
    }

    public function comments() {
        $current_user_id = get_current_user_id();
        $comments        = ( new Comments )->where( [ "event_id" => $this->event_id ] );
        foreach ( $comments as $key => $comment ) {
            $user             = get_userdata( $comment->user_id );
            $comment->name    = "{$user->first_name} {$user->last_name}";
            $comment->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            if ( $comment->user_id == $current_user_id ){
                $comment->owner = true;
            }
            $comment->details_raw = $comment->details;
            $comment->details     = ( new \Parsedown )->text( $comment->details );
            unset( $comment->user_id );
            $comments[ $key ] = $comment;
        }
        return $comments;
    }
    
    public function announce() {
        ( new Mailer )->announce_event( $this->event_id );
    }
}
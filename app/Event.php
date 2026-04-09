<?php

namespace LocalMeet;

class Event {

    public function __construct( $event_id = "" ) {
        $this->event_id = $event_id;  
    }

    public function fetch( $comments_per_page = 50, $comments_page = 1 ) {

        $event    = ( new Events )->get( $this->event_id );
        $time_now = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
        $event_at = $event->event_at;
        if ( $event_at > $time_now ) {
            $event->status = "upcoming";
        }
        if ( $event_at < $time_now ) {
            $event->status = "past";
        }
        $location_data = json_decode( $event->location );
        if ( $location_data && is_object( $location_data ) ) {
            $event->location_name    = $location_data->name ?? '';
            $event->location_address = $location_data->address ?? '';
        } else {
            $event->location_name    = $event->location ?? '';
            $event->location_address = '';
        }
        $event->description_raw = $event->description;
        $event->description     = ( new \Parsedown )->text( $event->description );
        $event->summary_raw     = $event->summary;
        $event->summary         = ( new \Parsedown )->text( $event->summary );
        $event->event_end_at         = property_exists( $event, 'event_end_at' ) ? $event->event_end_at : null;
        $event->capacity             = ( property_exists( $event, 'capacity' ) && $event->capacity ) ? (int) $event->capacity : null;
        $event->recurrence_rule      = property_exists( $event, 'recurrence_rule' ) ? $event->recurrence_rule : null;
        $event->recurrence_parent_id = property_exists( $event, 'recurrence_parent_id' ) ? $event->recurrence_parent_id : null;
        $event->image_id     = ! empty( $event->image_id ) ? (int) $event->image_id : null;
        $event->image_url    = $event->image_id ? wp_get_attachment_image_url( $event->image_id, 'large' ) : null;
        $event->cancelled_at  = property_exists( $event, 'cancelled_at' ) ? $event->cancelled_at : null;
        $event->announced_at = property_exists( $event, 'announced_at' ) ? $event->announced_at : null;
        if ( $event->cancelled_at ) {
            $event->status = 'cancelled';
        }
        $event->attendees       = self::going();
        $event->attendees_not   = self::not_going();
        $event->spots_taken     = count( $event->attendees );
        $event->is_full         = $event->capacity !== null && $event->spots_taken >= $event->capacity;
        // Group owners and admins see pending comments
        $user           = new User;
        $group          = ( new Groups )->get( $event->group_id );
        $include_pending = $user->is_admin() || ( $user->user_id() && $user->user_id() == $group->owner_id );
        $event->comments = self::comments( $comments_per_page, $comments_page, $include_pending );
        $event->comments_total  = ( new Comments )->count_where( [ "event_id" => $this->event_id ] );
        return $event;
    }

    public function going() {
        $current_user_id = get_current_user_id();
        $attendees = ( new Attendees )->where( [ "event_id" => $this->event_id, "going" => 1 ] );
        foreach ( $attendees as $key => $attendee ) {
            $user              = get_userdata( $attendee->user_id );
            $attendee->is_me   = ( $attendee->user_id == $current_user_id );
            $full_name         = trim( "{$user->first_name} {$user->last_name}" );
            $attendee->name    = $full_name !== '' ? $full_name : $user->display_name;
            $attendee->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            unset( $attendee->user_id );
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
        $current_user_id = get_current_user_id();
        $attendees       = ( new Attendees )->where( [ "event_id" => $this->event_id, "going" => 0 ] );
        foreach ( $attendees as $key => $attendee ) {
            $user              = get_userdata( $attendee->user_id );
            $attendee->is_me   = ( $attendee->user_id == $current_user_id );
            $full_name         = trim( "{$user->first_name} {$user->last_name}" );
            $attendee->name    = $full_name !== '' ? $full_name : $user->display_name;
            $attendee->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            unset( $attendee->user_id );
            $attendees[ $key ] = $attendee;
        }
        array_multisort(
            array_column( $attendees, 'description' ), SORT_DESC,
            array_column( $attendees, 'name' ), SORT_ASC,
            $attendees
        );
        return $attendees;
    }

    public function comments( $limit = 50, $page = 1, $include_pending = false ) {
        $offset          = ( $page - 1 ) * $limit;
        $current_user_id = get_current_user_id();
        $comments        = ( new Comments )->where( [ "event_id" => $this->event_id ], $limit, $offset );
        $filtered        = [];
        foreach ( $comments as $key => $comment ) {
            $status = property_exists( $comment, 'status' ) ? ( $comment->status ?? 'approved' ) : 'approved';
            // Hide pending comments from non-owners unless they wrote it
            if ( $status !== 'approved' && ! $include_pending && $comment->user_id != $current_user_id ) {
                continue;
            }
            $user             = get_userdata( $comment->user_id );
            $comment->name    = "{$user->first_name} {$user->last_name}";
            $comment->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
            if ( $comment->user_id == $current_user_id ){
                $comment->owner = true;
            }
            $comment->status      = $status;
            $comment->created_at  = ( new \DateTime( $comment->created_at ) )->setTimezone(new \DateTimeZone( get_option('timezone_string') ))->format('Y-m-d H:i:s');
            $comment->details_raw = $comment->details;
            $comment->details     = ( new \Parsedown )->text( $comment->details );
            unset( $comment->user_id );
            $filtered[] = $comment;
        }
        return $filtered;
    }
    
    public function announce() {
        ( new Mailer )->announce_event( $this->event_id );
    }
}
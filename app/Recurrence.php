<?php

namespace LocalMeet;

class Recurrence {

    private $event;

    public function __construct( $event ) {
        $this->event = $event;
    }

    public function generate_instances( $count = 8 ) {
        $rule     = $this->event->recurrence_rule;
        $start    = new \DateTime( $this->event->event_at );
        $duration = null;
        if ( ! empty( $this->event->event_end_at ) ) {
            $end      = new \DateTime( $this->event->event_end_at );
            $duration = $start->diff( $end );
        }

        $instances = [];
        for ( $i = 1; $i <= $count; $i++ ) {
            $next = clone $start;
            switch ( $rule ) {
                case 'weekly':
                    $next->modify( "+{$i} week" );
                    break;
                case 'biweekly':
                    $next->modify( "+" . ( $i * 2 ) . " weeks" );
                    break;
                case 'monthly':
                    $next->modify( "+{$i} month" );
                    break;
                default:
                    continue 2;
            }

            $instance = [
                "event_at"             => $next->format( 'Y-m-d H:i:s' ),
                "event_end_at"         => $duration ? ( clone $next )->add( $duration )->format( 'Y-m-d H:i:s' ) : null,
                "name"                 => $this->event->name,
                "group_id"             => $this->event->group_id,
                "description"          => $this->event->description ?? '',
                "location"             => $this->event->location ?? '',
                "capacity"             => $this->event->capacity ?? null,
                "recurrence_parent_id" => $this->event->event_id,
                "created_at"           => date( 'Y-m-d H:i:s' ),
            ];
            $instances[] = $instance;
        }
        return $instances;
    }
}

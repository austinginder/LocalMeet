<?php

namespace LocalMeet;

class Groups extends DB {

    static $primary_key = 'group_id';

    public function generate_unique_slug( $text ) {
        $valid_slug = false;
        $modifier   = "";
        
        do {
            $slug       = ( new App )->slugify( "$text$modifier" );
            $slug_check = self::where( [ "slug" => $slug ] );
            $modifier   = "-" . substr(md5(mt_rand()), 0, 3);
            if ( ! $slug_check ) {
                $valid_slug = true;
            }
        } while ( $valid_slug == false );

        return $slug;
    }

    private function enrich_group( $group, $user_id, $member_group_ids ) {
        global $wpdb;
        $is_owner  = $user_id && (int) $group->owner_id === $user_id;
        $is_member = in_array( $group->group_id, $member_group_ids );
        $group->is_pinned    = $is_owner || $is_member;
        $group->is_organizer = $is_owner;

        $group->description_raw = $group->description;
        $group->description     = ( new \Parsedown )->text( $group->description );

        // Live stats
        $gid = (int) $group->group_id;
        $members_table  = $wpdb->prefix . 'localmeet_members';
        $events_table   = $wpdb->prefix . 'localmeet_events';
        $now            = current_time( 'mysql' );

        $member_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$members_table} WHERE group_id = %d AND active = 1", $gid
        ) );
        $event_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$events_table} WHERE group_id = %d", $gid
        ) );
        $next_event = $wpdb->get_var( $wpdb->prepare(
            "SELECT event_at FROM {$events_table} WHERE group_id = %d AND event_at > %s ORDER BY event_at ASC LIMIT 1", $gid, $now
        ) );

        $group->stats = (object) [
            'members'    => $member_count,
            'events'     => $event_count,
            'next_event' => $next_event,
        ];
        $group->created_at_date = $group->created_at;

        unset( $group->details, $group->created_at, $group->owner_id );
        return $group;
    }

    public function list( $per_page = 0, $page = 1 ) {
        $offset  = ( $page - 1 ) * $per_page;
        $groups  = self::all( 'created_at', 'DESC', $per_page, $offset );
        $user_id = get_current_user_id();
        $member_group_ids = [];
        if ( $user_id ) {
            $members = ( new Members )->where( [ 'user_id' => $user_id, 'active' => 1 ] );
            if ( $members ) {
                $member_group_ids = array_column( $members, 'group_id' );
            }
        }
        foreach ( $groups as $key => $group ) {
            $groups[ $key ] = $this->enrich_group( $group, $user_id, $member_group_ids );
        }
        return $groups;
    }

    public function search_groups( $term, $limit = 20, $offset = 0 ) {
        $groups  = self::search( $term, [ 'name', 'description' ], $limit, $offset );
        $user_id = get_current_user_id();
        $member_group_ids = [];
        if ( $user_id ) {
            $members = ( new Members )->where( [ 'user_id' => $user_id, 'active' => 1 ] );
            if ( $members ) {
                $member_group_ids = array_column( $members, 'group_id' );
            }
        }
        foreach ( $groups as $key => $group ) {
            $groups[ $key ] = $this->enrich_group( $group, $user_id, $member_group_ids );
        }
        return $groups;
    }

}
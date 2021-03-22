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

    public function list() {
        $groups = self::all();
        foreach( $groups as $group ) {
            $details = json_decode( $group->details );
            unset( $group->details );
            unset( $group->created_at );
            unset( $group->owner_id );
            unset( $group->details );
            $group->stats = empty( $details->stats ) ? (object) [] : $details->stats;
            if ( empty( $group->stats->members ) ) {
                $group->stats->members = 0;
            }
            if ( empty( $group->stats->events ) ) {
                $group->stats->events = 0;
            }
        }
        return $groups;
    }

}
<?php

namespace LocalMeet;

class Group {

    public function __construct( $group_id = "" ) {
        $this->group_id = $group_id;
    }

    public function fetch() {
        $group           = ( new Groups )->get( $this->group_id );
        $group->members  = self::members();
        $group->upcoming = ( new Events )->upcoming( [ "group_id" => $this->group_id ] );
        $group->past     = ( new Events )->past( [ "group_id" => $this->group_id ] );
        if ( empty( $group->upcoming ) ) {
            $group->upcoming = [];
        }
        if ( empty( $group->past ) ) {
            $group->past = [];
        }
        $user = ( new User )->fetch();
        if ( $user->user_id != 0 && $user->user_id == $group->owner_id ) {
            $group->owner = true;
        }
        $group->description_raw = $group->description;
        $group->description     = ( new \Parsedown )->text( $group->description );
        unset( $group->owner_id );
        unset( $group->created_at );
        return $group;
    }

    public function generate_unique_event_slug( $text ) {

        $event_slugs = array_column( self::events(), "slug" );
        $valid_slug  = false;
        $modifier    = "";
        
        do {
            $slug       = ( new App )->slugify( "$text$modifier" );
            $slug_check = in_array ( $slug, $event_slugs );
            $modifier   = "-" . substr(md5(mt_rand()), 0, 3);
            if ( ! $slug_check ) {
                $valid_slug = true;
            }
        } while ( $valid_slug == false );

        return $slug;
    }

    public function events() {
        return ( new Events )->where( [ "group_id" => $this->group_id ] );
    }

    public function members() {
        $members = ( new Members )->where( [ "group_id" => $this->group_id, "active" => 1 ] );
        foreach( $members as $member ) {
            $user = get_user_by( 'ID', $member->member_id );
            $member->first_name = $user->first_name;
            $member->last_name  = $user->last_name;
            $member->email      = $user->user_email;
        }
        return $members;
    }

    public function import_members( $members = [] ) {
        foreach( $members as $member ) {
            $member  = (object) $member;
            $user    = get_user_by( 'email', $member->email );
            $user_id = $user->ID;
            if ( ! $user ) {
                $new_user = [
                    "first_name" => $member->first_name,
                    "last_name"  => $member->last_name,
                    'user_email' => $member->email,
                    'user_login' => $member->email,
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
            }
            $lookup = ( new Members )->where( [ "user_id" => $user_id, "group_id" => $this->group_id ] );
            if ( ! empty( $lookup ) ) {
                return;
            }
            ( new Members )->insert( [ 
                "user_id"  => $user_id,
                "group_id" => $this->group_id,
                "active"   => 1,
            ] );
        }
    }
    
}
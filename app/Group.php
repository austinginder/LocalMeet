<?php

namespace LocalMeet;

class Group {

    public function __construct( $group_id = "" ) {
        $this->group_id = $group_id;
    }

    public function fetch( $events_per_page = 20, $events_page = 1 ) {
        $group           = ( new Groups )->get( $this->group_id );
        $group->members  = self::members_list();
        $member_ids      = array_column( self::members(), "user_id" );
        $event_conditions = [ "group_id" => $this->group_id ];
        $events_offset   = ( $events_page - 1 ) * $events_per_page;
        $group->upcoming       = ( new Events )->upcoming( $event_conditions, $events_per_page, $events_offset );
        $group->upcoming_total = ( new Events )->count_upcoming( $event_conditions );
        $group->past           = ( new Events )->past( $event_conditions, $events_per_page, $events_offset );
        $group->past_total     = ( new Events )->count_past( $event_conditions );
        if ( empty( $group->upcoming ) ) {
            $group->upcoming = [];
        }
        if ( empty( $group->past ) ) {
            $group->past = [];
        }
        $user      = ( new User )->fetch();
        $user_obj  = new User;
        if ( $user->user_id != 0 && $user->user_id == $group->owner_id ) {
            $group->owner = true;
        }
        $group->can_manage = $user_obj->can_manage_group( $group );
        $group->is_admin   = $user_obj->is_admin();
        $group->is_member = false;
        $group->email_notifications = true;
        if ( $user->user_id != 0 && in_array( $user->user_id, $member_ids ) ) {
            $group->is_member = true;
            $membership = ( new Members )->where( [ "user_id" => $user->user_id, "group_id" => $this->group_id, "active" => 1 ] );
            if ( ! empty( $membership ) ) {
                $group->email_notifications = ! ( property_exists( $membership[0], 'email_notifications' ) && $membership[0]->email_notifications == 0 );
            }
        }
        $group->description_raw  = $group->description;
        $group->description      = ( new \Parsedown )->text( $group->description );
        $details                 = empty( $group->details ) ? (object) [] : json_decode( $group->details );
        $owner                   = get_userdata( $group->owner_id );
        $group->reply_to_name    = ! empty( $details->reply_to_name )  ? $details->reply_to_name  : ( $owner ? $owner->display_name : '' );
        $group->reply_to_email   = ! empty( $details->reply_to_email ) ? $details->reply_to_email : ( $owner ? $owner->user_email : '' );
        $group->email_footer_raw = $details->email_footer ?? '';
        $group->email_footer     = ! empty( $details->email_footer ) ? ( new \Parsedown )->text( $details->email_footer ) : '';
        $group->locations = [];
        if ( $group->can_manage ) {
            $locations = ( new Locations )->where( [ "group_id" => $this->group_id ] );
            $group->locations = ! empty( $locations ) ? $locations : [];
        }
        $group->show             = "list";
        unset( $group->owner_id );
        unset( $group->created_at );
        return $group;
    }

    public function generate_unique_event_slug( $text, $exclude_slug = '' ) {
        $event_slugs = array_column( self::events(), "slug" );
        if ( $exclude_slug !== '' ) {
            $event_slugs = array_values( array_diff( $event_slugs, [ $exclude_slug ] ) );
        }
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
        $members  = ( new Members )->where( [ "group_id" => $this->group_id, "active" => 1 ] );
        foreach( $members as $member ) {
            $user = get_user_by( 'ID', $member->user_id );
            $member->first_name = $user->first_name;
            $member->last_name  = $user->last_name;
            $member->email      = $user->user_email;
        }
        return $members;
    }

    public function members_list() {
        $group   = ( new Groups )->get( $this->group_id );
        $members = ( new Members )->where( [ "group_id" => $this->group_id, "active" => 1 ] );
        $list    = [];
        foreach( $members as $member ) {
            $user = get_user_by( 'ID', $member->user_id );
            $role = 'member';
            if ( $member->user_id == $group->owner_id ) {
                $role = 'organizer';
            } elseif ( property_exists( $member, 'role' ) && $member->role === 'manager' ) {
                $role = 'manager';
            }
            $list[] = [
                "member_id"  => $member->member_id,
                "user_id"    => $member->user_id,
                "created_at" => $member->created_at,
                "first_name" => $user->first_name,
                "last_name"  => $user->last_name,
                "avatar"     => get_avatar_url( $user->user_email, [ "size" => "80" ] ),
                "role"       => $role,
            ];
        }
        $role_order = [ 'organizer' => 0, 'manager' => 1, 'member' => 2 ];
        usort( $list, function( $a, $b ) use ( $role_order ) {
            return $role_order[ $a['role'] ] <=> $role_order[ $b['role'] ];
        } );
        return $list;
    }

    public function join() {
        $user_id  = ( new User )->user_id();
        $time_now = date("Y-m-d H:i:s");
        if ( empty( $user_id ) ) {
            return;
        }
        $lookup = ( new Members )->where( [ "user_id" => $user_id, "group_id" => $this->group_id ] );
        if ( ! empty( $lookup ) ) {
            foreach( $lookup as $membership ) {
                ( new Members )->update( [ "active" => 1, "left_at" => null ], [ "member_id" => $membership->member_id ] );
            }
            return;
        }
        $membership = [
            "created_at" => $time_now,
            "user_id"    => $user_id,
            "group_id"   => $this->group_id,
            "active"     => 1,
        ];
        ( new Members )->insert( $membership );
    }

    public function leave() {
        $user_id  = ( new User )->user_id();
        $time_now = date("Y-m-d H:i:s");
        if ( empty( $user_id ) ) {
            return;
        }
        $lookup = ( new Members )->where( [ "user_id" => $user_id, "group_id" => $this->group_id ] );
        if ( ! empty( $lookup ) ) {
            foreach( $lookup as $membership ) {
                ( new Members )->update( [ "active" => 0, "left_at" => $time_now ], [ "member_id" => $membership->member_id ] );
            }
            return;
        }
    }

    public function import_members( $members = [] ) {
        foreach( $members as $member ) {
            $member  = (object) $member;
            $user    = get_user_by( 'email', $member->email );
            if ( $user ) {
                $user_id = $user->ID;
            }
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
            if ( empty( $user_id ) ) {
                continue;
            }
            $lookup = ( new Members )->where( [ "user_id" => $user_id, "group_id" => $this->group_id ] );
            if ( ! empty( $lookup ) ) {
                echo "Already added";
                continue;
            }
            $membership = [
                "user_id"  => $user_id,
                "group_id" => $this->group_id,
                "active"   => 1,
            ];
            if ( ! empty( $member->joined ) ) {
                $membership["created_at"] = date( 'Y-m-d 12:00:00', strtotime( $member->joined ) );
            }
            ( new Members )->insert( $membership );
        }
    }

}
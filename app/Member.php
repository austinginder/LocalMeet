<?php

namespace LocalMeet;

class Member {

    public function __construct( $member_id = "" ) {
        $this->member_id = $member_id;
    }

    public function fetch() {
        $member      = ( new Members )->get( $this->member_id );
        $user        = get_user_by( 'ID', $member->user_id );
        $member_info = [
            "first_name" => $user->first_name,
            "last_name"  => $user->last_name,
            "email"      => $user->user_email,
            "avatar"     => get_avatar_url( $user->user_email, [ "size" => "80" ] ),
        ];
        
        return $member_info;
    }

    public function leave() {
        ( new Members )->update( [ "active" => 0 ], [ "member_id" => $this->member_id ] );
    }

}
<?php

namespace LocalMeet;

class User {

    protected $user_id = "";
    protected $roles   = "";

    public function __construct( $user_id = "", $admin = false ) {
        if ( $admin ) {
            $this->user_id = $user_id;
            $user_meta     = get_userdata( $this->user_id );
            $this->roles   = $user_meta->roles;
            return;
        }
        $this->user_id = get_current_user_id();
        if ( ! empty( $this->user_id )) {
            $user_meta     = get_userdata( $this->user_id );
            $this->roles   = $user_meta->roles;
        }
    }

    public function fetch() {
        $user   = get_user_by( "ID", $this->user_id );
        $record = [
            "user_id"      => $this->user_id,
            "username"     => $user->user_login,
            "email"        => $user->user_email,
            "name"         => $user->display_name,
            "new_password" => "",
            "errors"       => []
        ];
        if ( get_user_meta( $this->user_id, 'localmeet_password_not_set' ) ) {
            $record["password_not_set"] = true;
        }
        return (object) $record;
    }

    public function user_id() {
        return $this->user_id;
    }

    public function is_admin() {
        if ( is_array( $this->roles ) && in_array( 'administrator', $this->roles ) ) {
            return true;
        }
        return false;
    }

}
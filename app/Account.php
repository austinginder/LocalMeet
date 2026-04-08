<?php

namespace LocalMeet;

class Account {

    private $user_id;

    public function __construct( $user_id = null ) {
        $this->user_id = $user_id ?: get_current_user_id();
    }

    public function needs_password() {
        return (bool) get_user_meta( $this->user_id, 'localmeet_password_not_set', true );
    }

    public function set_password( $password ) {
        $errors = [];
        if ( strlen( $password ) < 8 ) {
            $errors[] = "Password too short!";
        }
        if ( ! preg_match( "#[0-9]+#", $password ) ) {
            $errors[] = "Password must include at least one number!";
        }
        if ( ! preg_match( "#[a-zA-Z]+#", $password ) ) {
            $errors[] = "Password must include at least one letter!";
        }
        if ( count( $errors ) > 0 ) {
            return $errors;
        }
        wp_set_password( $password, $this->user_id );
        delete_user_meta( $this->user_id, 'localmeet_password_not_set' );
        // Re-authenticate after password change
        wp_set_current_user( $this->user_id );
        wp_set_auth_cookie( $this->user_id );
        return [];
    }

    public function groups() {
        $memberships = ( new Members )->where( [ "user_id" => $this->user_id, "active" => 1 ] );
        $groups = [];
        foreach ( $memberships as $m ) {
            $group = ( new Groups )->get( $m->group_id );
            if ( $group ) {
                $group->description_raw = $group->description;
                $group->description     = ( new \Parsedown )->text( $group->description );
                unset( $group->details, $group->owner_id, $group->created_at );
                $groups[] = $group;
            }
        }
        return $groups;
    }
}

<?php

namespace LocalMeet;

class Members extends DB {

    static $primary_key = 'member_id';

    public static function wants_email( $user_id, $group_id ) {
        $record = self::where( [ 'user_id' => $user_id, 'group_id' => $group_id, 'active' => 1 ] );
        if ( empty( $record ) ) {
            return false;
        }
        if ( property_exists( $record[0], 'email_notifications' ) && $record[0]->email_notifications == 0 ) {
            return false;
        }
        return true;
    }

}
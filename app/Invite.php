<?php

namespace LocalMeet;

class Invite {

    public static function remaining_allowance( $user_id ) {
        $allowance    = (int) get_user_meta( $user_id, 'localmeet_group_allowance', true );
        $owned_groups = ( new Groups )->where( [ 'owner_id' => $user_id ] );
        $used         = is_array( $owned_groups ) ? count( $owned_groups ) : 0;
        return max( 0, $allowance - $used );
    }

    public static function can_create_group( $user_id ) {
        if ( empty( $user_id ) ) {
            return false;
        }
        return self::remaining_allowance( $user_id ) > 0;
    }

}

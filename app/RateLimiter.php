<?php

namespace LocalMeet;

class RateLimiter {

    static function check( $action, $identifier, $max_attempts = 5, $window_seconds = 300 ) {
        $key   = 'localmeet_rate_' . md5( $action . '_' . $identifier );
        $count = (int) get_transient( $key );
        if ( $count >= $max_attempts ) {
            return false;
        }
        set_transient( $key, $count + 1, $window_seconds );
        return true;
    }

    static function ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            return trim( $ips[0] );
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

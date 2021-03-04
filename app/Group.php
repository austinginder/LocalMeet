<?php

namespace LocalMeet;

class Group {

    public function __construct( $group_id = "" ) {
        $this->group_id = $group_id;
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
    
}
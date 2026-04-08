<?php

namespace LocalMeet;

class App {

    public function current_user() {
      $user     = wp_get_current_user();
      $role     = "subscriber";
      if ( current_user_can( "administrator" ) ) {
          $role = "administrator";
      }
      $can_create_group = $role === "administrator" || Invite::can_create_group( $user->ID );
      $record = [
        "user_id"          => $user->ID,
        "username"         => $user->user_login,
        "email"            => $user->user_email,
        "first_name"       => $user->first_name,
        "last_name"        => $user->last_name,
        "name"             => $user->display_name,
        "role"             => $role,
        "avatar"           => get_avatar_url( $user->user_email, [ "size" => "80" ] ),
        "errors"           => [],
        "can_create_group" => $can_create_group,
      ];
      if ( get_user_meta( $user->ID, 'localmeet_password_not_set' ) ) {
        $record["password_not_set"] = true;
      }
      $record["nonce"] = wp_create_nonce( 'wp_rest' );
      return $record;
    }

    public function slugify ( $text ) {

      // replace non letter or digits by -
      $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    
      // transliterate
      $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    
      // remove unwanted characters
      $text = preg_replace('~[^-\w]+~', '', $text);
    
      // trim
      $text = trim($text, '-');
    
      // remove duplicate -
      $text = preg_replace('~-+~', '-', $text);
    
      // lowercase
      $text = strtolower($text);
    
      if (empty($text)) {
        return 'n-a';
      }
    
      return $text;

    }

}

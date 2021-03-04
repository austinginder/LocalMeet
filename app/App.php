<?php

namespace LocalMeet;

class App {

    public function current_user() {
      $user     = wp_get_current_user();
      $role     = "subscriber";
      if ( current_user_can( "administrator" ) ) {
          $role = "administrator";
      }
      $record = [
        "user_id"     => $user->ID,
        "username"    => $user->user_login,
        "email"       => $user->user_email,
        "name"        => $user->display_name,
        "role"        => $role,
        "avatar"      => get_avatar_url( $user->user_email, [ "size" => "80" ] ),
        "errors"      => [],
      ];
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

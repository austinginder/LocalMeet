<?php

/**
 * Plugin Name:       LocalMeet
 * Plugin URI:        https://localmeet.io
 * Description:       Self-starting local meetups
 * Version:           1.0.0
 * Author:            Austin Ginder
 * Author URI:        https://austinginder.com
 * License:           MIT License
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       localmeet
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

add_filter( 'template_include', 'load_localmeet_template' );
function load_localmeet_template( $original_template ) {
    return plugin_dir_path( __FILE__ ) . 'template.php';
}

function localmeet_head_content() {
    ob_start();
    do_action('wp_head');
    return ob_get_clean();
}

function localmeet_header_content_extracted() {
	$output = "<script type='text/javascript'>\n/* <![CDATA[ */\n";
	$head   = localmeet_head_content();
	preg_match_all('/(var wpApiSettings.+)/', $head, $results );
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	$output = $output . "</script>\n";
	preg_match_all('/(<link rel="(icon|apple-touch-icon).+)/', $head, $results );
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	$localmeet_header_includes = is_array( get_option( "localmeet_header_includes" ) ) ? get_option("localmeet_header_includes" ) : [];
	foreach ( $localmeet_header_includes as $localmeet_header_include ) {
		preg_match_all("/$localmeet_header_include/", $head, $results );
		if ( isset( $results ) && $results[0] ) {
			foreach( $results[0] as $match ) {
				$output = $output . $match . "\n";
			}
		}
	}
	return $output;
}

function localmeet_enqueue_scripts() {

    if ( is_user_logged_in() ) {
        $wpApiSettings = json_encode( [
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
         ] );
        $wpApiSettings = "var wpApiSettings = ${wpApiSettings};";
        wp_register_script( 'localmeet-wp-api', '' );
        wp_enqueue_script( 'localmeet-wp-api' );
        wp_add_inline_script( 'localmeet-wp-api', $wpApiSettings );
    }

}

add_action( 'wp_enqueue_scripts', 'localmeet_enqueue_scripts' );

add_action( 'rest_api_init', 'localmeet_register_rest_endpoints' );

function localmeet_register_rest_endpoints() {

    register_rest_route(
		'localmeet/v1', '/login', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_login_func',
			'show_in_index' => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/event/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'  => 'GET',
			'callback' => 'localmeet_event_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/attend', [
			'methods'  => 'POST',
			'callback' => 'localmeet_event_attend_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/announce', [
			'methods'  => 'GET',
			'callback' => 'localmeet_event_announce_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/delete', [
			'methods'  => 'GET',
			'callback' => 'localmeet_event_delete_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/update', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_event_update_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/new', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_event_comment_new_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/(?P<comment_id>[a-zA-Z0-9-]+)/update', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_event_comment_update_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/delete', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_event_comment_delete_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/events/create', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_events_create_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/attendee/create', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_attendee_create_func',
            'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/attendee/create/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_attendee_create_verify_func',
            'show_in_index' => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/', [
			'methods'  => 'GET',
			'callback' => 'localmeet_groups_func',
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/create', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_groups_create_func',
            'show_in_index' => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/create/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_groups_create_verify_func',
            'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'  => 'GET',
			'callback' => 'localmeet_group_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/update', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_groups_update_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/join/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_groups_join_verify_func',
            'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/join', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_groups_join_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/join', [
			'methods'       => 'POST',
			'callback'      => 'localmeet_groups_join_request_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/leave', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_groups_leave_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/leave/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_groups_member_leave_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/delete', [
			'methods'  => 'GET',
			'callback' => 'localmeet_group_delete_func',
		]
	);

	register_rest_route(
		'localmeet/v1', '/member/get/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'localmeet_member_get_func',
            'show_in_index' => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/organization/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'  => 'GET',
			'callback' => 'localmeet_organization_func',
		]
	);

}

function localmeet_login_func( WP_REST_Request $request ) {

	$post = json_decode( file_get_contents( 'php://input' ) );

	if ( $post->command == "reset" ) {

		$user_data = get_user_by( 'login', $post->login->user_login );
		if ( ! $user_data ) {
			$user_data = get_user_by( 'email', $post->login->user_login );
		}
		if ( ! $user_data ) {
			return;
		}

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
	
		// Redefining user_login ensures we return the right case in the email.
		$key        = get_password_reset_key( $user_data );
	
		if ( is_wp_error( $key ) ) {
			return $key;
		}
	
		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
	
		$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
		$message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";
		$title   = sprintf( __( '[%s] Password Reset' ), $site_name );
		$title   = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
	
		if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			wp_die( __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
		}

	}

	if ( $post->command == "signIn" ) {
		$credentials = [
			"user_login"    => $post->login->user_login,
			"user_password" => $post->login->user_password,
			"remember"      => true,
		];

		$current_user = wp_signon( $credentials );

		if ( $current_user->ID !== null ) {
			return [ "message" =>  "Logged in." ];
		} else {
			return [ "errors" => "Login failed." ];
		}		
	}

	if ( $post->command == "signOut" ) {
		wp_logout();
	}

    if ( $post->command == "updateAccount" ) {
        $user_id  = get_current_user_id();
		$account  = $post->user;
		$response = (object) [];
		$errors   = [];

		if ( $account->name == "" ) {
			$errors[] = "Display name can't be empty.";
		}

		if ( ! filter_var($account->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "Email address is not valid.";
		}
		
		// If new password sent then valid it.
		if ( $account->new_password != "" ) {

			$password = $account->new_password;

			if (strlen($password) < 8) {
				$errors[] = "Password too short!";
			}
		
			if (!preg_match("#[0-9]+#", $password)) {
				$errors[] = "Password must include at least one number!";
			}
		
			if (!preg_match("#[a-zA-Z]+#", $password)) {
				$errors[] = "Password must include at least one letter!";
			}
			
		}

		if ( count( $errors ) == 0 ) {
			$result = wp_update_user( [ 
				'ID'           => $user_id, 
				'display_name' => $account->name,
				'user_email'   => $account->email,
             ] );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( count( $errors ) == 0 && $account->new_password != "") {
			$result = wp_update_user( array( 
				'ID'        => $user_id, 
				'user_pass' => $account->new_password,
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
			delete_user_meta( $user_id, 'localmeet_password_not_set' );
		}

		if ( count($errors) > 0 ) {
			$response->errors = $errors;
		}

		$response->user = $account;
		unset ( $response->user->new_password );
		return $response;
    }

}

function localmeet_attendee_create_func( WP_REST_Request $request ) { 
	$post    = json_decode( file_get_contents( 'php://input' ) );
    $request = $post->request;
    $errors  = [];

    if ( $request->first_name == "" ) {
        $errors[] = "First name can't be empty.";
    }

	if ( $request->last_name == "" ) {
        $errors[] = "Last name can't be empty.";
    }

    if ( ! filter_var( $request->email, FILTER_VALIDATE_EMAIL ) ) {
        $errors[] = "Email address is not valid.";
    }

    if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}

	$valid_token = false;
	do {
		$token       = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		$token_check = ( new LocalMeet\AttendeeRequests )->where( [ "token" => $token ] );
		if ( ! $token_check ) {
			$valid_token = true;
		}
	} while ( $valid_token == false );

	( new LocalMeet\AttendeeRequests )->insert( [
		"event_id"   => $request->event_id,
		"first_name" => $request->first_name,
		"last_name"  => $request->last_name,
		"email"      => $request->email,
		"token"      => $token,
	] );

	$event      = ( new LocalMeet\Events )->get( $request->event_id );
	$event_at   = date('D M jS Y \a\t h:ia', strtotime( $event->event_at ) );
	$verify_url = home_url() . "/wp-json/localmeet/v1/attendee/create/$token";
	$subject    = "LocalMeet - Confirm your RSVP to '{$event->name}' $event_at";
	$body       = "Thanks for your interest in '{$event->name}' scheduled for $event_at.<br /><br /><a href=\"{$verify_url}\">Confirm your RSVP</a>.";
	$headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

	// Send email
	wp_mail( $request->email, $subject, $body, $headers );

}

function localmeet_groups_create_func( WP_REST_Request $request ) {

	$post    = json_decode( file_get_contents( 'php://input' ) );
    $request = $post->request;
    $errors  = [];
	$user    = new LocalMeet\User;

    if ( $request->name == "" ) {
        $errors[] = "Group name can't be empty.";
    }

	if ( $user->user_id() != 0 ) {
		$request->email = $user->fetch()->email;
	}

    if ( ! filter_var( $request->email, FILTER_VALIDATE_EMAIL ) ) {
        $errors[] = "Email address is not valid.";
    }

    if ( count ( $errors ) == 0 ) {
        $valid_token = false;
        do {
            $token       = bin2hex( openssl_random_pseudo_bytes( 16 ) );
            $token_check = ( new LocalMeet\GroupRequests )->where( [ "token" => $token ] );
            if ( ! $token_check ) {
                $valid_token = true;
            }
        } while ( $valid_token == false );

        ( new LocalMeet\GroupRequests )->insert( [ 
            "name"        => $request->name,
            "email"       => $request->email,
            "description" => $request->description,
            "token"       => $token 
        ] );
        
        $verify_url = home_url() . "/wp-json/localmeet/v1/groups/create/$token";
        $subject    = "LocalMeet - Verify new group '{$request->name}'";
        $body       = "Your almost ready to begin your group '{$request->name}'.<br /><br /><a href=\"{$verify_url}\">Verify and create the new group</a>.";
        $headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

        // Send email
        wp_mail( $request->email, $subject, $body, $headers );
    }

    return [ "errors" => $errors ];
}

function localmeet_event_delete_func( $request ) {
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	$group    = ( new LocalMeet\Groups )->get( $event->group_id );
	$user     = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() == $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}

	( new LocalMeet\Events )->delete( $event_id  );
	return;
}

function localmeet_event_announce_func( $request ) {
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	$group    = ( new LocalMeet\Groups )->get( $event->group_id );
	$user     = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() == $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Event( $event_id ) )->announce();
	return;
}

function localmeet_event_attend_func( $request ) {
	$going     = 0;
	$user      = ( new LocalMeet\User )->fetch();
	$selection = $request['selection'];
	if ( $selection == "going" ) {
		$going = 1;
	}
	$event_id  = $request['event_id'];
	$lookup    = ( new LocalMeet\Attendees )->where( [ "user_id" => $user->user_id, "event_id" => $event_id ] );
	if ( count( $lookup ) > 0 ) {
		foreach( $lookup as $attendee ) {
			( new LocalMeet\Attendees )->update( [ "going" => $going ], [ "attendee_id" => $attendee->attendee_id ] );
		}
		return;
	}
	$time_now    = date("Y-m-d H:i:s");
	$attendee_id = ( new LocalMeet\Attendees )->insert( [ "created_at" => $time_now, "user_id" => $user->user_id, "event_id" => $event_id, "going" => $going ] );
    return $attendee_id;
}

function localmeet_event_update_func( $request ) {
	$errors = [];
	$edit_event = (object) $request['edit_event'];
	$event      = (object) $edit_event->event;
	if ( empty( $event->name ) ) {
		$errors[] = "Name can't be empty.";
	}
	if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	( new LocalMeet\Events )->update([
			"name"        => $event->name,
			"event_at"    => $event->event_at,
			"description" => $event->description_raw,
			"location"    => $event->location,
			"slug"        => $event->slug,
		],[ "event_id"    => $event->event_id ]);

    return $event->event_id;
}

function localmeet_event_comment_new_func( $request ) {
	$errors     = [];
	$event      = (object) $edit_event->event;
	$time_now   = date("Y-m-d H:i:s");
	$user       = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	$comment_id = ( new LocalMeet\Comments )->insert( [
		"user_id"     => $user->user_id(),
		"event_id"    => $request['event_id'],
		"details"     => $request['comment'],
		"created_at"  => $time_now,
	] );

    return $comment_id;
}

function localmeet_event_comment_update_func( $request ) {
	$errors     = [];
	$event      = (object) $edit_event->event;
	$time_now   = date("Y-m-d H:i:s");
	$user       = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( ! $user->is_admin() && $comment->user_id != $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$comment_id = ( new LocalMeet\Comments )->update( [
		"event_id"    => $request['event_id'],
		"details"     => $request['comment'],
	], [ "comment_id"    => $request['comment_id'] ] );

    return $comment_id;
}

function localmeet_event_comment_delete_func( $request ) {
	$user = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$comment = ( new LocalMeet\Comments )->get( $request['comment_id'] );
	if ( ! $user->is_admin() || $comment->user_id != $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Comments )->delete( $comment->comment_id );
    return;
}

function localmeet_events_create_func( $request ) {
	// TODO: If user has permissions for group, then proceed
	$time_now = date("Y-m-d H:i:s");
	$event    = (object) $request['new_event'];
	$group    = ( new LocalMeet\Groups )->get( $event->group_id );
	$user     =  new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() == $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}

	$slug     = ( new LocalMeet\Group( $event->group_id ) )->generate_unique_event_slug( $event->name );
    $event_id = ( new LocalMeet\Events )->insert( [
		"name"        => $event->name,
		"slug"        => $slug,
		"group_id"    => $event->group_id,
		"description" => $event->description,
		"location"    => $event->location,
		"event_at"    => "{$event->date} {$event->time}",
	] );
	( new LocalMeet\Attendees )->insert( [ 
		"created_at" => $time_now,
		"user_id"    => get_current_user_id(),
		"event_id"   => $event_id,
		"going"      => true,
	] );
    return $event_id;
}

function localmeet_group_delete_func( $request ) {
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user  = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() == $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Groups )->delete( $group->group_id );
	return;
}

function localmeet_groups_join_verify_func( $request ) {
	$time_now = date("Y-m-d H:i:s");
	$token    = $request['token'];
    $request  = ( new LocalMeet\MemberRequests )->where( [ "token" => $token ] );
	
    if ( $request ) {
        foreach( $request as $r ) {
			$group = ( new LocalMeet\Groups )->get( $r->group_id );
            $user  = get_user_by( 'email', $r->email );
            if ( ! $user ) {
                $new_user = [
					"first_name" => $r->first_name,
					"last_name"  => $r->last_name,
                    'user_email' => $r->email,
                    'user_login' => $r->email,
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
                $user    = (object) [ "ID" => $user_id ];
                update_user_meta( $user->ID, 'localmeet_password_not_set', true );
            }
			$member_checks = ( new LocalMeet\Members )->where( [ "user_id" => $user->ID, "group_id" => $r->group_id ] );
			if ( ! empty( $member_checks ) ) {
				foreach ( $member_checks as $member_check ) {
					( new LocalMeet\Members )->update( [ "active" => true ], [ "member_id" => $member_check->member_id ] );
				}
			}
			if ( empty( $member_checks ) ) {
				( new LocalMeet\Members )->insert( [
					"created_at" => $time_now,
					"group_id"   => $r->group_id,
					"user_id"    => $user->ID,
					"active"     => true,
				] );
			}

            ( new LocalMeet\MemberRequests )->delete( $r->member_request_id );

            // Login as user
            wp_set_current_user( $user->ID, $r->email );
	        wp_set_auth_cookie( $user->ID );

            wp_redirect( "/group/{$group->slug}?joined=confirmed" );
            exit();
        }
    }
}

function localmeet_groups_join_func( $request ) {
	( new LocalMeet\Group( $request['group_id'] ) )->join();
	return;
}

function localmeet_groups_leave_func( $request ) {
	( new LocalMeet\Group( $request['group_id'] ) )->leave();
	return;
}

function localmeet_groups_member_leave_func( $request ) {
	$token     = $request['token'];
	$parts     = explode( "-", $token );
	$member_id = $parts[0];
	$token     = $parts[1];
	if ( empty( $member_id ) ) {
		return "Not found";
	}
	$created_at = ( new LocalMeet\Members )->get( $member_id )->created_at;
	$member     = new LocalMeet\Member( $member_id );
	if ( md5( $created_at ) != $token ) {
		return "Not found";
	}
	( new LocalMeet\Member( $member_id ) )->leave();
	return;
}

function localmeet_member_get_func( $request ) {
	$token     = $request['token'];
	$parts     = explode( "-", $token );
	$member_id = $parts[0];
	$token     = $parts[1];
	if ( empty( $member_id ) ) {
		return "Not found";
	}
	$created_at = ( new LocalMeet\Members )->get( $member_id )->created_at;
	$member     = new LocalMeet\Member( $member_id );
	if ( md5( $created_at ) != $token ) {
		return "Not found";
	}
	return $member->fetch();
}

function localmeet_groups_join_request_func( $request ) {
	$group_id    = $request['group_id'];
	$request     = (object) $request['request'];
	$errors      = [];
	$time_now    = date("Y-m-d H:i:s");
	$valid_token = false;

	if ( $request->first_name == "" ) {
		$errors[] = "First name can't be empty.";
	}
	if ( $request->last_name == "" ) {
		$errors[] = "Last name can't be empty.";
	}
	if ( ! filter_var( $request->email, FILTER_VALIDATE_EMAIL ) ) {
		$errors[] = "Email address is not valid.";
	}
	if ( count( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}

	do {
		$token       = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		$token_check = ( new LocalMeet\MemberRequests )->where( [ "token" => $token ] );
		if ( ! $token_check ) {
			$valid_token = true;
		}
	} while ( $valid_token == false );
	
	( new LocalMeet\MemberRequests )->insert( [
		"created_at" => $time_now,
		"group_id"   => $group_id,
		"first_name" => $request->first_name,
		"last_name"  => $request->last_name,
		"email"      => $request->email,
		"token"      => $token,
	] );

	$group      = ( new LocalMeet\Groups )->get( $group_id );
	$verify_url = home_url() . "/wp-json/localmeet/v1/group/join/$token";
	$subject    = "LocalMeet - Confirm joining group '{$group->name}'";
	$body       = "Thanks for your interest in joining '{$group->name}'.<br /><br /><a href=\"{$verify_url}\">Confirm joining group</a>.";
	$headers    = [ 'Content-Type: text/html; charset=UTF-8' ];

	// Send email
	wp_mail( $request->email, $subject, $body, $headers );
	return $request;
}

function localmeet_groups_update_func( $request ) {
	$errors = [];
	$edit_group = (object) $request['edit_group'];
	$group      = (object) $edit_group->group;
	if ( empty( $group->name ) ) {
		$errors[] = "Name can't be empty.";
	}
	if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	$current = ( new LocalMeet\Groups )->get( $group->group_id );
	$details                 = empty( $current->details ) ? (object) [] : json_decode( $current->details );
	$details->email_footer   = $group->email_footer_raw;
	$details->reply_to_name  = $group->reply_to_name;
	$details->reply_to_email = $group->reply_to_email;
    ( new LocalMeet\Groups )->update([
		"name"        => $group->name,
		"description" => $group->description_raw,
		"details"     => json_encode( $details ),
		"slug"        => $group->slug,
	],[ "group_id"    => $group->group_id ]);
	return $group->group_id;
}

function localmeet_attendee_create_verify_func( $request ) {
	$time_now = date("Y-m-d H:i:s");
	$token    = $request['token'];
    $request  = ( new LocalMeet\AttendeeRequests )->where( [ "token" => $token ] );
	
    if ( $request ) {
        foreach( $request as $r ) {
			$event = ( new LocalMeet\Events )->get( $r->event_id );
			$group = ( new LocalMeet\Groups )->get( $event->group_id );
            $user  = get_user_by( 'email', $r->email );
            if ( ! $user ) {
                $new_user = [
					"first_name" => $r->first_name,
					"last_name"  => $r->last_name,
                    'user_email' => $r->email,
                    'user_login' => $r->email,
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
                $user    = (object) [ "ID" => $user_id ];
                update_user_meta( $user->ID, 'localmeet_password_not_set', true );
            }
			$rsvp_checks = ( new LocalMeet\Attendees )->where( [ "user_id" => $user->ID, "event_id" => $r->event_id] );
			foreach( $rsvp_checks as $rsvp_check ) {
				( new LocalMeet\Attendees )->delete( $rsvp_check->attendee_id );
			}
            ( new LocalMeet\Attendees )->insert( [
                "created_at" => $time_now,
				"event_id"   => $r->event_id,
                "user_id"    => $user->ID,
				"going"      => true,
            ] );

            ( new LocalMeet\AttendeeRequests )->delete( $r->attendee_request_id );

            // Login as user
            wp_set_current_user( $user->ID, $r->email );
	        wp_set_auth_cookie( $user->ID );

            wp_redirect( "/group/{$group->slug}/{$event->slug}?rsvp=confirmed" );
            exit();
        }
    }
}

function localmeet_groups_func( $request ) {
	return ( new LocalMeet\Groups )->list();
}

function localmeet_groups_create_verify_func( $request ) {
    $token   = $request['token'];
    $request = ( new LocalMeet\GroupRequests )->where( [ "token" => $token ] );
    if ( $request ) {
        foreach( $request as $r ) {
            $user = get_user_by( 'email', $r->email );
            if ( ! $user ) {
                $new_user = [
                    'user_email' => $r->email,
                    'user_login' => $r->email,
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
                $user    = (object) [ "ID" => $user_id ];
                update_user_meta( $user->ID, 'localmeet_password_not_set', true );
            }
            $unique_slug = ( new LocalMeet\Groups )->generate_unique_slug( $r->name );
            ( new LocalMeet\Groups )->insert( [
                "organization_id" => 0,
                "name"        => $r->name,
                "slug"        => $unique_slug,
                "description" => $r->description,
                "owner_id"    => $user->ID,
            ] );

            ( new LocalMeet\GroupRequests )->delete( $request->group_request_id );

            // Login as user
            wp_set_current_user( $user->ID, $r->email );
	        wp_set_auth_cookie( $user->ID );

            wp_redirect( "/group/$unique_slug" );
            exit();
        }
    }
}

function localmeet_event_func( $request ) {
    $organization    = $_GET['organization'];
    $group           = $_GET['group'];
    $organization_id = 0;
    if ( $organization != "group" ) {
        $organization = ( new LocalMeet\Organizations )->where( [ "slug" => $organization ] );
        if ( $organization && isset( $organization[0] ) ) {
            $organization_id = $organization[0]->organization_id;
        }
    }
    $group  = ( new LocalMeet\Groups )->where( [ "slug" => $group, "organization_id" => $organization_id ] );
    if ( $group && isset( $group[0] ) ) { 
        $group_id = $group[0]->group_id;
    }
    $name   = $request['name'];
    $lookup = ( new LocalMeet\Events )->where(  [ "slug" => $name, "group_id" => $group_id ] );
    if ( count( $lookup ) != 1 ) {
        return new WP_Error( 'not_found', 'Event not found.', [ 'status' => 404 ] );
    }
    $lookup   = (object) $lookup[0];
	$time_now = date("Y-m-d H:i:s");
	if ( $lookup->event_at > $time_now ) {
		$lookup->status = "upcoming";
	}
	if ( $lookup->event_at < $time_now ) {
		$lookup->status = "past";
	}
    $lookup->description_raw = $lookup->description;
	$lookup->description     = ( new Parsedown )->text( $lookup->description );
	$going                   = ( new LocalMeet\Attendees )->where( [ "event_id" => $lookup->event_id, "going" => 1 ] );
	$not_going               = ( new LocalMeet\Attendees )->where( [ "event_id" => $lookup->event_id, "going" => 0 ] );
	foreach ( $going as $key => $attendee ) {
		$user             = get_userdata( $attendee->user_id );
        $attendee->name   = "{$user->first_name} {$user->last_name}";
        $attendee->avatar = get_avatar_url( $user->user_email, [ "size" => "80" ] );
        $going[ $key ]    = $attendee;
	}
	foreach ( $not_going as $key => $attendee ) {
		$user              = get_userdata( $attendee->user_id );
        $attendee->name    = "{$user->first_name} {$user->last_name}";
        $attendee->avatar  = get_avatar_url( $user->user_email, [ "size" => "80" ] );
        $not_going[ $key ] = $attendee;
	}

	array_multisort( 
		array_column( $going, 'description' ), SORT_DESC,
		array_column( $going, 'name' ), SORT_ASC,
		$going
	);
		
    $lookup->attendees     = $going; 
	$lookup->attendees_not = $not_going;

	return $lookup;
}

function localmeet_group_func( $request ) {
    $organization = $_GET['organization'];
    $name         = $request['name'];
    $request      = [ "slug" => $name ];
    if ( ! empty( $organization ) ) {
        $lookup = ( new LocalMeet\Organizations )->where( [ "slug" => $organization ] );
        if ( count( $lookup ) == 1 ) {
            $request["organization_id"] = $lookup[0]->organization_id;
        }
    }
    $lookup = ( new LocalMeet\Groups )->where( $request );
    if ( count( $lookup ) != 1 ) {
        return new WP_Error( 'not_found', 'Group not found.', [ 'status' => 404 ] );
    }
    $group = ( new LocalMeet\Group( $lookup[0]->group_id ) )->fetch();
	return $group;
}

function localmeet_organization_func( $request ) {
    $organization = $_GET['organization'];
    $name         = $request['name'];
    $request      = [ "slug" => $name ];
    $lookup       = ( new LocalMeet\Organizations )->where( [ "slug" => $name ] );
    if ( count( $lookup ) != 1 ) {
        return new WP_Error( 'not_found', 'Organization not found.', [ 'status' => 404 ] );
    }
	return $lookup[0];
}

// Makes sure that any request going to /account/... will respond with a proper 200 http code
add_action( 'init', 'localmeet_rewrites_init' );
function localmeet_rewrites_init(){
	$pages = [ 
		'^find-group',
		'^group/(.+)',
		'^group/(.+)/(.+)',
	];
	foreach( $pages as $page ) {
		add_rewrite_rule( $page, 'index.php', 'top' );
	}
}

// Disable 404 redirects when unknown request goes to "/account/<..>/..." which allows a custom template to load. See https://wordpress.stackexchange.com/questions/3326/301-redirect-instead-of-404-when-url-is-a-prefix-of-a-post-or-page-name
add_filter('redirect_canonical', 'disable_redirection_for_localmeet');
function disable_redirection_for_localmeet($redirect_url) {
	global $wp;
	$pages = [ 
		'find-group',
		'group/',
	];
	foreach( $pages as $page ) {
		if ( strpos( $wp->request, $page ) !== false ) {
			return false;
		}
	}
    return $redirect_url;
}

function localmeet_meta_description() {
	global $wp;
	if ( empty( $wp->request ) ) {
		echo "Self-starting local meetups";
		return;
	}
}

function localmeet_content() {
	global $wp;

	if ( empty( $wp->request ) ) {
		echo 'LocalMeet is an <a href="https://github.com/austinginder/LocalMeet" target="_new">open source</a> meetup tool powered by WordPress.';
		echo '<a href="/start-group">Start Group</a>';
		echo '<a href="/find-group">Find Group</a>';
		return;
	}

	if ( $wp->request == "start-group" ) {
		echo "<h1>Start a new group</h1>";
		return;
	}

	if ( $wp->request == "find-group" ) {
		echo "<h1>Find a group</h1>";
		$groups = ( new LocalMeet\Groups )->all();
		foreach ( $groups as $group ) {
			echo "<a href=\"/group/$group->slug\">$group->name</a>\n";
		}
		return;
	}

	if ( strpos( $wp->request, "group/" ) !== false && substr_count ( $wp->request , "/" ) == 2 ) {
		$ids = explode( "/", $wp->request );
		$group           = $ids[1];
		$organization_id = 0;
		$group  = ( new LocalMeet\Groups )->where( [ "slug" => $group, "organization_id" => $organization_id ] );
		if ( $group && isset( $group[0] ) ) { 
			$group_id = $group[0]->group_id;
		}
		$name   = $ids[2];
		$lookup = ( new LocalMeet\Events )->where(  [ "slug" => $name, "group_id" => $group_id ] );
		if ( count( $lookup ) != 1 ) {
			return new WP_Error( 'not_found', 'Event not found.', [ 'status' => 404 ] );
		}
		$lookup   = (object) $lookup[0];
		$time_now = date("Y-m-d H:i:s");
		if ( $lookup->event_at > $time_now ) {
			$lookup->status = "upcoming";
		}
		if ( $lookup->event_at < $time_now ) {
			$lookup->status = "past";
		}
		$lookup->description_raw = $lookup->description;
		$lookup->description     = ( new Parsedown )->text( $lookup->description );
		$lookup->attendees       = ( new LocalMeet\Attendees )->where( [ "event_id" => $lookup->event_id, "going" => 1 ] );
		$lookup->attendees_not   = ( new LocalMeet\Attendees )->where( [ "event_id" => $lookup->event_id, "going" => 0 ] );
		foreach( $lookup->attendees as $key => $attendee ) {
			$user                      = get_userdata( $attendee->user_id );
			$attendee->name            = "{$user->first_name} {$user->last_name}";
			$attendee->avatar          = get_avatar_url( $user->user_email, [ "size" => "80" ] );
			$lookup->attendees[ $key ] = $attendee;
		}
		foreach( $lookup->attendees_not as $key => $attendee ) {
			$user                      = get_userdata( $attendee->user_id );
			$attendee->name            = "{$user->first_name} {$user->last_name}";
			$attendee->avatar          = get_avatar_url( $user->user_email, [ "size" => "80" ] );
			$lookup->attendees_not[ $key ] = $attendee;
		}
		$event_at = ( new DateTime( $lookup->event_at ) )->format('D F j, Y, H:i a');
		echo "<h1>$lookup->name</h1><h2>$event_at</h2><div>$lookup->description</div>";
		return;
	}

	if ( strpos( $wp->request, "group/" ) !== false ) {
		$url_splits   = explode( "/", $wp->request);
		$name         = $url_splits[1];
		$request      = [ "slug" => $name ];
		$lookup       = ( new LocalMeet\Groups )->where( $request );
		if ( count( $lookup ) != 1 ) {
			echo 'Group not found.';
		}
		$lookup = $lookup[0];
		$lookup->upcoming = ( new LocalMeet\Events )->upcoming( [ "group_id" => $lookup->group_id ] );
		$lookup->past = ( new LocalMeet\Events )->past( [ "group_id" => $lookup->group_id ] );
		if ( empty( $lookup->upcoming ) ) {
			$lookup->upcoming = [];
		}
		if ( empty( $lookup->past ) ) {
			$lookup->past = [];
		}
		echo "<h1>$lookup->name</h1><h2>$lookup->description</h2>";
		echo "<h1>Find a group.</h1>\n";
		echo "<h2>Upcoming Events</h2>\n";
		foreach( $lookup->upcoming as $event ) {
			echo "<a href=\"/group/$lookup->slug/$event->slug\">$event->slug</a>\n";
		}
		echo "<h2>Past Events</h2>\n";
		foreach( $lookup->past as $event ) {
			echo "<a href=\"/group/$lookup->slug/$event->slug\">$event->slug</a>\n";
		}
		return;
	}

	echo "Page not found.";
	return;

}
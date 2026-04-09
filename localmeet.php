<?php

/**
 * Plugin Name:       LocalMeet
 * Plugin URI:        https://localmeet.io
 * Description:       Self-starting local meetups
 * Version:           2.0.0
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

new LocalMeet\Updater();

function localmeet_generate_username( $email ) {
	$base = strstr( $email, '@', true );
	$base = preg_replace( '/[^a-zA-Z0-9]/', '', $base );
	if ( empty( $base ) ) {
		$base = 'user';
	}
	$username = $base;
	while ( username_exists( $username ) ) {
		$username = $base . '-' . substr( bin2hex( random_bytes( 4 ) ), 0, 6 );
	}
	return $username;
}

add_action( 'admin_init', 'localmeet_redirect_non_admins' );
function localmeet_redirect_non_admins() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_redirect( home_url() );
		exit;
	}
}

add_action( 'localmeet_send_email_batch', [ 'LocalMeet\EmailQueue', 'process_batch' ], 10, 3 );

add_filter( 'get_avatar_url', 'localmeet_local_avatar_url', 10, 3 );
function localmeet_local_avatar_url( $url, $id_or_email, $args ) {
	$user_id = 0;
	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
		if ( $user ) $user_id = $user->ID;
	} elseif ( $id_or_email instanceof \WP_User ) {
		$user_id = $id_or_email->ID;
	}
	if ( $user_id ) {
		$local = get_user_meta( $user_id, 'localmeet_avatar', true );
		if ( $local ) {
			$avatar_url = wp_get_attachment_url( $local );
			if ( $avatar_url ) return $avatar_url;
		}
	}
	return $url;
}

add_filter( 'show_admin_bar', 'localmeet_hide_admin_bar' );
function localmeet_hide_admin_bar( $show ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	return $show;
}

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
			'methods'             => 'POST',
			'callback'            => 'localmeet_login_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/event/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_event_func',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/attend', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_attend_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/announce', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_announce_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/announce-preview', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_announce_preview_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/announce-info', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_event_announce_info_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/announce-status', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_event_announce_status_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/rsvp-info/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_event_rsvp_info_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/rsvp-confirm/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_rsvp_confirm_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/delete', [
			'methods'             => 'DELETE',
			'callback'            => 'localmeet_event_delete_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/cancel', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_cancel_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/notice', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_group_notice_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/update', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_update_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/new', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_comment_new_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/(?P<comment_id>[a-zA-Z0-9-]+)/update', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_comment_update_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/delete', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_comment_delete_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/events/create', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_events_create_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/attendee/create', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_attendee_create_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/attendee/create/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_attendee_create_verify_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/media/upload', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_media_upload_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/media/mine', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_media_mine_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/test-email', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_group_test_email_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_func',
			'permission_callback' => '__return_true',
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/search', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_search_func',
			'permission_callback' => '__return_true',
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/create', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_groups_create_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/groups/create/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_create_verify_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_group_func',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/update', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_groups_update_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/join/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_join_verify_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/join', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_join_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/join', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_groups_join_request_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/leave', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_leave_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/leave/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_groups_member_leave_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/delete', [
			'methods'             => 'DELETE',
			'callback'            => 'localmeet_group_delete_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/events/search', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_events_search_func',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/members/export', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_members_export_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/locations', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_locations_list_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/locations/create', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_locations_create_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/location/(?P<location_id>[a-zA-Z0-9-]+)/update', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_location_update_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/location/(?P<location_id>[a-zA-Z0-9-]+)/delete', [
			'methods'             => 'DELETE',
			'callback'            => 'localmeet_location_delete_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/transfer', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_group_transfer_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/member/(?P<member_id>[0-9]+)/role', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_member_role_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/member/get/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_member_get_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/member/mute/(?P<token>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_member_mute_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[a-zA-Z0-9-]+)/notifications', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_member_notifications_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/event/(?P<event_id>[a-zA-Z0-9-]+)/comment/(?P<comment_id>[a-zA-Z0-9-]+)/moderate', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_event_comment_moderate_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

    register_rest_route(
		'localmeet/v1', '/organization/(?P<name>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_organization_func',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		'localmeet/v1', '/invite/create', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_invite_create_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/invites', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_invites_func',
			'permission_callback' => 'is_user_logged_in',
		]
	);

	register_rest_route(
		'localmeet/v1', '/invite/accept/(?P<token>[a-zA-Z0-9]+)', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_invite_accept_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[0-9]+)/merge-users/candidates', [
			'methods'             => 'GET',
			'callback'            => 'localmeet_merge_users_candidates_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

	register_rest_route(
		'localmeet/v1', '/group/(?P<group_id>[0-9]+)/merge-users', [
			'methods'             => 'POST',
			'callback'            => 'localmeet_merge_users_func',
			'permission_callback' => 'is_user_logged_in',
			'show_in_index'       => false
		]
	);

}

function localmeet_login_func( WP_REST_Request $request ) {

	$post = json_decode( file_get_contents( 'php://input' ) );

	if ( $post->command == "currentUser" ) {
		return ( new LocalMeet\App )->current_user();
	}

	if ( $post->command == "reset" ) {
		if ( ! LocalMeet\RateLimiter::check( 'reset', LocalMeet\RateLimiter::ip(), 3, 600 ) ) {
			return [ "errors" => "Too many reset attempts. Please try again later." ];
		}

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
		if ( ! LocalMeet\RateLimiter::check( 'login', LocalMeet\RateLimiter::ip(), 5, 300 ) ) {
			return [ "errors" => "Too many login attempts. Please try again later." ];
		}
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
		if ( ! empty( $account->new_password ) ) {

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
				'first_name'   => $account->first_name ?? '',
				'last_name'    => $account->last_name ?? '',
				'display_name' => $account->name,
				'user_email'   => $account->email,
             ] );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( count( $errors ) == 0 && ! empty( $account->new_password ) ) {
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

	if ( $post->command == "setPassword" ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [ "errors" => [ "Not logged in." ] ];
		}
		$account = new LocalMeet\Account( $user_id );
		$errors  = $account->set_password( $post->password );
		if ( count( $errors ) > 0 ) {
			return [ "errors" => $errors ];
		}
		return [ "message" => "Password set." ];
	}

	if ( $post->command == "myGroups" ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return [ "errors" => [ "Not logged in." ] ];
		}
		$account = new LocalMeet\Account( $user_id );
		return $account->groups();
	}

}

function localmeet_attendee_create_func( WP_REST_Request $request ) {
	if ( ! LocalMeet\RateLimiter::check( 'rsvp_email', LocalMeet\RateLimiter::ip(), 5, 300 ) ) {
		return [ "errors" => [ "Too many attempts. Please try again later." ] ];
	}
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
		$token       = bin2hex( random_bytes( 32 ) );
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
	$verify_url = home_url() . "/wp-json/localmeet/v1/attendee/create/$token";
	LocalMeet\Mailer::send_rsvp_verification( $request->email, $event->name, $verify_url );

}

function localmeet_groups_create_func( WP_REST_Request $request ) {
	if ( ! LocalMeet\RateLimiter::check( 'group_create', LocalMeet\RateLimiter::ip(), 3, 600 ) ) {
		return [ "errors" => [ "Too many attempts. Please try again later." ] ];
	}

	$post    = json_decode( file_get_contents( 'php://input' ) );
    $request = $post->request;
    $errors  = [];
	$user    = new LocalMeet\User;

	if ( ! $user->is_admin() && ! LocalMeet\Invite::can_create_group( $user->user_id() ) ) {
		$errors[] = "You don't have permission to create groups.";
		return [ "errors" => $errors ];
	}

    if ( $request->name == "" ) {
        $errors[] = "Group name can't be empty.";
    }

    if ( count ( $errors ) > 0 ) {
        return [ "errors" => $errors ];
    }

    $unique_slug = ( new LocalMeet\Groups )->generate_unique_slug( $request->name );
    $group_id = ( new LocalMeet\Groups )->insert( [
        "organization_id" => 0,
        "name"            => $request->name,
        "slug"            => $unique_slug,
        "description"     => $request->description ?? '',
        "owner_id"        => $user->user_id(),
    ] );

    // Auto-join owner as member
    ( new LocalMeet\Group( $group_id ) )->join();

    return [ "errors" => [], "redirect" => "/group/$unique_slug" ];
}

function localmeet_event_delete_func( $request ) {
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user  = new LocalMeet\User;
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}

	( new LocalMeet\Events )->delete( $event_id );
	return;
}

function localmeet_event_cancel_func( $request ) {
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user  = new LocalMeet\User;
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Events )->update( [
		"cancelled_at" => date( "Y-m-d H:i:s" ),
	], [ "event_id" => $event_id ] );

	$event_at = date( 'l, F jS Y \a\t g:i a', strtotime( $event->event_at ) );
	return [
		"message"        => "Event cancelled.",
		"notice_subject" => "{$event->name} has been cancelled",
		"notice_message" => "<p><strong>{$event->name}</strong> scheduled for {$event_at} has been cancelled.</p>",
	];
}

function localmeet_group_test_email_func( $request ) {
	$group_id = $request['group_id'];
	$group    = ( new LocalMeet\Groups )->get( $group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user = new LocalMeet\User;
	if ( ! $user->is_admin() && $user->user_id() != $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$current_user = wp_get_current_user();
	$group_data   = ( new LocalMeet\Group( $group_id ) )->fetch();
	$reply_to     = "Reply-To: {$group_data->reply_to_name} <{$group_data->reply_to_email}>";
	$group_url    = home_url( "/group/{$group->slug}" );
	$button       = LocalMeet\Mailer::action_button_public( $group_url, 'View Group' );

	$content = "
		<p>Hi {$current_user->first_name},</p>
		<p>This is a test email from <strong>{$group->name}</strong>. If you're reading this, email delivery is working correctly.</p>
		<p><strong>Reply-To Name:</strong> {$group_data->reply_to_name}<br>
		<strong>Reply-To Email:</strong> {$group_data->reply_to_email}</p>
		{$button}
		<div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #edf2f7; font-size: 13px; color: #a0aec0;'>
			{$group_data->email_footer}
		</div>
	";
	LocalMeet\Mailer::send_email_with_layout_public(
		$current_user->user_email,
		"Test email from {$group->name}",
		$group->name,
		'Test Email',
		$content,
		[ $reply_to ]
	);
	return [ "message" => "Test email sent to {$current_user->user_email}." ];
}

function localmeet_group_notice_func( $request ) {
	$group_id = $request['group_id'];
	$group    = ( new LocalMeet\Groups )->get( $group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user = new LocalMeet\User;
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( ! LocalMeet\RateLimiter::check( 'notice', $user->user_id(), 5, 300 ) ) {
		return [ "errors" => [ "Too many notices. Please try again later." ] ];
	}
	$post    = json_decode( $request->get_body() );
	$subject = sanitize_text_field( $post->subject ?? '' );
	$message = wp_kses_post( $post->message ?? '' );
	if ( empty( $subject ) || empty( $message ) ) {
		return [ "errors" => [ "Subject and message are required." ] ];
	}
	$result = LocalMeet\EmailQueue::start_notice( $group_id, $subject, $message );
	return [ "message" => "Notice started.", "sending" => true, "job_id" => $result['job_id'] ?? null, "total" => $result['total'] ];
}

function localmeet_event_announce_func( $request ) {
	$user     = new LocalMeet\User;
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	if ( property_exists( $event, 'announced_at' ) && ! empty( $event->announced_at ) ) {
		return [ "errors" => [ "This event has already been announced." ] ];
	}
	$group    = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( ! LocalMeet\RateLimiter::check( 'announce', $user->user_id(), 2, 300 ) ) {
		return [ "errors" => [ "Too many announcements. Please try again later." ] ];
	}
	$progress = LocalMeet\EmailQueue::get_progress( 'announce', $event_id );
	if ( $progress && $progress['status'] === 'sending' ) {
		return [ "errors" => [ "Announcement is already being sent." ] ];
	}
	LocalMeet\EmailQueue::start_announcement( $event_id );
	return [ "message" => "Announcement started.", "sending" => true ];
}

function localmeet_event_announce_preview_func( $request ) {
	$user     = new LocalMeet\User;
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( ! LocalMeet\RateLimiter::check( 'announce_preview', $user->user_id(), 5, 300 ) ) {
		return [ "errors" => [ "Too many preview emails. Please try again later." ] ];
	}
	$post  = json_decode( file_get_contents( 'php://input' ) );
	$email = ! empty( $post->email ) ? sanitize_email( $post->email ) : wp_get_current_user()->user_email;
	if ( ! is_email( $email ) ) {
		return [ "errors" => [ "Invalid email address." ] ];
	}
	LocalMeet\Mailer::announce_event_preview( $event_id, $email );
	return [ "message" => "Preview sent to {$email}." ];
}

function localmeet_event_announce_info_func( $request ) {
	$user     = new LocalMeet\User;
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	global $wpdb;
	$table = $wpdb->prefix . 'localmeet_members';
	$subscriber_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE group_id = %d AND active = 1 AND (email_notifications IS NULL OR email_notifications != 0)",
		$group->group_id
	) );
	$announced_at = property_exists( $event, 'announced_at' ) ? $event->announced_at : null;
	$response = [ "subscriber_count" => $subscriber_count, "announced_at" => $announced_at ];
	$progress = LocalMeet\EmailQueue::get_progress( 'announce', $event_id );
	if ( $progress && $progress['status'] === 'sending' ) {
		$response['sending'] = true;
		$response['sent']    = $progress['sent'];
		$response['total']   = $progress['total'];
	}
	return $response;
}

function localmeet_event_announce_status_func( $request ) {
	$user     = new LocalMeet\User;
	$event_id = $request['event_id'];
	$event    = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$progress = LocalMeet\EmailQueue::get_progress( 'announce', $event_id );
	if ( ! $progress ) {
		return [ "status" => "idle" ];
	}
	$result = [
		"status" => $progress['status'],
		"sent"   => $progress['sent'],
		"total"  => $progress['total'],
	];
	if ( $progress['status'] === 'complete' ) {
		$event = ( new LocalMeet\Events )->get( $event_id );
		$result['announced_at'] = property_exists( $event, 'announced_at' ) ? $event->announced_at : null;
		LocalMeet\EmailQueue::cleanup( 'announce', $event_id );
	}
	return $result;
}

function localmeet_validate_member_token( $token ) {
	$parts     = explode( "-", $token, 2 );
	$member_id = $parts[0] ?? '';
	$hash      = $parts[1] ?? '';
	if ( empty( $member_id ) || empty( $hash ) ) {
		return null;
	}
	$member_data = ( new LocalMeet\Members )->get( $member_id );
	if ( ! $member_data || wp_hash( $member_data->created_at ) !== $hash ) {
		return null;
	}
	return $member_data;
}

function localmeet_event_rsvp_info_func( $request ) {
	$token   = $request['token'];
	$member  = localmeet_validate_member_token( $token );
	if ( ! $member ) {
		return new WP_Error( 'invalid_token', 'Invalid or expired link.', [ 'status' => 403 ] );
	}

	$event_slug = $request['event_id'];
	$lookup     = ( new LocalMeet\Events )->where( [ "slug" => $event_slug, "group_id" => $member->group_id ] );
	if ( empty( $lookup ) ) {
		return new WP_Error( 'not_found', 'Event not found.', [ 'status' => 404 ] );
	}
	$event_id = $lookup[0]->event_id;
	$event    = ( new LocalMeet\Event( $event_id ) )->fetch();

	$user      = get_userdata( $member->user_id );
	$attending = ( new LocalMeet\Attendees )->where( [ "user_id" => $member->user_id, "event_id" => $event_id, "going" => 1 ] );

	return [
		"event_name"       => $event->name,
		"event_at"         => $event->event_at,
		"event_end_at"     => $event->event_end_at ?? null,
		"description"      => $event->description ?? '',
		"location_name"    => $event->location_name ?? '',
		"location_address" => $event->location_address ?? '',
		"attendees"        => $event->attendees ?? [],
		"first_name"       => $user ? $user->first_name : '',
		"is_going"         => ! empty( $attending ),
	];
}

function localmeet_event_rsvp_confirm_func( $request ) {
	$token   = $request['token'];
	$member  = localmeet_validate_member_token( $token );
	if ( ! $member ) {
		return new WP_Error( 'invalid_token', 'Invalid or expired link.', [ 'status' => 403 ] );
	}

	if ( ! $member->active ) {
		return [ "errors" => [ "You must join the group before RSVPing." ] ];
	}

	$event_slug = $request['event_id'];
	$lookup_evt = ( new LocalMeet\Events )->where( [ "slug" => $event_slug, "group_id" => $member->group_id ] );
	if ( empty( $lookup_evt ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$event_id   = $lookup_evt[0]->event_id;
	$event_data = $lookup_evt[0];

	// Capacity check
	if ( $event_data->capacity ) {
		$going_count = ( new LocalMeet\Attendees )->count_where( [ "event_id" => $event_id, "going" => 1 ] );
		$existing    = ( new LocalMeet\Attendees )->where( [ "user_id" => $member->user_id, "event_id" => $event_id, "going" => 1 ] );
		if ( empty( $existing ) && $going_count >= (int) $event_data->capacity ) {
			return [ "errors" => [ "This event is full." ] ];
		}
	}

	$lookup = ( new LocalMeet\Attendees )->where( [ "user_id" => $member->user_id, "event_id" => $event_id ] );
	if ( count( $lookup ) > 0 ) {
		foreach ( $lookup as $attendee ) {
			( new LocalMeet\Attendees )->update( [ "going" => 1 ], [ "attendee_id" => $attendee->attendee_id ] );
		}
	} else {
		$time_now = date( "Y-m-d H:i:s" );
		( new LocalMeet\Attendees )->insert( [ "created_at" => $time_now, "user_id" => $member->user_id, "event_id" => $event_id, "going" => 1 ] );
	}

	return [ "success" => true ];
}

function localmeet_event_attend_func( $request ) {
	$going     = 0;
	$user      = ( new LocalMeet\User )->fetch();
	$selection = $request['selection'];
	if ( $selection == "going" ) {
		$going = 1;
	}
	$event_id  = $request['event_id'];

	// Membership check
	$event_data = ( new LocalMeet\Events )->get( $event_id );
	if ( empty( $event_data ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$is_member = ( new LocalMeet\Members )->where( [ "user_id" => $user->user_id, "group_id" => $event_data->group_id, "active" => 1 ] );
	if ( empty( $is_member ) ) {
		return [ "errors" => [ "You must join the group before RSVPing." ] ];
	}

	// Capacity check when marking as going
	if ( $going ) {
		$event_data  = ( new LocalMeet\Events )->get( $event_id );
		if ( empty( $event_data ) ) {
			return [ "errors" => [ "Event not found." ] ];
		}
		if ( $event_data->capacity ) {
			$going_count = ( new LocalMeet\Attendees )->count_where( [ "event_id" => $event_id, "going" => 1 ] );
			// Exclude current user from count if already RSVP'd
			$existing = ( new LocalMeet\Attendees )->where( [ "user_id" => $user->user_id, "event_id" => $event_id, "going" => 1 ] );
			if ( empty( $existing ) && $going_count >= (int) $event_data->capacity ) {
				return [ "errors" => [ "This event is full." ] ];
			}
		}
	}

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
	$errors     = [];
	$edit_event = (object) $request['edit_event'];
	$event      = (object) $edit_event->event;
	$user       = new LocalMeet\User;
	$current    = ( new LocalMeet\Events )->get( $event->event_id );
	if ( empty( $current ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group      = ( new LocalMeet\Groups )->get( $current->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( empty( $event->name ) ) {
		$errors[] = "Name can't be empty.";
	}
	if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	$location = $event->location ?? '';
	if ( ! empty( $event->location_name ) || ! empty( $event->location_address ) ) {
		$location = json_encode( [
			"name"    => $event->location_name ?? '',
			"address" => $event->location_address ?? '',
		] );
	}
	$event_end_at = $event->event_end_at ?? null;
	$capacity     = isset( $event->capacity ) && $event->capacity !== '' ? (int) $event->capacity : null;
	$image_id     = property_exists( $event, 'image_id' ) ? ( ! empty( $event->image_id ) ? (int) $event->image_id : null ) : $current->image_id;

	// Use provided slug if changed, or regenerate when name changes
	$new_slug = $current->slug;
	if ( ! empty( $event->slug ) && $event->slug !== $current->slug ) {
		$new_slug = ( new LocalMeet\App )->slugify( $event->slug );
		// Ensure unique within group (excluding current event)
		global $wpdb;
		$events_table = $wpdb->prefix . 'localmeet_events';
		$conflict = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$events_table} WHERE group_id = %d AND slug = %s AND event_id != %d",
			$current->group_id, $new_slug, $current->event_id
		) );
		if ( $conflict ) {
			$new_slug .= '-' . substr( md5( $current->event_id ), 0, 4 );
		}
	} elseif ( $event->name !== $current->name ) {
		$new_slug = ( new LocalMeet\Group( $current->group_id ) )->generate_unique_event_slug( $event->name, $current->slug );
	}

	( new LocalMeet\Events )->update([
			"name"         => $event->name,
			"event_at"     => $event->event_at,
			"event_end_at" => $event_end_at,
			"capacity"     => $capacity,
			"description"  => $event->description_raw,
			"summary"      => $event->summary_raw,
			"location"     => $location,
			"slug"         => $new_slug,
			"image_id"     => $image_id,
		],[ "event_id"     => $event->event_id ]);

	// Detect time/location changes for optional notice
	$changes = [];
	if ( $current->event_at !== $event->event_at ) {
		$changes[] = "<strong>New date/time:</strong> " . date( 'l, F jS Y \a\t g:i a', strtotime( $event->event_at ) );
	}
	if ( $event_end_at !== $current->event_end_at ) {
		if ( $event_end_at ) {
			$changes[] = "<strong>New end time:</strong> " . date( 'g:i a', strtotime( $event_end_at ) );
		}
	}
	if ( $location !== $current->location ) {
		$loc_data = json_decode( $location );
		if ( $loc_data && is_object( $loc_data ) ) {
			$parts = array_filter( [ $loc_data->name ?? '', $loc_data->address ?? '' ] );
			$changes[] = "<strong>New location:</strong> " . implode( ', ', $parts );
		}
	}

	$response = [ "event_id" => $event->event_id, "slug" => $new_slug ];
	if ( ! empty( $changes ) ) {
		$response["notice_subject"] = "{$event->name} has been updated";
		$response["notice_message"] = "<p><strong>{$event->name}</strong> has been updated:</p><ul><li>" . implode( "</li><li>", $changes ) . "</li></ul>";
	}
    return $response;
}

function localmeet_event_comment_new_func( $request ) {
	$errors     = [];
	$time_now   = date("Y-m-d H:i:s");
	$user       = new LocalMeet\User;
	if ( ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$event_data = ( new LocalMeet\Events )->get( $request['event_id'] );
	$membership = ( new LocalMeet\Members )->where( [ "user_id" => $user->user_id(), "group_id" => $event_data->group_id, "active" => 1 ] );
	if ( ! $user->is_admin() && empty( $membership ) ) {
		return [ "errors" => [ "You must be a group member to comment." ] ];
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

	LocalMeet\Mailer::notify_organizer_new_comment( $request['event_id'], $request['comment'], $user->user_id() );

    return $comment_id;
}

function localmeet_event_comment_update_func( $request ) {
	$user = new LocalMeet\User;
	if ( ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$comment = ( new LocalMeet\Comments )->get( $request['comment_id'] );
	if ( ! $user->is_admin() && $comment->user_id != $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Comments )->update( [
		"event_id"    => $request['event_id'],
		"details"     => $request['comment'],
	], [ "comment_id"    => $request['comment_id'] ] );

    return $request['comment_id'];
}

function localmeet_event_comment_delete_func( $request ) {
	$user = new LocalMeet\User;
	if ( ! $user->is_admin() && ! $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$comment = ( new LocalMeet\Comments )->get( $request['comment_id'] );
	if ( empty( $comment ) ) {
		return [ "errors" => [ "Comment not found." ] ];
	}
	if ( ! $user->is_admin() && $comment->user_id != $user->user_id() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	( new LocalMeet\Comments )->delete( $comment->comment_id );
    return;
}

function localmeet_events_create_func( $request ) {
	$time_now = date("Y-m-d H:i:s");
	$event    = (object) $request['new_event'];
	$group    = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user     = new LocalMeet\User;
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}

	$errors = [];
	if ( empty( $event->name ) ) {
		$errors[] = "Name is required.";
	}
	if ( empty( $event->date ) || empty( $event->time ) ) {
		$errors[] = "Date and time are required.";
	}
	if ( ! empty( $event->date ) && ! empty( $event->time ) ) {
		$event_at = strtotime( "{$event->date} {$event->time}" );
		if ( $event_at && $event_at < time() ) {
			$errors[] = "Event date must be in the future.";
		}
	}
	if ( count( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}

	$location = $event->location ?? '';
	if ( ! empty( $event->location_name ) || ! empty( $event->location_address ) ) {
		$location = json_encode( [
			"name"    => $event->location_name ?? '',
			"address" => $event->location_address ?? '',
		] );
	}
	$event_end_at = null;
	if ( ! empty( $event->end_time ) ) {
		$end_date     = ! empty( $event->end_date ) ? $event->end_date : $event->date;
		$event_end_at = "{$end_date} {$event->end_time}";
	}
	$capacity = ! empty( $event->capacity ) ? (int) $event->capacity : null;

	$recurrence_rule = ! empty( $event->recurrence_rule ) ? sanitize_text_field( $event->recurrence_rule ) : null;
	$allowed_rules   = [ 'weekly', 'biweekly', 'monthly' ];
	if ( $recurrence_rule && ! in_array( $recurrence_rule, $allowed_rules ) ) {
		$recurrence_rule = null;
	}

	$image_id = ! empty( $event->image_id ) ? (int) $event->image_id : null;
	$slug     = ( new LocalMeet\Group( $event->group_id ) )->generate_unique_event_slug( $event->name );
    $event_id = ( new LocalMeet\Events )->insert( [
		"name"            => $event->name,
		"slug"            => $slug,
		"group_id"        => $event->group_id,
		"description"     => $event->description,
		"location"        => $location,
		"event_at"        => "{$event->date} {$event->time}",
		"event_end_at"    => $event_end_at,
		"capacity"        => $capacity,
		"recurrence_rule" => $recurrence_rule,
		"image_id"        => $image_id,
	] );
	( new LocalMeet\Attendees )->insert( [
		"created_at" => $time_now,
		"user_id"    => get_current_user_id(),
		"event_id"   => $event_id,
		"going"      => true,
	] );

	// Generate recurring event instances
	if ( $recurrence_rule ) {
		$parent_event = ( new LocalMeet\Events )->get( $event_id );
		$recurrence   = new LocalMeet\Recurrence( $parent_event );
		$instances    = $recurrence->generate_instances( 8 );
		foreach ( $instances as $instance ) {
			$instance['slug'] = ( new LocalMeet\Group( $event->group_id ) )->generate_unique_event_slug( $instance['name'] );
			( new LocalMeet\Events )->insert( $instance );
		}
	}

    return $event_id;
}

function localmeet_group_delete_func( $request ) {
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	$user  = new LocalMeet\User;
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	// Capture members before deletion for notification
	$members = ( new LocalMeet\Members )->where( [ 'group_id' => $group->group_id, 'active' => 1 ] );
	( new LocalMeet\Groups )->delete( $group->group_id );
	LocalMeet\Mailer::notify_members_group_deleted( $group, $members ?: [] );
	return;
}

function localmeet_events_search_func( $request ) {
	$search   = sanitize_text_field( $_GET['q'] ?? '' );
	$group_id = $request['group_id'];
	$group    = ( new LocalMeet\Groups )->get( $group_id );
	if ( ! $group ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( strlen( $search ) < 2 ) {
		return [ "upcoming" => [], "past" => [] ];
	}
	$all_matches = ( new LocalMeet\Events )->search( $search, [ 'name', 'description' ], 50, 0 );
	$time_now    = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
	$upcoming    = [];
	$past        = [];
	foreach ( $all_matches as $event ) {
		if ( $event->group_id != $group->group_id ) {
			continue;
		}
		if ( $event->event_at > $time_now ) {
			$upcoming[] = $event;
		} else {
			$past[] = $event;
		}
	}
	return [ "upcoming" => $upcoming, "past" => $past ];
}

function localmeet_members_export_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	// Export all members (active and inactive) so organizers have full history
	$all_members = ( new LocalMeet\Members )->where( [ "group_id" => $group->group_id ] );
	$csv         = "First Name,Last Name,Email,Status,Joined,Left\n";
	foreach ( $all_members as $member ) {
		$wp_user = get_user_by( 'ID', $member->user_id );
		if ( ! $wp_user ) continue;
		$first  = str_replace( '"', '""', $wp_user->first_name );
		$last   = str_replace( '"', '""', $wp_user->last_name );
		$email  = str_replace( '"', '""', $wp_user->user_email );
		$status = $member->active ? 'Active' : 'Left';
		$joined = $member->created_at ?? '';
		$left   = ( property_exists( $member, 'left_at' ) && $member->left_at ) ? $member->left_at : '';
		$csv   .= "\"{$first}\",\"{$last}\",\"{$email}\",\"{$status}\",\"{$joined}\",\"{$left}\"\n";
	}
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $group->slug ) . '-members.csv"' );
	echo $csv;
	exit();
}

function localmeet_locations_list_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$locations = ( new LocalMeet\Locations )->where( [ "group_id" => $group->group_id ] );
	return $locations ?: [];
}

function localmeet_locations_create_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$location = (object) $request['location'];
	$errors   = [];
	if ( empty( $location->name ) ) {
		$errors[] = "Name is required.";
	}
	if ( count( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	$time_now    = date("Y-m-d H:i:s");
	$location_id = ( new LocalMeet\Locations )->insert( [
		"group_id"   => $group->group_id,
		"name"       => $location->name,
		"address"    => $location->address ?? "",
		"notes"      => $location->notes ?? "",
		"created_at" => $time_now,
	] );
	return $location_id;
}

function localmeet_location_update_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$current = ( new LocalMeet\Locations )->get( $request['location_id'] );
	if ( empty( $current ) || $current->group_id != $group->group_id ) {
		return [ "errors" => [ "Location not found." ] ];
	}
	$location = (object) $request['location'];
	$errors   = [];
	if ( empty( $location->name ) ) {
		$errors[] = "Name is required.";
	}
	if ( count( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	( new LocalMeet\Locations )->update( [
		"name"    => $location->name,
		"address" => $location->address ?? "",
		"notes"   => $location->notes ?? "",
	], [ "location_id" => $current->location_id ] );
	return $current->location_id;
}

function localmeet_location_delete_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$current = ( new LocalMeet\Locations )->get( $request['location_id'] );
	if ( empty( $current ) || $current->group_id != $group->group_id ) {
		return [ "errors" => [ "Location not found." ] ];
	}
	( new LocalMeet\Locations )->delete( $current->location_id );
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
                    'user_login' => localmeet_generate_username( $r->email ),
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
            LocalMeet\Mailer::notify_organizer_new_member( $r->group_id, $user->ID );

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
	LocalMeet\Mailer::notify_organizer_new_member( $request['group_id'], get_current_user_id() );
	return;
}

function localmeet_groups_leave_func( $request ) {
	$user_id = get_current_user_id();
	$group   = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( $group && $user_id == $group->owner_id ) {
		return [ "errors" => [ "Group organizers can't leave their own group." ] ];
	}
	( new LocalMeet\Group( $request['group_id'] ) )->leave();
	LocalMeet\Mailer::notify_organizer_member_left( $request['group_id'], $user_id );
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
	$member_data = ( new LocalMeet\Members )->get( $member_id );
	if ( ! $member_data || wp_hash( $member_data->created_at ) !== $token ) {
		return "Not found";
	}
	$leaving_user_id  = $member_data->user_id;
	$leaving_group_id = $member_data->group_id;
	$group = ( new LocalMeet\Groups )->get( $leaving_group_id );
	if ( $group && $leaving_user_id == $group->owner_id ) {
		return "Group organizers can't leave their own group.";
	}
	( new LocalMeet\Member( $member_id ) )->leave();
	LocalMeet\Mailer::notify_organizer_member_left( $leaving_group_id, $leaving_user_id );
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
	$member_data = ( new LocalMeet\Members )->get( $member_id );
	if ( ! $member_data || wp_hash( $member_data->created_at ) !== $token ) {
		return "Not found";
	}
	$member = new LocalMeet\Member( $member_id );
	return $member->fetch();
}

function localmeet_member_mute_func( $request ) {
	$token     = $request['token'];
	$parts     = explode( "-", $token, 2 );
	$member_id = $parts[0];
	$hash      = $parts[1] ?? '';
	if ( empty( $member_id ) ) {
		return "Not found";
	}
	$member_data = ( new LocalMeet\Members )->get( $member_id );
	if ( ! $member_data || wp_hash( $member_data->created_at ) !== $hash ) {
		return "Not found";
	}
	( new LocalMeet\Members )->update( [ "email_notifications" => 0 ], [ "member_id" => $member_id ] );
	$group = ( new LocalMeet\Groups )->get( $member_data->group_id );
	wp_redirect( "/group/{$group->slug}?muted=confirmed" );
	exit();
}

function localmeet_member_notifications_func( $request ) {
	$user     = new LocalMeet\User;
	$group_id = $request['group_id'];
	$post     = json_decode( file_get_contents( 'php://input' ) );
	$enabled  = ! empty( $post->enabled ) ? 1 : 0;
	$membership = ( new LocalMeet\Members )->where( [ "user_id" => $user->user_id(), "group_id" => $group_id, "active" => 1 ] );
	if ( empty( $membership ) ) {
		return [ "errors" => [ "Not a member." ] ];
	}
	( new LocalMeet\Members )->update( [ "email_notifications" => $enabled ], [ "member_id" => $membership[0]->member_id ] );
	return [ "email_notifications" => (bool) $enabled ];
}

function localmeet_group_transfer_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	// Only the current owner or a site admin can transfer
	if ( ! $user->is_admin() && $user->user_id() != $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$post        = json_decode( file_get_contents( 'php://input' ) );
	$new_owner_id = (int) ( $post->user_id ?? 0 );
	if ( empty( $new_owner_id ) || $new_owner_id == $group->owner_id ) {
		return [ "errors" => [ "Invalid user." ] ];
	}
	// Verify the new owner is an active member
	$membership = ( new LocalMeet\Members )->where( [ "user_id" => $new_owner_id, "group_id" => $group->group_id, "active" => 1 ] );
	if ( empty( $membership ) ) {
		return [ "errors" => [ "User must be an active member." ] ];
	}
	( new LocalMeet\Groups )->update( [ "owner_id" => $new_owner_id ], [ "group_id" => $group->group_id ] );
	// Notify the new organizer
	$new_owner = get_userdata( $new_owner_id );
	if ( $new_owner ) {
		LocalMeet\Mailer::send_role_notification( $new_owner->user_email, $group->name, 'organizer', $group->slug );
	}
	return [ "success" => true ];
}

function localmeet_member_role_func( $request ) {
	$user  = new LocalMeet\User;
	$group = ( new LocalMeet\Groups )->get( $request['group_id'] );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	// Only site admins and the group owner can change roles (not managers)
	if ( ! $user->is_admin() && $user->user_id() != $group->owner_id ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$member = ( new LocalMeet\Members )->get( $request['member_id'] );
	if ( empty( $member ) || $member->group_id != $group->group_id || ! $member->active ) {
		return [ "errors" => [ "Member not found." ] ];
	}
	// Can't change the owner's role
	if ( $member->user_id == $group->owner_id ) {
		return [ "errors" => [ "Can't change the organizer's role." ] ];
	}
	$post         = json_decode( file_get_contents( 'php://input' ) );
	$new_role     = $post->role ?? '';
	$allowed_roles = [ 'member', 'manager' ];
	if ( ! in_array( $new_role, $allowed_roles ) ) {
		return [ "errors" => [ "Invalid role." ] ];
	}
	( new LocalMeet\Members )->update( [ "role" => $new_role ], [ "member_id" => $member->member_id ] );
	// Notify member of promotion
	if ( $new_role === 'manager' ) {
		$member_user = get_userdata( $member->user_id );
		if ( $member_user ) {
			LocalMeet\Mailer::send_role_notification( $member_user->user_email, $group->name, 'manager', $group->slug );
		}
	}
	return [ "member_id" => $member->member_id, "role" => $new_role ];
}

function localmeet_event_comment_moderate_func( $request ) {
	$user    = new LocalMeet\User;
	$comment = ( new LocalMeet\Comments )->get( $request['comment_id'] );
	if ( ! $comment ) {
		return [ "errors" => [ "Comment not found." ] ];
	}
	$event = ( new LocalMeet\Events )->get( $comment->event_id );
	if ( empty( $event ) ) {
		return [ "errors" => [ "Event not found." ] ];
	}
	$group = ( new LocalMeet\Groups )->get( $event->group_id );
	if ( empty( $group ) ) {
		return [ "errors" => [ "Group not found." ] ];
	}
	if ( ! $user->can_manage_group( $group ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$post   = json_decode( file_get_contents( 'php://input' ) );
	$action = $post->action ?? '';
	if ( $action === 'approve' ) {
		( new LocalMeet\Comments )->update( [ "status" => "approved" ], [ "comment_id" => $comment->comment_id ] );
	} elseif ( $action === 'reject' ) {
		( new LocalMeet\Comments )->delete( $comment->comment_id );
	}
	return [ "success" => true ];
}

function localmeet_groups_join_request_func( $request ) {
	if ( ! LocalMeet\RateLimiter::check( 'join_email', LocalMeet\RateLimiter::ip(), 5, 300 ) ) {
		return [ "errors" => [ "Too many attempts. Please try again later." ] ];
	}
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
		$token       = bin2hex( random_bytes( 32 ) );
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
	LocalMeet\Mailer::send_member_join_verification( $request->email, $group->name, $verify_url );
	return $request;
}

function localmeet_groups_update_func( $request ) {
	$errors = [];
	$edit_group = (object) $request['edit_group'];
	$group      = (object) $edit_group->group;
	$user       = new LocalMeet\User;
	$current    = ( new LocalMeet\Groups )->get( $group->group_id );
	if ( ! $user->can_manage_group( $current ) ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	if ( empty( $group->name ) ) {
		$errors[] = "Name can't be empty.";
	}
	if ( empty( $group->slug ) ) {
		$errors[] = "Slug can't be empty.";
	}
	// Validate slug format and uniqueness
	$slug = sanitize_title( $group->slug );
	if ( $slug !== $current->slug ) {
		$existing = ( new LocalMeet\Groups )->where( [ "slug" => $slug ] );
		if ( $existing ) {
			$errors[] = "That slug is already taken.";
		}
	}
	if ( count ( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}
	$details                 = empty( $current->details ) ? (object) [] : json_decode( $current->details );
	$details->email_footer   = $group->email_footer_raw;
	$details->reply_to_name  = $group->reply_to_name;
	$details->reply_to_email = $group->reply_to_email;
    ( new LocalMeet\Groups )->update([
		"name"        => $group->name,
		"description" => $group->description_raw,
		"details"     => json_encode( $details ),
		"slug"        => $slug,
	],[ "group_id"    => $group->group_id ]);
	return [ "group_id" => $group->group_id, "slug" => $slug ];
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
                    'user_login' => localmeet_generate_username( $r->email ),
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
                $user    = (object) [ "ID" => $user_id ];
                update_user_meta( $user->ID, 'localmeet_password_not_set', true );
            }
			// Capacity check
			if ( $event->capacity ) {
				$going_count = ( new LocalMeet\Attendees )->count_where( [ "event_id" => $r->event_id, "going" => 1 ] );
				$existing    = ( new LocalMeet\Attendees )->where( [ "user_id" => $user->ID, "event_id" => $r->event_id, "going" => 1 ] );
				if ( empty( $existing ) && $going_count >= (int) $event->capacity ) {
					( new LocalMeet\AttendeeRequests )->delete( $r->attendee_request_id );
					wp_redirect( "/group/{$group->slug}/{$event->slug}?rsvp=full" );
					exit();
				}
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

function localmeet_media_upload_func( $request ) {
	if ( empty( $_FILES['file'] ) ) {
		return new \WP_Error( 'no_file', 'No file uploaded.', [ 'status' => 400 ] );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Temporarily grant upload capability for subscribers
	$user_id = get_current_user_id();
	$grant_cap = function( $allcaps ) {
		$allcaps['upload_files'] = true;
		return $allcaps;
	};
	add_filter( 'user_has_cap', $grant_cap );

	$attachment_id = media_handle_upload( 'file', 0 );

	remove_filter( 'user_has_cap', $grant_cap );

	if ( is_wp_error( $attachment_id ) ) {
		return [ 'errors' => [ $attachment_id->get_error_message() ] ];
	}

	wp_update_post( [ 'ID' => $attachment_id, 'post_author' => $user_id ] );

	return [
		'id'        => $attachment_id,
		'url'       => wp_get_attachment_image_url( $attachment_id, 'large' ),
		'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
	];
}

function localmeet_media_mine_func( $request ) {
	$images = get_posts( [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'author'         => get_current_user_id(),
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'inherit',
	] );

	$results = [];
	foreach ( $images as $img ) {
		$results[] = [
			'id'        => $img->ID,
			'url'       => wp_get_attachment_image_url( $img->ID, 'large' ),
			'thumbnail' => wp_get_attachment_image_url( $img->ID, 'medium' ),
		];
	}
	return $results;
}

function localmeet_groups_func( $request ) {
	$per_page = min( 50, max( 1, (int) ( $_GET['per_page'] ?? 20 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$groups   = ( new LocalMeet\Groups )->list( $per_page, $page );
	$total    = ( new LocalMeet\Groups )->count_all();
	return [ "groups" => $groups, "total" => $total, "page" => $page, "per_page" => $per_page ];
}

function localmeet_groups_search_func( $request ) {
	$search   = sanitize_text_field( $_GET['q'] ?? '' );
	if ( strlen( $search ) < 2 ) {
		return [ "groups" => [], "total" => 0 ];
	}
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$per_page = min( 50, max( 1, (int) ( $_GET['per_page'] ?? 20 ) ) );
	$offset   = ( $page - 1 ) * $per_page;
	$groups   = ( new LocalMeet\Groups )->search_groups( $search, $per_page, $offset );
	return [ "groups" => $groups, "total" => count( $groups ), "page" => $page ];
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
                    'user_login' => localmeet_generate_username( $r->email ),
                    'role'       => 'subscriber'
                ];
                $user_id = wp_insert_user( $new_user );
                $user    = (object) [ "ID" => $user_id ];
                update_user_meta( $user->ID, 'localmeet_password_not_set', true );
            }
            $unique_slug = ( new LocalMeet\Groups )->generate_unique_slug( $r->name );
            $group_id = ( new LocalMeet\Groups )->insert( [
                "organization_id" => 0,
                "name"        => $r->name,
                "slug"        => $unique_slug,
                "description" => $r->description,
                "owner_id"    => $user->ID,
            ] );

            // Auto-join owner as member
            ( new LocalMeet\Members )->insert( [
                "created_at" => date( "Y-m-d H:i:s" ),
                "user_id"    => $user->ID,
                "group_id"   => $group_id,
                "active"     => 1,
            ] );

            ( new LocalMeet\GroupRequests )->delete( $r->group_request_id );

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
    if ( ! $group || ! isset( $group[0] ) ) {
        return new WP_Error( 'not_found', 'Group not found.', [ 'status' => 404 ] );
    }
    $group_id = $group[0]->group_id;
    $name   = $request['name'];
    $lookup = ( new LocalMeet\Events )->where(  [ "slug" => $name, "group_id" => $group_id ] );
    if ( count( $lookup ) != 1 ) {
        return new WP_Error( 'not_found', 'Event not found.', [ 'status' => 404 ] );
    }
	$comments_per_page = min( 100, max( 1, (int) ( $_GET['comments_per_page'] ?? 50 ) ) );
	$comments_page     = max( 1, (int) ( $_GET['comments_page'] ?? 1 ) );
	$event = ( new LocalMeet\Event( $lookup[0]->event_id ) )->fetch( $comments_per_page, $comments_page );

	return $event;
}

function localmeet_group_func( $request ) {
    $organization = $_GET['organization'];
    $name         = isset( $request['name'] ) ? $request['name'] : "";
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
    $events_per_page = min( 50, max( 1, (int) ( $_GET['events_per_page'] ?? 20 ) ) );
    $events_page     = max( 1, (int) ( $_GET['events_page'] ?? 1 ) );
    $group = ( new LocalMeet\Group( $lookup[0]->group_id ) )->fetch( $events_per_page, $events_page );
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

function localmeet_invite_create_func( WP_REST_Request $request ) {
	$user = new LocalMeet\User;
	if ( ! $user->is_admin() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}

	$post    = json_decode( file_get_contents( 'php://input' ) );
	$invite  = $post->invite;
	$errors  = [];

	if ( empty( $invite->email ) || ! filter_var( $invite->email, FILTER_VALIDATE_EMAIL ) ) {
		$errors[] = "Valid email address is required.";
	}
	if ( empty( $invite->group_allowance ) || $invite->group_allowance < 1 ) {
		$errors[] = "Group allowance must be at least 1.";
	}
	if ( count( $errors ) > 0 ) {
		return [ "errors" => $errors ];
	}

	$valid_token = false;
	do {
		$token       = bin2hex( random_bytes( 32 ) );
		$token_check = ( new LocalMeet\Invites )->where( [ "token" => $token ] );
		if ( ! $token_check ) {
			$valid_token = true;
		}
	} while ( $valid_token == false );

	$time_now = date( 'Y-m-d H:i:s' );
	( new LocalMeet\Invites )->insert( [
		"email"           => $invite->email,
		"group_allowance" => (int) $invite->group_allowance,
		"token"           => $token,
		"created_at"      => $time_now,
	] );

	$accept_url = home_url() . "/wp-json/localmeet/v1/invite/accept/{$token}";
	LocalMeet\Mailer::send_invite( $invite->email, (int) $invite->group_allowance, $accept_url );

	return [ "message" => "Invite sent to {$invite->email}" ];
}

function localmeet_invites_func( WP_REST_Request $request ) {
	$user = new LocalMeet\User;
	if ( ! $user->is_admin() ) {
		return [ "errors" => [ "Permission denied." ] ];
	}
	$invites = ( new LocalMeet\Invites )->all();
	if ( ! is_array( $invites ) ) {
		return [];
	}
	foreach ( $invites as $invite ) {
		if ( $invite->accepted_by ) {
			$wp_user = get_user_by( 'id', $invite->accepted_by );
			if ( $wp_user ) {
				$invite->accepted_name = $wp_user->display_name;
			}
		}
	}
	return $invites;
}

function localmeet_invite_accept_func( WP_REST_Request $request ) {
	$token  = $request['token'];
	$lookup = ( new LocalMeet\Invites )->where( [ "token" => $token ] );

	if ( ! $lookup || count( $lookup ) != 1 ) {
		return new WP_Error( 'not_found', 'Invalid or expired invite.', [ 'status' => 404 ] );
	}

	$invite = $lookup[0];

	if ( ! empty( $invite->accepted_at ) ) {
		wp_redirect( home_url( '/start-group' ) );
		exit;
	}

	$user = get_user_by( 'email', $invite->email );
	if ( ! $user ) {
		$new_user = [
			'user_email' => $invite->email,
			'user_login' => localmeet_generate_username( $invite->email ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		];
		$user_id = wp_insert_user( $new_user );
		update_user_meta( $user_id, 'localmeet_password_not_set', true );
	} else {
		$user_id = $user->ID;
	}

	$current_allowance = (int) get_user_meta( $user_id, 'localmeet_group_allowance', true );
	update_user_meta( $user_id, 'localmeet_group_allowance', $current_allowance + (int) $invite->group_allowance );

	( new LocalMeet\Invites )->update( [
		"accepted_at" => date( 'Y-m-d H:i:s' ),
		"accepted_by" => $user_id,
	], [ "invite_id" => $invite->invite_id ] );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id );
	wp_redirect( home_url( '/start-group' ) );
	exit;
}

// Makes sure that any request going to /account/... will respond with a proper 200 http code
add_action( 'init', 'localmeet_rewrites_init' );
function localmeet_rewrites_init(){
	$pages = [
		'^start-group',
		'^find-group',
		'^admin/invites',
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
		'start-group',
		'find-group',
		'admin/',
		'group/',
	];
	foreach( $pages as $page ) {
		if ( strpos( $wp->request, $page ) !== false ) {
			return false;
		}
	}
    return $redirect_url;
}

function localmeet_seo_data() {
	static $cache = null;
	if ( $cache !== null ) return $cache;

	global $wp;
	$site_name = get_bloginfo( 'name' );
	$data = [
		'title'       => $site_name,
		'description' => 'Self-starting local meetups',
		'og_image'    => '',
		'og_type'     => 'website',
		'canonical'   => home_url( $wp->request ? "/{$wp->request}" : '/' ),
		'content'     => '',
	];

	if ( empty( $wp->request ) ) {
		$data['content'] = '<p>LocalMeet is an <a href="https://github.com/austinginder/LocalMeet" target="_new">open source</a> meetup tool powered by WordPress.</p>'
			. '<nav><a href="/start-group">Start Group</a> | <a href="/find-group">Find Group</a></nav>';
		$cache = $data;
		return $cache;
	}

	if ( $wp->request == "start-group" ) {
		$data['title']       = "Start a New Group | {$site_name}";
		$data['description'] = "Create your own local meetup group on {$site_name}.";
		$data['content']     = '<h1>Start a new group</h1>';
		$cache = $data;
		return $cache;
	}

	if ( $wp->request == "find-group" ) {
		$data['title']       = "Find a Group | {$site_name}";
		$data['description'] = "Browse local meetup groups on {$site_name}.";
		$content = '<h1>Find a group</h1>';
		$groups = ( new LocalMeet\Groups )->all();
		foreach ( $groups as $group ) {
			$content .= '<a href="' . esc_url( "/group/$group->slug" ) . '">' . esc_html( $group->name ) . '</a> ';
		}
		$data['content'] = $content;
		$cache = $data;
		return $cache;
	}

	// Event page: group/slug/event-slug
	if ( strpos( $wp->request, "group/" ) !== false && substr_count( $wp->request, "/" ) == 2 ) {
		$ids             = explode( "/", $wp->request );
		$group_slug      = $ids[1];
		$event_slug      = $ids[2];
		$organization_id = 0;
		$group  = ( new LocalMeet\Groups )->where( [ "slug" => $group_slug, "organization_id" => $organization_id ] );
		if ( ! $group || ! isset( $group[0] ) ) {
			$data['title']   = "Not Found | {$site_name}";
			$data['content'] = '<p>Event not found.</p>';
			$cache = $data;
			return $cache;
		}
		$group_name = $group[0]->name;
		$group_id   = $group[0]->group_id;
		$lookup = ( new LocalMeet\Events )->where( [ "slug" => $event_slug, "group_id" => $group_id ] );
		if ( count( $lookup ) != 1 ) {
			$data['title']   = "Not Found | {$site_name}";
			$data['content'] = '<p>Event not found.</p>';
			$cache = $data;
			return $cache;
		}
		$event    = (object) $lookup[0];
		$event_at = ( new DateTime( $event->event_at ) )->format( 'l, F j, Y \a\t g:i A' );
		$desc_html = ( new Parsedown )->text( $event->description );
		$desc_text = wp_strip_all_tags( $desc_html );
		$desc_short = mb_substr( $desc_text, 0, 160 );

		$location_data = json_decode( $event->location );
		$location_str  = '';
		if ( $location_data && is_object( $location_data ) ) {
			$parts = array_filter( [ $location_data->name ?? '', $location_data->address ?? '' ] );
			$location_str = implode( ', ', $parts );
		}

		$data['title']       = esc_html( $event->name ) . " - " . esc_html( $group_name ) . " | {$site_name}";
		$data['description'] = $desc_short ?: "{$event->name} on {$event_at}";
		$data['og_type']     = 'article';

		if ( ! empty( $event->image_id ) ) {
			$data['og_image'] = wp_get_attachment_image_url( $event->image_id, 'large' ) ?: '';
		}

		$content  = '<article>';
		$content .= '<h1>' . esc_html( $event->name ) . '</h1>';
		$content .= '<p><strong>When:</strong> ' . esc_html( $event_at ) . '</p>';
		if ( $location_str ) {
			$content .= '<p><strong>Where:</strong> ' . esc_html( $location_str ) . '</p>';
		}
		$content .= '<p><strong>Group:</strong> <a href="' . esc_url( "/group/{$group_slug}" ) . '">' . esc_html( $group_name ) . '</a></p>';
		$content .= '<div>' . wp_kses_post( $desc_html ) . '</div>';
		$content .= '</article>';

		$data['content'] = $content;
		$cache = $data;
		return $cache;
	}

	// Group page: group/slug
	if ( strpos( $wp->request, "group/" ) !== false ) {
		$url_splits = explode( "/", $wp->request );
		$name       = $url_splits[1];
		$lookup     = ( new LocalMeet\Groups )->where( [ "slug" => $name ] );
		if ( count( $lookup ) != 1 ) {
			$data['title']   = "Not Found | {$site_name}";
			$data['content'] = '<p>Group not found.</p>';
			$cache = $data;
			return $cache;
		}
		$group   = $lookup[0];
		$desc_text = wp_strip_all_tags( $group->description );
		$desc_short = mb_substr( $desc_text, 0, 160 );

		$data['title']       = esc_html( $group->name ) . " | {$site_name}";
		$data['description'] = $desc_short ?: "Join {$group->name} on {$site_name}.";

		$upcoming = ( new LocalMeet\Events )->upcoming( [ "group_id" => $group->group_id ] ) ?: [];
		$past     = ( new LocalMeet\Events )->past( [ "group_id" => $group->group_id ] ) ?: [];

		$content  = '<article>';
		$content .= '<h1>' . esc_html( $group->name ) . '</h1>';
		$content .= '<p>' . wp_kses_post( ( new Parsedown )->text( $group->description ) ) . '</p>';
		if ( $upcoming ) {
			$content .= '<h2>Upcoming Events</h2><ul>';
			foreach ( $upcoming as $event ) {
				$content .= '<li><a href="' . esc_url( "/group/{$group->slug}/{$event->slug}" ) . '">' . esc_html( $event->name ) . '</a></li>';
			}
			$content .= '</ul>';
		}
		if ( $past ) {
			$content .= '<h2>Past Events</h2><ul>';
			foreach ( $past as $event ) {
				$content .= '<li><a href="' . esc_url( "/group/{$group->slug}/{$event->slug}" ) . '">' . esc_html( $event->name ) . '</a></li>';
			}
			$content .= '</ul>';
		}
		$content .= '</article>';

		$data['content'] = $content;
		$cache = $data;
		return $cache;
	}

	$data['title']   = "Page Not Found | {$site_name}";
	$data['content'] = '<p>Page not found.</p>';
	$cache = $data;
	return $cache;
}

function localmeet_meta_description() {
	$seo = localmeet_seo_data();
	echo esc_attr( $seo['description'] );
}

function localmeet_meta_tags() {
	$seo       = localmeet_seo_data();
	$site_name = get_bloginfo( 'name' );
	$tags      = '';

	// Open Graph
	$tags .= '<meta property="og:type" content="' . esc_attr( $seo['og_type'] ) . '" />' . "\n";
	$tags .= '<meta property="og:title" content="' . esc_attr( $seo['title'] ) . '" />' . "\n";
	$tags .= '<meta property="og:description" content="' . esc_attr( $seo['description'] ) . '" />' . "\n";
	$tags .= '<meta property="og:url" content="' . esc_url( $seo['canonical'] ) . '" />' . "\n";
	$tags .= '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
	if ( ! empty( $seo['og_image'] ) ) {
		$tags .= '<meta property="og:image" content="' . esc_url( $seo['og_image'] ) . '" />' . "\n";
	}

	// Twitter Card
	$tags .= '<meta name="twitter:card" content="' . ( ! empty( $seo['og_image'] ) ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
	$tags .= '<meta name="twitter:title" content="' . esc_attr( $seo['title'] ) . '" />' . "\n";
	$tags .= '<meta name="twitter:description" content="' . esc_attr( $seo['description'] ) . '" />' . "\n";
	if ( ! empty( $seo['og_image'] ) ) {
		$tags .= '<meta name="twitter:image" content="' . esc_url( $seo['og_image'] ) . '" />' . "\n";
	}

	// Canonical
	$tags .= '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '" />' . "\n";

	echo $tags;
}

function localmeet_title() {
	$seo = localmeet_seo_data();
	echo esc_html( $seo['title'] );
}

function localmeet_content() {
	$seo = localmeet_seo_data();
	echo $seo['content'];
}

function localmeet_merge_users_candidates_func( $request ) {
	$user     = new LocalMeet\User;
	$group_id = (int) $request['group_id'];
	$group    = ( new LocalMeet\Groups )->get( $group_id );
	if ( ! $group || ! $user->can_manage_group( $group ) ) {
		return [ 'errors' => [ 'Permission denied.' ] ];
	}

	global $wpdb;
	$members_table = $wpdb->prefix . 'localmeet_members';

	$member_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.user_id, m.created_at, u.display_name, u.user_email
		 FROM {$members_table} m
		 JOIN {$wpdb->users} u ON u.ID = m.user_id
		 WHERE m.group_id = %d AND m.active = 1
		 ORDER BY u.display_name, m.created_at",
		$group_id
	) );

	$members = [];
	$by_name = [];
	foreach ( $member_rows as $row ) {
		$member = [
			'user_id'      => (int) $row->user_id,
			'display_name' => $row->display_name,
			'email'        => $row->user_email,
			'avatar'       => get_avatar_url( $row->user_email, [ 'size' => 80 ] ),
			'joined'       => $row->created_at,
		];
		$members[] = $member;
		$by_name[ $row->display_name ][] = $member;
	}

	$candidates = [];
	foreach ( $by_name as $name => $group_members ) {
		if ( count( $group_members ) > 1 ) {
			$candidates[] = [ 'name' => $name, 'users' => $group_members ];
		}
	}

	return [ 'candidates' => $candidates, 'members' => $members ];
}

function localmeet_merge_users_func( $request ) {
	$user     = new LocalMeet\User;
	$group_id = (int) $request['group_id'];
	$group    = ( new LocalMeet\Groups )->get( $group_id );
	if ( ! $group || ! $user->can_manage_group( $group ) ) {
		return [ 'errors' => [ 'Permission denied.' ] ];
	}

	$post = json_decode( file_get_contents( 'php://input' ) );
	$keep_id  = (int) ( $post->keep_user_id ?? 0 );
	$merge_id = (int) ( $post->merge_user_id ?? 0 );

	if ( ! $keep_id || ! $merge_id ) {
		return [ 'errors' => [ 'Both users must be selected.' ] ];
	}
	if ( $keep_id === $merge_id ) {
		return [ 'errors' => [ 'Cannot merge a user into themselves.' ] ];
	}
	if ( $merge_id === get_current_user_id() ) {
		return [ 'errors' => [ 'Cannot merge your own account.' ] ];
	}

	$keep_user  = get_userdata( $keep_id );
	$merge_user = get_userdata( $merge_id );
	if ( ! $keep_user || ! $merge_user ) {
		return [ 'errors' => [ 'One or both users not found.' ] ];
	}

	global $wpdb;
	$attendees_table = $wpdb->prefix . 'localmeet_attendees';
	$members_table   = $wpdb->prefix . 'localmeet_members';
	$comments_table  = $wpdb->prefix . 'localmeet_comments';
	$groups_table    = $wpdb->prefix . 'localmeet_groups';
	$invites_table   = $wpdb->prefix . 'localmeet_invites';

	$wpdb->query( 'START TRANSACTION' );

	// 1. Reassign attendees (skip duplicates)
	$merge_attendees = $wpdb->get_results( $wpdb->prepare(
		"SELECT attendee_id, event_id FROM {$attendees_table} WHERE user_id = %d", $merge_id
	) );
	foreach ( $merge_attendees as $att ) {
		$conflict = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$attendees_table} WHERE event_id = %d AND user_id = %d",
			$att->event_id, $keep_id
		) );
		if ( $conflict ) {
			$wpdb->delete( $attendees_table, [ 'attendee_id' => $att->attendee_id ] );
		} else {
			$wpdb->update( $attendees_table, [ 'user_id' => $keep_id ], [ 'attendee_id' => $att->attendee_id ] );
		}
	}

	// 2. Reassign memberships (skip duplicates, keep earlier join date)
	$merge_memberships = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$members_table} WHERE user_id = %d", $merge_id
	) );
	foreach ( $merge_memberships as $mem ) {
		$keep_membership = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$members_table} WHERE user_id = %d AND group_id = %d",
			$keep_id, $mem->group_id
		) );
		if ( $keep_membership ) {
			// Use the earlier join date
			if ( $mem->created_at < $keep_membership->created_at ) {
				$wpdb->update( $members_table, [ 'created_at' => $mem->created_at ], [ 'user_id' => $keep_id, 'group_id' => $mem->group_id ] );
			}
			$wpdb->delete( $members_table, [ 'user_id' => $merge_id, 'group_id' => $mem->group_id ] );
		} else {
			$wpdb->update( $members_table, [ 'user_id' => $keep_id ], [ 'user_id' => $merge_id, 'group_id' => $mem->group_id ] );
		}
	}

	// 3. Reassign comments
	$wpdb->update( $comments_table, [ 'user_id' => $keep_id ], [ 'user_id' => $merge_id ] );

	// 4. Transfer group ownership
	$wpdb->update( $groups_table, [ 'owner_id' => $keep_id ], [ 'owner_id' => $merge_id ] );

	// 5. Transfer invites
	$wpdb->update( $invites_table, [ 'accepted_by' => $keep_id ], [ 'accepted_by' => $merge_id ] );

	// 6. Clean up merged user's avatar (don't transfer — keep user's Gravatar or existing avatar is preferred)
	$merge_avatar = get_user_meta( $merge_id, 'localmeet_avatar', true );
	if ( $merge_avatar ) {
		wp_delete_attachment( $merge_avatar, true );
	}

	// 7. Delete the merged user
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $merge_id, $keep_id );

	$wpdb->query( 'COMMIT' );

	return [ 'success' => true, 'message' => "{$merge_user->display_name} merged into {$keep_user->display_name}." ];
}
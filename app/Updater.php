<?php

namespace LocalMeet;

class Updater {

    public $plugin_slug;
    public $version;
    public $cache_key;
    public $cache_allowed;

    public function __construct() {
        if ( defined( 'LOCALMEET_DEV_MODE' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
            add_filter( 'https_local_ssl_verify', '__return_false' );
            add_filter( 'http_request_host_is_external', '__return_true' );
        }
        $this->plugin_slug   = 'localmeet';
        $this->version       = '2.0.0';
        $this->cache_key     = 'localmeet_updater';
        $this->cache_allowed = false;

        add_filter( 'plugins_api', [ $this, 'info' ], 30, 3 );
        add_filter( 'site_transient_update_plugins', [ $this, 'update' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
    }

    public function request() {
        $manifest_file  = dirname( __DIR__ ) . '/manifest.json';
        $local_manifest = null;
        if ( file_exists( $manifest_file ) ) {
            $local_manifest = json_decode( file_get_contents( $manifest_file ) );
        }

        if ( ! is_object( $local_manifest ) ) {
            $local_manifest = new \stdClass();
        }

        $remote = get_transient( $this->cache_key );

        if ( false === $remote || ! $this->cache_allowed ) {
            $remote_response = wp_remote_get( 'https://raw.githubusercontent.com/austinginder/localmeet/main/manifest.json', [
                'timeout' => 30,
                'headers' => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $remote_response ) || 200 !== wp_remote_retrieve_response_code( $remote_response ) || empty( wp_remote_retrieve_body( $remote_response ) ) ) {
                return $local_manifest;
            }

            $remote = json_decode( wp_remote_retrieve_body( $remote_response ) );
            set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );
        }

        if ( is_object( $remote ) ) {
            return $remote;
        }

        return $local_manifest;
    }

    public function info( $response, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $response;
        }

        $remote = $this->request();
        if ( ! $remote ) {
            return $response;
        }

        $response                 = new \stdClass();
        $response->name           = $remote->name;
        $response->slug           = $remote->slug;
        $response->version        = $remote->version;
        $response->tested         = $remote->tested;
        $response->requires       = $remote->requires;
        $response->author         = $remote->author;
        $response->author_profile = $remote->author_profile;
        $response->donate_link    = $remote->donate_link;
        $response->homepage       = $remote->homepage;
        $response->download_link  = $remote->download_url;
        $response->trunk          = $remote->download_url;
        $response->requires_php   = $remote->requires_php;
        $response->last_updated   = $remote->last_updated;
        $response->sections       = [ 'description' => $remote->sections->description ];

        if ( ! empty( $remote->banners ) ) {
            $response->banners = [
                'low'  => $remote->banners->low,
                'high' => $remote->banners->high,
            ];
        }

        return $response;
    }

    public function update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->request();
        if ( $remote && isset( $remote->version ) && version_compare( $this->version, $remote->version, '<' ) ) {
            $response              = new \stdClass();
            $response->slug        = $this->plugin_slug;
            $response->plugin      = "{$this->plugin_slug}/{$this->plugin_slug}.php";
            $response->new_version = $remote->version;
            $response->package     = $remote->download_url;
            $response->tested      = $remote->tested;
            $response->requires_php = $remote->requires_php;
            $transient->response[ $response->plugin ] = $response;
        }

        return $transient;
    }

    public function purge( $upgrader, $options ) {
        if ( $this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            delete_transient( $this->cache_key );
        }
    }
}

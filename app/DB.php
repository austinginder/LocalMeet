<?php

namespace LocalMeet;

class DB {

    private static function _table() {
        global $wpdb;
        $tablename = explode( '\\', get_called_class(), 2 );
        $tablename[0] = strtolower ( $tablename[0] );
        // Add '_' before each capitalized letter and trim the first
        $tablename[1] = strtolower ( trim ( preg_replace( '/([A-Z])/', '_$1', $tablename[1] ), "_" ) );
        $tablename = implode ( '_', $tablename);
        return $wpdb->prefix . $tablename;
    }

    private static function _fetch_sql( $value ) {
        global $wpdb;
        $sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
        return $wpdb->prepare( $sql, $value );
    }

    static function valid_check( $data ) {
        global $wpdb;

        $sql_where       = '';
        $sql_where_count = count( $data );
        $i               = 1;
        foreach ( $data as $key => $row ) {
            if ( $i < $sql_where_count ) {
                $sql_where .= "`$key` = '$row' and ";
            } else {
                $sql_where .= "`$key` = '$row'";
            }
            $i++;
        }
        $sql     = 'SELECT * FROM ' . self::_table() . " WHERE $sql_where";
        $results = $wpdb->get_results( $sql );
        if ( count( $results ) != 0 ) {
            return false;
        } else {
            return true;
        }
    }

    static function get( $value ) {
        global $wpdb;
        return $wpdb->get_row( self::_fetch_sql( $value ) );
    }

    static function insert( $data ) {
        global $wpdb;
        $wpdb->insert( self::_table(), $data );
        return $wpdb->insert_id;
    }

    static function update( $data, $where ) {
        global $wpdb;
        return $wpdb->update( self::_table(), $data, $where );
    }

    static function delete( $value ) {
        global $wpdb;
        $sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
        return $wpdb->query( $wpdb->prepare( $sql, $value ) );
    }

    static function where( $conditions ) {
        global $wpdb;
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            if ( is_array( $value ) ) {
                $values = implode( ", ", $value );
                $where_statements[] =  "`{$row}` IN ($values)";
                continue;
            }
            $where_statements[] =  "`{$row}` = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE $where_statements order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function upcoming( $conditions ) {
        global $wpdb;
        $where_statements = [];
        $today = gmdate('Y-m-d h:i:s');
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "`{$row}` = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE $where_statements and `event_at` > '$today'  order by `event_at` ASC";
        return $wpdb->get_results( $sql );
    }

    static function past( $conditions ) {
        global $wpdb;
        $where_statements = [];
        $today = gmdate('Y-m-d h:i:s');
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "`{$row}` = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE $where_statements and `event_at` < '$today'  order by `event_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function fetch( $value ) {
        global $wpdb;
        $value = intval( $value );
        $sql   = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function all( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::_table() . ' order by `' . $sort . '` '. $sort_order;
        return $wpdb->get_results( $sql );
    }

    static function where_compare( $conditions ) {
        global $wpdb;
        $where_statements = [];
        foreach ( $conditions as $condition ) {
            $where_statements[] =  "`{$condition["key"]}` {$condition["compare"]} '{$condition["value"]}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE $where_statements order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function mine( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE user_id = '{$user_id}' order by `{$sort}` {$sort_order}";
        return $wpdb->get_results( $sql );
    }

    static function select( $field = "site_id", $where = "status", $value = "active", $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $sql = "SELECT $field FROM " . self::_table() . " WHERE $where = '{$value}' order by `{$sort}` {$sort_order}";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    // Perform LocalMeet database upgrades by running `LocalMeet\DB::upgrade();`
    public static function upgrade( $force = false ) {

        $required_version = (int) "1";
        $version          = (int) get_site_option( 'localmeet_db_version' );
    
        if ( $version >= $required_version and $force != true ) {
            echo "Not needed `localmeet_db_version` is v{$version} and required v{$required_version}.";
            return;
        }
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_organizations` (
            organization_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) UNSIGNED,
            name varchar(255),
            slug varchar(255),
            details longtext,
            created_at datetime NOT NULL,
        PRIMARY KEY  (organization_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_groups` (
            group_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) UNSIGNED,
            organization_id bigint(20) UNSIGNED,
            name varchar(255),
            slug varchar(255),
            description longtext,
            details longtext,
            created_at datetime NOT NULL,
        PRIMARY KEY  (group_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_group_requests` (
            group_request_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255),
            email varchar(255),
            description varchar(255),
            token varchar(255),
            created_at datetime NOT NULL,
        PRIMARY KEY  (group_request_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_events` (
            event_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id bigint(20) UNSIGNED,
            location longtext,
            description longtext,
            name varchar(255),
            slug varchar(255),
            event_at datetime NOT NULL,
            created_at datetime NOT NULL,
        PRIMARY KEY  (event_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_members` (
            member_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED,
            group_id bigint(20) UNSIGNED,
            active boolean,
            created_at datetime NOT NULL,
        PRIMARY KEY  (member_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_member_requests` (
            member_request_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id bigint(20) UNSIGNED,
            first_name varchar(255),
            last_name varchar(255),
            email varchar(255),
            token varchar(255),
            created_at datetime NOT NULL,
        PRIMARY KEY  (member_request_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_attendees` (
            attendee_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED,
            user_id bigint(20) UNSIGNED,
            description varchar(255),
            going boolean,
            went boolean,
            created_at datetime NOT NULL,
        PRIMARY KEY  (attendee_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_attendee_requests` (
            attendee_request_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED,
            first_name varchar(255),
            last_name varchar(255),
            email varchar(255),
            token varchar(255),
            created_at datetime NOT NULL,
        PRIMARY KEY  (attendee_request_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        if ( ! empty( $wpdb->last_error ) ) {
            return $wpdb->last_error;
        }
    
        update_site_option( 'localmeet_db_version', $required_version );
        echo "Updated `localmeet_db_version` to v$required_version";
    }
}
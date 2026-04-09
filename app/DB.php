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

        $where_clauses = [];
        $where_values  = [];
        foreach ( $data as $key => $row ) {
            $where_clauses[] = "`$key` = %s";
            $where_values[]  = $row;
        }
        $sql     = 'SELECT * FROM ' . self::_table() . ' WHERE ' . implode( ' AND ', $where_clauses );
        $results = $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
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

    private static function _build_where( $conditions ) {
        $where_clauses = [];
        $where_values  = [];
        foreach ( $conditions as $row => $value ) {
            if ( is_array( $value ) ) {
                $placeholders    = implode( ', ', array_fill( 0, count( $value ), '%s' ) );
                $where_clauses[] = "`{$row}` IN ($placeholders)";
                $where_values    = array_merge( $where_values, $value );
                continue;
            }
            $where_clauses[] = "`{$row}` = %s";
            $where_values[]  = $value;
        }
        return [ implode( ' AND ', $where_clauses ), $where_values ];
    }

    private static function _apply_limit( $sql, &$values, $limit, $offset ) {
        if ( $limit > 0 ) {
            $sql      .= ' LIMIT %d OFFSET %d';
            $values[]  = $limit;
            $values[]  = $offset;
        }
        return $sql;
    }

    static function where( $conditions, $limit = 0, $offset = 0 ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE ' . $where_sql . ' ORDER BY `created_at` DESC';
        $sql = self::_apply_limit( $sql, $where_values, $limit, $offset );
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function count_where( $conditions ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' WHERE ' . $where_sql;
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function upcoming( $conditions, $limit = 0, $offset = 0 ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $today = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
        $where_sql     .= ' AND `event_at` > %s';
        $where_values[] = $today;
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE ' . $where_sql . ' ORDER BY `event_at` ASC';
        $sql = self::_apply_limit( $sql, $where_values, $limit, $offset );
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function count_upcoming( $conditions ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $today = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
        $where_sql     .= ' AND `event_at` > %s';
        $where_values[] = $today;
        $sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' WHERE ' . $where_sql;
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function past( $conditions, $limit = 0, $offset = 0 ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $today = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
        $where_sql     .= ' AND `event_at` < %s';
        $where_values[] = $today;
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE ' . $where_sql . ' ORDER BY `event_at` DESC';
        $sql = self::_apply_limit( $sql, $where_values, $limit, $offset );
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function count_past( $conditions ) {
        global $wpdb;
        list( $where_sql, $where_values ) = self::_build_where( $conditions );
        $today = ( new \DateTime("now", new \DateTimeZone( get_option('timezone_string') ) ) )->format('Y-m-d H:i:s');
        $where_sql     .= ' AND `event_at` < %s';
        $where_values[] = $today;
        $sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' WHERE ' . $where_sql;
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function search( $search_term, $fields = [ 'name' ], $limit = 20, $offset = 0 ) {
        global $wpdb;
        $allowed_fields = [ 'name', 'description', 'slug' ];
        $where_clauses  = [];
        $where_values   = [];
        foreach ( $fields as $field ) {
            if ( ! in_array( $field, $allowed_fields ) ) {
                continue;
            }
            $where_clauses[] = "`{$field}` LIKE %s";
            $where_values[]  = '%' . $wpdb->esc_like( $search_term ) . '%';
        }
        if ( empty( $where_clauses ) ) {
            return [];
        }
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE ' . implode( ' OR ', $where_clauses ) . ' ORDER BY `created_at` DESC';
        $sql = self::_apply_limit( $sql, $where_values, $limit, $offset );
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function fetch( $value ) {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE `site_id` = %s ORDER BY `created_at` DESC';
        return $wpdb->get_results( $wpdb->prepare( $sql, $value ) );
    }

    static function all( $sort = "created_at", $sort_order = "DESC", $limit = 0, $offset = 0 ) {
        global $wpdb;
        $allowed_sorts  = [ 'created_at', 'name', 'event_at', 'slug' ];
        $allowed_orders = [ 'ASC', 'DESC' ];
        $sort       = in_array( $sort, $allowed_sorts ) ? $sort : 'created_at';
        $sort_order = in_array( strtoupper( $sort_order ), $allowed_orders ) ? strtoupper( $sort_order ) : 'DESC';
        $sql = 'SELECT * FROM ' . self::_table() . ' ORDER BY `' . $sort . '` ' . $sort_order;
        if ( $limit > 0 ) {
            $sql = $wpdb->prepare( $sql . ' LIMIT %d OFFSET %d', $limit, $offset );
            return $wpdb->get_results( $sql );
        }
        return $wpdb->get_results( $sql );
    }

    static function count_all() {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::_table() );
    }

    static function where_compare( $conditions ) {
        global $wpdb;
        $where_clauses    = [];
        $where_values     = [];
        $allowed_compares = [ '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE' ];
        foreach ( $conditions as $condition ) {
            $compare = in_array( $condition["compare"], $allowed_compares ) ? $condition["compare"] : '=';
            $where_clauses[] = "`{$condition["key"]}` {$compare} %s";
            $where_values[]  = $condition["value"];
        }
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE ' . implode( ' AND ', $where_clauses ) . ' ORDER BY `created_at` DESC';
        return $wpdb->get_results( $wpdb->prepare( $sql, ...$where_values ) );
    }

    static function mine( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $user_id        = get_current_user_id();
        $allowed_sorts  = [ 'created_at', 'name', 'event_at', 'slug' ];
        $allowed_orders = [ 'ASC', 'DESC' ];
        $sort       = in_array( $sort, $allowed_sorts ) ? $sort : 'created_at';
        $sort_order = in_array( strtoupper( $sort_order ), $allowed_orders ) ? strtoupper( $sort_order ) : 'DESC';
        $sql = 'SELECT * FROM ' . self::_table() . ' WHERE `user_id` = %s ORDER BY `' . $sort . '` ' . $sort_order;
        return $wpdb->get_results( $wpdb->prepare( $sql, $user_id ) );
    }

    static function select( $field = "site_id", $where = "status", $value = "active", $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $allowed_sorts  = [ 'created_at', 'name', 'event_at', 'slug', 'site_id', 'status' ];
        $allowed_orders = [ 'ASC', 'DESC' ];
        $field      = in_array( $field, $allowed_sorts ) ? $field : 'site_id';
        $where      = in_array( $where, $allowed_sorts ) ? $where : 'status';
        $sort       = in_array( $sort, $allowed_sorts ) ? $sort : 'created_at';
        $sort_order = in_array( strtoupper( $sort_order ), $allowed_orders ) ? strtoupper( $sort_order ) : 'DESC';
        $sql = "SELECT `{$field}` FROM " . self::_table() . " WHERE `{$where}` = %s ORDER BY `{$sort}` {$sort_order}";
        $results = array_column( $wpdb->get_results( $wpdb->prepare( $sql, $value ) ), $field );
        return $results;
    }

    // Perform LocalMeet database upgrades by running `LocalMeet\DB::upgrade();`
    public static function upgrade( $force = false ) {

        $required_version = (int) "11";
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

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_comments` (
            comment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED,
            user_id bigint(20) UNSIGNED,
            details longtext,
            revisions longtext,
            status varchar(20) DEFAULT 'approved',
            created_at datetime NOT NULL,
        PRIMARY KEY  (comment_id)
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

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_locations` (
            location_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id bigint(20) UNSIGNED,
            name varchar(255),
            address longtext,
            notes longtext,
            created_at datetime NOT NULL,
        PRIMARY KEY  (location_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_events` (
            event_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id bigint(20) UNSIGNED,
            location longtext,
            description longtext,
            summary longtext,
            name varchar(255),
            slug varchar(255),
            event_at datetime NOT NULL,
            event_end_at datetime,
            capacity int UNSIGNED,
            recurrence_rule varchar(50),
            recurrence_parent_id bigint(20) UNSIGNED,
            image_id bigint(20) UNSIGNED,
            cancelled_at datetime,
            announced_at datetime,
            created_at datetime NOT NULL,
        PRIMARY KEY  (event_id)
        ) $charset_collate;";

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_members` (
            member_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED,
            group_id bigint(20) UNSIGNED,
            active boolean,
            role varchar(20) DEFAULT 'member',
            email_notifications boolean DEFAULT 1,
            created_at datetime NOT NULL,
            left_at datetime,
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

        $sql .= "CREATE TABLE `{$wpdb->base_prefix}localmeet_invites` (
            invite_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255),
            group_allowance int UNSIGNED DEFAULT 0,
            token varchar(255),
            accepted_at datetime,
            accepted_by bigint(20) UNSIGNED,
            created_at datetime NOT NULL,
        PRIMARY KEY  (invite_id)
        ) $charset_collate;";

        dbDelta($sql);

        if ( ! empty( $wpdb->last_error ) ) {
            return $wpdb->last_error;
        }

        update_site_option( 'localmeet_db_version', $required_version );
        echo "Updated `localmeet_db_version` to v$required_version";
    }
}

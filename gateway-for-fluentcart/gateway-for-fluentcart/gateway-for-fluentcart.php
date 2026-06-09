<?php
/*
* Plugin Name:       Licence Gateway For FluentCart
* Plugin URI:        https://reallyusefulplugins.com/plugins/hoster-api
* Description:       Adds FluentCart Intergration to UUPD Server & Hoster including customer portal, outbound emails for Licence Sales.
* Version:           0.9
* Author:            ReallyUsefulPlugins.com
* Author URI: https://Reallyusefulplugins.com
* Text Domain:       gateway-for-fluentcart
*/

/**
 * Default FluentCart dashboard licence icon.
 */
function rup_gateway_fc_default_dashboard_icon_svg() {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"><path d="M16 17.5H4C3.80109 17.5 3.61032 17.421 3.46967 17.2803C3.32902 17.1397 3.25 16.9489 3.25 16.75V3.25C3.25 3.05109 3.32902 2.86032 3.46967 2.71967C3.61032 2.57902 3.80109 2.5 4 2.5H16C16.1989 2.5 16.3897 2.57902 16.5303 2.71967C16.671 2.86032 16.75 3.05109 16.75 3.25V16.75C16.75 16.9489 16.671 17.1397 16.5303 17.2803C16.3897 17.421 16.1989 17.5 16 17.5ZM15.25 16V4H4.75V16H15.25ZM7 6.25H13V7.75H7V6.25ZM7 9.25H13V10.75H7V9.25ZM7 12.25H10.75V13.75H7V12.25Z" fill="currentColor"></path></svg>';
}

/**
 * Safely allow the small inline SVGs used by FluentCart dashboard tabs.
 */
function rup_gateway_fc_sanitize_svg_icon( $svg ) {
    $allowed = array(
        'svg' => array(
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'viewbox' => true,
            'viewBox' => true,
            'fill' => true,
            'class' => true,
            'aria-hidden' => true,
            'role' => true,
        ),
        'path' => array(
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'class' => true,
        ),
        'g' => array(
            'fill' => true,
            'stroke' => true,
            'class' => true,
        ),
        'rect' => array(
            'x' => true,
            'y' => true,
            'width' => true,
            'height' => true,
            'rx' => true,
            'fill' => true,
            'stroke' => true,
            'class' => true,
        ),
        'circle' => array(
            'cx' => true,
            'cy' => true,
            'r' => true,
            'fill' => true,
            'stroke' => true,
            'class' => true,
        ),
    );

    $svg = wp_kses( (string) $svg, $allowed );
    return trim( $svg ) ? $svg : rup_gateway_fc_default_dashboard_icon_svg();
}

/**
 * Default plugin settings. Licence delivery is handled through FluentCart receipt injection and shortcodes.
 */
function rup_gateway_fc_get_default_options() {
    return array(
        'debug_enabled'                    => 0,
        'gateway_provider_mode'            => 'auto',
        'debug_log_name'                   => 'fc-gateway-debug.log',
        'hoster_admin_improvements_enabled'       => 0,
        'auto_inject_receipt_licenses'     => 1,
        'add_licence_dashboard_tab'        => 1,
        'licence_dashboard_endpoint'       => 'licences',
        'licence_dashboard_tab_name'       => 'Licences',
        'licence_dashboard_title'          => 'Licences',
        'licence_dashboard_icon_svg'       => rup_gateway_fc_default_dashboard_icon_svg(),
        'licence_dashboard_only_with_licenses' => 1,
        'licence_dashboard_hide_expired_only' => 0,
        'licence_dashboard_insert_after' => 'downloads',
        'primary_button_color'             => '#16a34a',
        'secondary_button_color'           => '#0f6da8',
    );
}

function rup_gateway_fc_get_options() {
    return wp_parse_args( get_option( 'rup_gateway_fc_options', array() ), rup_gateway_fc_get_default_options() );
}


/**
 * Licence provider detection and adapter functions.
 */
function rup_gateway_fc_is_uupd_available() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    return (
        function_exists( 'uupd_create_license' )
        || function_exists( 'uupd_update_license' )
        || defined( 'UUPD_SERVER_FILE' )
        || class_exists( 'UUPD_Core' )
        || post_type_exists( 'uupd_license' )
        || post_type_exists( 'update' )
        || is_plugin_active( 'uupd-server/uupd-server.php' )
    );
}

function rup_gateway_fc_is_hoster_available() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    return (
        post_type_exists( 'hoster_license' )
        || post_type_exists( 'downloads' )
        || has_filter( 'hoster_create_new_license' )
        || has_filter( 'hoster_update_license' )
        || is_plugin_active( 'hoster/hoster.php' )
    );
}

function rup_gateway_fc_get_license_provider() {
    $options = rup_gateway_fc_get_options();
    $mode    = isset( $options['gateway_provider_mode'] ) ? sanitize_key( $options['gateway_provider_mode'] ) : 'auto';

    if ( 'uupd' === $mode ) {
        return 'uupd';
    }

    if ( 'hoster' === $mode ) {
        return 'hoster';
    }

    $hoster_available = rup_gateway_fc_is_hoster_available();
    $uupd_available   = rup_gateway_fc_is_uupd_available();

    // If only one provider is installed, use it automatically.
    if ( $uupd_available && ! $hoster_available ) {
        return 'uupd';
    }

    if ( $hoster_available && ! $uupd_available ) {
        return 'hoster';
    }

    // If both are installed, Gateway for FluentCart is UUPD-first and Hoster-second.
    if ( $uupd_available ) {
        return 'uupd';
    }

    if ( $hoster_available ) {
        return 'hoster';
    }

    return 'uupd';
}

function rup_gateway_fc_is_hoster_active_setup() {
    return ( 'hoster' === rup_gateway_fc_get_license_provider() && rup_gateway_fc_is_hoster_available() );
}

function rup_gateway_fc_license_provider_label() {
    return ( 'uupd' === rup_gateway_fc_get_license_provider() ) ? 'UUPD Server' : 'Hoster';
}

function rup_gateway_fc_normalize_provider_response( $response ) {
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( is_numeric( $response ) ) {
        $license_id = absint( $response );
        return array(
            'success'     => (bool) $license_id,
            'message'     => $license_id ? 'License successfully created.' : 'License creation failed.',
            'license_id'  => $license_id,
            'license_key' => $license_id ? get_post_meta( $license_id, 'license_key', true ) : '',
        );
    }

    return $response;
}

function rup_gateway_fc_provider_create_license( $mapping, $user_id, $expiry_date, $context = array() ) {
    $provider = rup_gateway_fc_get_license_provider();

    if ( 'uupd' === $provider ) {
        if ( ! function_exists( 'uupd_create_license' ) ) {
            return new WP_Error( 'uupd_create_missing', 'UUPD Server is active but uupd_create_license() is not available.' );
        }

        $response = uupd_create_license( array(
            'update_id'        => absint( $mapping->download_id ),
            'package_id'       => '',
            'user_id'          => absint( $user_id ),
            'activation_limit' => ! empty( $mapping->enable_activation_limit ) ? absint( $mapping->activation_limit ) : 0,
            'expiry'           => (string) $expiry_date,
            'order_id'         => isset( $context['order_id'] ) ? (string) absint( $context['order_id'] ) : '',
        ) );

        $response = rup_gateway_fc_normalize_provider_response( $response );

        $license_id = 0;
        if ( is_array( $response ) && ! empty( $response['license_id'] ) ) {
            $license_id = absint( $response['license_id'] );
        }

        if ( $license_id && ! empty( $mapping->status ) && 'active' !== sanitize_key( $mapping->status ) ) {
            rup_gateway_fc_provider_update_license( $license_id, array( 'status' => sanitize_key( $mapping->status ) ), $context );
        }

        return $response;
    }

    return apply_filters(
        'hoster_create_new_license',
        null,
        absint( $mapping->download_id ),
        absint( $user_id ),
        sanitize_text_field( $mapping->status ? $mapping->status : 'active' ),
        ! empty( $mapping->enable_activation_limit ),
        absint( $mapping->activation_limit ),
        (string) $expiry_date
    );
}

function rup_gateway_fc_provider_update_license( $license_id, $update_data, $context = array() ) {
    $provider   = rup_gateway_fc_get_license_provider();
    $license_id = absint( $license_id );

    if ( 'uupd' === $provider ) {
        if ( function_exists( 'uupd_update_license' ) ) {
            return uupd_update_license( $license_id, $update_data );
        }

        if ( isset( $update_data['status'] ) ) {
            update_post_meta( $license_id, 'status', sanitize_key( $update_data['status'] ) );
        }
        if ( isset( $update_data['expiry_date'] ) ) {
            update_post_meta( $license_id, 'expiry', sanitize_text_field( $update_data['expiry_date'] ) );
        }
        if ( isset( $update_data['expiry'] ) ) {
            update_post_meta( $license_id, 'expiry', sanitize_text_field( $update_data['expiry'] ) );
        }
        if ( isset( $update_data['activation_limit'] ) ) {
            update_post_meta( $license_id, 'activation_limit', absint( $update_data['activation_limit'] ) );
        }

        return array(
            'success'        => true,
            'message'        => 'License details updated successfully.',
            'license_id'     => $license_id,
            'updated_fields' => array_keys( $update_data ),
        );
    }

    return apply_filters( 'hoster_update_license', null, $license_id, $update_data );
}

function rup_gateway_fc_get_license_user_ids_query_args( $user_id, $download_id = 0 ) {
    $provider = rup_gateway_fc_get_license_provider();
    $args = array(
        'post_type'      => ( 'uupd' === $provider ) ? 'uupd_license' : 'hoster_license',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => 'user_id',
                'value'   => absint( $user_id ),
                'compare' => '=',
            ),
        ),
    );

    if ( $download_id ) {
        $args['meta_query'][] = array(
            'key'     => ( 'uupd' === $provider ) ? 'update_id' : 'download_id',
            'value'   => absint( $download_id ),
            'compare' => '=',
        );
    }

    return $args;
}

function rup_gateway_fc_provider_get_license_key( $license_id ) {
    return (string) get_post_meta( absint( $license_id ), 'license_key', true );
}

function rup_gateway_fc_provider_get_license_product_id( $license_id ) {
    $provider = rup_gateway_fc_get_license_provider();
    return absint( get_post_meta( absint( $license_id ), ( 'uupd' === $provider ) ? 'update_id' : 'download_id', true ) );
}

function rup_gateway_fc_provider_get_license_expiry( $license_id ) {
    $provider = rup_gateway_fc_get_license_provider();
    return (string) get_post_meta( absint( $license_id ), ( 'uupd' === $provider ) ? 'expiry' : 'expiry_date', true );
}

function rup_gateway_fc_provider_get_license_sites( $license_id ) {
    $provider = rup_gateway_fc_get_license_provider();
    $sites = get_post_meta( absint( $license_id ), ( 'uupd' === $provider ) ? 'used_domains' : 'used_sites', true );
    return is_array( $sites ) ? array_values( array_filter( array_map( 'trim', $sites ) ) ) : array();
}

function rup_gateway_fc_provider_update_license_sites( $license_id, $sites ) {
    $provider = rup_gateway_fc_get_license_provider();
    $sites = is_array( $sites ) ? array_values( array_filter( array_map( 'sanitize_text_field', $sites ) ) ) : array();
    update_post_meta( absint( $license_id ), ( 'uupd' === $provider ) ? 'used_domains' : 'used_sites', $sites );
}

function rup_gateway_fc_provider_get_license_download_url( $product_id, $license_key = '' ) {
    $product_id  = absint( $product_id );
    $license_key = trim( (string) $license_key );

    if ( ! $product_id ) {
        return '';
    }

    if ( 'uupd' === rup_gateway_fc_get_license_provider() ) {
        /*
         * UUPD builds signed customer download URLs from the update post and
         * licence key. Do not use generic/placeholder meta such as remote_url
         * here, because that can expose incorrect provider details in the
         * dashboard. If UUPD cannot provide a real URL, hide the button.
         */
        if ( function_exists( 'uupd_simple_build_download_url' ) ) {
            $download_url = uupd_simple_build_download_url( $product_id, $license_key );
            return $download_url ? esc_url_raw( $download_url ) : '';
        }

        return '';
    }

    if ( function_exists( 'rup_gateway_fc_generate_secure_download_link' ) ) {
        $download_url = get_post_meta( $product_id, 'download_url', true );
        return $download_url ? rup_gateway_fc_generate_secure_download_link( $download_url ) : '';
    }

    return '';
}


/**
 * Return normalised licence data for the active provider.
 *
 * This is used by the dashboard, receipt shortcode, and email injection so
 * Hoster and UUPD licences render through the same path.
 */
function rup_gateway_fc_provider_get_license_data( $license_id, $linked_product_id = 0 ) {
    $license_id = absint( $license_id );
    if ( ! $license_id ) {
        return array();
    }

    $post = get_post( $license_id );
    if ( ! $post ) {
        return array();
    }

    $provider   = rup_gateway_fc_get_license_provider();
    $product_id = absint( $linked_product_id );

    if ( ! $product_id ) {
        $product_id = rup_gateway_fc_provider_get_license_product_id( $license_id );
    }

    $license_key = rup_gateway_fc_provider_get_license_key( $license_id );
    if ( '' === $license_key ) {
        $license_key = get_the_title( $license_id );
    }

    $expiry = rup_gateway_fc_provider_get_license_expiry( $license_id );
    $sites  = rup_gateway_fc_provider_get_license_sites( $license_id );

    return array(
        'provider'         => $provider,
        'license_id'       => $license_id,
        'license_key'      => (string) $license_key,
        'status'           => (string) get_post_meta( $license_id, 'status', true ),
        'expiry_date'      => (string) $expiry,
        'activation_limit' => absint( get_post_meta( $license_id, 'activation_limit', true ) ),
        'sites'            => is_array( $sites ) ? $sites : array(),
        'product_id'       => $product_id,
        'product_name'     => $product_id ? get_the_title( $product_id ) : get_the_title( $license_id ),
        'download_url'     => $product_id ? rup_gateway_fc_provider_get_license_download_url( $product_id, $license_key ) : '',
    );
}


/**
 * Return the most recently observed FluentCart customer menu keys.
 *
 * FluentCart builds the customer menu as an associative array and exposes it
 * through fluent_cart/global_customer_menu_items on the front end. The settings
 * screen may not have a live customer dashboard menu available, so we cache the
 * keys whenever that filter runs and use that cache to populate the selector.
 */
function rup_gateway_fc_get_cached_customer_menu_positions() {
    $cached = get_option( 'rup_gateway_fc_customer_menu_positions', array() );
    if ( ! is_array( $cached ) ) {
        $cached = array();
    }

    $defaults = array(
        'dashboard'        => 'Dashboard',
        'purchase-history' => 'Purchase History',
        'subscriptions'    => 'Subscription Plans',
        'downloads'        => 'Downloads',
        'profile'          => 'Profile',
    );

    return array_merge( $defaults, $cached );
}

function rup_gateway_fc_menu_item_label( $key, $item ) {
    if ( is_array( $item ) ) {
        foreach ( array( 'title', 'label', 'name' ) as $label_key ) {
            if ( ! empty( $item[ $label_key ] ) && is_scalar( $item[ $label_key ] ) ) {
                return sanitize_text_field( $item[ $label_key ] );
            }
        }
    } elseif ( is_object( $item ) ) {
        foreach ( array( 'title', 'label', 'name' ) as $label_key ) {
            if ( ! empty( $item->{$label_key} ) && is_scalar( $item->{$label_key} ) ) {
                return sanitize_text_field( $item->{$label_key} );
            }
        }
    }

    return ucwords( str_replace( array( '-', '_' ), ' ', sanitize_key( $key ) ) );
}

function rup_gateway_fc_get_current_user_licence_counts() {
    $counts = array(
        'total'       => 0,
        'non_expired' => 0,
        'active'      => 0,
    );

    if ( ! is_user_logged_in() ) {
        return $counts;
    }

    if ( function_exists( 'rup_gateway_fc_get_license_user_ids_query_args' ) ) {
        $license_ids = get_posts( rup_gateway_fc_get_license_user_ids_query_args( get_current_user_id() ) );
    } else {
        $license_ids = get_posts( array(
            'post_type'      => 'hoster_license',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'user_id',
                    'value'   => get_current_user_id(),
                    'compare' => '=',
                ),
            ),
        ) );
    }

    $counts['total'] = count( $license_ids );

    foreach ( $license_ids as $license_id ) {
        $status = strtolower( (string) get_post_meta( $license_id, 'status', true ) );
        if ( 'active' === $status ) {
            $counts['active']++;
        }
        if ( ! in_array( $status, array( 'expired', 'cancelled', 'canceled', 'refunded', 'inactive' ), true ) ) {
            $counts['non_expired']++;
        }
    }

    return $counts;
}

function rup_gateway_fc_current_user_has_licences() {
    $counts = rup_gateway_fc_get_current_user_licence_counts();
    return $counts['total'] > 0;
}

function rup_gateway_fc_current_user_has_non_expired_licences() {
    $counts = rup_gateway_fc_get_current_user_licence_counts();
    return $counts['non_expired'] > 0;
}

function rup_gateway_fc_sanitize_options( $input ) {
    $endpoint = isset( $input['licence_dashboard_endpoint'] ) ? sanitize_title( $input['licence_dashboard_endpoint'] ) : 'licences';
    if ( empty( $endpoint ) ) {
        $endpoint = 'licences';
    }

    $gateway_provider_mode = in_array( sanitize_key( $input['gateway_provider_mode'] ?? 'auto' ), array( 'auto', 'hoster', 'uupd' ), true ) ? sanitize_key( $input['gateway_provider_mode'] ?? 'auto' ) : 'auto';
    $hoster_admin_improvements_enabled = 0;

    if ( isset( $input['hoster_admin_improvements_enabled'] ) && rup_gateway_fc_is_hoster_available() ) {
        if ( 'hoster' === $gateway_provider_mode || ( 'auto' === $gateway_provider_mode && 'hoster' === rup_gateway_fc_get_license_provider() ) ) {
            $hoster_admin_improvements_enabled = 1;
        }
    }

    return array(
        'debug_enabled'                    => isset( $input['debug_enabled'] ) ? 1 : 0,
        'gateway_provider_mode'            => $gateway_provider_mode,
        'debug_log_name'                   => sanitize_file_name( $input['debug_log_name'] ?? 'fc-gateway-debug.log' ),
        'hoster_admin_improvements_enabled'       => $hoster_admin_improvements_enabled,
        'auto_inject_receipt_licenses'     => isset( $input['auto_inject_receipt_licenses'] ) ? 1 : 0,
        'add_licence_dashboard_tab'        => isset( $input['add_licence_dashboard_tab'] ) ? 1 : 0,
        'licence_dashboard_endpoint'       => $endpoint,
        'licence_dashboard_tab_name'       => sanitize_text_field( $input['licence_dashboard_tab_name'] ?? 'Licences' ),
        'licence_dashboard_title'          => sanitize_text_field( $input['licence_dashboard_title'] ?? 'Licences' ),
        'licence_dashboard_icon_svg'       => rup_gateway_fc_sanitize_svg_icon( $input['licence_dashboard_icon_svg'] ?? rup_gateway_fc_default_dashboard_icon_svg() ),
        'licence_dashboard_only_with_licenses' => isset( $input['licence_dashboard_only_with_licenses'] ) ? 1 : 0,
        'licence_dashboard_hide_expired_only' => isset( $input['licence_dashboard_hide_expired_only'] ) ? 1 : 0,
        'licence_dashboard_insert_after' => sanitize_key( $input['licence_dashboard_insert_after'] ?? 'downloads' ),
        'primary_button_color'             => sanitize_hex_color( $input['primary_button_color'] ?? '#16a34a' ) ?: '#16a34a',
        'secondary_button_color'           => sanitize_hex_color( $input['secondary_button_color'] ?? '#0f6da8' ) ?: '#0f6da8',
    );
}

require 'shortcode.php'; // Shortcode
require 'gateway.php'; // Gateway Processing
require 'downloads.php'; // Secure Downloads
require 'hoster-admin-changes.php'; // Admin Improvements

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}



class Rup_Gateway_FC_Gateway {

    /**
     * Custom table name to store mappings.
     *
     * @var string
     */
    private $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'rup_gateway_fc_mappings';

        // Create custom table on activation.
        register_activation_hook( __FILE__, array( $this, 'rup_gateway_fc_install' ) );

        // Run upgrade routine on admin pages.
        add_action( 'admin_init', array( $this, 'rup_gateway_fc_upgrade' ) );

        // Admin menu and form processing.
        add_action( 'admin_menu', array( $this, 'rup_gateway_fc_admin_menu' ), 99 );
        add_action( 'admin_post_rup_gateway_fc_save_mapping', array( $this, 'rup_gateway_fc_save_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_delete_mapping', array( $this, 'rup_gateway_fc_delete_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_undo_mapping', array( $this, 'rup_gateway_fc_undo_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_save_settings', array( $this, 'rup_gateway_fc_save_settings' ) );
        add_action( 'admin_post_rup_gateway_fc_simulate_renewal', array( $this, 'rup_gateway_fc_simulate_renewal' ) );
        add_action( 'init', array( $this, 'rup_gateway_fc_register_customer_dashboard_tab' ), 20 );
        add_filter( 'fluent_cart/global_customer_menu_items', array( $this, 'rup_gateway_fc_reorder_customer_dashboard_tab' ), 20 );
    }


    /**
     * Creates the custom database table to store mapping records.
     */
    public function rup_gateway_fc_install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $this->table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            friendly_name VARCHAR(255) NOT NULL,
            price_id VARCHAR(255) NOT NULL,
            download_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            enable_activation_limit TINYINT(1) NOT NULL DEFAULT 0,
            activation_limit INT NOT NULL,
            lifetime TINYINT(1) NOT NULL DEFAULT 0,
            expiry_duration INT NOT NULL DEFAULT 0,
            expiry_unit VARCHAR(20) NOT NULL DEFAULT 'Days',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $links_table = $wpdb->prefix . 'rup_gateway_fc_license_links';
        $links_sql = "CREATE TABLE $links_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            order_item_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            subscription_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            customer_email VARCHAR(190) NOT NULL DEFAULT '',
            mapping_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            download_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            license_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            quantity_index INT NOT NULL DEFAULT 1,
            fluent_product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            fluent_variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id),
            KEY subscription_id (subscription_id),
            KEY license_id (license_id),
            KEY user_download (user_id, download_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        dbDelta( $links_sql );
    }

    /**
     * Upgrade routine to update the database table structure.
     */
    public function rup_gateway_fc_upgrade() {
        global $wpdb;
        $table = $this->table_name;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            $this->rup_gateway_fc_install();
        }
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return;
        }
        $column = $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE 'friendly_name'" );
        if ( empty( $column ) ) {
            $wpdb->query( "ALTER TABLE $table ADD friendly_name VARCHAR(255) NOT NULL DEFAULT '' AFTER id" );
        }

        $links_table = $wpdb->prefix . 'rup_gateway_fc_license_links';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $links_table ) ) !== $links_table ) {
            $this->rup_gateway_fc_install();
        }
    }


    /**
     * Adds the Gateway licence manager as an optional FluentCart customer dashboard tab.
     */
    public function rup_gateway_fc_register_customer_dashboard_tab() {
        $options = rup_gateway_fc_get_options();
        if ( empty( $options['add_licence_dashboard_tab'] ) || ! function_exists( 'fluent_cart_api' ) ) {
            return;
        }

        $licence_counts = rup_gateway_fc_get_current_user_licence_counts();

        if ( ! empty( $options['licence_dashboard_only_with_licenses'] ) && $licence_counts['total'] < 1 ) {
            return;
        }

        if ( ! empty( $options['licence_dashboard_hide_expired_only'] ) && $licence_counts['non_expired'] < 1 ) {
            return;
        }

        $endpoint = sanitize_title( $options['licence_dashboard_endpoint'] ?? 'licences' );
        if ( empty( $endpoint ) ) {
            $endpoint = 'licences';
        }

        $tab_title = sanitize_text_field( $options['licence_dashboard_tab_name'] ?? 'Licences' );

        fluent_cart_api()->addCustomerDashboardEndpoint( $endpoint, array(
            'title'    => $tab_title,
            'icon_svg' => rup_gateway_fc_sanitize_svg_icon( $options['licence_dashboard_icon_svg'] ?? rup_gateway_fc_default_dashboard_icon_svg() ),
            'render_callback' => function () use ( $options ) {
                $title = sanitize_text_field( $options['licence_dashboard_title'] ?? 'Licences' );
                echo '<div class="fluent-cart-custom-page-content rup-gateway-fc-dashboard-tab-content">';
                if ( ! empty( $title ) ) {
                    echo '<h2 class="rup-gateway-fc-dashboard-tab-title">' . esc_html( $title ) . '</h2>';
                }
                echo do_shortcode( '[gateway_licences]' );
                echo '</div>';
            },
        ) );
    }

    /**
     * Reorder the FluentCart customer dashboard menu using FluentCart's associative menu array.
     * The setting stores the menu key the licences tab should appear after.
     */
    public function rup_gateway_fc_reorder_customer_dashboard_tab( $items ) {
        if ( ! is_array( $items ) ) {
            return $items;
        }

        $observed_positions = array();
        foreach ( $items as $menu_key => $menu_item ) {
            $menu_key = sanitize_key( $menu_key );
            if ( '' === $menu_key ) {
                continue;
            }
            $observed_positions[ $menu_key ] = rup_gateway_fc_menu_item_label( $menu_key, $menu_item );
        }
        if ( ! empty( $observed_positions ) ) {
            update_option( 'rup_gateway_fc_customer_menu_positions', $observed_positions, false );
        }

        $options = rup_gateway_fc_get_options();
        if ( empty( $options['add_licence_dashboard_tab'] ) ) {
            return $items;
        }

        $endpoint = sanitize_title( $options['licence_dashboard_endpoint'] ?? 'licences' );
        if ( empty( $endpoint ) || ! array_key_exists( $endpoint, $items ) ) {
            return $items;
        }

        $licence_item = $items[ $endpoint ];
        unset( $items[ $endpoint ] );

        $insert_after = sanitize_key( $options['licence_dashboard_insert_after'] ?? 'downloads' );

        if ( 'first' === $insert_after ) {
            return array( $endpoint => $licence_item ) + $items;
        }

        $reordered = array();
        $inserted  = false;

        foreach ( $items as $key => $item ) {
            $reordered[ $key ] = $item;
            if ( $key === $insert_after ) {
                $reordered[ $endpoint ] = $licence_item;
                $inserted = true;
            }
        }

        if ( ! $inserted ) {
            $reordered[ $endpoint ] = $licence_item;
        }

        return $reordered;
    }


    /**
     * Adds an admin menu item.
     * Nests under the active licence provider when possible.
     */
    public function rup_gateway_fc_admin_menu() {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $provider = function_exists( 'rup_gateway_fc_get_license_provider' ) ? rup_gateway_fc_get_license_provider() : 'hoster';

        if ( 'uupd' === $provider && function_exists( 'rup_gateway_fc_is_uupd_available' ) && rup_gateway_fc_is_uupd_available() ) {
            add_submenu_page(
                'uupd_server',
                'FluentCart Gateway',
                'FluentCart Gateway',
                'manage_options',
                'rup-gateway-fc',
                array( $this, 'rup_gateway_fc_render_admin_page' )
            );
            return;
        }

        if ( function_exists( 'rup_gateway_fc_is_hoster_available' ) && rup_gateway_fc_is_hoster_available() ) {
            add_submenu_page(
                'edit.php?post_type=downloads',
                'FluentCart Gateway',
                'FluentCart Gateway',
                'manage_options',
                'rup-gateway-fc',
                array( $this, 'rup_gateway_fc_render_admin_page' )
            );
            return;
        }

        add_menu_page(
            'FluentCart Gateway',
            'FluentCart Gateway',
            'manage_options',
            'rup-gateway-fc',
            array( $this, 'rup_gateway_fc_render_admin_page' ),
            'dashicons-admin-generic',
            26
        );
    }

    /**
     * Renders the main admin page.
     */
    public function rup_gateway_fc_render_admin_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        if ( 'settings' === $action ) {
            $this->rup_gateway_fc_render_settings();
        } elseif ( 'add' === $action || 'edit' === $action ) {
            $this->rup_gateway_fc_render_form();
        } else {
            $this->rup_gateway_fc_render_list();
        }
    }


    /**
     * Returns published Hoster downloads for mapping selectors.
     */
    private function rup_gateway_fc_get_download_choices() {
        $provider = function_exists( 'rup_gateway_fc_get_license_provider' ) ? rup_gateway_fc_get_license_provider() : 'hoster';
        $post_type = ( 'uupd' === $provider ) ? 'update' : 'downloads';

        $posts = get_posts( array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $choices = array();
        foreach ( $posts as $post_id ) {
            $choices[] = array(
                'id'    => absint( $post_id ),
                'label' => get_the_title( $post_id ) . ' (#' . absint( $post_id ) . ')',
            );
        }

        return $choices;
    }

    /**
     * Returns FluentCart products and variations for the product/price selector.
     */
    private function rup_gateway_fc_get_fluent_product_choices() {
        global $wpdb;

        $choices = array();
        $products = get_posts( array(
            'post_type'      => 'fluent-products',
            'post_status'    => array( 'publish', 'private', 'draft' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        foreach ( $products as $post_id ) {
            $product_title = get_the_title( $post_id );
            $choices[] = array(
                'id'    => (string) absint( $post_id ),
                'label' => $product_title . ' — Product #' . absint( $post_id ),
                'type'  => 'product',
            );
        }

        $variations_table = $wpdb->prefix . 'fct_product_variations';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $variations_table ) ) === $variations_table ) {
            $variations = $wpdb->get_results(
                "SELECT id, post_id, variation_title, variation_identifier, sku, payment_type, item_status FROM {$variations_table} ORDER BY post_id ASC, serial_index ASC, id ASC",
                ARRAY_A
            );

            foreach ( $variations as $variation ) {
                $product_title = get_the_title( absint( $variation['post_id'] ) );
                $bits = array_filter( array(
                    $product_title,
                    $variation['variation_title'],
                    ! empty( $variation['payment_type'] ) ? '(' . $variation['payment_type'] . ')' : '',
                    ! empty( $variation['sku'] ) ? 'SKU: ' . $variation['sku'] : '',
                    ! empty( $variation['variation_identifier'] ) ? 'Identifier: ' . $variation['variation_identifier'] : '',
                ) );
                $choices[] = array(
                    'id'    => (string) absint( $variation['id'] ),
                    'label' => implode( ' — ', $bits ) . ' — Variation #' . absint( $variation['id'] ) . ' / Product #' . absint( $variation['post_id'] ),
                    'type'  => 'variation',
                );
            }
        }

        return $choices;
    }

    /**
     * Builds a display label for a saved Hoster download ID.
     */
    private function rup_gateway_fc_get_download_label( $download_id ) {
        $download_id = absint( $download_id );
        if ( ! $download_id ) {
            return '';
        }
        $title = get_the_title( $download_id );
        return $title ? $title . ' (#' . $download_id . ')' : '#' . $download_id;
    }

    /**
     * Builds a display label for a saved FluentCart product or variation ID.
     */
    private function rup_gateway_fc_get_fluent_product_label( $price_id ) {
        global $wpdb;

        $price_id = sanitize_text_field( $price_id );
        if ( '' === $price_id ) {
            return '';
        }

        if ( ctype_digit( (string) $price_id ) ) {
            $post = get_post( absint( $price_id ) );
            if ( $post && 'fluent-products' === $post->post_type ) {
                return get_the_title( $post ) . ' — Product #' . absint( $price_id );
            }

            $variations_table = $wpdb->prefix . 'fct_product_variations';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $variations_table ) ) === $variations_table ) {
                $variation = $wpdb->get_row( $wpdb->prepare( "SELECT id, post_id, variation_title, payment_type, sku FROM {$variations_table} WHERE id = %d", absint( $price_id ) ), ARRAY_A );
                if ( $variation ) {
                    $bits = array_filter( array(
                        get_the_title( absint( $variation['post_id'] ) ),
                        $variation['variation_title'],
                        ! empty( $variation['payment_type'] ) ? '(' . $variation['payment_type'] . ')' : '',
                        ! empty( $variation['sku'] ) ? 'SKU: ' . $variation['sku'] : '',
                    ) );
                    return implode( ' — ', $bits ) . ' — Variation #' . absint( $variation['id'] );
                }
            }
        }

        return $price_id;
    }

    /**
     * Renders the add/edit form for a mapping.
     */
    public function rup_gateway_fc_render_form() {
        global $wpdb;
        $is_edit = false;
        $mapping = array(
            'id'                      => '',
            'friendly_name'           => '',
            'price_id'                => '',
            'download_id'             => '',
            'status'                  => '',
            'enable_activation_limit' => 0,
            'activation_limit'        => '',
            'lifetime'                => 0,
            'expiry_duration'         => '',
            'expiry_unit'             => 'Days',
        );
        if ( isset( $_GET['mapping_id'] ) && ! empty( $_GET['mapping_id'] ) ) {
            $is_edit = true;
            $mapping_id = intval( $_GET['mapping_id'] );
            $mapping = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $mapping_id ), ARRAY_A );
            if ( ! $mapping ) {
                echo '<div class="notice notice-error"><p>Mapping not found.</p></div>';
                return;
            }
        }
        $download_choices = $this->rup_gateway_fc_get_download_choices();
        $fluent_product_choices = $this->rup_gateway_fc_get_fluent_product_choices();
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit' : 'Add'; ?> Mapping</h1>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <?php wp_nonce_field( 'rup_gateway_fc_save_mapping_nonce' ); ?>
                <input type="hidden" name="action" value="rup_gateway_fc_save_mapping">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping['id'] ); ?>">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="friendly_name">Friendly Name</label></th>
                        <td>
                            <input name="friendly_name" type="text" id="friendly_name" value="<?php echo esc_attr( $mapping['friendly_name'] ); ?>" class="regular-text" required />
                            <p class="description">A human-friendly name to identify this mapping.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="price_id">FluentCart Product / Variation</label></th>
                        <td>
                            <input name="price_id" type="text" id="price_id" value="<?php echo esc_attr( $mapping['price_id'] ); ?>" class="regular-text" list="rup-gateway-fc-products" required autocomplete="off" />
                            <datalist id="rup-gateway-fc-products">
                                <?php foreach ( $fluent_product_choices as $choice ) : ?>
                                    <option value="<?php echo esc_attr( $choice['id'] ); ?>" label="<?php echo esc_attr( $choice['label'] ); ?>"><?php echo esc_html( $choice['label'] ); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                            <p class="description">Start typing to search FluentCart products and variations. Variations are loaded from <code><?php global $wpdb; echo esc_html( $wpdb->prefix . 'fct_product_variations' ); ?></code>; products are loaded from the <code>fluent-products</code> post type.</p>
                            <?php if ( ! empty( $mapping['price_id'] ) ) : ?>
                                <p class="description">Current selection: <?php echo esc_html( $this->rup_gateway_fc_get_fluent_product_label( $mapping['price_id'] ) ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="download_id">Licence Product</label></th>
                        <td>
                            <input name="download_id" type="text" id="download_id" value="<?php echo esc_attr( $mapping['download_id'] ); ?>" class="regular-text" list="rup-gateway-fc-downloads" required autocomplete="off" />
                            <datalist id="rup-gateway-fc-downloads">
                                <?php foreach ( $download_choices as $choice ) : ?>
                                    <option value="<?php echo esc_attr( $choice['id'] ); ?>" label="<?php echo esc_attr( $choice['label'] ); ?>"><?php echo esc_html( $choice['label'] ); ?></option>
                                <?php endforeach; ?>
                            </datalist>
                            <p class="description">Start typing to search available <?php echo ( function_exists( 'rup_gateway_fc_get_license_provider' ) && 'uupd' === rup_gateway_fc_get_license_provider() ) ? 'UUPD updates from the <code>update</code> post type' : 'Hoster downloads from the <code>downloads</code> post type'; ?>.</p>
                            <?php if ( ! empty( $mapping['download_id'] ) ) : ?>
                                <p class="description">Current selection: <?php echo esc_html( $this->rup_gateway_fc_get_download_label( $mapping['download_id'] ) ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status" required>
                                <option value="active" <?php selected( $mapping['status'], 'active' ); ?>>Active</option>
                                <option value="expired" <?php selected( $mapping['status'], 'expired' ); ?>>Expired</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Activation Limit</th>
                        <td>
                            <input type="checkbox" name="enable_activation_limit" id="enable_activation_limit" value="1" <?php checked( $mapping['enable_activation_limit'], 1 ); ?> />
                        </td>
                    </tr>
                    <tr id="activation-limit-row" style="<?php echo ( $mapping['enable_activation_limit'] ) ? '' : 'display:none;'; ?>">
                        <th scope="row"><label for="activation_limit">Activation Limit</label></th>
                        <td>
                            <input name="activation_limit" type="number" id="activation_limit" value="<?php echo esc_attr( $mapping['activation_limit'] ); ?>" class="small-text" min="1" <?php echo ( $mapping['enable_activation_limit'] ) ? 'required' : 'disabled'; ?> />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Expiry</th>
                        <td>
                            <label>
                                <input type="checkbox" name="lifetime" id="lifetime" value="1" <?php checked( $mapping['lifetime'], 1 ); ?> />
                                Lifetime / No Expiry
                            </label>
                            <br /><br />
                            <span id="expiry-settings" <?php if ( ! empty( $mapping['lifetime'] ) ) echo 'style="display:none;"'; ?>>
                                <input name="expiry_duration" type="number" id="expiry_duration" value="<?php echo esc_attr( $mapping['expiry_duration'] ); ?>" class="small-text" min="1" <?php echo ( empty( $mapping['lifetime'] ) ? 'required' : 'disabled' ); ?> /> 
                                <select name="expiry_unit" id="expiry_unit">
                                    <option value="Days" <?php selected( $mapping['expiry_unit'], 'Days' ); ?>>Days</option>
                                    <option value="Weeks" <?php selected( $mapping['expiry_unit'], 'Weeks' ); ?>>Weeks</option>
                                    <option value="Months" <?php selected( $mapping['expiry_unit'], 'Months' ); ?>>Months</option>
                                    <option value="Years" <?php selected( $mapping['expiry_unit'], 'Years' ); ?>>Years</option>
                                </select>
                            </span>
                        </td>
                    </tr>
                </table>
                <?php submit_button( $is_edit ? 'Update Mapping' : 'Add Mapping' ); ?>
            </form>
            <p><a href="<?php echo admin_url( 'admin.php?page=rup-gateway-fc' ); ?>">&laquo; Back to list</a></p>
            <script>
                // Toggle expiry fields when lifetime checkbox is toggled.
                var lifetimeCheckbox = document.getElementById('lifetime');
                var expirySettings = document.getElementById('expiry-settings');
                var expiryDurationInput = document.getElementById('expiry_duration');

                lifetimeCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        expirySettings.style.display = 'none';
                        expiryDurationInput.setAttribute('disabled', 'disabled');
                        expiryDurationInput.removeAttribute('required');
                        expiryDurationInput.value = "";
                    } else {
                        expirySettings.style.display = 'inline';
                        expiryDurationInput.removeAttribute('disabled');
                        expiryDurationInput.setAttribute('required', 'required');
                    }
                });

                // Toggle activation limit row when enable activation limit checkbox is toggled.
                var activationCheckbox = document.getElementById('enable_activation_limit');
                var activationLimitRow = document.getElementById('activation-limit-row');
                var activationLimitInput = document.getElementById('activation_limit');

                activationCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        activationLimitRow.style.display = '';
                        activationLimitInput.removeAttribute('disabled');
                        activationLimitInput.setAttribute('required', 'required');
                    } else {
                        activationLimitRow.style.display = 'none';
                        activationLimitInput.setAttribute('disabled', 'disabled');
                        activationLimitInput.removeAttribute('required');
                        activationLimitInput.value = "";
                    }
                });

                // Confirm deletion with a type-to-delete prompt.
                function confirmDelete() {
                    var confirmation = prompt("Type DELETE to confirm deletion of this mapping. This action cannot be undone.");
                    console.log("confirmDelete() input: " + confirmation);
                    return (confirmation === "DELETE");
                }
            </script>
        </div>
        <?php
    }



    /**
     * Returns recent linked licences for the debug renewal simulator.
     */
    private function rup_gateway_fc_get_linked_license_rows_for_simulator( $limit = 100 ) {
        global $wpdb;
        $links_table = $wpdb->prefix . 'rup_gateway_fc_license_links';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $links_table ) ) !== $links_table ) {
            return array();
        }

        $limit = max( 1, min( 500, absint( $limit ) ) );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, m.friendly_name, m.price_id, m.status AS mapping_status, m.activation_limit, m.expiry_duration, m.expiry_unit, m.lifetime
                 FROM {$links_table} l
                 LEFT JOIN {$this->table_name} m ON l.mapping_id = m.id
                 WHERE l.license_id > 0
                 ORDER BY l.id DESC
                 LIMIT %d",
                $limit
            )
        );

        foreach ( $rows as $row ) {
            $row->license_key    = function_exists( 'rup_gateway_fc_provider_get_license_key' ) ? rup_gateway_fc_provider_get_license_key( absint( $row->license_id ) ) : get_post_meta( absint( $row->license_id ), 'license_key', true );
            $row->license_status = get_post_meta( absint( $row->license_id ), 'status', true );
            $row->license_expiry = function_exists( 'rup_gateway_fc_provider_get_license_expiry' ) ? rup_gateway_fc_provider_get_license_expiry( absint( $row->license_id ) ) : get_post_meta( absint( $row->license_id ), 'expiry_date', true );
            $row->download_title = $row->download_id ? get_the_title( absint( $row->download_id ) ) : '';
            $row->product_title  = $row->fluent_product_id ? get_the_title( absint( $row->fluent_product_id ) ) : '';
        }

        return $rows;
    }

    /**
     * Debug-only manual renewal simulator. Uses the stored FluentCart -> Hoster link row.
     */
    public function rup_gateway_fc_simulate_renewal() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        check_admin_referer( 'rup_gateway_fc_simulate_renewal_nonce' );

        $options = rup_gateway_fc_get_options();
        if ( empty( $options['debug_enabled'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings&message=simulator_requires_debug' ) );
            exit;
        }

        global $wpdb;
        $link_id = isset( $_POST['license_link_id'] ) ? absint( $_POST['license_link_id'] ) : 0;
        $days_to_add = isset( $_POST['days_to_add'] ) ? max( 1, absint( $_POST['days_to_add'] ) ) : 1;
        $links_table = $wpdb->prefix . 'rup_gateway_fc_license_links';
        $link = $link_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$links_table} WHERE id = %d", $link_id ) ) : null;

        if ( ! $link || empty( $link->license_id ) ) {
            wp_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings&message=simulator_link_not_found' ) );
            exit;
        }

        $mapping = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", absint( $link->mapping_id ) ) );
        if ( ! $mapping ) {
            wp_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings&message=simulator_mapping_not_found' ) );
            exit;
        }

        $current_expiry = function_exists( 'rup_gateway_fc_provider_get_license_expiry' ) ? rup_gateway_fc_provider_get_license_expiry( absint( $link->license_id ) ) : get_post_meta( absint( $link->license_id ), 'expiry_date', true );
        $base = $current_expiry ? $current_expiry : current_time( 'Y-m-d' );
        try {
            $date = new DateTime( $base );
            $date->add( new DateInterval( 'P' . $days_to_add . 'D' ) );
            $new_expiry = $date->format( 'Y-m-d' );
        } catch ( Exception $e ) {
            $new_expiry = date( 'Y-m-d', strtotime( '+' . $days_to_add . ' days', current_time( 'timestamp' ) ) );
        }

        rup_gateway_fc_debug_log( array(
            'manual_subscription_renewal_simulation_requested' => array(
                'link_id'        => $link_id,
                'license_id'     => absint( $link->license_id ),
                'order_id'       => absint( $link->order_id ),
                'order_item_id'  => absint( $link->order_item_id ),
                'subscription_id'=> absint( $link->subscription_id ),
                'mapping_id'     => absint( $link->mapping_id ),
                'download_id'    => absint( $link->download_id ),
                'customer_email' => $link->customer_email,
                'current_expiry' => $current_expiry,
                'days_to_add'    => $days_to_add,
                'new_expiry'     => $new_expiry,
            ),
        ) );

        rup_gateway_fc_update_license_by_id(
            absint( $link->license_id ),
            $mapping,
            $new_expiry,
            'manual_subscription_renewal_simulation',
            array(
                'link_id'        => $link_id,
                'order_id'       => absint( $link->order_id ),
                'order_item_id'  => absint( $link->order_item_id ),
                'subscription_id'=> absint( $link->subscription_id ),
                'customer_email' => $link->customer_email,
                'days_to_add'    => $days_to_add,
            )
        );

        $wpdb->update( $links_table, array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $link_id ), array( '%s' ), array( '%d' ) );

        wp_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings&message=simulated_renewal' ) );
        exit;
    }


    /**
     * Saves FluentCart Gateway settings.
     */
    public function rup_gateway_fc_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        check_admin_referer( 'rup_gateway_fc_save_settings_nonce' );
        $input = isset( $_POST['rup_gateway_fc_options'] ) ? wp_unslash( $_POST['rup_gateway_fc_options'] ) : array();
        update_option( 'rup_gateway_fc_options', rup_gateway_fc_sanitize_options( $input ) );
        wp_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings&message=settings_updated' ) );
        exit;
    }

    /**
     * Renders settings to replace wp-config constants.
     */
    public function rup_gateway_fc_render_settings() {
        $options = rup_gateway_fc_get_options();
        $download_choices = $this->rup_gateway_fc_get_download_choices();
        $fluent_product_choices = $this->rup_gateway_fc_get_fluent_product_choices();
        ?>
        <div class="wrap">
            <h1>FluentCart Licence Gateway Settings</h1>
            <?php if ( isset( $_GET['message'] ) ) : ?>
                <div class="updated notice"><p><?php echo esc_html( $_GET['message'] ); ?></p></div>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc' ) ); ?>">&laquo; Back to mappings</a></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'rup_gateway_fc_save_settings_nonce' ); ?>
                <input type="hidden" name="action" value="rup_gateway_fc_save_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gateway_provider_mode">Licence Provider</label></th>
                        <td>
                            <?php $provider_mode = isset( $options['gateway_provider_mode'] ) ? sanitize_key( $options['gateway_provider_mode'] ) : 'auto'; ?>
                            <select id="gateway_provider_mode" name="rup_gateway_fc_options[gateway_provider_mode]">
                                <option value="auto" <?php selected( $provider_mode, 'auto' ); ?>>Auto-detect</option>
                                <option value="hoster" <?php selected( $provider_mode, 'hoster' ); ?>>Force Hoster</option>
                                <option value="uupd" <?php selected( $provider_mode, 'uupd' ); ?>>Force UUPD Server</option>
                            </select>
                            <p class="description">Auto-detect prefers UUPD Server when available, otherwise Hoster. Current detected provider: <strong><?php echo esc_html( function_exists( 'rup_gateway_fc_license_provider_label' ) ? rup_gateway_fc_license_provider_label() : 'Hoster' ); ?></strong>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="debug_enabled">Enable Debug Logging</label></th>
                        <td><input type="checkbox" id="debug_enabled" name="rup_gateway_fc_options[debug_enabled]" value="1" <?php checked( 1, $options['debug_enabled'] ); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="debug_log_name">Debug Log Filename</label></th>
                        <td><input class="regular-text" id="debug_log_name" name="rup_gateway_fc_options[debug_log_name]" value="<?php echo esc_attr( $options['debug_log_name'] ); ?>"></td>
                    </tr>
                    <?php if ( function_exists( 'rup_gateway_fc_is_hoster_active_setup' ) && rup_gateway_fc_is_hoster_active_setup() ) : ?>
                    <tr>
                        <th scope="row"><label for="hoster_admin_improvements_enabled">Enable Hoster Admin Improvements</label></th>
                        <td>
                            <input type="checkbox" id="hoster_admin_improvements_enabled" name="rup_gateway_fc_options[hoster_admin_improvements_enabled]" value="1" <?php checked( 1, $options['hoster_admin_improvements_enabled'] ); ?>>
                            <p class="description">Adds copyable IDs and extra Gateway licence/admin columns when Hoster is the active gateway provider.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><label for="auto_inject_receipt_licenses">Auto-inject Licences into FluentCart Receipts</label></th>
                        <td>
                            <input type="checkbox" id="auto_inject_receipt_licenses" name="rup_gateway_fc_options[auto_inject_receipt_licenses]" value="1" <?php checked( 1, $options['auto_inject_receipt_licenses'] ); ?>>
                            <p class="description">Enabled by default. Appends a “Your Licence Keys” section to FluentCart receipt/email content when an order has linked licences. Turn this off once native FluentCart template editing is available and you are using <code>{{gateway_licenses}}</code> manually.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="add_licence_dashboard_tab">Add Licence Tab to FluentCart Dashboard</label></th>
                        <td>
                            <input type="checkbox" id="add_licence_dashboard_tab" name="rup_gateway_fc_options[add_licence_dashboard_tab]" value="1" <?php checked( 1, $options['add_licence_dashboard_tab'] ); ?>>
                            <p class="description">Enabled by default. Adds the <code>[gateway_licences]</code> output as a native FluentCart customer dashboard tab.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_only_with_licenses">Only Show Licence Tab When User Has Licences</label></th>
                        <td>
                            <input type="checkbox" id="licence_dashboard_only_with_licenses" name="rup_gateway_fc_options[licence_dashboard_only_with_licenses]" value="1" <?php checked( 1, $options['licence_dashboard_only_with_licenses'] ); ?>>
                            <p class="description">Enabled by default. Hides the FluentCart dashboard licence tab for customers who do not currently have Gateway licences.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_hide_expired_only">Hide Licence Tab When Only Expired Licences Exist</label></th>
                        <td>
                            <input type="checkbox" id="licence_dashboard_hide_expired_only" name="rup_gateway_fc_options[licence_dashboard_hide_expired_only]" value="1" <?php checked( 1, $options['licence_dashboard_hide_expired_only'] ); ?>>
                            <p class="description">When enabled, the dashboard tab is hidden if the customer only has expired, cancelled, refunded, or inactive licences.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_insert_after">Licence Tab Menu Position</label></th>
                        <td>
                            <?php
                            $menu_positions = rup_gateway_fc_get_cached_customer_menu_positions();
                            $current_insert_after = isset( $options['licence_dashboard_insert_after'] ) ? sanitize_key( $options['licence_dashboard_insert_after'] ) : 'downloads';
                            if ( ! isset( $menu_positions[ $current_insert_after ] ) && 'first' !== $current_insert_after ) {
                                $menu_positions[ $current_insert_after ] = rup_gateway_fc_menu_item_label( $current_insert_after, array() );
                            }
                            ?>
                            <select id="licence_dashboard_insert_after" name="rup_gateway_fc_options[licence_dashboard_insert_after]">
                                <option value="first" <?php selected( $current_insert_after, 'first' ); ?>>First item</option>
                                <?php foreach ( $menu_positions as $position_key => $position_label ) : ?>
                                    <option value="<?php echo esc_attr( $position_key ); ?>" <?php selected( $current_insert_after, $position_key ); ?>><?php echo esc_html( 'After ' . $position_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">This list is learned from FluentCart's live associative customer menu array, so menu items added by FluentCart or other plugins will appear here after the customer dashboard has loaded once. If the selected key no longer exists, the licences tab is appended to the end.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_endpoint">Licence Tab Endpoint</label></th>
                        <td>
                            <input class="regular-text" id="licence_dashboard_endpoint" name="rup_gateway_fc_options[licence_dashboard_endpoint]" value="<?php echo esc_attr( $options['licence_dashboard_endpoint'] ); ?>">
                            <p class="description">URL slug for the customer dashboard tab. Default: <code>licences</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_tab_name">Licence Tab Name</label></th>
                        <td><input class="regular-text" id="licence_dashboard_tab_name" name="rup_gateway_fc_options[licence_dashboard_tab_name]" value="<?php echo esc_attr( $options['licence_dashboard_tab_name'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_title">Licence Page Title</label></th>
                        <td><input class="regular-text" id="licence_dashboard_title" name="rup_gateway_fc_options[licence_dashboard_title]" value="<?php echo esc_attr( $options['licence_dashboard_title'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="licence_dashboard_icon_svg">Licence Tab SVG Icon</label></th>
                        <td>
                            <textarea class="large-text code" rows="5" id="licence_dashboard_icon_svg" name="rup_gateway_fc_options[licence_dashboard_icon_svg]"><?php echo esc_textarea( $options['licence_dashboard_icon_svg'] ); ?></textarea>
                            <p class="description">Paste an inline SVG. Unsafe tags/attributes are stripped on save. Leave blank to restore the default icon.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="primary_button_color">Primary Button Colour</label></th>
                        <td>
                            <input type="color" id="primary_button_color" name="rup_gateway_fc_options[primary_button_color]" value="<?php echo esc_attr( $options['primary_button_color'] ); ?>">
                            <p class="description">Overrides <code>.rup-gateway-fc-primary-button</code> and download button colour.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="secondary_button_color">Secondary Button Colour</label></th>
                        <td>
                            <input type="color" id="secondary_button_color" name="rup_gateway_fc_options[secondary_button_color]" value="<?php echo esc_attr( $options['secondary_button_color'] ); ?>">
                            <p class="description">Overrides <code>.rup-gateway-fc-secondary-button</code> and toggle button colour.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <?php if ( ! empty( $options['debug_enabled'] ) ) : ?>
                <hr>
                <h2>Debug: Simulate Subscription Renewal</h2>
                <p>Use this to test the Hoster update path against a specific stored FluentCart → Gateway licence link. It does not create a FluentCart payment; it only simulates the licence extension step.</p>
                <?php $linked_license_rows = $this->rup_gateway_fc_get_linked_license_rows_for_simulator(); ?>
                <?php if ( empty( $linked_license_rows ) ) : ?>
                    <p><em>No linked licences found yet. Create a test order first.</em></p>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'rup_gateway_fc_simulate_renewal_nonce' ); ?>
                        <input type="hidden" name="action" value="rup_gateway_fc_simulate_renewal">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="license_link_id">Linked Licence</label></th>
                                <td>
                                    <select id="license_link_id" name="license_link_id" class="regular-text" required>
                                        <?php foreach ( $linked_license_rows as $row ) : ?>
                                            <?php
                                            $label = sprintf(
                                                'Link #%d | Licence #%d %s | Order #%d Item #%d Sub #%d | %s | Download #%d %s | Product #%d Var #%d | Qty #%d | Expiry %s',
                                                absint( $row->id ),
                                                absint( $row->license_id ),
                                                $row->license_key ? '(' . $row->license_key . ')' : '',
                                                absint( $row->order_id ),
                                                absint( $row->order_item_id ),
                                                absint( $row->subscription_id ),
                                                $row->customer_email ? $row->customer_email : 'no email',
                                                absint( $row->download_id ),
                                                $row->download_title ? '- ' . $row->download_title : '',
                                                absint( $row->fluent_product_id ),
                                                absint( $row->fluent_variation_id ),
                                                absint( $row->quantity_index ),
                                                $row->license_expiry ? $row->license_expiry : 'lifetime/blank'
                                            );
                                            ?>
                                            <option value="<?php echo esc_attr( $row->id ); ?>"><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Rows come from the licence link table, so pick the exact order/item/subscription/licence you want to test.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="days_to_add">Days to Add</label></th>
                                <td>
                                    <input type="number" id="days_to_add" name="days_to_add" value="1" min="1" class="small-text">
                                    <p class="description">Adds this many days to the current licence expiry and sends the update through Hoster's <code>hoster_update_license</code> filter with reason <code>manual_subscription_renewal_simulation</code>.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Simulate Renewal Update', 'secondary', 'submit', false, array( 'onclick' => "return confirm('This will update the selected Gateway licence expiry. Continue?');" ) ); ?>
                    </form>
                <?php endif; ?>
            <?php else : ?>
                <hr>
                <h2>Debug: Simulate Subscription Renewal</h2>
                <p><em>Enable debug logging and save settings to show the renewal simulator.</em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Processes saving (add/update) a mapping.
     */
    public function rup_gateway_fc_save_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        check_admin_referer( 'rup_gateway_fc_save_mapping_nonce' );
        global $wpdb;

        $mapping_id = isset( $_POST['mapping_id'] ) ? intval( $_POST['mapping_id'] ) : 0;
        $friendly_name = sanitize_text_field( $_POST['friendly_name'] );
        $price_id = sanitize_text_field( $_POST['price_id'] );
        $download_id = intval( $_POST['download_id'] );
        $status = sanitize_text_field( $_POST['status'] );
        $enable_activation_limit = isset( $_POST['enable_activation_limit'] ) ? 1 : 0;
        $activation_limit = $enable_activation_limit ? intval( $_POST['activation_limit'] ) : 0;
        $lifetime = isset( $_POST['lifetime'] ) ? 1 : 0;
        $expiry_duration = $lifetime ? 0 : intval( $_POST['expiry_duration'] );
        $expiry_unit = $lifetime ? 'Days' : sanitize_text_field( $_POST['expiry_unit'] );

        $data = array(
            'friendly_name'           => $friendly_name,
            'price_id'                => $price_id,
            'download_id'             => $download_id,
            'status'                  => $status,
            'enable_activation_limit' => $enable_activation_limit,
            'activation_limit'        => $activation_limit,
            'lifetime'                => $lifetime,
            'expiry_duration'         => $expiry_duration,
            'expiry_unit'             => $expiry_unit,
        );

        if ( $mapping_id > 0 ) {
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array( 'id' => $mapping_id ),
                array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' ),
                array( '%d' )
            );
            $redirect_url = admin_url( 'admin.php?page=rup-gateway-fc&message=updated' );
        } else {
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' )
            );
            $redirect_url = admin_url( 'admin.php?page=rup-gateway-fc&message=added' );
        }

        if ( false === $result ) {
            wp_die( 'Database error' );
        }
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Processes deletion of a mapping.
     */
    public function rup_gateway_fc_delete_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        check_admin_referer( 'rup_gateway_fc_delete_mapping_nonce' );
        global $wpdb;

        $mapping_id = isset( $_GET['mapping_id'] ) ? intval( $_GET['mapping_id'] ) : 0;
        if ( $mapping_id > 0 ) {
            $mapping = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $mapping_id ), ARRAY_A );
            if ( $mapping ) {
                unset($mapping['id'], $mapping['created_at']);
                set_transient( "rup_gateway_fc_deleted_{$mapping_id}", $mapping, HOUR_IN_SECONDS );
            }
            $wpdb->delete( $this->table_name, array( 'id' => $mapping_id ), array( '%d' ) );
        }
        wp_redirect( admin_url( "admin.php?page=rup-gateway-fc&message=deleted&undo=1&mapping_id={$mapping_id}" ) );
        exit;
    }

    /**
     * Processes undo of a mapping deletion.
     */
    public function rup_gateway_fc_undo_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user' );
        }
        check_admin_referer( 'rup_gateway_fc_undo_mapping_nonce' );
        global $wpdb;
        $mapping_id = isset( $_GET['mapping_id'] ) ? intval( $_GET['mapping_id'] ) : 0;
        if ( $mapping_id > 0 ) {
            $mapping = get_transient( "rup_gateway_fc_deleted_{$mapping_id}" );
            if ( $mapping ) {
                $result = $wpdb->insert(
                    $this->table_name,
                    $mapping,
                    array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' )
                );
                delete_transient( "rup_gateway_fc_deleted_{$mapping_id}" );
                wp_redirect( admin_url( "admin.php?page=rup-gateway-fc&message=restored" ) );
                exit;
            } else {
                wp_redirect( admin_url( "admin.php?page=rup-gateway-fc&message=undo_failed" ) );
                exit;
            }
        }
    }

    /**
     * Renders the list of mappings.
     * 
     * Coloum Removed <td><?php echo esc_html( $mapping['id'] ); ?></td>
     */
    public function rup_gateway_fc_render_list() {
        global $wpdb;
        if ( isset( $_GET['message'] ) ) {
            echo '<div class="updated notice"><p>' . esc_html( $_GET['message'] ) . '</p></div>';
        }
        if ( isset( $_GET['undo'] ) && $_GET['undo'] == 1 && isset( $_GET['mapping_id'] ) ) {
            $undo_url = wp_nonce_url( admin_url( 'admin-post.php?action=rup_gateway_fc_undo_mapping&mapping_id=' . intval( $_GET['mapping_id'] ) ), 'rup_gateway_fc_undo_mapping_nonce' );
            echo "<div class='notice notice-info'><p>Mapping deleted. <a href='{$undo_url}'>Undo</a></p></div>";
        }
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $where_clause = '';
        $search_sql = array();
        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_sql[] = $wpdb->prepare( "friendly_name LIKE %s", $like );
            $search_sql[] = $wpdb->prepare( "price_id LIKE %s", $like );
            $search_sql[] = $wpdb->prepare( "CAST(download_id AS CHAR) LIKE %s", $like );
            $where_clause = 'WHERE ' . implode( ' OR ', $search_sql );
        }
        $per_page     = 10;
        $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset       = ( $current_page - 1 ) * $per_page;
        $total_items  = $wpdb->get_var( "SELECT COUNT(*) FROM $this->table_name $where_clause" );
        $mappings = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $this->table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ),
            ARRAY_A
        );
        $download_choices = $this->rup_gateway_fc_get_download_choices();
        $fluent_product_choices = $this->rup_gateway_fc_get_fluent_product_choices();
        ?>
        <div class="wrap">
            <h1>
                FluentCart Licence Gateway
                <a href="<?php echo admin_url( 'admin.php?page=rup-gateway-fc&action=add' ); ?>" class="page-title-action">Add New</a>
                <a href="<?php echo admin_url( 'admin.php?page=rup-gateway-fc&action=settings' ); ?>" class="page-title-action">Settings</a>
            </h1>
            <?php
            // Determine the base URL for the form.
            // If the Hoster plugin is active, your page is nested under Downloads.
            $base_url = is_plugin_active( 'hoster/hoster.php' ) ? admin_url( 'edit.php' ) : admin_url( 'admin.php' );
            ?>
            <form method="get" action="<?php echo $base_url; ?>">
                <!-- Always include the page slug -->
                <input type="hidden" name="page" value="rup-gateway-fc" />
                <?php if ( function_exists( 'rup_gateway_fc_get_license_provider' ) && 'hoster' === rup_gateway_fc_get_license_provider() ) : ?>
                    <input type="hidden" name="post_type" value="downloads" />
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="mapping-search-input">Search Mappings:</label>
                    <input id="mapping-search-input" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" />
                    <input type="submit" id="search-submit" class="button" value="Search Mappings" />
                </p>
            </form>


            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:20%;">Friendly Name</th>
                        <th style="width:22%;">FluentCart Product / Variation</th>
                        <th>Licence Product</th>
                        <th>Status</th>
                        <th style="width:10%;">Activation Limit Enabled</th>
                        <th>Activation Limit</th>
                        <th>Lifetime</th>
                        <th>Expiry Duration</th>
                        <th>Expiry Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $mappings ) : ?>
                        <?php foreach ( $mappings as $mapping ) : ?>
                            <tr>
                                <td><?php echo esc_html( $mapping['friendly_name'] ); ?></td>
                                <td><?php echo esc_html( $this->rup_gateway_fc_get_fluent_product_label( $mapping['price_id'] ) ); ?><br><code><?php echo esc_html( $mapping['price_id'] ); ?></code></td>
                                <td><?php echo esc_html( $this->rup_gateway_fc_get_download_label( $mapping['download_id'] ) ); ?><br><code><?php echo esc_html( $mapping['download_id'] ); ?></code></td>
                                <td><?php echo esc_html( $mapping['status'] ); ?></td>
                                <td><?php echo $mapping['enable_activation_limit'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html( $mapping['activation_limit'] ); ?></td>
                                <td><?php echo $mapping['lifetime'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html( $mapping['expiry_duration'] ); ?></td>
                                <td><?php echo esc_html( $mapping['expiry_unit'] ); ?></td>
                                <td>
                                    <a href="<?php echo admin_url( 'admin.php?page=rup-gateway-fc&action=edit&mapping_id=' . intval( $mapping['id'] ) ); ?>">Edit</a> |
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=rup_gateway_fc_delete_mapping&mapping_id=' . intval( $mapping['id'] ) ), 'rup_gateway_fc_delete_mapping_nonce' ); ?>" onclick="return confirmDelete();">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="11">No mappings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $total_pages = ceil( $total_items / $per_page );
            if ( $total_pages > 1 ) {
                $page_links = paginate_links( array(
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total'     => $total_pages,
                    'current'   => $current_page,
                ) );
                echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0;">' . $page_links . '</div></div>';
            }
            ?>
            <script>
                function confirmDelete() {
                    var confirmation = prompt("Type DELETE to confirm deletion of this mapping. This action cannot be undone.");
                    console.log("confirmDelete() input: " + confirmation);
                    return (confirmation === "DELETE");
                }
            </script>
        </div>
        <?php
    }
}

new Rup_Gateway_FC_Gateway();

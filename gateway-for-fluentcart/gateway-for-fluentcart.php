<?php
/*
* Plugin Name:       Licence Gateway For FluentCart
* Plugin URI:        https://reallyusefulplugins.com/plugins/hoster-api
* Description:       Adds FluentCart Intergration to UUPD Server & Hoster including customer portal, outbound emails for Licence Sales.
* Version:           1.0.0
* Author:            ReallyUsefulPlugins.com
* Author URI: https://Reallyusefulplugins.com
* Text Domain:       gateway-for-fluentcart
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        'license_history_enabled'          => 0,
        'gateway_provider_mode'            => 'auto',
        'failure_alert_email'              => get_option( 'admin_email', '' ),
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
            'allow_rotate'     => isset( $mapping->uupd_allow_rotate ) && -1 !== (int) $mapping->uupd_allow_rotate ? (string) $mapping->uupd_allow_rotate : null,
            'allow_children'   => isset( $mapping->uupd_allow_children ) && -1 !== (int) $mapping->uupd_allow_children ? (string) $mapping->uupd_allow_children : null,
            'max_children'     => isset( $mapping->uupd_max_children ) && -1 !== (int) $mapping->uupd_max_children ? (int) $mapping->uupd_max_children : null,
        ) );

        $response = rup_gateway_fc_normalize_provider_response( $response );

        $license_id = 0;
        if ( is_array( $response ) && ! empty( $response['license_id'] ) ) {
            $license_id = absint( $response['license_id'] );
        }

        if ( $license_id && ! empty( $context['subscription_id'] ) ) {
            update_post_meta( $license_id, 'subscription_id', absint( $context['subscription_id'] ) );
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

    if ( 'uupd' === $provider ) {
        $args['meta_query'][] = array(
            'key' => 'uupd_parent_license_id',
            'compare' => 'NOT EXISTS',
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

    // Hoster 1.4 can store packages privately with no download_url meta. Its
    // button endpoint checks the current customer's licence on each click.
    if ( function_exists( 'hoster_download_button_url' ) ) {
        return esc_url_raw( hoster_download_button_url( $product_id ) );
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
    $hoster_admin_improvements_enabled = isset( $input['hoster_admin_improvements_enabled'] ) ? 1 : 0;

    return array(
        'debug_enabled'                    => isset( $input['debug_enabled'] ) ? 1 : 0,
        'license_history_enabled'          => isset( $input['license_history_enabled'] ) ? 1 : 0,
        'gateway_provider_mode'            => $gateway_provider_mode,
        'failure_alert_email'              => sanitize_email( $input['failure_alert_email'] ?? '' ),
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

// Activation hooks do not run during an ordinary plugin update.
if ( ! get_option( 'rup_gateway_fc_debug_log_state' ) ) {
    rup_gateway_fc_get_debug_log_path();
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
        add_action( 'admin_enqueue_scripts', array( $this, 'rup_gateway_fc_enqueue_admin_styles' ) );
        add_action( 'admin_footer', array( $this, 'enqueue_fluentcart_license_panel' ) );
        add_action( 'wp_ajax_rup_gateway_fc_admin_license_links', array( $this, 'admin_license_links' ) );
        add_action( 'add_meta_boxes', array( $this, 'register_hoster_history_box' ) );
        add_filter( 'manage_edit-uupd_license_columns', array( $this, 'add_uupd_subscription_column' ), 20 );
        add_action( 'manage_uupd_license_posts_custom_column', array( $this, 'render_uupd_subscription_column' ), 20, 2 );
        add_action( 'restrict_manage_posts', array( $this, 'render_uupd_subscription_filter' ), 20, 2 );
        add_action( 'pre_get_posts', array( $this, 'prepare_uupd_subscription_filter' ) );
        add_filter( 'posts_where', array( $this, 'apply_uupd_subscription_filter' ), 10, 2 );
        add_filter( 'uupd_license_order_reference_html', array( $this, 'link_uupd_order_reference' ), 10, 3 );
        add_action( 'add_meta_boxes_uupd_license', array( $this, 'register_uupd_purchase_box' ) );
        add_action( 'admin_post_rup_gateway_fc_save_mapping', array( $this, 'rup_gateway_fc_save_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_delete_mapping', array( $this, 'rup_gateway_fc_delete_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_undo_mapping', array( $this, 'rup_gateway_fc_undo_mapping' ) );
        add_action( 'admin_post_rup_gateway_fc_save_settings', array( $this, 'rup_gateway_fc_save_settings' ) );
        add_action( 'admin_post_rup_gateway_fc_view_debug_log', array( $this, 'rup_gateway_fc_view_debug_log' ) );
        add_action( 'admin_post_rup_gateway_fc_test_debug_log', array( $this, 'rup_gateway_fc_test_debug_log' ) );
        add_action( 'admin_post_rup_gateway_fc_simulate_renewal', array( $this, 'rup_gateway_fc_simulate_renewal' ) );
        add_action( 'init', array( $this, 'rup_gateway_fc_register_customer_dashboard_tab' ), 20 );
        add_filter( 'fluent_cart/global_customer_menu_items', array( $this, 'rup_gateway_fc_reorder_customer_dashboard_tab' ), 20 );
    }


    /**
     * Creates the custom database table to store mapping records.
     */
    public function rup_gateway_fc_install() {
        rup_gateway_fc_get_debug_log_path();
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
            uupd_allow_rotate TINYINT(1) NOT NULL DEFAULT -1,
            uupd_allow_children TINYINT(1) NOT NULL DEFAULT -1,
            uupd_max_children SMALLINT NOT NULL DEFAULT -1,
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
        foreach ( array( 'uupd_allow_rotate' => -1, 'uupd_allow_children' => -1 ) as $name => $default ) {
            if ( ! $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE '" . esc_sql( $name ) . "'" ) ) {
                $wpdb->query( "ALTER TABLE $table ADD $name TINYINT(1) NOT NULL DEFAULT $default" );
            }
        }
        if ( ! $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE 'uupd_max_children'" ) ) {
            $wpdb->query( "ALTER TABLE $table ADD uupd_max_children SMALLINT NOT NULL DEFAULT -1" );
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

    public function rup_gateway_fc_enqueue_admin_styles() {
        if ( ! isset( $_GET['page'] ) || 'rup-gateway-fc' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
        wp_enqueue_style( 'rup-gateway-fc-admin', plugin_dir_url( __FILE__ ) . 'css/admin-mappings.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'css/admin-mappings.css' ) );
    }

    /** Load after FluentCart has rendered its admin wrapper. */
    public function enqueue_fluentcart_license_panel() {
        if ( ! isset( $_GET['page'] ) || 'fluent-cart' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $config = array( 'url' => admin_url( 'admin-ajax.php' ), 'adminPage' => admin_url( 'admin.php?page=fluent-cart' ), 'nonce' => wp_create_nonce( 'rup_gateway_fc_admin_links' ) );
        $src = add_query_arg( 'ver', filemtime( plugin_dir_path( __FILE__ ) . 'js/admin-license-links.js' ), plugin_dir_url( __FILE__ ) . 'js/admin-license-links.js' );
        echo '<script>window.rupGatewayAdminLinks = ' . wp_json_encode( $config ) . ';</script>';
        echo '<script src="' . esc_url( $src ) . '"></script>';
    }

    public function admin_license_links() {
        check_ajax_referer( 'rup_gateway_fc_admin_links', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        }
        $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! $id || ! in_array( $type, array( 'order', 'subscription' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid view' ), 400 );
        }
        $rows = rup_gateway_fc_get_license_links( array( $type . '_id' => $id ) );
        $items = array();
        foreach ( $rows as $row ) {
            $license_id = absint( $row->license_id );
            $post = get_post( $license_id );
            if ( ! $post || ! in_array( $post->post_type, array( 'uupd_license', 'hoster_license' ), true ) ) {
                continue;
            }
            $items[] = array(
                'id' => $license_id,
                'key' => (string) get_post_meta( $license_id, 'license_key', true ),
                'status' => (string) get_post_meta( $license_id, 'status', true ),
                'orderId' => absint( $row->order_id ),
                'subscriptionId' => absint( $row->subscription_id ),
                'url' => get_edit_post_link( $license_id, 'raw' ),
            );
        }
        wp_send_json_success( $items );
    }

    public function register_hoster_history_box() {
        if ( 'hoster' !== rup_gateway_fc_get_license_provider() ) {
            return;
        }
        add_meta_box( 'rup_gateway_fc_history', 'Licence interactions', array( $this, 'render_hoster_history_box' ), 'hoster_license', 'normal' );
    }

    public function render_hoster_history_box( $post ) {
        if ( empty( rup_gateway_fc_get_options()['license_history_enabled'] ) ) {
            echo '<p>Enable licence interaction history in the Gateway settings to record events.</p>';
            return;
        }
        $history = get_post_meta( $post->ID, '_uupd_interaction_history', true );
        $history = is_array( $history ) ? array_reverse( $history ) : array();
        echo '<details><summary>View logs</summary>';
        if ( ! $history ) {
            echo '<p>No interactions recorded yet.</p>';
        } else {
            echo '<ol>';
            foreach ( $history as $entry ) {
                echo '<li><strong>' . esc_html( $entry['at'] ?? '' ) . ' — ' . esc_html( $entry['event'] ?? '' ) . '</strong>: ' . esc_html( wp_json_encode( $entry['details'] ?? array() ) ) . '</li>';
            }
            echo '</ol>';
        }
        echo '</details>';
    }

    /** FluentCart-specific UUPD admin extensions are owned by the Gateway. */
    private function uupd_admin_integration_active() {
        return 'uupd' === rup_gateway_fc_get_license_provider() && function_exists( 'uupd_create_license' ) && post_type_exists( 'uupd_license' );
    }

    private function get_uupd_gateway_link( $license_id ) {
        static $cache = array();
        static $table_exists = null;
        $license_id = absint( $license_id );
        if ( array_key_exists( $license_id, $cache ) ) {
            return $cache[ $license_id ];
        }
        $cache[ $license_id ] = null;
        if ( ! $license_id || ! $this->uupd_admin_integration_active() ) {
            return null;
        }
        global $wpdb;
        $table = rup_gateway_fc_get_license_links_table();
        if ( null === $table_exists ) {
            $table_exists = rup_gateway_fc_table_exists( $table );
        }
        if ( ! $table_exists ) {
            return null;
        }
        $cache[ $license_id ] = $wpdb->get_row( $wpdb->prepare( "SELECT order_id, subscription_id FROM {$table} WHERE license_id = %d ORDER BY (subscription_id > 0) DESC, id DESC LIMIT 1", $license_id ) );
        return $cache[ $license_id ];
    }

    private function fluentcart_admin_reference_html( $type, $id ) {
        $id = absint( $id );
        $path = 'order' === $type ? 'orders' : 'subscriptions';
        $label = 'order' === $type ? __( 'View FluentCart order', 'gateway-for-fluentcart' ) : __( 'View FluentCart subscription', 'gateway-for-fluentcart' );
        $url = admin_url( 'admin.php?page=fluent-cart' ) . '#/' . $path . '/' . $id . '/view';
        return sprintf( '<a href="%s" aria-label="%s">%s <span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:middle"></span></a>', esc_url( $url ), esc_attr( $label ), esc_html( $id ) );
    }

    public function add_uupd_subscription_column( $columns ) {
        if ( ! $this->uupd_admin_integration_active() ) {
            return $columns;
        }
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'uupd_order_id' === $key ) {
                $result['rup_gateway_fc_subscription_id'] = __( 'Subscription', 'gateway-for-fluentcart' );
            }
        }
        return $result;
    }

    public function render_uupd_subscription_filter( $post_type, $which = 'top' ) {
        if ( 'uupd_license' !== $post_type || 'top' !== $which || ! $this->uupd_admin_integration_active() ) {
            return;
        }
        $id = isset( $_GET['rup_gateway_fc_subscription'] ) ? absint( $_GET['rup_gateway_fc_subscription'] ) : 0;
        printf( '<label class="screen-reader-text" for="rup-gateway-fc-subscription-filter">%s</label><input id="rup-gateway-fc-subscription-filter" type="number" min="1" name="rup_gateway_fc_subscription" value="%s" placeholder="%s">', esc_html__( 'Subscription ID', 'gateway-for-fluentcart' ), $id ? esc_attr( $id ) : '', esc_attr__( 'Subscription ID', 'gateway-for-fluentcart' ) );
    }

    public function prepare_uupd_subscription_filter( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'uupd_license' !== $query->get( 'post_type' ) || ! $this->uupd_admin_integration_active() ) {
            return;
        }
        $id = isset( $_GET['rup_gateway_fc_subscription'] ) ? absint( $_GET['rup_gateway_fc_subscription'] ) : 0;
        if ( $id ) {
            $query->set( 'rup_gateway_fc_subscription_filter', $id );
        }
    }

    public function apply_uupd_subscription_filter( $where, $query ) {
        $id = absint( $query->get( 'rup_gateway_fc_subscription_filter' ) );
        if ( ! $id || ! is_admin() || ! $query->is_main_query() || 'uupd_license' !== $query->get( 'post_type' ) || ! $this->uupd_admin_integration_active() ) {
            return $where;
        }
        global $wpdb;
        $matches = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS rup_sub_meta WHERE rup_sub_meta.post_id = {$wpdb->posts}.ID AND rup_sub_meta.meta_key = 'subscription_id' AND rup_sub_meta.meta_value = %d)", $id );
        $table = rup_gateway_fc_get_license_links_table();
        if ( rup_gateway_fc_table_exists( $table ) ) {
            $matches .= $wpdb->prepare( " OR EXISTS (SELECT 1 FROM {$table} AS rup_sub_link WHERE rup_sub_link.license_id = {$wpdb->posts}.ID AND rup_sub_link.subscription_id = %d)", $id );
        }
        return $where . ' AND (' . $matches . ')';
    }

    public function render_uupd_subscription_column( $column, $license_id ) {
        if ( 'rup_gateway_fc_subscription_id' !== $column || ! $this->uupd_admin_integration_active() ) {
            return;
        }
        $link = $this->get_uupd_gateway_link( $license_id );
        $subscription_id = absint( get_post_meta( $license_id, 'subscription_id', true ) );
        if ( ! $subscription_id && $link ) {
            $subscription_id = absint( $link->subscription_id );
        }
        if ( ! $subscription_id ) {
            echo '&mdash;';
        } elseif ( $link && $subscription_id === absint( $link->subscription_id ) && current_user_can( 'manage_options' ) ) {
            echo wp_kses_post( $this->fluentcart_admin_reference_html( 'subscription', $subscription_id ) );
        } else {
            echo esc_html( $subscription_id );
        }
    }

    public function link_uupd_order_reference( $html, $license_id, $order_id ) {
        if ( ! $this->uupd_admin_integration_active() || ! current_user_can( 'manage_options' ) || ! ctype_digit( (string) $order_id ) ) {
            return $html;
        }
        $link = $this->get_uupd_gateway_link( $license_id );
        return $link && absint( $order_id ) === absint( $link->order_id ) ? $this->fluentcart_admin_reference_html( 'order', $link->order_id ) : $html;
    }

    public function register_uupd_purchase_box() {
        if ( $this->uupd_admin_integration_active() ) {
            add_meta_box( 'rup_gateway_fc_uupd_purchase', __( 'FluentCart purchase', 'gateway-for-fluentcart' ), array( $this, 'render_uupd_purchase_box' ), 'uupd_license', 'side' );
        }
    }

    public function render_uupd_purchase_box( $post ) {
        $link = $this->get_uupd_gateway_link( $post->ID );
        if ( ! $link ) {
            echo '<p>' . esc_html__( 'No Gateway purchase link found.', 'gateway-for-fluentcart' ) . '</p>';
            return;
        }
        echo '<p><strong>' . esc_html__( 'Order', 'gateway-for-fluentcart' ) . '</strong><br>' . wp_kses_post( $this->fluentcart_admin_reference_html( 'order', $link->order_id ) ) . '</p>';
        if ( ! empty( $link->subscription_id ) ) {
            echo '<p><strong>' . esc_html__( 'Subscription', 'gateway-for-fluentcart' ) . '</strong><br>' . wp_kses_post( $this->fluentcart_admin_reference_html( 'subscription', $link->subscription_id ) ) . '</p>';
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
            'uupd_allow_rotate'       => -1,
            'uupd_allow_children'     => -1,
            'uupd_max_children'       => -1,
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
        <div class="wrap rup-map-admin rup-map-editor">
            <div class="rup-map-page-heading"><div><span class="rup-map-eyebrow">FluentCart Licence Gateway</span><h1><?php echo $is_edit ? 'Edit mapping' : 'Add mapping'; ?></h1><p>Connect a FluentCart purchase to a licence product and choose how new licences are issued.</p></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc' ) ); ?>">Back to mappings</a></div>
            <form class="rup-map-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'rup_gateway_fc_save_mapping_nonce' ); ?>
                <input type="hidden" name="action" value="rup_gateway_fc_save_mapping">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="mapping_id" value="<?php echo esc_attr( $mapping['id'] ); ?>">
                <?php endif; ?>
                <section class="rup-map-panel"><div class="rup-map-panel__heading"><span class="rup-map-eyebrow">01 · Product link</span><h2>What purchase issues which licence?</h2><p>Choose the FluentCart product or variation and the matching licence product.</p></div><table class="form-table">
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
                            <p class="description">Start typing a product or variation name, then select its ID from the suggestions.</p>
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
                            <p class="description">Start typing to find the matching <?php echo ( function_exists( 'rup_gateway_fc_get_license_provider' ) && 'uupd' === rup_gateway_fc_get_license_provider() ) ? 'UUPD update' : 'Hoster download'; ?>.</p>
                            <?php if ( ! empty( $mapping['download_id'] ) ) : ?>
                                <p class="description">Current selection: <?php echo esc_html( $this->rup_gateway_fc_get_download_label( $mapping['download_id'] ) ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table></section>
                <section class="rup-map-panel"><div class="rup-map-panel__heading"><span class="rup-map-eyebrow">02 · Licence terms</span><h2>Licence issued to the customer</h2><p>Set its initial status, activation allowance and expiry.</p></div><table class="form-table">
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
                </table></section>
                <?php if ( function_exists( 'rup_gateway_fc_get_license_provider' ) && 'uupd' === rup_gateway_fc_get_license_provider() && function_exists( 'uupd_create_license' ) ) : ?>
                <section class="rup-map-panel rup-map-uupd"><div class="rup-map-panel__heading"><span class="rup-map-eyebrow">03 · UUPD customer controls</span><h2>Key and child licence permissions</h2><p>These settings apply to new UUPD licences. Administrators can manage keys regardless of customer permissions.</p></div><table class="form-table">
                    <tr><th scope="row"><label for="uupd_allow_rotate">Customer key rotation</label></th><td><select name="uupd_allow_rotate" id="uupd_allow_rotate"><option value="-1" <?php selected( (int) $mapping['uupd_allow_rotate'], -1 ); ?>>Use UUPD default</option><option value="1" <?php selected( (int) $mapping['uupd_allow_rotate'], 1 ); ?>>Allow</option><option value="0" <?php selected( (int) $mapping['uupd_allow_rotate'], 0 ); ?>>Disallow</option></select><p class="description">Controls whether customers see Rotate key. Store administrators can still rotate keys.</p></td></tr>
                    <tr><th scope="row"><label for="uupd_allow_children">Customer child keys</label></th><td><select name="uupd_allow_children" id="uupd_allow_children"><option value="-1" <?php selected( (int) $mapping['uupd_allow_children'], -1 ); ?>>Use UUPD default</option><option value="1" <?php selected( (int) $mapping['uupd_allow_children'], 1 ); ?>>Allow</option><option value="0" <?php selected( (int) $mapping['uupd_allow_children'], 0 ); ?>>Disallow</option></select><p class="description">Controls customer creation. Existing children of an issued licence are managed on that licence.</p></td></tr>
                    <tr><th scope="row"><label for="uupd_max_children">Maximum child keys</label></th><td><input type="number" name="uupd_max_children" id="uupd_max_children" min="0" step="1" value="<?php echo esc_attr( (int) $mapping['uupd_max_children'] < 0 ? '' : (int) $mapping['uupd_max_children'] ); ?>" class="small-text"><p class="description">Leave blank to use the UUPD site default. A finite licence cannot have a child count higher than its activation limit.</p><label id="uupd-unlimited-children-wrap" class="rup-map-check"><input type="checkbox" name="uupd_unlimited_children" id="uupd_unlimited_children" value="1" <?php checked( (int) $mapping['uupd_max_children'], -2 ); ?>> Unlimited child keys when activations are unlimited</label><p class="description">Revoked child keys count until deleted.</p></td></tr>
                </table></section>
                <?php endif; ?>
                <div class="rup-map-form__actions"><?php submit_button( $is_edit ? 'Update mapping' : 'Add mapping' ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc' ) ); ?>">Cancel</a></div>
            </form>
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
                var childMaximumInput = document.getElementById('uupd_max_children');
                var unlimitedChildrenWrap = document.getElementById('uupd-unlimited-children-wrap');
                var unlimitedChildrenInput = document.getElementById('uupd_unlimited_children');
                function syncChildMaximum() {
                    if (!childMaximumInput) return;
                    if (activationCheckbox.checked) {
                        childMaximumInput.max = activationLimitInput.value || '';
                        if (unlimitedChildrenInput) { unlimitedChildrenInput.checked = false; unlimitedChildrenWrap.style.display = 'none'; }
                    } else {
                        childMaximumInput.removeAttribute('max');
                        if (unlimitedChildrenWrap) unlimitedChildrenWrap.style.display = '';
                    }
                    childMaximumInput.disabled = !!(unlimitedChildrenInput && unlimitedChildrenInput.checked);
                }
                if (unlimitedChildrenInput) unlimitedChildrenInput.addEventListener('change', syncChildMaximum);
                activationLimitInput.addEventListener('input', syncChildMaximum);
                syncChildMaximum();

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
                    syncChildMaximum();
                });

                // Confirm deletion with a type-to-delete prompt.
                function confirmDelete() {
                    var confirmation = prompt("Type DELETE to confirm deletion of this mapping. This action cannot be undone.");
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

    /** Serve the private log only to WordPress administrators. */
    public function rup_gateway_fc_view_debug_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'rup_gateway_fc_view_debug_log' );
        $path = rup_gateway_fc_get_debug_log_path();
        if ( ! $path || ! is_file( $path ) || ! is_readable( $path ) ) {
            wp_die( 'Gateway debug log is unavailable.', '', array( 'response' => 404 ) );
        }
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $path );
        exit;
    }

    /** Let an administrator verify that the configured log accepts writes. */
    public function rup_gateway_fc_test_debug_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized user', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'rup_gateway_fc_test_debug_log' );
        if ( ! empty( rup_gateway_fc_get_options()['debug_enabled'] ) ) {
            rup_gateway_fc_debug_log( 'Administrator requested a test log entry.' );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=rup-gateway-fc&action=settings' ) );
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
                    <tr><th scope="row"><label for="license_history_enabled">Hoster licence interaction history</label></th><td><label><input type="checkbox" id="license_history_enabled" name="rup_gateway_fc_options[license_history_enabled]" value="1" <?php checked( 1, $options['license_history_enabled'] ); ?>> Keep the latest 20 Gateway events per Hoster licence</label><p class="description">Records issuance, renewals and expiry changes. UUPD licences use the separate history setting in UUPD Server.</p></td></tr>
                    <tr>
                        <th scope="row">Debug Log</th>
                        <td>
                            <?php $debug_log_path = rup_gateway_fc_get_debug_log_path(); ?>
                            <code><?php echo esc_html( $debug_log_path ?: 'Unavailable: the private log directory or file is not writable.' ); ?></code>
                            <?php if ( $debug_log_path && is_readable( $debug_log_path ) ) : ?>
                                <p><a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rup_gateway_fc_view_debug_log' ), 'rup_gateway_fc_view_debug_log' ) ); ?>">View log</a> <span class="description"><?php echo esc_html( size_format( filesize( $debug_log_path ) ) ); ?></span></p>
                            <?php endif; ?>
                            <?php if ( ! empty( $options['debug_enabled'] ) ) : ?>
                                <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rup_gateway_fc_test_debug_log' ), 'rup_gateway_fc_test_debug_log' ) ); ?>">Write test entry</a></p>
                            <?php endif; ?>
                            <p class="description">This is a server path outside the public web root, so it cannot be opened as a website URL. The log rotates every 7 days or at 5 MB.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="failure_alert_email">Gateway Failure Alert Email</label></th>
                        <td><input type="email" class="regular-text" id="failure_alert_email" name="rup_gateway_fc_options[failure_alert_email]" value="<?php echo esc_attr( $options['failure_alert_email'] ); ?>"><p class="description">Receives at most one alert per issue each day. Leave empty to disable email alerts; failures are still recorded in the debug log when enabled.</p></td>
                    </tr>
                    <?php if ( rup_gateway_fc_is_hoster_available() ) : ?>
                    <tr>
                        <th scope="row"><label for="hoster_admin_improvements_enabled">Enable Hoster Admin Improvements</label></th>
                        <td>
                            <input type="checkbox" id="hoster_admin_improvements_enabled" name="rup_gateway_fc_options[hoster_admin_improvements_enabled]" value="1" <?php checked( 1, $options['hoster_admin_improvements_enabled'] ); ?>>
                            <p class="description">Adds copyable IDs, purchase and subscription links, a live subscription column, and order, subscription, and expiry filters to Hoster licence admin. This works regardless of which provider Gateway uses for new licences. User and download filters remain supplied by Hoster.</p>
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
        $uupd_active = function_exists( 'rup_gateway_fc_get_license_provider' ) && 'uupd' === rup_gateway_fc_get_license_provider() && function_exists( 'uupd_create_license' );

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
        $formats = array( '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s' );
        if ( $uupd_active ) {
            $data['uupd_allow_rotate'] = isset( $_POST['uupd_allow_rotate'] ) && in_array( (int) $_POST['uupd_allow_rotate'], array( -1, 0, 1 ), true ) ? (int) $_POST['uupd_allow_rotate'] : -1;
            $data['uupd_allow_children'] = isset( $_POST['uupd_allow_children'] ) && in_array( (int) $_POST['uupd_allow_children'], array( -1, 0, 1 ), true ) ? (int) $_POST['uupd_allow_children'] : -1;
            if ( ! $enable_activation_limit && isset( $_POST['uupd_unlimited_children'] ) ) {
                $data['uupd_max_children'] = -2;
            } elseif ( isset( $_POST['uupd_max_children'] ) && '' !== trim( (string) wp_unslash( $_POST['uupd_max_children'] ) ) ) {
                $requested_children = min( 32767, absint( wp_unslash( $_POST['uupd_max_children'] ) ) );
                $data['uupd_max_children'] = $enable_activation_limit ? min( $requested_children, max( 1, $activation_limit ) ) : $requested_children;
            } else {
                $data['uupd_max_children'] = -1;
            }
            $formats[] = '%d';
            $formats[] = '%d';
            $formats[] = '%d';
        }

        if ( $mapping_id > 0 ) {
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array( 'id' => $mapping_id ),
                $formats,
                array( '%d' )
            );
            $redirect_url = admin_url( 'admin.php?page=rup-gateway-fc&message=updated' );
        } else {
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                $formats
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
        <div class="wrap rup-map-admin rup-map-list">
            <div class="rup-map-page-heading"><div><span class="rup-map-eyebrow">FluentCart Licence Gateway</span><h1>Product mappings</h1><p>Choose which licence each FluentCart purchase creates.</p></div><div class="rup-map-page-actions"><a href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc&action=add' ) ); ?>" class="button button-primary">Add mapping</a><a href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc&action=settings' ) ); ?>" class="button">Settings</a></div></div>
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


            <div class="rup-map-panel rup-map-list-panel"><div class="rup-map-panel__heading"><span class="rup-map-eyebrow">Active provider</span><h2><?php echo esc_html( function_exists( 'rup_gateway_fc_get_license_provider' ) && 'uupd' === rup_gateway_fc_get_license_provider() ? 'UUPD Server' : 'Hoster' ); ?> mappings</h2><p>Mapping options apply when a new licence is issued.</p></div><div class="rup-map-table-wrap"><table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:20%;">Mapping</th>
                        <th style="width:25%;">FluentCart purchase</th>
                        <th style="width:25%;">Licence product</th>
                        <th style="width:20%;">Issued licence</th>
                        <th style="width:10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( $mappings ) : ?>
                        <?php foreach ( $mappings as $mapping ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $mapping['friendly_name'] ); ?></strong></td>
                                <td><?php echo esc_html( $this->rup_gateway_fc_get_fluent_product_label( $mapping['price_id'] ) ); ?><br><code><?php echo esc_html( $mapping['price_id'] ); ?></code></td>
                                <td><?php echo esc_html( $this->rup_gateway_fc_get_download_label( $mapping['download_id'] ) ); ?><br><code><?php echo esc_html( $mapping['download_id'] ); ?></code></td>
                                <td><span class="rup-map-status <?php echo 'active' === $mapping['status'] ? 'is-active' : 'is-inactive'; ?>"><?php echo esc_html( ucfirst( $mapping['status'] ) ); ?></span><small><?php echo esc_html( $mapping['enable_activation_limit'] ? (int) $mapping['activation_limit'] . ' activations' : 'Unlimited activations' ); ?></small><small><?php echo esc_html( $mapping['lifetime'] ? 'Lifetime' : (int) $mapping['expiry_duration'] . ' ' . strtolower( $mapping['expiry_unit'] ) ); ?></small></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rup-gateway-fc&action=edit&mapping_id=' . intval( $mapping['id'] ) ) ); ?>">Edit</a><br>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rup_gateway_fc_delete_mapping&mapping_id=' . intval( $mapping['id'] ) ), 'rup_gateway_fc_delete_mapping_nonce' ) ); ?>" onclick="return confirmDelete();">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No mappings found. Add a mapping to issue licences from FluentCart purchases.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table></div></div>
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
                    return (confirmation === "DELETE");
                }
            </script>
        </div>
        <?php
    }
}

new Rup_Gateway_FC_Gateway();

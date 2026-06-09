<?php
/**
 * Enqueue our license management JavaScript and CSS.
 */
function rup_gateway_fc_enqueue_license_management_assets() {
    // Enqueue the JavaScript.
    wp_enqueue_script( 'license-management', plugin_dir_url( __FILE__ ) . 'js/license-management.js', array('jquery'), '1.0', true );
    wp_localize_script( 'license-management', 'licenseManagement', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'license_management_nonce' ),
    ) );

    // Enqueue the CSS from the plugin's css directory.
    wp_enqueue_style(
        'rup_gateway_fc_shc_license-management-style',
        plugin_dir_url( __FILE__ ) . 'css/styles.css',
        array(),
        filemtime( plugin_dir_path( __FILE__ ) . 'css/styles.css' )
    );

    $options = function_exists( 'rup_gateway_fc_get_options' ) ? rup_gateway_fc_get_options() : array();
    $primary_color = isset( $options['primary_button_color'] ) ? sanitize_hex_color( $options['primary_button_color'] ) : '#16a34a';
    $secondary_color = isset( $options['secondary_button_color'] ) ? sanitize_hex_color( $options['secondary_button_color'] ) : '#0f6da8';

    $custom_css = sprintf(
        '.rup-gateway-fc-primary-button,.download-btn,.add-site-form button{background:%1$s;color:#fff!important}.rup-gateway-fc-secondary-button,.toggle-details-btn{background:%2$s;color:#fff!important}',
        $primary_color ?: '#16a34a',
        $secondary_color ?: '#0f6da8'
    );
    wp_add_inline_style( 'rup_gateway_fc_shc_license-management-style', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'rup_gateway_fc_enqueue_license_management_assets' );

/**
 * AJAX handler for adding and removing sites.
 */
function rup_gateway_fc_manage_license_ajax() {
    // Verify the nonce.
    check_ajax_referer( 'license_management_nonce', 'security' );
    
    // Get and sanitize inputs.
    $license_id = isset( $_POST['license_id'] ) ? absint( $_POST['license_id'] ) : 0;
    $action     = isset( $_POST['license_action'] ) ? sanitize_text_field( $_POST['license_action'] ) : '';
    
    if ( ! $license_id || empty( $action ) ) {
        wp_send_json_error( 'Invalid parameters.' );
    }
    
    // Must be logged in.
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'You must be logged in.' );
    }
    
    $current_user_id = get_current_user_id();
    $license_owner   = get_post_meta( $license_id, 'user_id', true );
    if ( intval( $license_owner ) !== $current_user_id ) {
        wp_send_json_error( 'Permission denied.' );
    }
    
    // Retrieve the current used_sites array.
    $used_sites = function_exists( 'rup_gateway_fc_provider_get_license_sites' ) ? rup_gateway_fc_provider_get_license_sites( $license_id ) : get_post_meta( $license_id, 'used_sites', true );
    $used_sites = maybe_unserialize( $used_sites );
    if ( ! is_array( $used_sites ) ) {
        $used_sites = array();
    }
    
    if ( 'add_site' === $action ) {
        // Sanitize new site URL.
        $new_site = isset( $_POST['new_site'] ) ? esc_url_raw( $_POST['new_site'] ) : '';
        if ( empty( $new_site ) ) {
            wp_send_json_error( 'Invalid site URL.' );
        }
        
        // Check activation limit.
        $activation_limit = intval( get_post_meta( $license_id, 'activation_limit', true ) );
        if ( $activation_limit !== 0 && count( $used_sites ) >= $activation_limit ) {
            wp_send_json_error( 'Activation limit reached.' );
        }
        
        // Append the new site.
        $used_sites[] = $new_site;
        function_exists( 'rup_gateway_fc_provider_update_license_sites' ) ? rup_gateway_fc_provider_update_license_sites( $license_id, $used_sites ) : update_post_meta( $license_id, 'used_sites', $used_sites );
        wp_send_json_success( array( 'sites' => $used_sites ) );
        
    } elseif ( 'remove_site' === $action ) {
        // Sanitize the site to remove.
        $site_to_remove = isset( $_POST['site'] ) ? esc_url_raw( $_POST['site'] ) : '';
        if ( empty( $site_to_remove ) ) {
            wp_send_json_error( 'Invalid site URL.' );
        }
        
        if ( ! in_array( $site_to_remove, $used_sites, true ) ) {
            wp_send_json_error( 'Site not found.' );
        }
        
        // Remove the site.
        $used_sites = array_diff( $used_sites, array( $site_to_remove ) );
        $used_sites = array_values( $used_sites );
        function_exists( 'rup_gateway_fc_provider_update_license_sites' ) ? rup_gateway_fc_provider_update_license_sites( $license_id, $used_sites ) : update_post_meta( $license_id, 'used_sites', $used_sites );
        wp_send_json_success( array( 'sites' => $used_sites ) );
    }
    
    wp_send_json_error( 'Invalid action.' );
}
add_action( 'wp_ajax_manage_license_ajax', 'rup_gateway_fc_manage_license_ajax' );
add_action( 'wp_ajax_nopriv_manage_license_ajax', 'rup_gateway_fc_manage_license_ajax' );

/**
 * Shortcode to display all gateway licenses for the current user using FluentCart-style account UI classes.
 */
function rup_gateway_fc_licences_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="rup-gateway-fc-empty-state"><p>Please log in to view your licences.</p></div>';
    }
    
    $current_user_id = get_current_user_id();
    
    // Query licences for the active provider.
    if ( function_exists( 'rup_gateway_fc_get_license_user_ids_query_args' ) ) {
        $license_ids = get_posts( rup_gateway_fc_get_license_user_ids_query_args( $current_user_id ) );
        $licenses = array_filter( array_map( 'get_post', $license_ids ) );
    } else {
        $licenses = get_posts( array(
            'post_type'      => 'hoster_license',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'user_id',
                    'value'   => $current_user_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'ID',
            'order'   => 'DESC'
        ) );
    }
    
    if ( empty( $licenses ) ) {
        return '<div class="rup-gateway-fc-empty-state"><p>No licences found.</p></div>';
    }
    
    $output = '<div class="rup-gateway-fc-licences fluent-cart-custom-page-content">';
    
    foreach ( $licenses as $license ) {
        $license_id       = $license->ID;
        $license_key      = function_exists( 'rup_gateway_fc_provider_get_license_key' ) ? rup_gateway_fc_provider_get_license_key( $license_id ) : get_post_meta( $license_id, 'license_key', true );
        $activation_limit = intval( get_post_meta( $license_id, 'activation_limit', true ) );
        $used_sites       = function_exists( 'rup_gateway_fc_provider_get_license_sites' ) ? rup_gateway_fc_provider_get_license_sites( $license_id ) : get_post_meta( $license_id, 'used_sites', true );
        $used_sites       = maybe_unserialize( $used_sites );
        if ( ! is_array( $used_sites ) ) {
            $used_sites = array();
        }
        $used_site_count = count( $used_sites );
        $activation_limit_display = ( 0 === $activation_limit ) ? 'Unlimited' : (string) $activation_limit;
        $activation_usage_display = ( 0 === $activation_limit ) ? sprintf( '%d / ∞ Used', $used_site_count ) : sprintf( '%d / %d Used', $used_site_count, $activation_limit );
        
        // Cross-reference download_id to get product title.
        $download_id   = function_exists( 'rup_gateway_fc_provider_get_license_product_id' ) ? rup_gateway_fc_provider_get_license_product_id( $license_id ) : get_post_meta( $license_id, 'download_id', true );
        $product_title = $download_id ? get_the_title( $download_id ) : 'Unknown Product';
        
        // Retrieve the download_url meta from the "download" post (if it exists).
        //$download_url = $download_id ? get_post_meta( $download_id, 'download_url', true ) : '';
        $download_url = ( $download_id && function_exists( 'rup_gateway_fc_provider_get_license_download_url' ) ) ? rup_gateway_fc_provider_get_license_download_url( $download_id ) : ( $download_id ? rup_gateway_fc_generate_secure_download_link( get_post_meta( $download_id, 'download_url', true ) ) : '' );
        
        $status = get_post_meta( $license_id, 'status', true );
        $status_class = sanitize_html_class( strtolower( $status ? $status : 'unknown' ) );

        $output .= '<article class="rup-gateway-fc-license-card fct-card" id="license-' . $license_id . '">';
        
        // Header
        $output .= '<div class="rup-gateway-fc-license-card-header accordion-header" data-target="#accordion-content-' . $license_id . '">';
        $output .= '<div class="rup-gateway-fc-license-title-wrap">';
        $output .= '<h3>' . esc_html( $product_title ) . '</h3>';
        $output .= '<p class="rup-gateway-fc-license-key"><span>Licence key</span><code>' . esc_html( $license_key ) . '</code><button type="button" class="rup-gateway-fc-copy-license-key" data-license-key="' . esc_attr( $license_key ) . '" aria-label="Copy licence key">Copy</button></p>';
        $output .= '</div>';
        
        // Toggle Details + Download Buttons
        $output .= '<div class="accordion-header-buttons rup-gateway-fc-actions">';
        $output .= '<button type="button" class="toggle-details-btn rup-gateway-fc-secondary-button">Toggle Details</button>';

        if ( ! empty( $download_url ) ) {
            $output .= '<a href="' . esc_url( $download_url ) . '" class="download-btn rup-gateway-fc-primary-button" target="_blank" rel="noopener">Download</a>';
        }

        $output .= '</div>'; // .accordion-header-buttons

        
        $output .= '</div>'; // .accordion-header
        
        // Content
        $output .= '<div class="accordion-content rup-gateway-fc-license-card-content" id="accordion-content-' . $license_id . '">';
        $output .= '<div class="rup-gateway-fc-license-meta-grid">';
        $output .= '<div class="rup-gateway-fc-license-meta"><span>Status</span><strong class="rup-gateway-fc-status rup-gateway-fc-status-' . esc_attr( $status_class ) . '">' . esc_html( $status ) . '</strong></div>';
        $output .= '<div class="rup-gateway-fc-license-meta"><span>Limit</span><strong>' . esc_html( $activation_limit_display ) . '</strong></div>';
        $output .= '<div class="rup-gateway-fc-license-meta"><span>Activations</span><strong class="rup-gateway-fc-activation-count" data-license-id="' . esc_attr( $license_id ) . '" data-activation-limit="' . esc_attr( $activation_limit ) . '" data-activation-label="Used">' . esc_html( $activation_usage_display ) . '</strong></div>';
        
        // Retrieve the expiry date. If not set, default to Lifetime.
        $expiry_date = function_exists( 'rup_gateway_fc_provider_get_license_expiry' ) ? rup_gateway_fc_provider_get_license_expiry( $license_id ) : get_post_meta( $license_id, 'expiry_date', true );
        $expiry_display = ( ! $expiry_date ) ? 'Lifetime' : date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) );
        $output .= '<div class="rup-gateway-fc-license-meta"><span>Expiry</span><strong>' . esc_html( $expiry_display ) . '</strong></div>';
        $output .= '</div>';
        
        // Render the manage block with the manage_license shortcode.
        $output .= do_shortcode( '[rup_gateway_fc_manage_license license_id="' . $license_id . '"]' );
        $output .= '</div>'; // .accordion-content
        
        $output .= '</article>'; // .rup-gateway-fc-license-card
    }
    
    $output .= '</div>'; // .rup-gateway-fc-licences
    
    // Inline JS for toggling accordion items.
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var headers = document.querySelectorAll(".accordion-header");
        headers.forEach(function(header) {
            header.addEventListener("click", function(e) {
                if ( e.target.closest(".rup-gateway-fc-copy-license-key, a, input, textarea, select, .add-site-form, .remove-site-btn") ) {
                    return;
                }
                if ( e.target.classList.contains("toggle-details-btn") || e.target === header || e.target.closest(".rup-gateway-fc-license-title-wrap") ) {
                    var target = document.querySelector(header.getAttribute("data-target"));
                    if (target) {
                        target.style.display = (target.style.display === "block") ? "none" : "block";
                    }
                }
            });
        });
    });
    </script>';
    
    return $output;
}
add_shortcode( 'gateway_licences', 'rup_gateway_fc_licences_shortcode' );
add_shortcode( 'gateway_licenses', 'rup_gateway_fc_licences_shortcode' );

/**
 * Shortcode to display and manage sites for a specific license.
 * Usage: [rup_gateway_fc_manage_license license_id="123"]
 */
function rup_gateway_fc_manage_license_shortcode( $atts ) {
    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to manage your license.</p>';
    }
    
    $atts = shortcode_atts( array(
        'license_id' => 0,
    ), $atts, 'rup_gateway_fc_manage_license' );
    
    $license_id = absint( $atts['license_id'] );
    if ( ! $license_id ) {
        return '<p>Invalid license ID.</p>';
    }
    
    // Verify that the current user owns this license.
    $current_user_id = get_current_user_id();
    $license_owner   = get_post_meta( $license_id, 'user_id', true );
    if ( intval( $license_owner ) !== $current_user_id ) {
        return '<p>You do not have permission to manage this license.</p>';
    }
    
    // Retrieve the activation limit and current sites.
    $activation_limit = intval( get_post_meta( $license_id, 'activation_limit', true ) );
    $used_sites       = function_exists( 'rup_gateway_fc_provider_get_license_sites' ) ? rup_gateway_fc_provider_get_license_sites( $license_id ) : get_post_meta( $license_id, 'used_sites', true );
    $used_sites       = maybe_unserialize( $used_sites );
    if ( ! is_array( $used_sites ) ) {
        $used_sites = array();
    }
    
    $output = '<div class="manage-license rup-gateway-fc-manage-sites" data-license-id="' . $license_id . '">';
    $output .= '<h4>Manage Sites</h4>';
    
    // List current sites.
    if ( ! empty( $used_sites ) ) {
        $output .= '<ul class="site-list" id="site-list-' . $license_id . '">';
        foreach ( $used_sites as $site ) {
            $output .= '<li>';
            $output .= esc_html( $site );
            $output .= ' <button class="remove-site-btn" data-license-id="' . $license_id . '" data-site="' . esc_attr( $site ) . '">Remove</button>';
            $output .= '</li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<p class="rup-gateway-fc-muted" id="no-sites-' . $license_id . '">No sites added yet.</p>';
    }
    
    // Form for adding a new site.
    if ( $activation_limit === 0 || count( $used_sites ) < $activation_limit ) {
        $output .= '<form class="add-site-form" data-license-id="' . $license_id . '">';
        $output .= '<input type="text" name="new_site" placeholder="Enter site URL" required>';
        $output .= ' <button type="submit" class="rup-gateway-fc-primary-button">Add Site</button>';
        $output .= '</form>';
    } else {
        $output .= '<p>You have reached the activation limit.</p>'; 
    }  

    
    $output .= '</div>';    
    return $output;
}
add_shortcode( 'rup_gateway_fc_manage_license', 'rup_gateway_fc_manage_license_shortcode' );

/**
 * Process form submissions for adding or removing a site (non-AJAX fallback).
 */
function rup_gateway_fc_handle_license_site_submission() {
    if ( isset( $_POST['license_action'] ) ) {

        // Must be logged in.
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Get action and license ID.
        $action     = sanitize_text_field( $_POST['license_action'] );
        $license_id = isset( $_POST['license_id'] ) ? absint( $_POST['license_id'] ) : 0;
        if ( ! $license_id ) {
            return;
        }

        // Check the nonce.
        if ( ! isset( $_POST['manage_license_nonce'] ) || ! wp_verify_nonce( $_POST['manage_license_nonce'], 'manage_license_' . $license_id ) ) {
            return;
        }

        // Check that the current user owns this license.
        $current_user_id = get_current_user_id();
        $license_owner   = get_post_meta( $license_id, 'user_id', true );
        if ( intval( $license_owner ) !== $current_user_id ) {
            return;
        }

        // Retrieve the current used_sites array.
        $used_sites = function_exists( 'rup_gateway_fc_provider_get_license_sites' ) ? rup_gateway_fc_provider_get_license_sites( $license_id ) : get_post_meta( $license_id, 'used_sites', true );
        $used_sites = maybe_unserialize( $used_sites );
        if ( ! is_array( $used_sites ) ) {
            $used_sites = array();
        }

        if ( 'add_site' === $action ) {
            // Sanitize new site URL.
            $new_site = isset( $_POST['new_site'] ) ? esc_url_raw( $_POST['new_site'] ) : '';
            if ( $new_site ) {
                // Check activation limit.
                $activation_limit = intval( get_post_meta( $license_id, 'activation_limit', true ) );
                if ( $activation_limit !== 0 && count( $used_sites ) >= $activation_limit ) {
                    // Optionally add an error message.
                } else {
                    // Append new site.
                    $used_sites[] = $new_site;
                    function_exists( 'rup_gateway_fc_provider_update_license_sites' ) ? rup_gateway_fc_provider_update_license_sites( $license_id, $used_sites ) : update_post_meta( $license_id, 'used_sites', $used_sites );
                }
            }
        } elseif ( 'remove_site' === $action ) {
            // Sanitize the site value to remove.
            $site_to_remove = isset( $_POST['site'] ) ? esc_url_raw( $_POST['site'] ) : '';
            if ( $site_to_remove && in_array( $site_to_remove, $used_sites, true ) ) {
                // Remove the site.
                $used_sites = array_diff( $used_sites, array( $site_to_remove ) );
                // Reindex the array.
                $used_sites = array_values( $used_sites );
                function_exists( 'rup_gateway_fc_provider_update_license_sites' ) ? rup_gateway_fc_provider_update_license_sites( $license_id, $used_sites ) : update_post_meta( $license_id, 'used_sites', $used_sites );
            }
        }
        
        // Determine a clean URL for redirection.
        $referer = wp_get_referer();
        if ( ! $referer ) {
            $referer = home_url();
        }
        
        // Remove unwanted query args.
        if ( function_exists( 'remove_query_arg' ) ) {
            $redirect_url = remove_query_arg( array( 'new_site', 'license_action', 'site' ), $referer );
        } else {
            // Fallback: manually rebuild URL without these parameters.
            $parts = parse_url( $referer );
            $redirect_url = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );
        }
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
add_action( 'init', 'rup_gateway_fc_handle_license_site_submission' );

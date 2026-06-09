<?php
/**
 * Optional Hoster admin list-table improvements.
 * Controlled from Gateway for FluentCart settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


if ( function_exists( 'rup_gateway_fc_get_options' ) && function_exists( 'rup_gateway_fc_is_hoster_active_setup' ) && rup_gateway_fc_is_hoster_active_setup() && ! empty( rup_gateway_fc_get_options()['hoster_admin_improvements_enabled'] ) ) {

    if ( ! class_exists( 'rup_gateway_hoster_admin_improvements' ) ) {

        class rup_gateway_hoster_admin_improvements {

            public function __construct() {
                // Downloads post type modifications
                add_filter( 'manage_edit-downloads_columns', array( $this, 'my_custom_downloads_columns' ) );
                add_action( 'manage_downloads_posts_custom_column', array( $this, 'my_custom_downloads_column_content' ), 10, 2 );
                add_action( 'admin_footer', array( $this, 'my_downloads_id_copy_script' ) );

                // Hoster License post type modifications
                add_filter( 'manage_edit-hoster_license_columns', array( $this, 'custom_hoster_license_columns' ) );
                add_action( 'manage_hoster_license_posts_custom_column', array( $this, 'custom_hoster_license_column_content' ), 10, 2 );
                add_action( 'admin_footer', array( $this, 'hoster_license_copy_script' ) );
                add_action( 'admin_head', array( $this, 'hoster_license_column_width_css' ) );
            }

            /**
             * Add a new column to the downloads list.
             */
            public function my_custom_downloads_columns( $columns ) {
                $new_columns = array();
                foreach ( $columns as $key => $value ) {
                    $new_columns[ $key ] = $value;
                    if ( 'title' === $key ) {
                        $new_columns['download_id'] = __( 'Download ID', 'text_domain' );
                    }
                }
                return $new_columns;
            }

            /**
             * Populate the downloads column with the post ID and a clipboard icon.
             */
            public function my_custom_downloads_column_content( $column, $post_id ) {
                if ( 'download_id' === $column ) {
                    echo '<span class="download-id" style="margin-right:10px;">' . esc_html( $post_id ) . '</span>';
                    echo '<button class="copy-btn" data-clipboard-text="' . esc_attr( $post_id ) . '" title="' . esc_attr__( 'Copy ID', 'text_domain' ) . '">';
                    echo '<span class="dashicons dashicons-clipboard"></span>';
                    echo '</button>';
                }
            }

            /**
             * Enqueue a script for copying the downloads post ID.
             */
            public function my_downloads_id_copy_script() {
                $screen = get_current_screen();
                if ( isset( $screen->id ) && 'edit-downloads' === $screen->id ) :
                    wp_enqueue_style( 'dashicons' );
                    ?>
                    <script type="text/javascript">
                    (function() {
                        document.addEventListener('DOMContentLoaded', function() {
                            document.querySelectorAll('.copy-btn').forEach(function(button) {
                                button.addEventListener('click', function() {
                                    var text = button.getAttribute('data-clipboard-text');
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(text).then(function() {
                                            showCopiedState(button);
                                        });
                                    } else {
                                        var tempInput = document.createElement('input');
                                        tempInput.style.position = 'absolute';
                                        tempInput.style.left = '-9999px';
                                        tempInput.value = text;
                                        document.body.appendChild(tempInput);
                                        tempInput.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(tempInput);
                                        showCopiedState(button);
                                    }
                                });
                            });

                            function showCopiedState(button) {
                                button.innerHTML = 'Copied!';
                                setTimeout(function() {
                                    button.innerHTML = '<span class="dashicons dashicons-clipboard"></span>';
                                }, 2000);
                            }
                        });
                    })();
                    </script>
                    <?php
                endif;
            }

            /**
             * Modify columns for the hoster_license post type.
             */
            public function custom_hoster_license_columns( $columns ) {
                $cb       = isset( $columns['cb'] ) ? $columns['cb'] : '';
                $download = isset( $columns['download'] ) ? $columns['download'] : '';
                $user     = isset( $columns['user'] ) ? $columns['user'] : '';
                $status   = isset( $columns['status'] ) ? $columns['status'] : '';
                $date     = isset( $columns['date'] ) ? $columns['date'] : '';

                $new_columns = array();

                if ( $cb ) {
                    $new_columns['cb'] = $cb;
                }
                $new_columns['licence_key'] = __( 'Licence Key', 'text_domain' );
                if ( $download ) {
                    $new_columns['download'] = $download;
                }
                if ( $user ) {
                    $new_columns['user'] = $user;
                }
                if ( $status ) {
                    $new_columns['status'] = $status;
                }
                $new_columns['activation_limit'] = __( 'Activation Limit', 'text_domain' );
                $new_columns['expiry_date'] = __( 'Expiry Date', 'text_domain' );
                if ( $date ) {
                    $new_columns['date'] = $date;
                }

                return $new_columns;
            }

            /**
             * Populate custom columns for the hoster_license post type.
             */
            public function custom_hoster_license_column_content( $column, $post_id ) {
                switch ( $column ) {
                    case 'licence_key':
                        $license_key = get_post_meta( $post_id, 'license_key', true );
                        if ( empty( $license_key ) ) {
                            $license_key = __( '(No Licence Key)', 'text_domain' );
                        }
                        $edit_link = get_edit_post_link( $post_id );
                        echo '<strong><a class="row-title" href="' . esc_url( $edit_link ) . '">' . esc_html( $license_key ) . '</a></strong>';
                        echo ' <button onclick="event.stopPropagation();" class="copy-btn" data-clipboard-text="' . esc_attr( $license_key ) . '" title="' . esc_attr__( 'Copy Licence Key', 'text_domain' ) . '" style="border:none; background:none; cursor:pointer;">';
                        echo '<span class="dashicons dashicons-clipboard"></span>';
                        echo '</button>';
                        break;

                    case 'activation_limit':
                        $limit = get_post_meta( $post_id, 'activation_limit', true );
                        echo ( intval( $limit ) < 1 ) ? 'Unlimited' : esc_html( $limit );
                        break;

                    case 'expiry_date':
                        $date = get_post_meta( $post_id, 'expiry_date', true );
                        if ( empty( $date ) ) {
                            echo 'Lifetime';
                        } else {
                            echo date( 'd/m/Y', strtotime( $date ) );
                        }
                        break;
                }
            }

            /**
             * Enqueue the script for copying functionality on the hoster_license listing screen.
             */
            public function hoster_license_copy_script() {
                $screen = get_current_screen();
                if ( isset( $screen->id ) && 'edit-hoster_license' === $screen->id ) :
                    wp_enqueue_style( 'dashicons' );
                    ?>
                    <script type="text/javascript">
                    (function() {
                        document.addEventListener('DOMContentLoaded', function() {
                            document.querySelectorAll('.copy-btn').forEach(function(button) {
                                button.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    var text = button.getAttribute('data-clipboard-text');
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(text).then(function() {
                                            showCopiedState(button);
                                        });
                                    } else {
                                        var tempInput = document.createElement('input');
                                        tempInput.style.position = 'absolute';
                                        tempInput.style.left = '-9999px';
                                        tempInput.value = text;
                                        document.body.appendChild(tempInput);
                                        tempInput.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(tempInput);
                                        showCopiedState(button);
                                    }
                                });
                            });
                            function showCopiedState(button) {
                                var original = button.innerHTML;
                                button.innerHTML = 'Copied!';
                                setTimeout(function() {
                                    button.innerHTML = original;
                                }, 2000);
                            }
                        });
                    })();
                    </script>
                    <?php
                endif;
            }

            /**
             * Adjust column widths on the hoster_license listing screen.
             */
            public function hoster_license_column_width_css() {
                $screen = get_current_screen();
                if ( isset( $screen->id ) && 'edit-hoster_license' === $screen->id ) {
                    ?>
                    <style>
                        .wp-list-table .column-licence_key {
                            width: 200px;
                        }
                        .wp-list-table .column-download {
                            width: 200px;
                        }
                        .wp-list-table .column-user {
                            width: 150px;
                        }
                        .wp-list-table .column-status {
                            width: 150px;
                        }
                        .wp-list-table .column-activation_limit {
                            width: 150px;
                        }
                        .wp-list-table .column-expiry_date {
                            width: 120px;
                        }
                    </style>
                    <?php
                }
            }
        } // end class

        // Instantiate the class.
        new rup_gateway_hoster_admin_improvements();
    }
}

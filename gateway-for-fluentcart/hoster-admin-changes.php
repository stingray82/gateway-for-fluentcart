<?php
/**
 * Optional Hoster admin list-table improvements.
 * Controlled from Gateway for FluentCart settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


// These admin hooks apply to Hoster's post type independently of the provider
// Gateway uses for new purchases. Both providers can be installed together.
if ( function_exists( 'rup_gateway_fc_get_options' ) && ! empty( rup_gateway_fc_get_options()['hoster_admin_improvements_enabled'] ) ) {

    if ( ! class_exists( 'rup_gateway_hoster_admin_improvements' ) ) {

        class rup_gateway_hoster_admin_improvements {

            public function __construct() {
                // Downloads post type modifications
                add_filter( 'manage_edit-downloads_columns', array( $this, 'my_custom_downloads_columns' ) );
                add_action( 'manage_downloads_posts_custom_column', array( $this, 'my_custom_downloads_column_content' ), 10, 2 );
                add_action( 'admin_footer', array( $this, 'my_downloads_id_copy_script' ) );

                // Hoster License post type modifications
                add_filter( 'manage_edit-hoster_license_columns', array( $this, 'custom_hoster_license_columns' ) );
                add_filter( 'manage_hoster_license_posts_columns', array( $this, 'custom_hoster_license_columns' ), 20 );
                add_action( 'manage_hoster_license_posts_custom_column', array( $this, 'custom_hoster_license_column_content' ), 10, 2 );
                add_action( 'admin_footer', array( $this, 'hoster_license_copy_script' ) );
                add_action( 'admin_head', array( $this, 'hoster_license_column_width_css' ) );
                add_action( 'restrict_manage_posts', array( $this, 'render_purchase_filters' ), 20, 2 );
                add_action( 'pre_get_posts', array( $this, 'prepare_purchase_filters' ), 20 );
                add_filter( 'posts_where', array( $this, 'apply_purchase_filters' ), 20, 2 );
                add_action( 'add_meta_boxes_hoster_license', array( $this, 'register_purchase_box' ) );
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
                $new_columns['rup_gateway_fc_subscription_id'] = __( 'Subscription', 'gateway-for-fluentcart' );
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
                            $timestamp = strtotime( $date );
                            echo $timestamp ? esc_html( wp_date( 'd/m/Y', $timestamp ) ) : '&mdash;';
                        }
                        break;

                    case 'rup_gateway_fc_subscription_id':
                        $link = $this->get_purchase_link( $post_id );
                        if ( $link && absint( $link->subscription_id ) ) {
                            echo wp_kses_post( $this->fluentcart_reference( 'subscription', $link->subscription_id ) );
                        } else {
                            echo '&mdash;';
                        }
                        break;
                }
            }

            /** Gateway purchase data lives in its own link table, including Hoster licences. */
            private function get_purchase_link( $license_id ) {
                static $cache = array();
                static $table_exists = null;
                $license_id = absint( $license_id );
                if ( array_key_exists( $license_id, $cache ) ) {
                    return $cache[ $license_id ];
                }
                $cache[ $license_id ] = null;
                if ( ! $license_id ) {
                    return null;
                }
                global $wpdb;
                $table = rup_gateway_fc_get_license_links_table();
                if ( null === $table_exists ) {
                    $table_exists = rup_gateway_fc_table_exists( $table );
                }
                if ( $table_exists ) {
                    $cache[ $license_id ] = $wpdb->get_row( $wpdb->prepare( "SELECT order_id, subscription_id FROM {$table} WHERE license_id = %d ORDER BY (subscription_id > 0) DESC, id DESC LIMIT 1", $license_id ) );
                }
                return $cache[ $license_id ];
            }

            private function fluentcart_reference( $type, $id ) {
                $id = absint( $id );
                $path = 'order' === $type ? 'orders' : 'subscriptions';
                $label = 'order' === $type ? __( 'View FluentCart order', 'gateway-for-fluentcart' ) : __( 'View FluentCart subscription', 'gateway-for-fluentcart' );
                $url = admin_url( 'admin.php?page=fluent-cart' ) . '#/' . $path . '/' . $id . '/view';
                return sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( $url ), esc_attr( $label ), esc_html( $id ) );
            }

            public function render_purchase_filters( $post_type, $which = 'top' ) {
                if ( 'hoster_license' !== $post_type || 'top' !== $which ) {
                    return;
                }
                $order = isset( $_GET['rup_gateway_fc_order'] ) ? absint( wp_unslash( $_GET['rup_gateway_fc_order'] ) ) : 0;
                $subscription = isset( $_GET['rup_gateway_fc_subscription'] ) ? absint( wp_unslash( $_GET['rup_gateway_fc_subscription'] ) ) : 0;
                $from = $this->valid_date_filter( 'rup_gateway_fc_expiry_from' );
                $to = $this->valid_date_filter( 'rup_gateway_fc_expiry_to' );
                printf( '<label class="screen-reader-text" for="rup-gateway-fc-order-filter">%s</label><input id="rup-gateway-fc-order-filter" type="number" min="1" name="rup_gateway_fc_order" value="%s" placeholder="%s">', esc_html__( 'Order ID', 'gateway-for-fluentcart' ), $order ? esc_attr( $order ) : '', esc_attr__( 'Order ID', 'gateway-for-fluentcart' ) );
                printf( '<label class="screen-reader-text" for="rup-gateway-fc-subscription-filter">%s</label><input id="rup-gateway-fc-subscription-filter" type="number" min="1" name="rup_gateway_fc_subscription" value="%s" placeholder="%s">', esc_html__( 'Subscription ID', 'gateway-for-fluentcart' ), $subscription ? esc_attr( $subscription ) : '', esc_attr__( 'Subscription ID', 'gateway-for-fluentcart' ) );
                printf( '<label class="screen-reader-text" for="rup-gateway-fc-expiry-from">%s</label><input id="rup-gateway-fc-expiry-from" type="date" name="rup_gateway_fc_expiry_from" value="%s" title="%s">', esc_html__( 'Expiry from', 'gateway-for-fluentcart' ), esc_attr( $from ), esc_attr__( 'Expiry from', 'gateway-for-fluentcart' ) );
                printf( '<label class="screen-reader-text" for="rup-gateway-fc-expiry-to">%s</label><input id="rup-gateway-fc-expiry-to" type="date" name="rup_gateway_fc_expiry_to" value="%s" title="%s">', esc_html__( 'Expiry to', 'gateway-for-fluentcart' ), esc_attr( $to ), esc_attr__( 'Expiry to', 'gateway-for-fluentcart' ) );
                printf( '<a class="button" href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=hoster_license' ) ), esc_html__( 'Clear filters', 'gateway-for-fluentcart' ) );
            }

            private function valid_date_filter( $key ) {
                $value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
                if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                    return '';
                }
                list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );
                return checkdate( $month, $day, $year ) ? $value : '';
            }

            public function prepare_purchase_filters( $query ) {
                if ( ! is_admin() || ! $query->is_main_query() || 'hoster_license' !== $query->get( 'post_type' ) ) {
                    return;
                }
                $order = isset( $_GET['rup_gateway_fc_order'] ) ? absint( wp_unslash( $_GET['rup_gateway_fc_order'] ) ) : 0;
                $subscription = isset( $_GET['rup_gateway_fc_subscription'] ) ? absint( wp_unslash( $_GET['rup_gateway_fc_subscription'] ) ) : 0;
                if ( $order || $subscription ) {
                    $query->set( 'rup_gateway_fc_hoster_order', $order );
                    $query->set( 'rup_gateway_fc_hoster_subscription', $subscription );
                }
                $meta_query = (array) $query->get( 'meta_query' );
                foreach ( array( 'rup_gateway_fc_expiry_from' => '>=', 'rup_gateway_fc_expiry_to' => '<=' ) as $key => $compare ) {
                    $date = $this->valid_date_filter( $key );
                    if ( $date ) {
                        $meta_query[] = array( 'key' => 'expiry_date', 'value' => $date, 'compare' => $compare, 'type' => 'DATE' );
                    }
                }
                $query->set( 'meta_query', $meta_query );
            }

            public function apply_purchase_filters( $where, $query ) {
                if ( ! is_admin() || ! $query->is_main_query() || 'hoster_license' !== $query->get( 'post_type' ) ) {
                    return $where;
                }
                $order = absint( $query->get( 'rup_gateway_fc_hoster_order' ) );
                $subscription = absint( $query->get( 'rup_gateway_fc_hoster_subscription' ) );
                if ( ! $order && ! $subscription ) {
                    return $where;
                }
                global $wpdb;
                $table = rup_gateway_fc_get_license_links_table();
                if ( ! rup_gateway_fc_table_exists( $table ) ) {
                    return $where . ' AND 1=0';
                }
                $clauses = array( "rup_purchase_link.license_id = {$wpdb->posts}.ID" );
                if ( $order ) {
                    $clauses[] = $wpdb->prepare( 'rup_purchase_link.order_id = %d', $order );
                }
                if ( $subscription ) {
                    $clauses[] = $wpdb->prepare( 'rup_purchase_link.subscription_id = %d', $subscription );
                }
                return $where . " AND EXISTS (SELECT 1 FROM {$table} AS rup_purchase_link WHERE " . implode( ' AND ', $clauses ) . ')';
            }

            public function register_purchase_box() {
                add_meta_box( 'rup_gateway_fc_hoster_purchase', __( 'FluentCart purchase', 'gateway-for-fluentcart' ), array( $this, 'render_purchase_box' ), 'hoster_license', 'side' );
            }

            public function render_purchase_box( $post ) {
                $link = $this->get_purchase_link( $post->ID );
                if ( ! $link ) {
                    echo '<p>' . esc_html__( 'No Gateway purchase link found.', 'gateway-for-fluentcart' ) . '</p>';
                    return;
                }
                echo '<p><strong>' . esc_html__( 'Order', 'gateway-for-fluentcart' ) . '</strong><br>' . wp_kses_post( $this->fluentcart_reference( 'order', $link->order_id ) ) . '</p>';
                if ( absint( $link->subscription_id ) ) {
                    echo '<p><strong>' . esc_html__( 'Subscription', 'gateway-for-fluentcart' ) . '</strong><br>' . wp_kses_post( $this->fluentcart_reference( 'subscription', $link->subscription_id ) ) . '</p>';
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
                        .wp-list-table .column-rup_gateway_fc_subscription_id {
                            width: 110px;
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

<?php
/**
 * FluentCart lifecycle integration for Gateway licenses.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function rup_gateway_fc_debug_log( $message ) {
    $options = function_exists( 'rup_gateway_fc_get_options' ) ? rup_gateway_fc_get_options() : array();
    $debug_enabled = ! empty( $options['debug_enabled'] );

    if ( ! $debug_enabled ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $log_name   = ! empty( $options['debug_log_name'] ) ? sanitize_file_name( $options['debug_log_name'] ) : 'fc-gateway-debug.log';
    $log_file   = trailingslashit( $upload_dir['basedir'] ) . $log_name;
    @file_put_contents( $log_file, date( 'Y-m-d H:i:s' ) . ' - ' . print_r( $message, true ) . PHP_EOL, FILE_APPEND );
}

function rup_gateway_fc_get_mapping_table() {
    global $wpdb;
    return $wpdb->prefix . 'rup_gateway_fc_mappings';
}

function rup_gateway_fc_get_license_links_table() {
    global $wpdb;
    return $wpdb->prefix . 'rup_gateway_fc_license_links';
}

function rup_gateway_fc_table_exists( $table ) {
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}

function rup_gateway_fc_ensure_license_links_table() {
    global $wpdb;
    $table = rup_gateway_fc_get_license_links_table();
    if ( rup_gateway_fc_table_exists( $table ) ) {
        return true;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
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
    ) {$charset_collate};";
    dbDelta( $sql );
    return rup_gateway_fc_table_exists( $table );
}

function rup_gateway_fc_extract_license_id_from_response( $response ) {
    if ( is_array( $response ) && ! empty( $response['license_id'] ) ) {
        return absint( $response['license_id'] );
    }
    if ( is_object( $response ) && ! empty( $response->license_id ) ) {
        return absint( $response->license_id );
    }
    return 0;
}

function rup_gateway_fc_get_entity_id( $object, $keys = array() ) {
    $keys = $keys ? $keys : array( 'id', 'ID', 'order_id', 'subscription_id' );
    return absint( rup_gateway_fc_get_object_value( $object, $keys ) );
}

function rup_gateway_fc_get_order_id_from_order( $order ) {
    return rup_gateway_fc_get_entity_id( $order, array( 'id', 'ID', 'order_id' ) );
}

function rup_gateway_fc_get_order_item_id( $item ) {
    return rup_gateway_fc_get_entity_id( $item, array( 'id', 'ID', 'order_item_id', 'item_id' ) );
}

function rup_gateway_fc_get_subscription_id_from_data( $data, $subscription = null, $item = null ) {
    $subscription = $subscription ? $subscription : ( isset( $data['subscription'] ) ? $data['subscription'] : null );
    $id = rup_gateway_fc_get_entity_id( $subscription, array( 'id', 'ID', 'subscription_id' ) );
    if ( $id ) {
        return $id;
    }
    $id = rup_gateway_fc_get_entity_id( $item, array( 'subscription_id', 'subscriptionId' ) );
    if ( $id ) {
        return $id;
    }
    return rup_gateway_fc_get_entity_id( $data, array( 'subscription_id', 'subscriptionId' ) );
}

function rup_gateway_fc_get_table_columns( $table_suffix ) {
    global $wpdb;
    $table = $wpdb->prefix . $table_suffix;
    if ( ! rup_gateway_fc_table_exists( $table ) ) {
        return array();
    }
    return (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
}

function rup_gateway_fc_get_exact_item_product_variation_ids( $item, $mapping = null, $subscription = null ) {
    $variation_id = absint( rup_gateway_fc_get_object_value( $subscription, array( 'variation_id', 'price_id', 'variant_id' ) ) );
    $product_id   = absint( rup_gateway_fc_get_object_value( $subscription, array( 'product_id', 'post_id' ) ) );

    if ( ! $variation_id ) {
        // FluentCart order items normally store the variation/price row in object_id.
        $variation_id = absint( rup_gateway_fc_get_object_value( $item, array( 'variation_id', 'price_id', 'variant_id', 'object_id' ) ) );
    }

    if ( ! $product_id ) {
        // FluentCart order items normally store the fluent-products post ID in post_id.
        $product_id = absint( rup_gateway_fc_get_object_value( $item, array( 'product_id', 'post_id' ) ) );
    }

    if ( $mapping && ! $variation_id ) {
        $mapped_variation = rup_gateway_fc_get_variation_row( $mapping->price_id );
        if ( $mapped_variation ) {
            $variation_id = absint( $mapped_variation->id );
            if ( ! $product_id ) {
                $product_id = absint( $mapped_variation->post_id );
            }
        }
    }

    if ( $variation_id ) {
        $variation = rup_gateway_fc_get_variation_row( $variation_id );
        if ( $variation && ! $product_id ) {
            $product_id = absint( $variation->post_id );
        }
    }

    return array( $product_id, $variation_id );
}

function rup_gateway_fc_get_fluentcart_subscription_row_for_context( $order = null, $item = null, $mapping = null, $data = array() ) {
    global $wpdb;

    $subscriptions_table = $wpdb->prefix . 'fct_subscriptions';
    if ( ! rup_gateway_fc_table_exists( $subscriptions_table ) ) {
        return null;
    }

    $order_id = rup_gateway_fc_get_order_id_from_order( $order );
    if ( ! $order_id ) {
        return null;
    }

    list( $product_id, $variation_id ) = rup_gateway_fc_get_exact_item_product_variation_ids(
        $item,
        $mapping,
        isset( $data['subscription'] ) ? $data['subscription'] : null
    );

    $subscription_columns = rup_gateway_fc_get_table_columns( 'fct_subscriptions' );
    $attempts = array();

    // Confirmed FluentCart relationship: subscription parent_order_id is the original order ID.
    if ( in_array( 'parent_order_id', $subscription_columns, true ) ) {
        $where  = 'parent_order_id = %d';
        $values = array( $order_id );

        if ( $variation_id && in_array( 'variation_id', $subscription_columns, true ) ) {
            $where   .= ' AND variation_id = %d';
            $values[] = $variation_id;
        }

        if ( $product_id && in_array( 'product_id', $subscription_columns, true ) ) {
            $where   .= ' AND product_id = %d';
            $values[] = $product_id;
        }

        $attempts[] = array( $where, $values, 'parent_order_id_product_variation' );
        $attempts[] = array( 'parent_order_id = %d', array( $order_id ), 'parent_order_id' );
    }

    foreach ( $attempts as $attempt ) {
        list( $where, $values, $match_type ) = $attempt;
        $subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subscriptions_table} WHERE {$where} ORDER BY id DESC LIMIT 1", $values ) );
        if ( $subscription ) {
            rup_gateway_fc_debug_log( array(
                'fluentcart_subscription_matched_for_order_item' => array(
                    'match_type'      => $match_type,
                    'subscription_id' => absint( $subscription->id ),
                    'order_id'        => $order_id,
                    'order_item_id'   => rup_gateway_fc_get_order_item_id( $item ),
                    'product_id'      => $product_id,
                    'variation_id'    => $variation_id,
                    'where'           => $where,
                    'values'          => $values,
                ),
            ) );
            return $subscription;
        }
    }

    // Secondary confirmed relationship: subscription payments can also expose subscription_id in transactions.
    $transactions_table = $wpdb->prefix . 'fct_order_transactions';
    if ( rup_gateway_fc_table_exists( $transactions_table ) ) {
        $transaction_columns = rup_gateway_fc_get_table_columns( 'fct_order_transactions' );
        if ( in_array( 'order_id', $transaction_columns, true ) && in_array( 'subscription_id', $transaction_columns, true ) ) {
            $subscription_id = absint( $wpdb->get_var( $wpdb->prepare(
                "SELECT subscription_id FROM {$transactions_table} WHERE order_id = %d AND subscription_id > 0 ORDER BY id DESC LIMIT 1",
                $order_id
            ) ) );

            if ( $subscription_id ) {
                $subscription = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$subscriptions_table} WHERE id = %d LIMIT 1", $subscription_id ) );
                if ( $subscription ) {
                    rup_gateway_fc_debug_log( array(
                        'fluentcart_subscription_matched_for_order_item' => array(
                            'match_type'      => 'order_transaction_subscription_id',
                            'subscription_id' => $subscription_id,
                            'order_id'        => $order_id,
                            'order_item_id'   => rup_gateway_fc_get_order_item_id( $item ),
                            'product_id'      => $product_id,
                            'variation_id'    => $variation_id,
                        ),
                    ) );
                    return $subscription;
                }
            }
        }
    }

    rup_gateway_fc_debug_log( array(
        'fluentcart_subscription_not_found_for_order_item' => array(
            'order_id'      => $order_id,
            'order_item_id' => rup_gateway_fc_get_order_item_id( $item ),
            'product_id'    => $product_id,
            'variation_id'  => $variation_id,
            'note'          => 'Expected a FluentCart subscription row with parent_order_id matching the paid order.',
        ),
    ) );

    return null;
}

function rup_gateway_fc_get_subscription_id_for_order_item( $order = null, $item = null, $mapping = null, $data = array() ) {
    $subscription_id = rup_gateway_fc_get_subscription_id_from_data( $data, isset( $data['subscription'] ) ? $data['subscription'] : null, $item );
    if ( $subscription_id ) {
        return $subscription_id;
    }

    $subscription = rup_gateway_fc_get_fluentcart_subscription_row_for_context( $order, $item, $mapping, $data );
    return $subscription ? absint( $subscription->id ) : 0;
}

function rup_gateway_fc_insert_license_link( $args ) {
    global $wpdb;
    if ( empty( $args['license_id'] ) || ! rup_gateway_fc_ensure_license_links_table() ) {
        return false;
    }

    $defaults = array(
        'order_id'            => 0,
        'order_item_id'       => 0,
        'subscription_id'     => 0,
        'user_id'             => 0,
        'customer_email'      => '',
        'mapping_id'          => 0,
        'download_id'         => 0,
        'license_id'          => 0,
        'quantity_index'      => 1,
        'fluent_product_id'   => 0,
        'fluent_variation_id' => 0,
        'created_at'          => current_time( 'mysql' ),
        'updated_at'          => current_time( 'mysql' ),
    );
    $data = wp_parse_args( $args, $defaults );
    $data['customer_email'] = sanitize_email( $data['customer_email'] );

    $wpdb->insert( rup_gateway_fc_get_license_links_table(), $data );
    rup_gateway_fc_debug_log( array( 'license_link_stored' => $data, 'db_error' => $wpdb->last_error ) );
    return empty( $wpdb->last_error );
}

function rup_gateway_fc_get_license_links( $args = array() ) {
    global $wpdb;
    if ( ! rup_gateway_fc_ensure_license_links_table() ) {
        return array();
    }

    $where = array( 'license_id > 0' );
    $values = array();
    foreach ( array( 'order_id', 'order_item_id', 'subscription_id', 'user_id', 'mapping_id', 'download_id' ) as $field ) {
        if ( ! empty( $args[ $field ] ) ) {
            $where[] = "{$field} = %d";
            $values[] = absint( $args[ $field ] );
        }
    }

    if ( empty( $values ) && count( $where ) === 1 ) {
        return array();
    }

    $sql = "SELECT * FROM " . rup_gateway_fc_get_license_links_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id ASC';
    return $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );
}

function rup_gateway_fc_update_fluentcart_order_item_license_meta( $order_item_id, $license_id, $license_key = '' ) {
    global $wpdb;
    $order_item_id = absint( $order_item_id );
    $license_id    = absint( $license_id );
    if ( ! $order_item_id || ! $license_id ) {
        return;
    }

    $table = $wpdb->prefix . 'fct_order_items';
    if ( ! rup_gateway_fc_table_exists( $table ) ) {
        return;
    }

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
    if ( in_array( 'other_info', (array) $columns, true ) ) {
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT other_info FROM {$table} WHERE id = %d", $order_item_id ) );
        $info = $raw ? json_decode( $raw, true ) : array();
        if ( ! is_array( $info ) ) {
            $info = array();
        }
        if ( empty( $info['hoster_license_ids'] ) || ! is_array( $info['hoster_license_ids'] ) ) {
            $info['hoster_license_ids'] = array();
        }
        $info['hoster_license_ids'][] = $license_id;
        $info['hoster_license_ids'] = array_values( array_unique( array_map( 'absint', $info['hoster_license_ids'] ) ) );
        if ( $license_key ) {
            if ( empty( $info['hoster_license_keys'] ) || ! is_array( $info['hoster_license_keys'] ) ) {
                $info['hoster_license_keys'] = array();
            }
            $info['hoster_license_keys'][] = sanitize_text_field( $license_key );
            $info['hoster_license_keys'] = array_values( array_unique( $info['hoster_license_keys'] ) );
        }
        $wpdb->update( $table, array( 'other_info' => wp_json_encode( $info ) ), array( 'id' => $order_item_id ) );
        rup_gateway_fc_debug_log( array( 'fluentcart_order_item_meta_updated' => array( 'order_item_id' => $order_item_id, 'license_id' => $license_id, 'db_error' => $wpdb->last_error ) ) );
    }
}


function rup_gateway_fc_get_object_value( $object, $keys, $default = '' ) {
    foreach ( (array) $keys as $key ) {
        if ( is_array( $object ) && isset( $object[ $key ] ) ) {
            return $object[ $key ];
        }
        if ( is_object( $object ) ) {
            if ( isset( $object->{$key} ) ) {
                return $object->{$key};
            }
            if ( method_exists( $object, $key ) ) {
                return $object->{$key}();
            }
            $getter = 'get_' . $key;
            if ( method_exists( $object, $getter ) ) {
                return $object->{$getter}();
            }
        }
    }
    return $default;
}


function rup_gateway_fc_debug_summarize( $value, $depth = 0 ) {
    if ( $depth > 2 ) {
        return is_object( $value ) ? get_class( $value ) : gettype( $value );
    }

    if ( is_object( $value ) ) {
        $summary = array( '__class' => get_class( $value ) );
        foreach ( array( 'id', 'ID', 'order_id', 'product_id', 'variation_id', 'price_id', 'customer_id', 'email', 'customer_email', 'billing_email', 'status', 'payment_status' ) as $key ) {
            $val = rup_gateway_fc_get_object_value( $value, array( $key ), null );
            if ( null !== $val && '' !== $val && ! is_object( $val ) && ! is_array( $val ) ) {
                $summary[ $key ] = $val;
            }
        }
        return $summary;
    }

    if ( is_array( $value ) ) {
        $summary = array();
        foreach ( $value as $key => $item ) {
            if ( is_scalar( $item ) || null === $item ) {
                $summary[ $key ] = $item;
            } else {
                $summary[ $key ] = rup_gateway_fc_debug_summarize( $item, $depth + 1 );
            }
            if ( count( $summary ) >= 20 ) {
                $summary['__truncated'] = true;
                break;
            }
        }
        return $summary;
    }

    return $value;
}

function rup_gateway_fc_get_table_row_by_id( $table_suffix, $id ) {
    global $wpdb;
    $id = absint( $id );
    if ( ! $id ) {
        return null;
    }

    $table = $wpdb->prefix . $table_suffix;
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
        return null;
    }

    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
}

function rup_gateway_fc_get_db_order_row( $order ) {
    $order_id = absint( rup_gateway_fc_get_object_value( $order, array( 'id', 'ID', 'order_id' ) ) );
    return $order_id ? rup_gateway_fc_get_table_row_by_id( 'fct_orders', $order_id ) : null;
}

function rup_gateway_fc_get_db_customer_row( $customer_id ) {
    return rup_gateway_fc_get_table_row_by_id( 'fct_customers', $customer_id );
}

function rup_gateway_fc_get_customer_email( $order = null, $customer = null ) {
    $email = rup_gateway_fc_get_object_value( $customer, array( 'email', 'user_email', 'billing_email' ) );
    if ( $email ) {
        return sanitize_email( $email );
    }

    $email = rup_gateway_fc_get_object_value( $order, array( 'email', 'customer_email', 'billing_email', 'user_email' ) );
    if ( $email ) {
        return sanitize_email( $email );
    }

    $order_customer = rup_gateway_fc_get_object_value( $order, array( 'customer' ) );
    $email = rup_gateway_fc_get_object_value( $order_customer, array( 'email', 'user_email', 'billing_email' ) );
    if ( $email ) {
        return sanitize_email( $email );
    }

    $db_order = rup_gateway_fc_get_db_order_row( $order );
    if ( $db_order ) {
        foreach ( array( 'email', 'customer_email', 'billing_email' ) as $field ) {
            if ( ! empty( $db_order->{$field} ) ) {
                return sanitize_email( $db_order->{$field} );
            }
        }

        $customer_id = absint( rup_gateway_fc_get_object_value( $db_order, array( 'customer_id', 'user_id' ) ) );
        if ( $customer_id ) {
            $db_customer = rup_gateway_fc_get_db_customer_row( $customer_id );
            if ( $db_customer ) {
                foreach ( array( 'email', 'user_email', 'billing_email' ) as $field ) {
                    if ( ! empty( $db_customer->{$field} ) ) {
                        return sanitize_email( $db_customer->{$field} );
                    }
                }
            }
        }
    }

    return '';
}

function rup_gateway_fc_get_customer_user_id( $order = null, $customer = null, $email = '' ) {
    $user_id = absint( rup_gateway_fc_get_object_value( $customer, array( 'user_id', 'wp_user_id', 'userId' ) ) );
    if ( ! $user_id ) {
        $order_customer = rup_gateway_fc_get_object_value( $order, array( 'customer' ) );
        $user_id = absint( rup_gateway_fc_get_object_value( $order_customer, array( 'user_id', 'wp_user_id', 'userId' ) ) );
    }
    if ( ! $user_id ) {
        $user_id = absint( rup_gateway_fc_get_object_value( $order, array( 'user_id', 'wp_user_id', 'customer_user_id' ) ) );
    }
    if ( ! $user_id && $email ) {
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $user_id = absint( $user->ID );
        } else {
            $name_parts = explode( '@', $email );
            $user_id = wp_insert_user( array(
                'user_login' => sanitize_user( $email ),
                'user_email' => $email,
                'user_pass'  => wp_generate_password( 20 ),
                'role'       => 'subscriber',
                'first_name' => sanitize_text_field( rup_gateway_fc_get_object_value( $customer, array( 'first_name', 'firstName' ), $name_parts[0] ) ),
                'last_name'  => sanitize_text_field( rup_gateway_fc_get_object_value( $customer, array( 'last_name', 'lastName' ), '' ) ),
            ) );
            if ( is_wp_error( $user_id ) ) {
                rup_gateway_fc_debug_log( array( 'user_creation_failed' => $user_id->get_error_messages(), 'email' => $email ) );
                $user_id = 0;
            }
        }
    }
    return absint( $user_id );
}

function rup_gateway_fc_extract_order_items( $order ) {
    $items = rup_gateway_fc_get_object_value( $order, array( 'order_items', 'items', 'line_items' ), array() );
    if ( empty( $items ) && is_object( $order ) ) {
        foreach ( array( 'orderItems', 'items', 'lineItems' ) as $method ) {
            if ( method_exists( $order, $method ) ) {
                $items = $order->{$method}();
                break;
            }
        }
    }
    if ( is_object( $items ) && method_exists( $items, 'all' ) ) {
        $items = $items->all();
    } elseif ( is_object( $items ) && method_exists( $items, 'toArray' ) ) {
        $items = $items->toArray();
    }

    if ( empty( $items ) ) {
        global $wpdb;
        $order_id = absint( rup_gateway_fc_get_object_value( $order, array( 'id', 'ID', 'order_id' ) ) );
        $table = $wpdb->prefix . 'fct_order_items';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $order_id && $exists === $table ) {
            $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ) );
        }
    }

    return is_array( $items ) || $items instanceof Traversable ? $items : array();
}

function rup_gateway_fc_get_variation_row( $variation_id ) {
    return rup_gateway_fc_get_table_row_by_id( 'fct_product_variations', $variation_id );
}

function rup_gateway_fc_get_variation_ids_for_product( $post_id ) {
    global $wpdb;
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return array();
    }

    $table = $wpdb->prefix . 'fct_product_variations';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
        return array();
    }

    return array_map( 'strval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d", $post_id ) ) );
}

function rup_gateway_fc_item_candidate_ids( $item ) {
    $ids = array();
    foreach ( array( 'price_id', 'product_id', 'variation_id', 'variant_id', 'post_id', 'item_id', 'id' ) as $field ) {
        $value = rup_gateway_fc_get_object_value( $item, array( $field ) );
        if ( $value !== '' && $value !== null ) {
            $ids[] = (string) $value;
        }
    }

    foreach ( array( 'product', 'variation', 'variant', 'price', 'plan' ) as $relation ) {
        $related = rup_gateway_fc_get_object_value( $item, array( $relation ) );
        foreach ( array( 'price_id', 'id', 'ID', 'product_id', 'variation_id', 'variant_id', 'post_id' ) as $field ) {
            $value = rup_gateway_fc_get_object_value( $related, array( $field ) );
            if ( $value !== '' && $value !== null ) {
                $ids[] = (string) $value;
            }
        }
    }

    // FluentCart line items may expose only the variation row ID. Add its parent product post_id.
    foreach ( $ids as $id ) {
        $variation = rup_gateway_fc_get_variation_row( $id );
        if ( $variation && ! empty( $variation->post_id ) ) {
            $ids[] = (string) absint( $variation->post_id );
        }
    }

    // Or the mapping may be product-level. Add all variation IDs for product post IDs.
    foreach ( $ids as $id ) {
        foreach ( rup_gateway_fc_get_variation_ids_for_product( $id ) as $variation_id ) {
            $ids[] = (string) $variation_id;
        }
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

function rup_gateway_fc_mapping_matches_any_sibling_variation( $mapping_price_id, $candidate_ids ) {
    $mapped_variation = rup_gateway_fc_get_variation_row( $mapping_price_id );
    if ( ! $mapped_variation || empty( $mapped_variation->post_id ) ) {
        return false;
    }

    foreach ( (array) $candidate_ids as $candidate_id ) {
        $candidate_variation = rup_gateway_fc_get_variation_row( $candidate_id );
        if ( $candidate_variation && absint( $candidate_variation->post_id ) === absint( $mapped_variation->post_id ) ) {
            return true;
        }
        if ( absint( $candidate_id ) === absint( $mapped_variation->post_id ) ) {
            return true;
        }
    }

    return false;
}

function rup_gateway_fc_find_mapping_for_item( $item ) {
    global $wpdb;
    $table = rup_gateway_fc_get_mapping_table();
    $candidate_ids = rup_gateway_fc_item_candidate_ids( $item );

    foreach ( $candidate_ids as $candidate_id ) {
        $mapping = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE price_id = %s", $candidate_id ) );
        if ( $mapping ) {
            return $mapping;
        }
    }

    // If an admin mapped one variation, treat sibling variations for the same FluentCart product as the same product.
    $mappings = $wpdb->get_results( "SELECT * FROM {$table}" );
    foreach ( (array) $mappings as $mapping ) {
        if ( rup_gateway_fc_mapping_matches_any_sibling_variation( $mapping->price_id, $candidate_ids ) ) {
            rup_gateway_fc_debug_log( array(
                'mapping_found_by_sibling_variation' => array(
                    'mapping_id'    => $mapping->id,
                    'mapping_price' => $mapping->price_id,
                    'candidate_ids' => $candidate_ids,
                ),
            ) );
            return $mapping;
        }
    }

    return null;
}

function rup_gateway_fc_calculate_expiry_date( $mapping, $base_date = '' ) {
    if ( intval( $mapping->lifetime ) === 1 ) {
        return '';
    }
    try {
        $date = $base_date ? new DateTime( $base_date ) : new DateTime( current_time( 'mysql' ) );
        $duration = max( 1, intval( $mapping->expiry_duration ) );
        switch ( $mapping->expiry_unit ) {
            case 'Weeks':
                $interval = "P{$duration}W";
                break;
            case 'Months':
                $interval = "P{$duration}M";
                break;
            case 'Years':
                $interval = "P{$duration}Y";
                break;
            case 'Days':
            default:
                $interval = "P{$duration}D";
                break;
        }
        $date->add( new DateInterval( $interval ) );
        return $date->format( 'Y-m-d' );
    } catch ( Exception $e ) {
        rup_gateway_fc_debug_log( 'Expiry date error: ' . $e->getMessage() );
        return '';
    }
}

function rup_gateway_fc_format_license_expiry_date( $date_raw ) {
    $date_raw = trim( (string) $date_raw );
    if ( '' === $date_raw ) {
        return '';
    }

    $timestamp = strtotime( $date_raw );
    if ( ! $timestamp ) {
        return $date_raw;
    }

    // Hoster and UUPD both store expiry as a date-only value.
    // Store FluentCart's actual next billing date.
    return date( 'Y-m-d', $timestamp );
}

function rup_gateway_fc_get_subscription_expiry_from_row( $subscription ) {
    if ( ! $subscription ) {
        return '';
    }
    $raw = rup_gateway_fc_get_object_value( $subscription, array( 'next_billing_date', 'nextBillingDate', 'billing_date', 'renews_at', 'current_period_end' ), '' );
    return $raw ? rup_gateway_fc_format_license_expiry_date( $raw ) : '';
}

function rup_gateway_fc_get_user_license_ids( $user_id ) {
    if ( ! $user_id ) {
        return array();
    }
    return get_posts( rup_gateway_fc_get_license_user_ids_query_args( $user_id ) );
}

function rup_gateway_fc_get_user_license_ids_for_download( $user_id, $download_id ) {
    if ( ! $user_id || ! $download_id ) {
        return array();
    }
    return get_posts( rup_gateway_fc_get_license_user_ids_query_args( $user_id, $download_id ) );
}

function rup_gateway_fc_get_subscription_next_billing_date( $subscription = null ) {
    $raw = rup_gateway_fc_get_object_value( $subscription, array( 'next_billing_date', 'nextBillingDate', 'billing_date', 'renews_at', 'current_period_end' ), '' );
    if ( ! $raw ) {
        return '';
    }
    $timestamp = strtotime( $raw );
    return $timestamp ? date( 'Y-m-d H:i:s', $timestamp ) : '';
}

function rup_gateway_fc_update_license_by_id( $license_id, $mapping, $expiry_date, $reason = '', $context = array() ) {
    $license_id = absint( $license_id );
    if ( ! $license_id ) {
        return false;
    }

    $update_data = array(
        'status'      => sanitize_text_field( $mapping->status ? $mapping->status : 'active' ),
        'expiry_date' => $expiry_date,
    );

    if ( isset( $mapping->activation_limit ) ) {
        $update_data['activation_limit'] = absint( $mapping->activation_limit );
    }

    $response = rup_gateway_fc_provider_update_license( $license_id, $update_data, array_merge( $context, array( 'reason' => $reason ) ) );
    rup_gateway_fc_debug_log( array(
        'license_provider_update_response' => $response,
        'provider'                         => rup_gateway_fc_get_license_provider(),
        'license_id'                       => $license_id,
        'download_id'                      => absint( $mapping->download_id ),
        'expiry_date'                      => $expiry_date,
        'reason'                           => $reason,
    ) );
    return true;
}

function rup_gateway_fc_find_linked_licenses_for_renewal( $criteria, $context = array(), $reason = '' ) {
    $links = rup_gateway_fc_get_license_links( $criteria );
    if ( ! empty( $links ) ) {
        rup_gateway_fc_debug_log( array(
            'linked_license_found' => array(
                'match_type' => 'subscription_id_exact',
                'criteria'   => $criteria,
                'license_ids' => wp_list_pluck( $links, 'license_id' ),
                'reason'     => $reason,
                'context'    => $context,
            ),
        ) );
        return array( $links, 'subscription_id_exact' );
    }

    rup_gateway_fc_debug_log( array(
        'linked_license_exact_lookup_failed' => array(
            'criteria' => $criteria,
            'reason'   => $reason,
            'context'  => $context,
            'note'     => 'Strict release-mode lookup: the initial purchase should have stored subscription_id; no alternative licence matching was attempted.',
        ),
    ) );

    return array( array(), 'none' );
}

function rup_gateway_fc_update_linked_licenses( $criteria, $mapping, $expiry_date, $reason = '', $context = array() ) {
    list( $links, $match_type ) = rup_gateway_fc_find_linked_licenses_for_renewal( $criteria, $context, $reason );
    if ( empty( $links ) ) {
        rup_gateway_fc_debug_log( array(
            'linked_license_not_found' => array(
                'criteria' => $criteria,
                'mapping'  => array( 'id' => $mapping->id, 'download_id' => $mapping->download_id, 'price_id' => $mapping->price_id ),
                'reason'   => $reason,
                'context'  => $context,
            ),
        ) );
        return array();
    }

    $updated_license_ids = array();
    foreach ( $links as $link ) {
        $updated_license_ids[] = absint( $link->license_id );
        rup_gateway_fc_update_license_by_id( $link->license_id, $mapping, $expiry_date, $reason, array_merge( $context, array( 'link_id' => $link->id, 'link_match_type' => $match_type ) ) );
    }
    return array_values( array_unique( array_filter( $updated_license_ids ) ) );
}

function rup_gateway_fc_is_subscription_mapping( $mapping ) {
    global $wpdb;
    if ( empty( $mapping->price_id ) ) {
        return false;
    }
    $variation_table = $wpdb->prefix . 'fct_product_variations';
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $variation_table ) );
    if ( $table_exists !== $variation_table ) {
        return false;
    }
    $payment_type = $wpdb->get_var( $wpdb->prepare( "SELECT payment_type FROM {$variation_table} WHERE id = %d", absint( $mapping->price_id ) ) );
    return 'subscription' === $payment_type;
}

function rup_gateway_fc_extend_licenses_for_subscription( $data, $reason = 'subscription_renewal' ) {
    $subscription = isset( $data['subscription'] ) ? $data['subscription'] : null;
    $order        = isset( $data['order'] ) ? $data['order'] : rup_gateway_fc_get_object_value( $subscription, array( 'order' ) );
    $customer     = isset( $data['customer'] ) ? $data['customer'] : rup_gateway_fc_get_object_value( $subscription, array( 'customer' ) );

    if ( ! $order && ! $subscription ) {
        rup_gateway_fc_debug_log( array( 'renewal_skipped' => 'No order or subscription found', 'reason' => $reason, 'fluentcart_payload' => rup_gateway_fc_debug_summarize( $data ) ) );
        return;
    }

    $email           = rup_gateway_fc_get_customer_email( $order, $customer );
    $user_id         = rup_gateway_fc_get_customer_user_id( $order, $customer, $email );
    $order_id        = rup_gateway_fc_get_order_id_from_order( $order );
    $subscription_id = rup_gateway_fc_get_subscription_id_from_data( $data, $subscription );
    $next_billing    = rup_gateway_fc_get_subscription_next_billing_date( $subscription );

    rup_gateway_fc_debug_log( array(
        'fluentcart_subscription_event_received' => array(
            'reason'          => $reason,
            'order_id'        => $order_id,
            'subscription_id' => $subscription_id,
            'user_id'         => $user_id,
            'email'           => $email,
            'next_billing'    => $next_billing,
            'payload_summary' => rup_gateway_fc_debug_summarize( $data ),
        ),
    ) );

    if ( ! $user_id ) {
        rup_gateway_fc_debug_log( array( 'renewal_skipped' => 'No user resolved', 'reason' => $reason ) );
        return;
    }

    $items = $order ? rup_gateway_fc_extract_order_items( $order ) : array();
    foreach ( $items as $item ) {
        $mapping = rup_gateway_fc_find_mapping_for_item( $item );
        if ( ! $mapping ) {
            rup_gateway_fc_debug_log( array( 'renewal_mapping_not_found' => rup_gateway_fc_item_candidate_ids( $item ), 'order_id' => $order_id, 'subscription_id' => $subscription_id, 'item_summary' => rup_gateway_fc_debug_summarize( $item ) ) );
            continue;
        }

        $expiry_date = $next_billing ? rup_gateway_fc_format_license_expiry_date( $next_billing ) : rup_gateway_fc_format_license_expiry_date( rup_gateway_fc_calculate_expiry_date( $mapping ) );
        list( $product_id, $variation_id ) = rup_gateway_fc_get_exact_item_product_variation_ids( $item, $mapping, $subscription );
        $criteria = array(
            'user_id'     => $user_id,
            'mapping_id'  => absint( $mapping->id ),
            'download_id' => absint( $mapping->download_id ),
        );
        if ( $subscription_id ) {
            $criteria['subscription_id'] = $subscription_id;
        } elseif ( $order_id ) {
            $criteria['order_id'] = $order_id;
        }

        $order_item_id = rup_gateway_fc_get_order_item_id( $item );
        $updated_license_ids = rup_gateway_fc_update_linked_licenses( $criteria, $mapping, $expiry_date, $reason, array(
            'order_id'            => $order_id,
            'order_item_id'       => $order_item_id,
            'subscription_id'     => $subscription_id,
            'fluent_product_id'   => $product_id,
            'fluent_variation_id' => $variation_id,
            'item'                => rup_gateway_fc_debug_summarize( $item ),
        ) );

        if ( ! empty( $updated_license_ids ) ) {
            rup_gateway_fc_debug_log( array(
                'renewal_receipt_injection_skipped' => array(
                    'reason'      => 'Renewal confirmations do not include licence keys.',
                    'order_id'    => $order_id,
                    'email'       => $email,
                    'license_ids' => $updated_license_ids,
                ),
            ) );
        }
    }
}

function rup_gateway_fc_create_licenses_for_order( $order, $customer = null, $data = array() ) {
    $email   = rup_gateway_fc_get_customer_email( $order, $customer );
    $user_id = rup_gateway_fc_get_customer_user_id( $order, $customer, $email );

    if ( ! $user_id ) {
        rup_gateway_fc_debug_log( array( 'create_skipped' => 'No user could be resolved', 'email' => $email, 'order_summary' => rup_gateway_fc_debug_summarize( $order ), 'customer_summary' => rup_gateway_fc_debug_summarize( $customer ), 'fluentcart_payload' => rup_gateway_fc_debug_summarize( $data ) ) );
        return;
    }

    $order_id = rup_gateway_fc_get_order_id_from_order( $order );
    $processed_orders = get_option( 'rup_gateway_fc_processed_orders', array() );
    if ( $order_id && in_array( $order_id, array_map( 'absint', (array) $processed_orders ), true ) ) {
        rup_gateway_fc_debug_log( array( 'order_already_processed' => $order_id ) );
        return;
    }

    $existing_license_ids = rup_gateway_fc_get_user_license_ids( $user_id );
    $items = rup_gateway_fc_extract_order_items( $order );
    $base_date = rup_gateway_fc_get_object_value( $order, array( 'created_at', 'createdAt', 'date_created' ), '' );

    rup_gateway_fc_debug_log( array(
        'fluentcart_order_event_received' => array(
            'order_id'        => $order_id,
            'user_id'         => $user_id,
            'email'           => $email,
            'item_count'      => is_countable( $items ) ? count( $items ) : 0,
            'order_summary'   => rup_gateway_fc_debug_summarize( $order ),
            'payload_summary' => rup_gateway_fc_debug_summarize( $data ),
        ),
    ) );

    foreach ( $items as $item ) {
        $mapping = rup_gateway_fc_find_mapping_for_item( $item );
        if ( ! $mapping ) {
            rup_gateway_fc_debug_log( array( 'mapping_not_found' => rup_gateway_fc_item_candidate_ids( $item ), 'order_id' => $order_id, 'item_summary' => rup_gateway_fc_debug_summarize( $item ) ) );
            continue;
        }

        $quantity        = max( 1, absint( rup_gateway_fc_get_object_value( $item, array( 'quantity', 'qty' ), 1 ) ) );
        $order_item_id   = rup_gateway_fc_get_order_item_id( $item );
        $subscription_id = rup_gateway_fc_get_subscription_id_for_order_item( $order, $item, $mapping, $data );
        $candidate_ids   = rup_gateway_fc_item_candidate_ids( $item );
        $subscription    = $subscription_id ? rup_gateway_fc_get_table_row_by_id( 'fct_subscriptions', $subscription_id ) : null;
        $expiry_date     = $subscription ? rup_gateway_fc_get_subscription_expiry_from_row( $subscription ) : rup_gateway_fc_calculate_expiry_date( $mapping, $base_date );
        list( $product_id, $variation_id ) = rup_gateway_fc_get_exact_item_product_variation_ids( $item, $mapping, $subscription );

        rup_gateway_fc_debug_log( array(
            'mapping_selected_for_item' => array(
                'order_id'        => $order_id,
                'order_item_id'   => $order_item_id,
                'subscription_id' => $subscription_id,
                'candidate_ids'   => $candidate_ids,
                'fluent_product_id'   => $product_id,
                'fluent_variation_id' => $variation_id,
                'quantity'        => $quantity,
                'mapping'         => array( 'id' => $mapping->id, 'price_id' => $mapping->price_id, 'download_id' => $mapping->download_id ),
                'expiry_date'     => $expiry_date,
            ),
        ) );

        for ( $i = 1; $i <= $quantity; $i++ ) {
            $provider_context = array(
                'order_id'            => $order_id,
                'order_item_id'       => $order_item_id,
                'subscription_id'     => $subscription_id,
                'mapping_id'          => absint( $mapping->id ),
                'quantity_index'      => $i,
                'fluent_product_id'   => $product_id,
                'fluent_variation_id' => $variation_id,
            );

            $response = rup_gateway_fc_provider_create_license( $mapping, $user_id, $expiry_date, $provider_context );

            $license_id = rup_gateway_fc_extract_license_id_from_response( $response );
            $license_key = '';
            if ( is_array( $response ) && ! empty( $response['license_key'] ) ) {
                $license_key = sanitize_text_field( $response['license_key'] );
            } elseif ( $license_id ) {
                $license_key = sanitize_text_field( rup_gateway_fc_provider_get_license_key( $license_id ) );
            }

            rup_gateway_fc_debug_log( array( 'license_provider_create_response' => $response, 'provider' => rup_gateway_fc_get_license_provider(), 'order_id' => $order_id, 'mapping_id' => $mapping->id, 'license_id' => $license_id ) );

            if ( $license_id ) {
                rup_gateway_fc_insert_license_link( array(
                    'order_id'            => $order_id,
                    'order_item_id'       => $order_item_id,
                    'subscription_id'     => $subscription_id,
                    'user_id'             => $user_id,
                    'customer_email'      => $email,
                    'mapping_id'          => absint( $mapping->id ),
                    'download_id'         => absint( $mapping->download_id ),
                    'license_id'          => $license_id,
                    'quantity_index'      => $i,
                    'fluent_product_id'   => $product_id,
                    'fluent_variation_id' => $variation_id,
                ) );
                rup_gateway_fc_update_fluentcart_order_item_license_meta( $order_item_id, $license_id, $license_key );
            }
        }
    }

    if ( $order_id ) {
        $processed_orders[] = $order_id;
        $processed_orders = array_slice( array_values( array_unique( array_map( 'absint', $processed_orders ) ) ), -500 );
        update_option( 'rup_gateway_fc_processed_orders', $processed_orders, false );
    }

    $new_license_ids = array_diff( rup_gateway_fc_get_user_license_ids( $user_id ), $existing_license_ids );
    rup_gateway_fc_debug_log( array( 'new_licenses_for_receipt_injection' => array_values( $new_license_ids ), 'buyer_email' => $email ) );

    if ( $email && $order_id && ! empty( $new_license_ids ) ) {
        rup_gateway_fc_set_recent_receipt_injection_context( $email, $order_id, array_values( $new_license_ids ) );
    }
}

function rup_gateway_fc_expire_licenses_for_order( $order, $customer = null, $reason = '' ) {
    $email    = rup_gateway_fc_get_customer_email( $order, $customer );
    $user_id  = rup_gateway_fc_get_customer_user_id( $order, $customer, $email );
    $order_id = rup_gateway_fc_get_order_id_from_order( $order );

    if ( ! $user_id ) {
        rup_gateway_fc_debug_log( array( 'expire_skipped' => 'No user resolved', 'reason' => $reason, 'order_summary' => rup_gateway_fc_debug_summarize( $order ) ) );
        return;
    }

    $linked = $order_id ? rup_gateway_fc_get_license_links( array( 'order_id' => $order_id, 'user_id' => $user_id ) ) : array();
    if ( ! empty( $linked ) ) {
        foreach ( $linked as $link ) {
            $response = rup_gateway_fc_provider_update_license( absint( $link->license_id ), array(
                'status'      => 'expired',
                'expiry_date' => current_time( 'Y-m-d' ),
            ), array( 'reason' => $reason, 'order_id' => $order_id, 'link_id' => absint( $link->id ) ) );
            rup_gateway_fc_debug_log( array( 'linked_license_expired' => $response, 'provider' => rup_gateway_fc_get_license_provider(), 'license_id' => absint( $link->license_id ), 'link_id' => absint( $link->id ), 'order_id' => $order_id, 'reason' => $reason ) );
        }
        return;
    }

    rup_gateway_fc_debug_log( array(
        'expire_skipped_no_link_found' => array(
            'order_id' => $order_id,
            'user_id'  => $user_id,
            'reason'   => $reason,
            'note'     => 'Release-mode safety: no user/download fallback was attempted. Licences are only expired through explicit order link rows.',
        ),
    ) );
}

function rup_gateway_fc_order_paid( $data ) {
    // FluentCart exposes some hooks with an array payload and fluent_cart/order_paid with the order model directly.
    $order    = ( is_array( $data ) && isset( $data['order'] ) ) ? $data['order'] : $data;
    $customer = ( is_array( $data ) && isset( $data['customer'] ) ) ? $data['customer'] : null;

    if ( $order ) {
        rup_gateway_fc_create_licenses_for_order( $order, $customer, is_array( $data ) ? $data : array( 'order' => $order ) );
    }
}

function rup_gateway_fc_order_cancelled( $data ) {
    $order = isset( $data['order'] ) ? $data['order'] : null;
    if ( $order ) {
        rup_gateway_fc_expire_licenses_for_order( $order, null, 'order_cancelled' );
    }
}

function rup_gateway_fc_order_refunded( $data ) {
    $order = isset( $data['order'] ) ? $data['order'] : null;
    if ( $order ) {
        $customer = isset( $data['customer'] ) ? $data['customer'] : null;
        rup_gateway_fc_expire_licenses_for_order( $order, $customer, 'order_fully_refunded' );
    }
}

function rup_gateway_fc_subscription_active( $data ) {
    rup_gateway_fc_extend_licenses_for_subscription( $data, 'subscription_active' );
}

function rup_gateway_fc_subscription_inactive( $data ) {
    $order = isset( $data['order'] ) ? $data['order'] : null;
    $customer = isset( $data['customer'] ) ? $data['customer'] : null;
    $status = isset( $data['new_status'] ) ? $data['new_status'] : 'subscription_inactive';
    if ( $order ) {
        rup_gateway_fc_expire_licenses_for_order( $order, $customer, $status );
    }
}

// Run early on FluentCart's primary paid-order event so licence links exist before the customer receipt email is rendered.
add_action( 'fluent_cart/order_paid', 'rup_gateway_fc_order_paid', 1, 1 );
add_action( 'fluent_cart/payment_status_changed_to_paid', 'rup_gateway_fc_order_paid', 10, 1 );
add_action( 'fluent_cart/order_status_changed_to_completed', 'rup_gateway_fc_order_paid', 10, 1 );
add_action( 'fluent_cart/order_status_changed_to_canceled', 'rup_gateway_fc_order_cancelled', 10, 1 );
add_action( 'fluent_cart/payment_status_changed_to_refunded', 'rup_gateway_fc_order_refunded', 10, 1 );
add_action( 'fluent_cart/order_fully_refunded', 'rup_gateway_fc_order_refunded', 10, 1 );
add_action( 'fluent_cart/payments/subscription_active', 'rup_gateway_fc_subscription_active', 10, 1 );
add_action( 'fluent_cart/payments/subscription_trialing', 'rup_gateway_fc_subscription_active', 10, 1 );
add_action( 'fluent_cart/payments/subscription_canceled', 'rup_gateway_fc_subscription_inactive', 10, 1 );
add_action( 'fluent_cart/payments/subscription_expired', 'rup_gateway_fc_subscription_inactive', 10, 1 );
add_action( 'fluent_cart/payments/subscription_completed', 'rup_gateway_fc_subscription_inactive', 10, 1 );
add_action( 'fluent_cart/payments/subscription_paused', 'rup_gateway_fc_subscription_inactive', 10, 1 );
add_action( 'fluent_cart/payments/subscription_status_changed', function( $data ) {
    $inactive_statuses = array( 'canceled', 'cancelled', 'expired', 'completed', 'paused' );
    if ( isset( $data['new_status'] ) && in_array( $data['new_status'], $inactive_statuses, true ) ) {
        rup_gateway_fc_subscription_inactive( $data );
    }
}, 20, 1 );

add_action( 'fluent_cart/subscription/data_updated', function( $data ) {
    if ( ! empty( $data['updated_data']['next_billing_date'] ) ) {
        rup_gateway_fc_extend_licenses_for_subscription( $data, 'subscription_next_billing_date_updated' );
    }
}, 10, 1 );


/**
 * Stores a short-lived order context so the wp_mail fallback can append licences
 * to the FluentCart receipt even when FluentCart does not expose a content filter.
 */
function rup_gateway_fc_set_recent_receipt_injection_context( $email, $order_id, $license_ids = array() ) {
    $email    = sanitize_email( $email );
    $order_id = absint( $order_id );
    if ( ! $email || ! $order_id ) {
        return;
    }

    $context = array(
        'email'       => $email,
        'order_id'    => $order_id,
        'license_ids' => array_values( array_map( 'absint', (array) $license_ids ) ),
        'created_at'  => time(),
    );

    // This context is only a secondary fallback for customer emails that do not expose an order number.
    // It is deliberately short lived and consumed after a successful injection.
    set_transient( 'rup_gateway_fc_recent_receipt_' . md5( strtolower( $email ) ), $context, 10 * MINUTE_IN_SECONDS );
    rup_gateway_fc_debug_log( array( 'receipt_injection_context_stored' => $context ) );
}

function rup_gateway_fc_get_recent_receipt_injection_context( $email ) {
    $email = sanitize_email( $email );
    if ( ! $email ) {
        return array();
    }

    $context = get_transient( 'rup_gateway_fc_recent_receipt_' . md5( strtolower( $email ) ) );
    if ( ! is_array( $context ) || empty( $context['order_id'] ) || empty( $context['email'] ) ) {
        return array();
    }

    if ( strtolower( $email ) !== strtolower( sanitize_email( $context['email'] ) ) ) {
        return array();
    }

    if ( ! empty( $context['created_at'] ) && ( time() - absint( $context['created_at'] ) ) > ( 10 * MINUTE_IN_SECONDS ) ) {
        delete_transient( 'rup_gateway_fc_recent_receipt_' . md5( strtolower( $email ) ) );
        return array();
    }

    return $context;
}

function rup_gateway_fc_clear_recent_receipt_injection_context( $email ) {
    $email = sanitize_email( $email );
    if ( $email ) {
        delete_transient( 'rup_gateway_fc_recent_receipt_' . md5( strtolower( $email ) ) );
    }
}

function rup_gateway_fc_extract_email_from_mail_to( $to ) {
    if ( is_array( $to ) ) {
        $to = reset( $to );
    }
    $to = (string) $to;
    if ( preg_match( '/<([^>]+)>/', $to, $matches ) ) {
        $to = $matches[1];
    }
    return sanitize_email( trim( $to ) );
}

function rup_gateway_fc_mail_subject( $args ) {
    return isset( $args['subject'] ) ? wp_strip_all_tags( (string) $args['subject'] ) : '';
}

function rup_gateway_fc_mail_is_admin_notification( $args ) {
    $subject = strtolower( rup_gateway_fc_mail_subject( $args ) );
    $message = isset( $args['message'] ) ? strtolower( wp_strip_all_tags( (string) $args['message'] ) ) : '';

    foreach ( array( 'new sales', 'new sale', 'new renewal', 'new order on your shop', 'just placed an order' ) as $needle ) {
        if ( false !== strpos( $subject . ' ' . $message, $needle ) ) {
            return true;
        }
    }

    // Admin order detail URLs should never receive customer licence keys.
    if ( ! empty( $args['message'] ) && false !== strpos( (string) $args['message'], '/wp-admin/admin.php?page=fluent-cart#/orders/' ) ) {
        return true;
    }

    return false;
}

function rup_gateway_fc_extract_order_id_from_mail_args( $args ) {
    $subject = rup_gateway_fc_mail_subject( $args );
    $message = isset( $args['message'] ) ? (string) $args['message'] : '';

    foreach ( array( $subject, $message ) as $source ) {
        if ( preg_match( '/\bINV-(\d+)\b/i', $source, $matches ) ) {
            return absint( $matches[1] );
        }
    }

    return 0;
}

function rup_gateway_fc_order_has_license_links_for_email( $order_id, $email ) {
    global $wpdb;
    $order_id = absint( $order_id );
    $email    = sanitize_email( $email );
    if ( ! $order_id || ! $email || ! rup_gateway_fc_ensure_license_links_table() ) {
        return false;
    }

    $table = rup_gateway_fc_get_license_links_table();
    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND customer_email = %s AND license_id > 0",
        $order_id,
        $email
    ) );

    return absint( $count ) > 0;
}

function rup_gateway_fc_mail_looks_like_order_receipt( $args ) {
    $subject = strtolower( rup_gateway_fc_mail_subject( $args ) );
    if ( preg_match( '/purchase\s+receipt\s+#?INV-\d+/i', rup_gateway_fc_mail_subject( $args ) ) ) {
        return true;
    }
    // Renewal confirmations intentionally do not include licence keys.
    return false;
}

function rup_gateway_fc_maybe_inject_licenses_into_wp_mail( $args ) {
    $options = function_exists( 'rup_gateway_fc_get_options' ) ? rup_gateway_fc_get_options() : array();
    if ( empty( $options['auto_inject_receipt_licenses'] ) ) {
        return $args;
    }

    if ( empty( $args['message'] ) || ! is_string( $args['message'] ) ) {
        return $args;
    }

    if ( false !== strpos( $args['message'], 'rup-gateway-fc-licenses' ) ) {
        return $args;
    }

    $email   = rup_gateway_fc_extract_email_from_mail_to( $args['to'] ?? '' );
    $subject = rup_gateway_fc_mail_subject( $args );
    if ( false !== strpos( strtolower( $subject ), 'renewal confirmation' ) ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'Renewal confirmation skipped; licence keys are only sent on initial purchase receipts.', 'to' => $email, 'subject' => $subject ) ) );
        return $args;
    }
    if ( ! $email ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'No recipient email could be resolved', 'to' => $args['to'] ?? '', 'subject' => $subject ) ) );
        return $args;
    }

    if ( rup_gateway_fc_mail_is_admin_notification( $args ) ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'Admin notification skipped', 'to' => $email, 'subject' => $subject ) ) );
        return $args;
    }

    if ( ! rup_gateway_fc_mail_looks_like_order_receipt( $args ) ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'Mail did not look like a customer receipt', 'to' => $email, 'subject' => $subject ) ) );
        return $args;
    }

    $order_id = rup_gateway_fc_extract_order_id_from_mail_args( $args );
    $source   = 'mail_subject_or_body';

    // Renewal emails may not include INV-n in the subject. Use the one-time context only in that case.
    if ( ! $order_id ) {
        $context = rup_gateway_fc_get_recent_receipt_injection_context( $email );
        if ( ! empty( $context['order_id'] ) ) {
            $order_id = absint( $context['order_id'] );
            $source   = 'one_time_context';
        }
    }

    if ( ! $order_id ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'No exact order ID available', 'to' => $email, 'subject' => $subject ) ) );
        return $args;
    }

    if ( ! rup_gateway_fc_order_has_license_links_for_email( $order_id, $email ) ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'No licence links for exact order and recipient', 'to' => $email, 'subject' => $subject, 'order_id' => $order_id, 'order_id_source' => $source ) ) );
        return $args;
    }

    $html = rup_gateway_fc_render_licenses_for_order( $order_id, array( 'source' => 'wp_mail_exact_order', 'mail_args' => array( 'to' => $email, 'subject' => $subject ), 'order_id_source' => $source ) );
    if ( empty( $html ) ) {
        rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_skipped' => array( 'reason' => 'Licence HTML was empty', 'to' => $email, 'subject' => $subject, 'order_id' => $order_id, 'order_id_source' => $source ) ) );
        return $args;
    }

    $insertion = rup_gateway_fc_insert_license_html_into_email_message( $args['message'], $html );
    $args['message'] = $insertion['message'];

    // The context is single-use; clearing it prevents cross-order leakage.
    rup_gateway_fc_clear_recent_receipt_injection_context( $email );

    rup_gateway_fc_debug_log( array( 'wp_mail_receipt_injection_appended' => array( 'to' => $email, 'subject' => $subject, 'order_id' => $order_id, 'order_id_source' => $source, 'placement' => $insertion['placement'], 'message_length_after' => strlen( $args['message'] ) ) ) );
    return $args;
}
add_filter( 'wp_mail', 'rup_gateway_fc_maybe_inject_licenses_into_wp_mail', 9999, 1 );


/**
 * Places the licence block inside FluentCart's receipt body where possible.
 * The wp_mail fallback sees the final rendered email, so appending before
 * </body> can put content outside the central receipt card. These markers keep
 * the block near the order details and above the footer/button area.
 */
function rup_gateway_fc_insert_license_html_into_email_message( $message, $html ) {
    $placements = array(
        'before_receipt_download_text' => '/<p[^>]*>\s*To download receipt and view your order details,.*?<\/p>/is',
        'before_view_details_button'   => '/<a[^>]*>\s*View Details\s*<\/a>/is',
        'before_powered_by'            => '/<[^>]+>\s*Powered by\s*<a[^>]+>\s*FluentCart\s*<\/a>\s*<\/[^>]+>/is',
        'before_body_close'            => '/<\/body>/i',
    );

    foreach ( $placements as $placement => $pattern ) {
        if ( preg_match( $pattern, $message ) ) {
            $replacement = $html . '$0';
            if ( 'before_body_close' === $placement ) {
                $replacement = $html . '</body>';
            }
            $updated = preg_replace( $pattern, $replacement, $message, 1 );
            if ( is_string( $updated ) && $updated !== $message ) {
                return array(
                    'message'   => $updated,
                    'placement' => $placement,
                );
            }
        }
    }

    return array(
        'message'   => $message . $html,
        'placement' => 'append_fallback',
    );
}

/**
 * FluentCart receipt/email shortcode integration.
 *
 * Adds {{gateway.licenses}} to FluentCart template pickers and renders it where
 * FluentCart exposes a replaceable content filter. A normal WordPress shortcode
 * is also provided for templates that run do_shortcode().
 */
function rup_gateway_fc_register_email_shortcodes( $shortcodes ) {
    if ( ! is_array( $shortcodes ) ) {
        $shortcodes = array();
    }

    $shortcodes['hoster'] = array(
        'title'      => 'Hoster Licences',
        'key'        => 'hoster',
        'shortcodes' => array(
            '{{gateway.licenses}}'       => 'Purchased licence keys',
            '{{order.gateway_licenses}}' => 'Purchased licence keys',
            '{{gateway_licenses}}'       => 'Purchased licence keys',
        ),
    );

    return $shortcodes;
}
add_filter( 'fluent_cart/editor_shortcodes', 'rup_gateway_fc_register_email_shortcodes', 10, 1 );

function rup_gateway_fc_register_confirmation_shortcodes( $groups, $data = array() ) {
    if ( ! is_array( $groups ) ) {
        $groups = array();
    }

    $groups[] = array(
        'title'      => 'Hoster Licences',
        'key'        => 'hoster',
        'shortcodes' => array(
            '{{gateway.licenses}}'       => 'Purchased licence keys',
            '{{order.gateway_licenses}}' => 'Purchased licence keys',
            '{{gateway_licenses}}'       => 'Purchased licence keys',
        ),
    );

    return $groups;
}
add_filter( 'fluent_cart/confirmation_shortcodes', 'rup_gateway_fc_register_confirmation_shortcodes', 10, 2 );

function rup_gateway_fc_shortcode_order_id_from_context( $context = null ) {
    foreach ( func_get_args() as $candidate ) {
        if ( empty( $candidate ) ) {
            continue;
        }

        if ( is_numeric( $candidate ) ) {
            return absint( $candidate );
        }

        if ( is_array( $candidate ) ) {
            foreach ( array( 'order_id', 'id', 'ID' ) as $key ) {
                if ( ! empty( $candidate[ $key ] ) && is_numeric( $candidate[ $key ] ) ) {
                    return absint( $candidate[ $key ] );
                }
            }
            foreach ( array( 'order', 'data', 'context' ) as $key ) {
                if ( ! empty( $candidate[ $key ] ) ) {
                    $found = rup_gateway_fc_shortcode_order_id_from_context( $candidate[ $key ] );
                    if ( $found ) {
                        return $found;
                    }
                }
            }
        }

        if ( is_object( $candidate ) ) {
            foreach ( array( 'order_id', 'id', 'ID' ) as $key ) {
                if ( isset( $candidate->{$key} ) && is_numeric( $candidate->{$key} ) ) {
                    return absint( $candidate->{$key} );
                }
            }
            foreach ( array( 'order', 'data', 'context' ) as $key ) {
                if ( isset( $candidate->{$key} ) ) {
                    $found = rup_gateway_fc_shortcode_order_id_from_context( $candidate->{$key} );
                    if ( $found ) {
                        return $found;
                    }
                }
            }
        }
    }

    foreach ( array( 'order_id', 'fct_order_id', 'order' ) as $request_key ) {
        if ( isset( $_GET[ $request_key ] ) && is_numeric( $_GET[ $request_key ] ) ) {
            return absint( $_GET[ $request_key ] );
        }
    }

    return 0;
}

function rup_gateway_fc_render_licenses_for_order( $order_id = 0, $context = null ) {
    $order_id = absint( $order_id );
    if ( ! $order_id ) {
        $order_id = rup_gateway_fc_shortcode_order_id_from_context( $context );
    }

    if ( ! $order_id ) {
        rup_gateway_fc_debug_log( array( 'hoster_receipt_shortcode_skipped' => 'No order ID could be resolved', 'context' => rup_gateway_fc_debug_summarize( $context ) ) );
        return '';
    }

    $links = rup_gateway_fc_get_license_links( array( 'order_id' => $order_id ) );
    if ( empty( $links ) ) {
        rup_gateway_fc_debug_log( array( 'hoster_receipt_shortcode_empty' => array( 'order_id' => $order_id ) ) );
        return '';
    }

    $rows = array();
    foreach ( $links as $link ) {
        $license_id = absint( $link->license_id );
        if ( ! $license_id ) {
            continue;
        }

        $download_id = absint( $link->download_id );
        $license_data = function_exists( 'rup_gateway_fc_provider_get_license_data' ) ? rup_gateway_fc_provider_get_license_data( $license_id, $download_id ) : array();

        $license_key = isset( $license_data['license_key'] ) ? (string) $license_data['license_key'] : rup_gateway_fc_provider_get_license_key( $license_id );
        if ( '' === trim( $license_key ) ) {
            rup_gateway_fc_debug_log( array( 'hoster_receipt_license_row_skipped' => array( 'reason' => 'Empty licence key', 'license_id' => $license_id, 'order_id' => $order_id, 'provider' => function_exists( 'rup_gateway_fc_get_license_provider' ) ? rup_gateway_fc_get_license_provider() : 'unknown' ) ) );
            continue;
        }

        $product_name = ! empty( $license_data['product_name'] ) ? $license_data['product_name'] : ( $download_id ? get_the_title( $download_id ) : 'Product' );
        $secure_download_url = isset( $license_data['download_url'] ) ? (string) $license_data['download_url'] : ( $download_id ? rup_gateway_fc_provider_get_license_download_url( $download_id ) : '' );

        $rows[] = array(
            'product_name' => $product_name,
            'license_key'  => $license_key,
            'download_url' => $secure_download_url,
        );
    }

    if ( empty( $rows ) ) {
        return '';
    }

    ob_start();
    ?>
    <div class="rup-gateway-fc-licenses" style="max-width:600px;margin:22px auto;padding:0;font-family:Arial,Helvetica,sans-serif;color:#111827;">
        <h3 style="margin:0 0 10px;font-size:16px;line-height:1.35;font-weight:700;color:#111827;">Your Licence Keys</h3>
        <table style="width:100%;border-collapse:collapse;border-spacing:0;" cellpadding="0" cellspacing="0" role="presentation">
            <tbody>
            <?php foreach ( $rows as $row ) : ?>
                <tr>
                    <td style="padding:10px 0;border-top:1px solid #eef0f3;vertical-align:top;font-size:13px;line-height:1.45;color:#111827;">
                        <strong style="display:block;margin-bottom:5px;font-weight:700;color:#111827;"><?php echo esc_html( $row['product_name'] ); ?></strong>
                        <span style="display:block;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.5;word-break:break-all;color:#111827;"><?php echo esc_html( $row['license_key'] ); ?></span>
                        <?php if ( ! empty( $row['download_url'] ) ) : ?>
                            <a style="display:inline-block;margin-top:7px;color:#1677ff;text-decoration:none;" href="<?php echo esc_url( $row['download_url'] ); ?>" target="_blank" rel="noopener">Download</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();
    rup_gateway_fc_debug_log( array( 'hoster_receipt_shortcode_rendered' => array( 'order_id' => $order_id, 'license_count' => count( $rows ) ) ) );
    return $html;
}

function rup_gateway_fc_replace_gateway_license_tokens( $content, $data = array(), $context = null ) {
    if ( ! is_string( $content ) || false === strpos( $content, 'hoster' ) ) {
        return $content;
    }

    $tokens = array( '{{gateway.licenses}}', '{{order.gateway_licenses}}', '{{gateway_licenses}}' );
    $needs_replacement = false;
    foreach ( $tokens as $token ) {
        if ( false !== strpos( $content, $token ) ) {
            $needs_replacement = true;
            break;
        }
    }

    if ( ! $needs_replacement ) {
        return $content;
    }

    $order_id = rup_gateway_fc_shortcode_order_id_from_context( $data, $context );
    $html = rup_gateway_fc_render_licenses_for_order( $order_id, array( 'data' => $data, 'context' => $context ) );
    return str_replace( $tokens, $html, $content );
}

// These filters are intentionally broad/no-op safe so the token resolves across email and receipt render paths as FluentCart evolves.
foreach ( array(
    'fluent_cart/email_body',
    'fluent_cart/email_content',
    'fluent_cart/rendered_email_body',
    'fluent_cart/rendered_email_content',
    'fluent_cart/notification_email_body',
    'fluent_cart/notification_content',
    'fluent_cart/confirmation_content',
    'fluent_cart/confirmation_page_content',
    'fluent_cart/receipt_content',
) as $rup_gateway_fc_content_filter ) {
    add_filter( $rup_gateway_fc_content_filter, 'rup_gateway_fc_replace_gateway_license_tokens', 10, 3 );
    add_filter( $rup_gateway_fc_content_filter, 'rup_gateway_fc_auto_inject_gateway_licenses', 20, 3 );
}
unset( $rup_gateway_fc_content_filter );



function rup_gateway_fc_auto_inject_gateway_licenses( $content, $data = array(), $context = null ) {
    $options = function_exists( 'rup_gateway_fc_get_options' ) ? rup_gateway_fc_get_options() : array();
    if ( empty( $options['auto_inject_receipt_licenses'] ) ) {
        return $content;
    }

    if ( ! is_string( $content ) || '' === trim( $content ) ) {
        return $content;
    }

    // Avoid duplicate output when a manual merge tag/shortcode already rendered the licence block.
    if ( false !== strpos( $content, 'rup-gateway-fc-licenses' ) ) {
        return $content;
    }

    $order_id = rup_gateway_fc_shortcode_order_id_from_context( $data, $context );
    $html = rup_gateway_fc_render_licenses_for_order( $order_id, array( 'data' => $data, 'context' => $context, 'source' => 'auto_inject' ) );
    if ( empty( $html ) ) {
        rup_gateway_fc_debug_log( array( 'hoster_receipt_auto_inject_skipped' => array( 'order_id' => $order_id, 'reason' => 'No licence HTML generated' ) ) );
        return $content;
    }

    rup_gateway_fc_debug_log( array( 'hoster_receipt_auto_inject_rendered' => array( 'order_id' => $order_id, 'content_length_before' => strlen( $content ), 'license_html_length' => strlen( $html ) ) ) );

    // Prefer to place before closing body if a full HTML email is being filtered; otherwise append.
    if ( false !== stripos( $content, '</body>' ) ) {
        return preg_replace( '/<\/body>/i', $html . '</body>', $content, 1 );
    }

    return $content . $html;
}

function rup_gateway_fc_order_licenses_wp_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array(
        'order_id' => 0,
    ), $atts, 'rup_gateway_fc_order_licenses' );
    return rup_gateway_fc_render_licenses_for_order( absint( $atts['order_id'] ) );
}
add_shortcode( 'rup_gateway_fc_order_licenses', 'rup_gateway_fc_order_licenses_wp_shortcode' );
add_shortcode( 'gateway_order_licenses', 'rup_gateway_fc_order_licenses_wp_shortcode' );
add_shortcode( 'uupd_order_licenses', 'rup_gateway_fc_order_licenses_wp_shortcode' );
add_shortcode( 'fluentcart_license_keys', 'rup_gateway_fc_order_licenses_wp_shortcode' );

<?php
/**
 * Generate a secure download link for a file.
 *
 * @param string $file_url       The actual file URL (relative or absolute).
 * @param int    $expiry_seconds The link expiry time in seconds (default: 3600 = 1 hour).
 * @return string                The secure download URL.
 */
function rup_gateway_fc_generate_secure_download_link( $file_url, $expiry_seconds = 3600 ) {
    // Generate a secure random token.
    $token = bin2hex( random_bytes( 16 ) );
    
    // Store the file URL in a transient using the token as a key.
    set_transient( 'secure_download_' . $token, $file_url, $expiry_seconds );
    
    // Return a URL that uses "rup-downloads" as the endpoint.
    return home_url( '/rup-downloads?token=' . $token );
}

/**
 * Handle secure download requests.
 * This function intercepts requests to the "rup-downloads" endpoint with a "token" parameter and serves the file.
 */
function rup_gateway_fc_handle_secure_download() {
    if ( isset( $_GET['token'] ) && strpos( $_SERVER['REQUEST_URI'], 'rup-downloads' ) !== false ) {
        // Sanitize the token value.
        $token = sanitize_text_field( $_GET['token'] );
        $transient_key = 'secure_download_' . $token;
        
        // Retrieve the stored file URL.
        $file_url = get_transient( $transient_key );
        
        if ( ! $file_url ) {
            wp_die( 'Download link expired or invalid.' );
        }
        
        // Uncomment the following line to enforce one-time use:
        // delete_transient( $transient_key );
        
        // Clear all output buffers to avoid corrupting the download.
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Ensure we send a proper HTTP response.
        header("HTTP/1.1 200 OK");
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . basename( $file_url ) . '"' );
        header( 'Content-Transfer-Encoding: binary' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Pragma: public' );
        
        // Determine if the file URL is remote or local.
        $parsed_url   = wp_parse_url( $file_url );
        $file_host    = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
        $current_host = $_SERVER['HTTP_HOST'];
        
        if ( $file_host && ( strtolower( $file_host ) !== strtolower( $current_host ) ) ) {
            // For remote files, attempt to read directly.
            $result = @readfile( $file_url );
            if ( $result === false ) {
                wp_die( 'Error reading remote file.' );
            }
            exit;
        } else {
            // If the URL is absolute but on your domain, remove the domain portion.
            if ( $file_host ) {
                $file_url = preg_replace( '#^https?://' . preg_quote( $current_host, '#' ) . '#i', '', $file_url );
            }
            // Build the absolute path for local files.
            $file_path = ABSPATH . ltrim( $file_url, '/' );
            
            if ( ! file_exists( $file_path ) ) {
                wp_die( 'File not found.' );
            }
            
            header( 'Content-Length: ' . filesize( $file_path ) );
            flush();
            $result = @readfile( $file_path );
            if ( $result === false ) {
                wp_die( 'Error reading local file.' );
            }
            exit;
        }
    }
}
add_action( 'template_redirect', 'rup_gateway_fc_handle_secure_download' );

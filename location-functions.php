<?php
// Save location data to the database
function ls_save_location_data( $lat, $lng, $accuracy ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ls_location_data';
    $user_id = get_current_user_id();
    $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'timestamp' => current_time( 'mysql' )
        )
    );
}

// Handle the location form submission
<?php
/**
 * Functions for handling location data.
 */

/**
 * Get the user's location data based on their IP address.
 *
 * @return array|false The user's location data or false if unable to retrieve.
 */
function get_user_location_data() {
    $ip_address = get_user_ip_address();
    $location_data = false;

    // If the IP address is a local IP address, return default location data.
    if (is_local_ip_address($ip_address)) {
        $location_data = array(
            'ip' => $ip_address,
            'latitude' => '40.7128',
            'longitude' => '-74.0060',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'postal_code' => '10001'
        );
    } else {
        // If the IP address is not local, retrieve the user's location data from the IPInfo API.
        $api_key = get_option('location_sharing_api_key');
        if ($api_key) {
            $url = "https://ipinfo.io/{$ip_address}?token={$api_key}";
            $response = wp_remote_get($url);
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                $location_data = json_decode($response_body, true);
            }
        }
    }

    return $location_data;
}

/**
 * Get the user's IP address.
 *
 * @return string The user's IP address.
 */
function get_user_ip_address() {
    $ip_address = '';

    // If the X-Forwarded-For header is set, use the first IP address listed.
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip_address = explode(',', $ip_address)[0];
    } else if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    return $ip_address;
}

/**
 * Determine if an IP address is a local IP address.
 *
 * @param string $ip_address The IP address to check.
 *
 * @return bool Whether or not the IP address is local.
 */
function is_local_ip_address($ip_address) {
    $local_ips = array(
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16'
    );

    foreach ($local_ips as $local_ip) {
        if (ip_in_range($ip_address, $local_ip)) {
            return true;
        }
    }

    return false;
}

/**
 * Determine if an IP address is within a range of IP addresses.
 *
 * @param string $ip_address The IP address to check.
 * @param string $ip_range The IP range to check against.
 *
 * @return bool Whether or not the IP address is within the range.
 */
function ip_in_range($ip_address, $ip_range) {
    list($subnet, $mask) = explode('/', $ip_range);
    if (!$mask) {
        $mask = 32;
    }

    $subnet = ip2long($subnet);
    $ip_address = ip2long($ip_address);
    $mask = -1 << (32 - $mask);
    $subnet &= $mask;

    return ($ip_address & $mask) === $subnet;
}

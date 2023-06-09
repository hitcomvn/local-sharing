<?php
/**
 * Plugin Name: Local Sharing
 * Plugin URI: https://example.com
 * Description: A plugin to enable requesting location sharing on website and save the log using Local Privacy.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

// Enqueue script and style
function local_sharing_enqueue_scripts() {
    wp_enqueue_script( 'location-script', plugin_dir_url( __FILE__ ) . 'location-script.js', array( 'jquery' ), '1.0', true );
    wp_enqueue_style( 'location-style', plugin_dir_url( __FILE__ ) . 'location-style.css' );
}
add_action( 'wp_enqueue_scripts', 'local_sharing_enqueue_scripts' );

// Add Privacy Setting
function local_sharing_add_privacy_setting() {
    add_settings_field(
        'local_sharing_setting',
        'Local Sharing',
        'local_sharing_render_privacy_setting',
        'privacy',
        'default',
        array( 'label_for' => 'local_sharing_setting' )
    );
    register_setting( 'privacy', 'local_sharing_setting' );
}
add_action( 'admin_init', 'local_sharing_add_privacy_setting' );

// Render Privacy Setting
function local_sharing_render_privacy_setting() {
    ?>
    <label for="local_sharing_setting">
        <input type="checkbox" name="local_sharing_setting" id="local_sharing_setting" value="1" <?php checked( get_option( 'local_sharing_setting' ), 1 ); ?>>
        Enable Local Sharing
    </label>
    <?php
}

// Save Privacy Setting
function local_sharing_save_privacy_setting( $old_value, $value ) {
    if ( isset( $_POST['local_sharing_setting'] ) && $_POST['local_sharing_setting'] ) {
        $value = 1;
    } else {
        $value = 0;
    }
    return $value;
}
add_filter( 'pre_update_option_local_sharing_setting', 'local_sharing_save_privacy_setting', 10, 2 );

// Handle Ajax Request
function local_sharing_handle_ajax_request() {
    if ( isset( $_POST['action'] ) && $_POST['action'] === 'local_sharing_request_location' ) {
        $data = array(
            'ip' => $_SERVER['REMOTE_ADDR'],
            'location' => $_POST['location'],
            'timestamp' => current_time( 'timestamp' )
        );
        $log = get_option( 'local_privacy_log', array() );
        array_push( $log, $data );
        update_option( 'local_privacy_log', $log );
        echo 'success';
    }
    wp_die();
}
add_action( 'wp_ajax_local_sharing_request_location', 'local_sharing_handle_ajax_request' );
add_action( 'wp_ajax_nopriv_local_sharing_request_location', 'local_sharing_handle_ajax_request' );

function local_sharing_save_location() {
    $remote_addr = $_SERVER['REMOTE_ADDR'];
    $geo_data = array();
    if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
        $geo_data['latitude'] = $_POST['latitude'];
        $geo_data['longitude'] = $_POST['longitude'];
        $geo_data['accuracy'] = isset($_POST['accuracy']) ? $_POST['accuracy'] : null;
        $geo_data['address'] = isset($_POST['address']) ? $_POST['address'] : null;
        
        // Get additional location data using Google Maps Geocoding API
        $api_key = 'AIzaSyBs5CTk8t1VvTKyTYZ7dIwyd4WetqW7jLc';
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$geo_data['latitude'].','.$geo_data['longitude'].'&key='.$api_key;
        $response = wp_remote_get($url);
        if (is_array($response)) {
            $body = $response['body'];
            $data = json_decode($body);
            if ($data->status === 'OK') {
                $geo_data['address'] = $data->results[0]->formatted_address;
                $geo_data['types'] = $data->results[0]->types;
            }
        }
        
        // Append location data to log file
        $log_file = plugin_dir_path( __FILE__ ) . 'logs/log.txt';
        $log_message = date('Y-m-d H:i:s') . ' - IP: ' . $remote_addr . ' - Latitude: ' . $geo_data['latitude'] . ' - Longitude: ' . $geo_data['longitude'] . ' - Accuracy: ' . $geo_data['accuracy'] . ' - Address: ' . $geo_data['address'] . ' - Types: ' . implode(',', $geo_data['types']) . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    }
}
// Lưu thông tin vị trí vào file log
function local_sharing_log_location($location) {
    $log = date('Y-m-d H:i:s') . ' - ' . json_encode($location) . "\n";
    $file = plugin_dir_path(__FILE__) . 'location-sharing.log';
    file_put_contents($file, $log, FILE_APPEND);
}

// Lấy thông tin vị trí từ API của Google
function local_sharing_get_location($latitude, $longitude) {
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . $latitude . ',' . $longitude . '&key=' . LOCAL_SHARING_API_KEY;
    $response = wp_remote_get($url);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    return isset($data['results'][0]) ? $data['results'][0]['formatted_address'] : 'Unknown';
}

// Lưu thông tin vị trí vào cơ sở dữ liệu
function local_sharing_save_location() {
    if (isset($_POST['local_sharing_latitude'], $_POST['local_sharing_longitude'])) {
        $latitude = sanitize_text_field($_POST['local_sharing_latitude']);
        $longitude = sanitize_text_field($_POST['local_sharing_longitude']);
        $location = array(
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => local_sharing_get_location($latitude, $longitude)
        );
        local_sharing_log_location($location);
    }
}

add_action('admin_post_local_sharing_save_location', 'local_sharing_save_location');

function lsp_get_location($ip) {
    $api_key = 'AIzaSyBs5CTk8t1VvTKyTYZ7dIwyd4WetqW7jLc'; // Thay YOUR_API_KEY bằng API Key của bạn
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";
    $data = file_get_contents($url);
    $result = json_decode($data, true);

    if ($result['status'] == 'OK') {
        $location = $result['results'][0]['formatted_address'];
    } else {
        $location = '';
    }

    return $location;
}

function lsp_write_to_log($message) {
    $log_file = WP_CONTENT_DIR . '/logs/location-sharing.log';
    $ip = lsp_get_client_ip();
    $location = lsp_get_location($ip);
    $log = date('Y-m-d H:i:s') . " [$ip] [$location] $message\n";
    file_put_contents($log_file, $log, FILE_APPEND);
}

add_action('admin_menu', 'local_sharing_menu');

function local_sharing_menu() {
    add_menu_page('Location Sharing Log', 'Location Sharing Log', 'manage_options', 'location-sharing-log', 'local_sharing_log_page');
}

if (!function_exists('local_sharing_log_page')) {
    function local_sharing_log_page() {
        // code của bạn để tạo trang log ở đây
    }
}
function local_sharing_log_page() {
    $log_file = WP_CONTENT_DIR . '/location-sharing.log';
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);

    echo '<div class="wrap">';
    echo '<h1>Location Sharing Log</h1>';

    echo '<table class="widefat">';
    echo '<thead><tr><th>Time</th><th>IP Address</th><th>Latitude</th><th>Longitude</th><th>Address</th></tr></thead>';
    echo '<tbody>';
    foreach ($log_lines as $log_line) {
        $log_data = json_decode($log_line, true);
        if ($log_data) {
            echo '<tr>';
            echo '<td>' . date('Y-m-d H:i:s', $log_data['time']) . '</td>';
            echo '<td>' . $log_data['ip'] . '</td>';
            echo '<td>' . $log_data['lat'] . '</td>';
            echo '<td>' . $log_data['lng'] . '</td>';
            echo '<td>' . $log_data['address'] . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table>';

    echo '</div>';
}

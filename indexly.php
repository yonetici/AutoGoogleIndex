<?php
/*
Plugin Name: Auto Google Indexing
Description: Yeni içerikler için otomatik Google indeksleme talebi gönderir
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

class AutoGoogleIndexing {
    private $service_account_file;
    private $options;

    public function __construct() {
        $this->service_account_file = plugin_dir_path(__FILE__) . 'service-account.json';
        $this->options = get_option('auto_google_indexing_settings');
        
        // Hook'ları ekle
        add_action('publish_post', array($this, 'handle_content_publish'), 10, 2);
        add_action('transition_post_status', array($this, 'handle_question_status_change'), 10, 3);
        
        // Admin menüsünü ekle
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Dosya yükleme işlemi
        add_action('admin_post_upload_service_account', array($this, 'upload_service_account'));
    }

    public function handle_content_publish($post_id, $post) {
        if ($post->post_status == 'publish') {
            $url = get_permalink($post_id);
            $this->send_indexing_request($url);
        }
    }

public function handle_question_status_change($new_status, $old_status, $post) {
    if ($post->post_type === 'question') {
        if ($new_status === 'publish') {
            // Yayınlanan soruyla ilgili işlem
            //error_log("Question with ID {$post->ID} has been published.");
            $url = get_permalink($post->ID);
            $this->send_indexing_request($url);
        } elseif ($new_status === 'moderate') {
            // Moderasyona alınan soruyla ilgili işlem
           // error_log("Question with ID {$post->ID} is under moderation.");
        }
    }
}
private function send_indexing_request($url) {
    try {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            throw new Exception('Access token alınamadı');
        }

        $api_url = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'body' => json_encode(array(
                'url' => $url,
                'type' => 'URL_UPDATED'
            ))
        );

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body);

        if (isset($result->error)) {
            throw new Exception($result->error->message);
        }

        // Başarılı işlem günlüğü
        $this->log_indexing_result($url, 'Başarılı');

    } catch (Exception $e) {
        $this->log_indexing_result($url, 'Hata: ' . $e->getMessage());
    }
}

private function log_indexing_result($url, $result) {
    $log_entry = sprintf(
        "[%s] URL: %s - Sonuç: %s\n",
        date("Y-m-d H:i:s"),
        $url,
        $result
    );
    file_put_contents(plugin_dir_path(__FILE__) . 'indexing_log.txt', $log_entry, FILE_APPEND);
}
	private function get_access_token() {
        if (!file_exists($this->service_account_file)) {
            error_log('Service account JSON dosyası bulunamadı');
            return false;
        }

        $credentials = json_decode(file_get_contents($this->service_account_file), true);

        // JWT token oluştur
        $now = time();
        $jwt_header = base64_encode(json_encode(array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        )));

        $jwt_claim = base64_encode(json_encode(array(
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        )));

        $private_key = openssl_pkey_get_private($credentials['private_key']);
        $signature = '';
        openssl_sign($jwt_header . '.' . $jwt_claim, $signature, $private_key, 'SHA256');
        $jwt_signature = base64_encode($signature);

        $jwt = $jwt_header . '.' . $jwt_claim . '.' . $jwt_signature;

        // Access token al
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            )
        ));

        if (is_wp_error($response)) {
            error_log('Token alınamadı: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return isset($body->access_token) ? $body->access_token : false;
    }

public function add_admin_menu() {
    add_options_page(
        'Auto Google Indexing Ayarları',
        'Auto Google Indexing',
        'manage_options',
        'auto-google-indexing',
        array($this, 'settings_page')
    );

    add_menu_page(
        'Indexing Logları',
        'Indexing Logları',
        'manage_options',
        'indexing-logs',
        array($this, 'display_logs_page')
    );
}
public function display_logs_page() {
    // Eğer logları temizle butonuna tıklandıysa
    if (isset($_POST['clear_logs']) && check_admin_referer('clear_logs_action', 'clear_logs_nonce')) {
        $log_file = plugin_dir_path(__FILE__) . 'indexing_log.txt';
        if (file_exists($log_file)) {
            file_put_contents($log_file, ''); // Log dosyasını sıfırla
            echo '<div class="updated"><p>Log file successfully cleared.</p></div>';
        } else {
            echo '<div class="error"><p>Log file not found.</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h2>Indexing Logs</h2>
        <form method="post">
            <?php wp_nonce_field('clear_logs_action', 'clear_logs_nonce'); ?>
            <input type="submit" name="clear_logs" class="button button-secondary" value="Clear Logs" />
        </form>
        <table class="widefat">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>Date/Time</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $log_file = plugin_dir_path(__FILE__) . 'indexing_log.txt';
                if (file_exists($log_file)) {
                    $logs = file($log_file, FILE_IGNORE_NEW_LINES);
                    if (!empty($logs)) {
                        foreach ($logs as $log) {
                            list($datetime, $url, $result) = explode(' - ', $log);
                            echo '<tr>';
                            echo '<td>' . esc_html($url) . '</td>';
                            echo '<td>' . esc_html($datetime) . '</td>';
                            echo '<td>' . esc_html($result) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">No logs yet.</td></tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">Log file not found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

    public function register_settings() {
        register_setting('auto_google_indexing', 'auto_google_indexing_settings');
        
        add_settings_section(
            'auto_google_indexing_section',
            'API Ayarları',
            null,
            'auto-google-indexing'
        );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>Auto Google Indexing Settings</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php
                settings_fields('auto_google_indexing');
                do_settings_sections('auto-google-indexing');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Service Account JSON Status</th>
                        <td>
                            <?php
                            if (file_exists($this->service_account_file)) {
                                echo '<span style="color:green;">JSON dosyası mevcut</span>';
                            } else {
                                echo '<span style="color:red;">JSON dosyası eksik!</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">JSON Dosyası Yükle</th>
                        <td>
                            <input type="file" name="service_account_json" />
                            <input type="hidden" name="action" value="upload_service_account" />
                            <?php submit_button('Yükle', 'secondary', 'submit', false); ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function upload_service_account() {
        if (!current_user_can('manage_options')) {
            wp_die('Blocked');
        }

        if (!empty($_FILES['service_account_json']['tmp_name'])) {
            $uploaded_file = $_FILES['service_account_json']['tmp_name'];
            $destination = $this->service_account_file;

            if (move_uploaded_file($uploaded_file, $destination)) {
                add_settings_error('auto_google_indexing', 'upload_success', 'JSON dosyası başarıyla yüklendi.', 'updated');
            } else {
                add_settings_error('auto_google_indexing', 'upload_error', 'JSON dosyası yüklenirken hata oluştu.', 'error');
            }
        }

        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('options-general.php?page=auto-google-indexing'));
        exit;
    }
}

new AutoGoogleIndexing();

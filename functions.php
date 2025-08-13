<?php
if (!defined('ABSPATH')) exit;

// Create DB table on activation
function create_myly_url_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'myly_urls';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        short_code varchar(10) NOT NULL UNIQUE,
        long_url text NOT NULL,
        created_date datetime DEFAULT CURRENT_TIMESTAMP,
        clicks int(11) DEFAULT 0,
        user_ip varchar(45),
        referrer varchar(255),
        PRIMARY KEY (id),
        KEY short_code (short_code)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_myly_url_table');

// Generate unique short code
function generate_short_code($length = 6) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'myly_urls';
    do {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $short_code = '';
        for ($i = 0; $i < $length; $i++) {
            $short_code .= $characters[rand(0, strlen($characters) - 1)];
        }
    } while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE short_code = %s", $short_code)) > 0);
    return $short_code;
}

// AJAX: Shorten URL
function handle_url_shortening() {
    check_ajax_referer('shorten_url_nonce', 'nonce');
    $long_url = sanitize_url($_POST['long_url']);
    if (!filter_var($long_url, FILTER_VALIDATE_URL)) wp_send_json_error(['message' => 'Invalid URL']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'myly_urls';
    $existing = $wpdb->get_row($wpdb->prepare("SELECT short_code FROM $table_name WHERE long_url = %s", $long_url));
    $short_code = $existing ? $existing->short_code : generate_short_code();

    if (!$existing) {
        $referrer = !empty($_SERVER['HTTP_REFERER']) ? esc_url($_SERVER['HTTP_REFERER']) : null;
        $wpdb->insert($table_name, [
            'short_code' => $short_code,
            'long_url' => $long_url,
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'referrer' => $referrer
        ]);
    }

    wp_send_json_success([
        'short_url' => home_url('/') . $short_code,
        'short_code' => $short_code
    ]);
}
add_action('wp_ajax_shorten_url', 'handle_url_shortening');
add_action('wp_ajax_nopriv_shorten_url', 'handle_url_shortening');

// AJAX: Contact form
function handle_contact_form() {
    check_ajax_referer('contact_form_nonce', 'nonce');
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $subject = sanitize_text_field($_POST['subject']);
    $message = sanitize_textarea_field($_POST['message']);
    $to = get_option('admin_email');
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    wp_mail($to, "Myly Contact: $subject", "Name: $name\nEmail: $email\nMessage:\n$message", $headers)
        ? wp_send_json_success()
        : wp_send_json_error();
}
add_action('wp_ajax_submit_contact', 'handle_contact_form');
add_action('wp_ajax_nopriv_submit_contact', 'handle_contact_form');

// Handle redirects & track clicks
function handle_myly_redirects() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'myly_urls';
    $short_code = trim($_SERVER['REQUEST_URI'], '/?');
    if (strlen($short_code) > 10 || preg_match('/^(wp-|admin)/', $short_code)) return;

    $result = $wpdb->get_row($wpdb->prepare("SELECT long_url FROM $table_name WHERE short_code = %s", $short_code));
    if ($result) {
        $referrer = !empty($_SERVER['HTTP_REFERER']) ? esc_url($_SERVER['HTTP_REFERER']) : null;
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET clicks = clicks + 1, referrer = COALESCE(%s, referrer) WHERE short_code = %s",
            $referrer, $short_code
        ));
        wp_redirect($result->long_url, 301);
        exit;
    }
}
add_action('init', 'handle_myly_redirects');

// Rewrite rules
add_action('init', function () {
    add_rewrite_rule('^([a-zA-Z0-9]{6,10})/?', 'index.php?myly_redirect=$matches[1]', 'top');
});
add_filter('query_vars', function ($vars) {
    $vars[] = 'myly_redirect';
    return $vars;
});

// Admin dashboard
add_action('admin_menu', function () {
    add_menu_page(
        'Myly Dashboard',
        'Myly URLs',
        'manage_options',
        'myly-dashboard',
        'myly_admin_page',
        'dashicons-admin-links',
        30
    );
});

function myly_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'myly_urls';
    $urls = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_date DESC");
    $site_url = home_url('/');
    ?>
    <div class="wrap">
        <h1>Myly URL Shortener - Dashboard</h1>
        <p>Manage all shortened URLs and view analytics.</p>
        <?php if ($urls): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Short URL</th>
                    <th>Long URL</th>
                    <th>Clicks</th>
                    <th>Created</th>
                    <th>Referrer</th>
                    <th>QR Code</th>
                    <th>Copy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urls as $url): ?>
                <tr>
                    <td><?php echo $url->id; ?></td>
                    <td><a href="<?php echo $site_url . $url->short_code; ?>" target="_blank"><?php echo $url->short_code; ?></a></td>
                    <td><small><?php echo esc_url($url->long_url); ?></small></td>
                    <td><?php echo $url->clicks; ?></td>
                    <td><?php echo $url->created_date; ?></td>
                    <td><?php echo $url->referrer ?: 'Direct'; ?></td>
                    <td>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo urlencode($site_url . $url->short_code); ?>"
                             alt="QR Code" width="40" height="40">
                    </td>
                    <td>
                        <button class="button" onclick="copyToClipboard('<?php echo $site_url . $url->short_code; ?>')">Copy</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No URLs have been shortened yet.</p>
        <?php endif; ?>
    </div>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard: ' + text);
            });
        }
    </script>
    <?php
}

// Enqueue scripts
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('myly-js', get_template_directory_uri() . '/assets/js/scripts.js', [], '1.1', true);
    wp_localize_script('myly-js', 'myly_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('shorten_url_nonce'),
        'contact_nonce' => wp_create_nonce('contact_form_nonce'),
        'home_url' => home_url('/')
    ]);
});

<?php
defined('ABSPATH') || exit;

final class KMD_Admin_Tools {
    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_kmd_upload', [__CLASS__, 'upload']);
        add_action('admin_post_kmd_override', [__CLASS__, 'override']);
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=kmd_experience', 'Private files and access', 'Files & access',
            'manage_options', 'kmd-tools', [__CLASS__, 'screen']);
    }

    private static function authorize($action) {
        if (!current_user_can('manage_options')) wp_die('Forbidden.', '', ['response' => 403]);
        check_admin_referer($action);
    }

    private static function id($key) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? absint($_POST[$key]) : 0;
    }

    private static function finish() {
        wp_safe_redirect(admin_url('edit.php?post_type=kmd_experience&page=kmd-tools&saved=1'));
        exit;
    }

    public static function screen() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>Private files and participant access</h1>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Saved.</p></div>';
        echo '<h2>Upload private material</h2>';
        if (!KMD_Private_Media::root()) {
            echo '<p>Private storage is not configured. Ask your host to configure KMD_PRIVATE_DIR outside all public web roots.</p>';
        } else {
            echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('kmd_upload');
            echo '<input type="hidden" name="action" value="kmd_upload">';
            KMD_Core::post_choices('lesson', 'Content section', KMD_Core::LESSON);
            echo '<p><label>Audio or PDF <input type="file" name="material" accept=".mp3,.m4a,.ogg,.wav,.pdf" required></label></p>';
            submit_button('Upload private file');
            echo '</form>';
        }
        echo '<h2>Participant access</h2><p>Grant or block access for an existing WordPress account, or restore normal purchase-based access. The experience expiration still applies.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('kmd_override');
        echo '<input type="hidden" name="action" value="kmd_override">';
        KMD_Core::post_choices('experience', 'Experience', KMD_Core::TYPE);
        echo '<p><label>Participant account email <input type="email" name="participant_email" required></label></p>';
        echo '<p><label>Access <select name="decision"><option value="grant">Grant access</option><option value="deny">Block access</option><option value="normal">Restore purchase rules</option></select></label></p>';
        submit_button('Save access');
        echo '</form></div>';
    }

    public static function upload() {
        self::authorize('kmd_upload');
        $lesson = self::id('lesson');
        $root = KMD_Private_Media::root();
        if (!$root || get_post_type($lesson) !== KMD_Core::LESSON || !current_user_can('edit_post', $lesson)) wp_die('Invalid content section or private storage.');
        $file = $_FILES['material'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])) wp_die('Upload failed.');
        $size = filesize($file['tmp_name']);
        if (!$size || $size > wp_max_upload_size()) wp_die('Invalid file size.');
        $name = sanitize_file_name($file['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $types = ['mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'video/mp4', 'audio/x-m4a'],
            'ogg' => ['audio/ogg', 'application/ogg'], 'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'], 'pdf' => ['application/pdf']];
        if (!isset($types[$ext]) || !class_exists('finfo')) wp_die('Unsupported file type.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $types[$ext], true)) wp_die('File type does not match its extension.');
        $key = bin2hex(random_bytes(16));
        $path = $root . DIRECTORY_SEPARATOR . $key;
        if (file_exists($path) || !move_uploaded_file($file['tmp_name'], $path)) wp_die('Unable to store file.');
        if (!chmod($path, 0600)) { unlink($path); wp_die('Unable to secure file permissions.'); }
        $files = KMD_Private_Media::files($lesson);
        $files[$key] = ['name' => $name];
        if (!update_post_meta($lesson, '_kmd_files', $files)) {
            unlink($path);
            wp_die('Unable to save file record.');
        }
        self::finish();
    }

    public static function override() {
        self::authorize('kmd_override');
        $experience = self::id('experience');
        $email = isset($_POST['participant_email']) && is_string($_POST['participant_email']) ? sanitize_email(wp_unslash($_POST['participant_email'])) : '';
        $account = $email ? get_user_by('email', $email) : false;
        $user = $account ? (int)$account->ID : 0;
        $decision = isset($_POST['decision']) && is_string($_POST['decision']) ? wp_unslash($_POST['decision']) : '';
        if (get_post_type($experience) !== KMD_Core::TYPE || !get_userdata($user)
            || !in_array($decision, ['grant', 'deny', 'normal'], true)) wp_die('Invalid access settings.');
        $key = '_kmd_override_' . $experience;
        if ($decision === 'normal') delete_user_meta($user, $key);
        else update_user_meta($user, $key, $decision);
        $expected = $decision === 'normal' ? '' : $decision;
        if (get_user_meta($user, $key, true) !== $expected) wp_die('Unable to save access settings.');
        self::finish();
    }
}

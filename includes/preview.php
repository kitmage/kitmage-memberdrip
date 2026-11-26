<?php
defined('ABSPATH') || exit;

final class KMD_Preview {
    public static function boot() {
        add_filter('post_row_actions', [__CLASS__, 'row_action'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function row_action($actions, $post) {
        if ($post->post_type !== KMD_Core::TYPE || $post->post_status === 'trash'
            || !current_user_can('manage_options') || !current_user_can('edit_post', $post->ID)) return $actions;
        $url = add_query_arg(['post_type'=>KMD_Core::TYPE, 'page'=>'kmd-preview', 'experience'=>$post->ID],
            admin_url('edit.php'));
        $actions['kmd_preview'] = '<a href="' . esc_url(wp_nonce_url($url, 'kmd_preview_' . $post->ID)) . '">Preview schedule</a>';
        return $actions;
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=kmd_experience', 'Experience preview', 'Preview', 'manage_options',
            'kmd-preview', [__CLASS__, 'screen']);
    }

    private static function date_label($stamp) {
        return $stamp === false ? 'Not configured' : ($stamp === 0 ? 'Immediately' :
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $stamp));
    }

    public static function screen() {
        if (!current_user_can('manage_options')) wp_die('Forbidden.', '', ['response'=>403]);
        nocache_headers();
        $id = isset($_GET['experience']) && is_string($_GET['experience']) ? absint($_GET['experience']) : 0;
        echo '<div class="wrap"><h1>Experience preview</h1>';
        if (!$id) {
            echo '<p>Choose Preview schedule from an experience in the Experiences list.</p></div>';
            return;
        }
        check_admin_referer('kmd_preview_' . $id);
        $experience = get_post($id);
        if (!$experience || $experience->post_type !== KMD_Core::TYPE || !current_user_can('edit_post', $id)) wp_die('Invalid experience.');
        $mode = isset($_POST['preview_mode']) && $_POST['preview_mode'] === 'all' ? 'all' : 'schedule';
        $as_of = time();
        $start = time();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('kmd_preview_form_' . $id, 'kmd_preview_form_nonce');
            foreach (['as_of','enrollment'] as $field) {
                $raw = isset($_POST[$field]) && is_string($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
                $value = KMD_Core::timestamp($raw);
                if ($value === false || !$value) wp_die('Enter valid preview dates.');
                if ($field === 'as_of') $as_of = $value;
                else $start = $value;
            }
        }
        echo '<p>This is a read-only administrator preview. It does not grant access, send messages, display private reflections, or bypass the media endpoint.</p>';
        echo '<form method="post">';
        wp_nonce_field('kmd_preview_form_' . $id, 'kmd_preview_form_nonce');
        echo '<p><label>Simulated viewing time <input type="datetime-local" name="as_of" required value="' . esc_attr(wp_date('Y-m-d\TH:i', $as_of)) . '"></label></p>';
        echo '<p><label>Simulated enrollment time <input type="datetime-local" name="enrollment" required value="' . esc_attr(wp_date('Y-m-d\TH:i', $start)) . '"></label></p>';
        echo '<p><label>Mode <select name="preview_mode"><option value="schedule"' . selected($mode,'schedule',false) . '>Simulated schedule</option><option value="all"' . selected($mode,'all',false) . '>Show all written material</option></select></label></p>';
        submit_button('Update preview', 'secondary');
        echo '</form><hr><h2>' . esc_html($experience->post_title) . '</h2>';
        $expiry = KMD_Core::timestamp((string)get_post_meta($id,'_kmd_expiry',true));
        $active = $experience->post_status === 'publish' && $start <= $as_of && $expiry !== false && (!$expiry || $as_of < $expiry);
        echo '<p>Experience status: ' . esc_html($experience->post_status) . ' | Closes: ' . esc_html($expiry === 0 ? 'No expiry' : self::date_label($expiry)) . '</p>';
        if (!$active) echo '<p><strong>Simulated access is closed.</strong></p>';
        if ($active || $mode === 'all') echo wpautop(wp_kses_post(strip_shortcodes($experience->post_content)));
        $lessons = get_posts(['post_type'=>KMD_Core::LESSON,'post_status'=>['publish','draft','private','pending','future'],
            'numberposts'=>-1,'meta_key'=>'_kmd_parent','meta_value'=>$id,'orderby'=>['menu_order'=>'ASC','ID'=>'ASC']]);
        foreach ($lessons as $lesson) {
            $release = KMD_Core::release_for_start($lesson->ID, $start);
            $open = $active && $lesson->post_status === 'publish' && $release !== false && (!$release || $release <= $as_of);
            echo '<hr><h3>' . esc_html($lesson->post_title) . '</h3><p>' . esc_html($lesson->post_status) .
                ' | ' . esc_html(self::date_label($release)) . ' | ' . ($open ? 'Available' : 'Locked') . '</p>';
            if ($open || $mode === 'all') {
                echo wpautop(wp_kses_post(strip_shortcodes($lesson->post_content)));
                foreach (KMD_Private_Media::files($lesson->ID) as $file) {
                    if (is_array($file) && isset($file['name'])) echo '<p>Private file: ' . esc_html($file['name']) . '</p>';
                }
            }
        }
        echo '</div>';
    }
}

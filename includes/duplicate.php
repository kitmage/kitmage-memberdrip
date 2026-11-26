<?php
defined('ABSPATH') || exit;

final class KMD_Duplicate {
    public static function boot() {
        add_filter('post_row_actions', [__CLASS__, 'row_action'], 10, 2);
        add_action('admin_post_kmd_duplicate', [__CLASS__, 'duplicate']);
    }

    public static function row_action($actions, $post) {
        if ($post->post_type !== KMD_Core::TYPE || $post->post_status === 'trash'
            || !current_user_can('manage_options') || !current_user_can('edit_post', $post->ID)) return $actions;
        $url = wp_nonce_url(add_query_arg(['action'=>'kmd_duplicate','experience'=>$post->ID], admin_url('admin-post.php')), 'kmd_duplicate_' . $post->ID);
        $actions['kmd_duplicate'] = '<a href="' . esc_url($url) . '">Duplicate as draft</a>';
        return $actions;
    }

    private static function copy_post($source, $title, $meta) {
        return wp_insert_post(wp_slash([
            'post_type'=>$source->post_type, 'post_status'=>'draft',
            'post_title'=>$title, 'post_content'=>$source->post_content,
            'post_excerpt'=>$source->post_excerpt, 'menu_order'=>(int)$source->menu_order,
            'post_author'=>get_current_user_id(), 'comment_status'=>'closed', 'ping_status'=>'closed',
            'meta_input'=>$meta,
        ]), true);
    }

    public static function duplicate() {
        $id = isset($_GET['experience']) && is_string($_GET['experience']) ? absint($_GET['experience']) : 0;
        if (!current_user_can('manage_options') || !current_user_can('edit_post', $id)) wp_die('Forbidden.', '', ['response'=>403]);
        check_admin_referer('kmd_duplicate_' . $id);
        $source = get_post($id);
        if (!$source || $source->post_type !== KMD_Core::TYPE || $source->post_status === 'trash') wp_die('Invalid experience.');
        $lessons = get_posts(['post_type'=>KMD_Core::LESSON, 'post_status'=>['publish','draft','pending','private','future'],
            'numberposts'=>-1, 'meta_key'=>'_kmd_parent', 'meta_value'=>$id, 'orderby'=>['menu_order'=>'ASC','ID'=>'ASC']]);
        // No product association, access rules, media, comments or email history are copied.
        // Invalid date sentinel deliberately denies access until the administrator chooses a date or clears it.
        $meta = ['_kmd_product'=>0, '_kmd_expiry'=>'configure', '_kmd_email_enabled'=>'0', '_kmd_email_page'=>0];
        foreach (['welcome','release','closure'] as $event) {
            foreach (['subject','body'] as $field) {
                $key = '_kmd_email_' . $event . '_' . $field;
                $meta[$key] = get_post_meta($id, $key, true);
            }
        }
        $new = self::copy_post($source, $source->post_title . ' (copy)', $meta);
        if (is_wp_error($new) || !$new) wp_die('Unable to duplicate experience.');
        $created = [$new];
        foreach ($lessons as $lesson) {
            $copy = self::copy_post($lesson, $lesson->post_title, [
                '_kmd_parent'=>$new, '_kmd_release'=>'configure',
                '_kmd_sharing'=>get_post_meta($lesson->ID, '_kmd_sharing', true) === '1' ? '1' : '0',
            ]);
            if (is_wp_error($copy) || !$copy) {
                foreach (array_reverse($created) as $created_id) wp_delete_post($created_id, true);
                wp_die('Unable to duplicate all sections. The incomplete copy was discarded.');
            }
            $created[] = $copy;
        }
        wp_safe_redirect(admin_url('post.php?post=' . $new . '&action=edit'));
        exit;
    }
}

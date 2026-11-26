<?php
defined('ABSPATH') || exit;

final class KMD_Sharing {
    const VERSION = '1';
    public static function boot() {
        add_action('admin_init', [__CLASS__, 'install']);
        add_filter('wp_privacy_personal_data_exporters', [__CLASS__, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [__CLASS__, 'erasers']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_kmd_share', [__CLASS__, 'submit']);
        add_action('admin_post_kmd_moderate', [__CLASS__, 'moderate']);
    }
    public static function exporters($exporters) {
        $exporters['kmd-reflections'] = ['exporter_friendly_name'=>'Member Drip reflections', 'callback'=>[__CLASS__, 'export_personal']];
        return $exporters;
    }

    public static function erasers($erasers) {
        $erasers['kmd-reflections'] = ['eraser_friendly_name'=>'Member Drip reflections', 'callback'=>[__CLASS__, 'erase_personal']];
        return $erasers;
    }

    public static function export_personal($email, $page = 1) {
        $account = get_user_by('email', $email);
        if (!$account || !self::ready()) return ['data'=>[], 'done'=>true];
        global $wpdb;
        $table = self::table();
        $offset = (max(1, (int)$page)-1)*100;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE author=%d ORDER BY id LIMIT 100 OFFSET %d", $account->ID, $offset));
        $data = [];
        foreach ($rows ?: [] as $row) {
            $data[] = ['group_id'=>'kmd-reflections', 'group_label'=>'Member Drip reflections',
                'item_id'=>'kmd-reflection-' . $row->id, 'data'=>[
                    ['name'=>'Content section','value'=>get_the_title($row->lesson)],
                    ['name'=>'Reflection','value'=>$row->body],
                    ['name'=>'Submitted (UTC)','value'=>$row->created],
                    ['name'=>'Status','value'=>$row->approved ? 'Approved' : 'Pending'],
                ]];
        }
        return ['data'=>$data,'done'=>count($rows ?: [])<100];
    }

    public static function erase_personal($email, $page = 1) {
        $account = get_user_by('email', $email);
        $result = ['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];
        if (!$account || !self::ready()) return $result;
        global $wpdb;
        $table = self::table();
        // Tombstones preserve other participants' replies without retaining the erased author's text or identity.
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE author=%d ORDER BY id LIMIT 100", $account->ID));
        foreach ($ids ?: [] as $id) {
            $updated = $wpdb->update($table, ['author'=>0,'body'=>'[Reflection removed]','created'=>'1970-01-01 00:00:00'], ['id'=>$id,'author'=>$account->ID], ['%d','%s','%s'], ['%d','%d']);
            if ($updated === false) {
                $result['items_retained'] = true;
                $result['messages'][] = 'A reflection could not be erased. Please contact the site administrator.';
            } elseif ($updated) $result['items_removed'] = true;
        }
        $result['done'] = $result['items_retained'] || count($ids ?: [])<100;
        return $result;
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'kmd_shares';
    }
    public static function install() {
        if (!current_user_can('manage_options') || get_option('kmd_shares_schema') === self::VERSION) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lesson bigint(20) unsigned NOT NULL,
            author bigint(20) unsigned NOT NULL,
            parent bigint(20) unsigned NOT NULL DEFAULT 0,
            body text NOT NULL,
            approved tinyint(1) NOT NULL DEFAULT 0,
            created datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY lesson_status (lesson,approved,id),
            KEY author_created (author,created)
        ) $collation;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            update_option('kmd_shares_schema', self::VERSION, false);
        }
    }
    private static function ready() {
        return get_option('kmd_shares_schema') === self::VERSION;
    }
    private static function enabled($lesson) {
        return self::ready() && get_post_meta($lesson, '_kmd_sharing', true) === '1';
    }
    private static function id($key) {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? absint($_POST[$key]) : 0;
    }
    public static function render($lesson) {
        $user = get_current_user_id();
        if (!self::enabled($lesson) || !KMD_Core::lesson_allowed($lesson, $user)) return '';
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,author,parent,body FROM $table WHERE lesson=%d AND approved=1 ORDER BY id ASC LIMIT 500", $lesson));
        $out = '<div class="kmd-sharing"><h4>Reflections &amp; sharing</h4><p>Share only what you feel comfortable sharing with other participants. Your display name will be visible. Submissions are moderated.</p>';
        $groups = [];
        foreach ($rows ?: [] as $row) $groups[(int)$row->parent][] = $row;
        foreach ($groups[0] ?? [] as $row) {
            $out .= self::entry($row);
            foreach ($groups[(int)$row->id] ?? [] as $reply) $out .= '<div class="kmd-reply">' . self::entry($reply) . '</div>';
            $out .= self::form($lesson, (int)$row->id);
        }
        if (count($rows ?: []) >= 500) $out .= '<p>Only the first 500 approved reflections and replies are shown.</p>';
        return $out . self::form($lesson, 0) . '</div>';
    }
    private static function entry($row) {
        $author = $row->author ? get_userdata($row->author) : false;
        return '<article><strong>' . esc_html($author ? $author->display_name : 'Anonymous participant') . '</strong>' .
            wpautop(esc_html($row->body)) . '</article>';
    }
    private static function form($lesson, $parent) {
        $label = $parent ? 'Reply' : 'Share a reflection';
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">' .
            wp_nonce_field('kmd_share_' . $lesson, '_wpnonce', false, false) .
            '<input type="hidden" name="action" value="kmd_share"><input type="hidden" name="lesson" value="' . (int)$lesson .
            '"><input type="hidden" name="parent" value="' . $parent . '"><p><label>' . $label .
            '<br><textarea name="body" maxlength="4000" required rows="3"></textarea></label></p><button type="submit">' . $label . '</button></form>';
    }
    public static function submit() {
        $lesson = self::id('lesson');
        $user = get_current_user_id();
        if (!self::enabled($lesson) || !KMD_Core::lesson_allowed($lesson, $user)) wp_die('Not available.', '', ['response'=>403]);
        check_admin_referer('kmd_share_' . $lesson);
        $body = isset($_POST['body']) && is_string($_POST['body']) ? trim(sanitize_textarea_field(wp_unslash($_POST['body']))) : '';
        if ($body === '' || strlen($body) > 16000) wp_die('Please provide a shorter reflection.');
        global $wpdb;
        $table = self::table();
        $parent = self::id('parent');
        if ($parent && !$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id=%d AND lesson=%d AND approved=1 AND parent=0", $parent, $lesson))) wp_die('Reply unavailable.');
        $recent = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE author=%d AND created >= %s", $user, gmdate('Y-m-d H:i:s', time()-60)));
        if ((int)$recent >= 5) wp_die('Please try again later.', '', ['response'=>429]);
        if (!$wpdb->insert($table, ['lesson'=>$lesson,'author'=>$user,'parent'=>$parent,'body'=>$body,'approved'=>0,'created'=>gmdate('Y-m-d H:i:s')], ['%d','%d','%d','%s','%d','%s'])) wp_die('Unable to save reflection.');
        $back = wp_get_referer();
        wp_safe_redirect($back ?: home_url('/'));
        exit;
    }
    public static function menu() {
        add_submenu_page('edit.php?post_type=kmd_experience', 'Moderate reflections', 'Reflections', 'manage_options', 'kmd-sharing', [__CLASS__, 'screen']);
    }
    public static function screen() {
        if (!current_user_can('manage_options') || !self::ready()) return;
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY approved ASC,id DESC LIMIT 100");
        echo '<div class="wrap"><h1>Private reflections</h1><p>Newest 100 pending or approved submissions, pending first.</p>';
        foreach ($rows ?: [] as $row) {
            echo '<hr><p>Section: ' . esc_html(get_the_title($row->lesson)) . ' | ' . ($row->approved ? 'Approved' : 'Pending') . '</p>' . self::entry($row);
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('kmd_moderate_' . $row->id);
            echo '<input type="hidden" name="action" value="kmd_moderate"><input type="hidden" name="id" value="' . (int)$row->id . '"><button class="button" name="decision" value="approve">Approve</button> <button class="button" name="decision" value="hide">Hide</button> <button class="button" name="decision" value="delete">Delete with replies</button></form>';
        }
        echo '</div>';
    }
    public static function moderate() {
        if (!current_user_can('manage_options') || !self::ready()) wp_die('Forbidden.', '', ['response'=>403]);
        $id = self::id('id');
        check_admin_referer('kmd_moderate_' . $id);
        $decision = isset($_POST['decision']) && is_string($_POST['decision']) ? $_POST['decision'] : '';
        global $wpdb;
        $table = self::table();
        if ($decision === 'approve' || $decision === 'hide') {
            $wpdb->update($table, ['approved'=>$decision === 'approve' ? 1 : 0], ['id'=>$id], ['%d'], ['%d']);
        } elseif ($decision === 'delete') {
            $wpdb->delete($table, ['parent'=>$id], ['%d']);
            $wpdb->delete($table, ['id'=>$id], ['%d']);
        } else wp_die('Invalid moderation action.');
        wp_safe_redirect(admin_url('edit.php?post_type=kmd_experience&page=kmd-sharing'));
        exit;
    }
}

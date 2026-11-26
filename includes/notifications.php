<?php
defined('ABSPATH') || exit;

final class KMD_Notifications {
    const HOOK = 'kmd_notification_tick';
    public static function boot() {
        add_action('init', [__CLASS__, 'schedule']);
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_filter('cron_schedules', [__CLASS__, 'interval']);
    }
    public static function interval($schedules) {
        $schedules['kmd_five_minutes'] = ['interval'=>300, 'display'=>'Every five minutes (Member Drip)'];
        return $schedules;
    }
    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) wp_schedule_event(time()+300, 'kmd_five_minutes', self::HOOK);
    }
    public static function deactivate() {
        wp_clear_scheduled_hook(self::HOOK);
    }
    private static function title($post) {
        return trim(wp_strip_all_tags($post->post_title));
    }
    public static function message($experience, $event, $subject, $body, $tokens) {
        $prefix = '_kmd_email_' . $event;
        $custom_subject = (string)get_post_meta($experience, $prefix . '_subject', true);
        $custom_body = (string)get_post_meta($experience, $prefix . '_body', true);
        $subject = strtr($custom_subject !== '' ? $custom_subject : $subject, $tokens);
        $body = strtr($custom_body !== '' ? $custom_body : $body, $tokens);
        // Always retain a way to reach the access page.
        if (!empty($tokens['{link}']) && strpos($body, $tokens['{link}']) === false) $body .= "\n\n" . $tokens['{link}'];
        return [$subject, $body];
    }
    private static function send_event($user, $id, $event, $key, $subject, $body, $tokens) {
        [$subject, $body] = self::message($id, $event, $subject, $body, $tokens);
        return self::send($user, $key, $subject, $body);
    }
    private static function send($user, $event, $subject, $body) {
        if (!is_email($user->user_email)) return false;
        // Unique database option acts as an at-most-once claim. A crashed send
        // may require manual recovery; never automatically resend an ambiguous claim.
        $key = 'kmd_mail_' . hash('sha256', $user->ID . ':' . $event);
        if (!add_option($key, 'claimed', '', false)) return get_option($key) === 'sent';
        if (wp_mail($user->user_email, str_replace(["\r","\n"], ' ', $subject), $body)) {
            update_option($key, 'sent', false);
            return true;
        }
        delete_option($key);
        return false;
    }
    public static function run() {
        // Database uniqueness prevents overlapping workers; no stale lock is
        // automatically broken because that could allow overlapping mail sends.
        if (!add_option('kmd_mail_worker_lock', time(), '', false)) return;
        try {
            $experiences = get_posts([
                'kmd_internal'=>true, 'post_type'=>KMD_Core::TYPE, 'post_status'=>'publish',
                'numberposts'=>-1, 'meta_key'=>'_kmd_email_enabled', 'meta_value'=>'1',
            ]);
            if (!$experiences) return;
            $offset = max(0, (int)get_option('kmd_mail_user_offset', 0));
            $users = get_users(['number'=>25, 'offset'=>$offset, 'orderby'=>'ID', 'order'=>'ASC']);
            if (!$users) { update_option('kmd_mail_user_offset', 0, false); return; }
            foreach ($experiences as $experience) {
                $id = (int)$experience->ID;
                $page = get_post(absint(get_post_meta($id, '_kmd_email_page', true)));
                if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') continue;
                $link = get_permalink($page);
                if (!$link) continue;
                $title = self::title($experience);
                $lessons = get_posts(['kmd_internal'=>true, 'post_type'=>KMD_Core::LESSON,
                    'post_status'=>'publish', 'numberposts'=>-1, 'meta_key'=>'_kmd_parent', 'meta_value'=>$id]);
                $expiry = KMD_Core::timestamp((string)get_post_meta($id, '_kmd_expiry', true));
                $tokens = ['{experience}'=>$title,'{section}'=>'','{link}'=>$link,
                    '{expiry}'=>$expiry ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiry) : ''];
                foreach ($users as $user) {
                    if (!KMD_Core::allowed($id, (int)$user->ID)) continue;
                    $welcome_key = '_kmd_welcome_' . $id;
                    $welcomed = (int)get_user_meta($user->ID, $welcome_key, true);
                    if (!$welcomed) {
                        if (self::send_event($user, $id, "welcome", "welcome:$id", "Welcome to $title", "Your experience is ready. Sign in to access your available material:\n$link", $tokens)) {
                            update_user_meta($user->ID, $welcome_key, time());
                        }
                        continue;
                    }
                    foreach ($lessons as $lesson) {
                        $release = KMD_Core::timestamp((string)get_post_meta($lesson->ID, '_kmd_release', true));
                        if (!$release || $release <= $welcomed || $release > time() || time()-$release > 7*DAY_IN_SECONDS) continue;
                        if (!KMD_Core::lesson_allowed($lesson->ID, (int)$user->ID)) continue;
                        $name = self::title($lesson);
                        self::send_event($user, $id, "release", "release:$id:" . $lesson->ID, "New material in $title", "$name is now available. Sign in here:\n$link", array_replace($tokens, ["{section}"=>$name]));
                    }
                    if ($expiry && $expiry > time() && $expiry-time() <= 7*DAY_IN_SECONDS) {
                        $date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiry);
                        self::send_event($user, $id, "closure", "closure:$id", "$title is closing soon", "Your access closes on $date (site time). Revisit your material before then:\n$link", $tokens);
                    }
                }
            }
            update_option('kmd_mail_user_offset', count($users)<25 ? 0 : $offset+25, false);
        } finally {
            delete_option('kmd_mail_worker_lock');
        }
    }
}

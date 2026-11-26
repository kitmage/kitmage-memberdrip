<?php
/**
 * Plugin Name: KitMage Member Drip
 * Description: Development foundation for purchase-protected seasonal experiences.
 * Version: 0.10.0-dev
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;

final class KMD_Core {
    const TYPE = 'kmd_experience';
    const LESSON = 'kmd_lesson';

    public static function boot() {
        add_action('init', [__CLASS__, 'register']);
        add_action('template_redirect', [__CLASS__, 'guard_request'], 0);
        add_action('pre_get_posts', [__CLASS__, 'guard_query'], 99);
        add_filter('map_meta_cap', [__CLASS__, 'guard_capability'], 10, 4);
        add_action('add_meta_boxes', [__CLASS__, 'boxes']);
        add_action('save_post', [__CLASS__, 'save'], 10, 2);
        add_action('wp_enqueue_scripts', [__CLASS__, 'portal_assets']);
        add_action('wp_ajax_kmd_portal', [__CLASS__, 'portal_request']);
        add_action('wp_ajax_nopriv_kmd_portal', [__CLASS__, 'portal_request']);
        add_shortcode('kmd_experiences', [__CLASS__, 'dashboard']);
        add_shortcode('kmd_experience', [__CLASS__, 'experience']);
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_filter('comments_open', [__CLASS__, 'close_comments'], 10, 2);
        add_filter('pings_open', [__CLASS__, 'close_comments'], 10, 2);
    }

    public static function register() {
        $base = [
            'public' => false, 'publicly_queryable' => false,
            'exclude_from_search' => true, 'show_ui' => true,
            'show_in_rest' => false, 'rewrite' => false,
            'query_var' => false, 'has_archive' => false,
            'capability_type' => 'post', 'map_meta_cap' => true,
            'capabilities' => ['create_posts' => 'manage_options',
                'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options', 'delete_others_posts' => 'manage_options'],
        ];
        register_post_type(self::TYPE, array_merge($base, [
            'label' => 'Experiences', 'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-calendar-alt',
        ]));
        register_post_type(self::LESSON, array_merge($base, [
            'label' => 'Experience content', 'supports' => ['title', 'editor', 'page-attributes'],
            'show_in_menu' => 'edit.php?post_type=' . self::TYPE,
        ]));
    }

    public static function notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>KitMage Member Drip is a development build. Do not use real private content yet. Media protection and integration testing are pending.</p></div>';
        }
    }

    public static function close_comments($open, $id) {
        return in_array(get_post_type($id), [self::TYPE, self::LESSON], true) ? false : $open;
    }

    public static function boxes() {
        foreach ([self::TYPE, self::LESSON] as $type) {
            add_meta_box('kmd_settings', 'Experience settings', [__CLASS__, 'fields'], $type);
        }
    }

    public static function post_choices($name, $label, $type, $selected_id = 0) {
        $posts = get_posts(['post_type'=>$type, 'post_status'=>['publish','draft','private','pending','future'],
            'numberposts'=>-1, 'orderby'=>'title', 'order'=>'ASC']);
        echo '<p><label>' . esc_html($label) . '<br><select name="' . esc_attr($name) . '"><option value="0">Select…</option>';
        foreach ($posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) continue;
            echo '<option value="' . (int)$post->ID . '" ' . selected($selected_id, $post->ID, false) . '>' .
                esc_html($post->post_title . ' (#' . $post->ID . ', ' . $post->post_status . ')') . '</option>';
        }
        if ($selected_id && !in_array($selected_id, array_map('intval', wp_list_pluck($posts, 'ID')), true)) {
            echo '<option value="' . (int)$selected_id . '" selected>Existing selection (#' . (int)$selected_id . ')</option>';
        }
        echo '</select></label></p>';
    }

    private static function field($id, $key, $label, $type) {
        $value = get_post_meta($id, $key, true);
        printf('<p><label for="%1$s">%2$s</label><br><input id="%1$s" name="%1$s" type="%3$s" value="%4$s"></p>',
            esc_attr($key), esc_html($label), esc_attr($type), esc_attr($value));
        if ($type === 'datetime-local' && $value === 'configure') {
            echo '<p><strong>Choose a new date before access can open.</strong> <label><input type="checkbox" name="' . esc_attr($key . '_clear') . '" value="1"> Explicitly use no date</label></p>';
        }
    }

    public static function fields($post) {
        wp_nonce_field('kmd_save', 'kmd_nonce');
        if ($post->post_type === self::TYPE) {
            self::post_choices('_kmd_product', 'WooCommerce product', 'product', absint(get_post_meta($post->ID, '_kmd_product', true)));
            echo '<p>To use a specific product variation, select its ID below instead.</p>';
            self::field($post->ID, '_kmd_variation', 'Optional variation ID (overrides product)', 'number');
            self::field($post->ID, '_kmd_expiry', 'Access closes (site local date/time; blank = no expiry)', 'datetime-local');
            self::post_choices('_kmd_email_page', 'Experience access page for email links', 'page', absint(get_post_meta($post->ID, '_kmd_email_page', true)));
            echo '<p><label><input type="checkbox" name="_kmd_email_enabled" value="1" ' . checked(get_post_meta($post->ID, '_kmd_email_enabled', true), '1', false) . '> Enable automatic participant emails (off by default)</label></p>';
            echo '<h4>Optional email wording</h4><p>Leave blank for default text. Placeholders: {experience}, {section}, {link}, {expiry}.</p>';
            foreach (['welcome'=>'Welcome','release'=>'New material','closure'=>'Closing reminder'] as $event=>$label) {
                $key = '_kmd_email_' . $event;
                self::field($post->ID, $key . '_subject', $label . ' subject', 'text');
                echo '<p><label>' . esc_html($label . ' message') . '<br><textarea class="large-text" rows="4" name="' . esc_attr($key . '_body') . '">' . esc_textarea(get_post_meta($post->ID, $key . '_body', true)) . '</textarea></label></p>';
            }
        } else {
            self::post_choices('_kmd_parent', 'Experience', self::TYPE, absint(get_post_meta($post->ID, '_kmd_parent', true)));
            self::field($post->ID, '_kmd_release', 'Release (site local date/time; blank = immediately)', 'datetime-local');
            $mode = get_post_meta($post->ID, '_kmd_release_mode', true);
            echo '<p><label>Release rule <select name="_kmd_release_mode"><option value="fixed" ' . selected($mode !== 'relative', true, false) . '>Calendar date</option><option value="relative" ' . selected($mode, 'relative', false) . '>Days after enrollment</option></select></label></p>';
            self::field($post->ID, '_kmd_release_days', 'Days after enrollment (0 = immediate; blank = not configured)', 'number');
            echo '<p><label><input type="checkbox" name="_kmd_sharing" value="1" ' . checked(get_post_meta($post->ID, '_kmd_sharing', true), '1', false) . '> Enable private reflections</label></p>';
        }
    }

    public static function timestamp($value) {
        if ($value === '') return 0;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) return false;
        return $date->getTimestamp();
    }

    public static function save($id, $post) {
        if (!in_array($post->post_type, [self::TYPE, self::LESSON], true)
            || wp_is_post_revision($id) || wp_is_post_autosave($id)
            || !current_user_can('manage_options') || !current_user_can('edit_post', $id)
            || !isset($_POST['kmd_nonce']) || !is_string($_POST['kmd_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kmd_nonce'])), 'kmd_save')) return;
        if ($post->post_type === self::LESSON) {
            update_post_meta($id, '_kmd_sharing', isset($_POST['_kmd_sharing']) && $_POST['_kmd_sharing'] === '1' ? '1' : '0');
        }
        if ($post->post_type === self::TYPE) {
            update_post_meta($id, '_kmd_email_enabled', isset($_POST['_kmd_email_enabled']) && $_POST['_kmd_email_enabled'] === '1' ? '1' : '0');
            foreach (['welcome','release','closure'] as $event) {
                foreach (['subject','body'] as $field) {
                    $key = '_kmd_email_' . $event . '_' . $field;
                    if (!isset($_POST[$key]) || !is_string($_POST[$key])) continue;
                    $value = wp_unslash($_POST[$key]);
                    update_post_meta($id, $key, $field === 'body' ? sanitize_textarea_field($value) : sanitize_text_field($value));
                }
            }
        }
        $keys = $post->post_type === self::TYPE
            ? ['_kmd_product', '_kmd_variation', '_kmd_expiry', '_kmd_email_page'] : ['_kmd_parent', '_kmd_release', '_kmd_release_mode', '_kmd_release_days'];
        foreach ($keys as $key) {
            if (!isset($_POST[$key]) || !is_string($_POST[$key])) continue;
            $value = sanitize_text_field(wp_unslash($_POST[$key]));
            if (in_array($key, ['_kmd_product', '_kmd_variation', '_kmd_parent', '_kmd_email_page'], true)) $value = absint($value);
            if (in_array($key, ['_kmd_expiry','_kmd_release'], true) && $value === ''
                && get_post_meta($id, $key, true) === 'configure' && empty($_POST[$key . '_clear'])) continue;
            if ($key === '_kmd_release_mode' && !in_array($value, ['fixed','relative'], true)) $value = 'invalid';
            // Preserve invalid dates so access fails closed rather than opening accidentally.
            update_post_meta($id, $key, $value);
        }
    }

    public static function enrollment_start($experience, $user) {
        if (!$user) return false;
        $override = get_user_meta($user, '_kmd_override_' . $experience, true);
        if ($override === 'deny') return false;
        if ($override === 'grant') {
            $start = (int)get_user_meta($user, '_kmd_grant_start_' . $experience, true);
            return $start > 0 && $start <= time() ? $start : 0;
        }
        if (!function_exists('wc_get_orders') || !function_exists('wc_get_is_paid_statuses')) return false;
        $product = absint(get_post_meta($experience, '_kmd_product', true));
        $variation = absint(get_post_meta($experience, '_kmd_variation', true));
        if ($variation) $product = $variation;
        if (!$product) return false;
        $page = 1;
        $earliest = false;
        do {
            $result = wc_get_orders([
                'customer_id' => $user, 'status' => wc_get_is_paid_statuses(),
                'limit' => 50, 'page' => $page++, 'paginate' => true,
            ]);
            foreach ($result->orders as $order) {
                if (!$order->is_paid() || !$order->get_date_paid() || (int)$order->get_customer_id() !== $user) continue;
                if ($order->get_total_refunded() > 0 && $order->get_total_refunded() >= $order->get_total()) continue;
                foreach ($order->get_items('line_item') as $item) {
                    if ((int)$item->get_product_id() !== $product && (int)$item->get_variation_id() !== $product) continue;
                    if ($item->get_quantity() + $order->get_qty_refunded_for_item($item->get_id()) > 0) {
                        $paid = $order->get_date_paid()->getTimestamp();
                        if ($paid > 0 && $paid <= time() && ($earliest === false || $paid < $earliest)) $earliest = $paid;
                    }
                }
            }
        } while ($page <= $result->max_num_pages);
        return $earliest;
    }

    public static function enrolled($experience, $user) {
        return self::enrollment_start($experience, $user) !== false;
    }

    public static function release_at($lesson, $user) {
        $mode = (string)get_post_meta($lesson, '_kmd_release_mode', true);
        $start = $mode === 'relative' ? self::enrollment_start(absint(get_post_meta($lesson, '_kmd_parent', true)), $user) : 0;
        return self::release_for_start($lesson, $start);
    }

    public static function release_for_start($lesson, $start) {
        $mode = (string)get_post_meta($lesson, '_kmd_release_mode', true);
        if ($mode === '' || $mode === 'fixed') return self::timestamp((string)get_post_meta($lesson, '_kmd_release', true));
        if ($mode !== 'relative') return false;
        $days = (string)get_post_meta($lesson, '_kmd_release_days', true);
        if (!preg_match('/^[0-9]{1,4}$/D', $days)) return false;
        if (!$start || !is_int($start)) return false;
        return $start + (int)$days * 86400;
    }

    public static function effective_expiry($id, $user) {
        $expiry = self::timestamp((string)get_post_meta($id, '_kmd_expiry', true));
        if ($expiry === false || !$expiry || !$user) return $expiry;
        $extension = self::timestamp((string)get_user_meta($user, '_kmd_extension_' . $id, true));
        return $extension !== false && $extension > $expiry ? $extension : $expiry;
    }

    public static function allowed($id, $user) {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::TYPE || $post->post_status !== 'publish') return false;
        $expiry = self::effective_expiry($id, $user);
        if ($expiry === false || ($expiry && time() >= $expiry)) return false;
        return self::enrolled($id, $user);
    }

    public static function lesson_allowed($lesson, $user) {
        $post = get_post($lesson);
        if (!$post || $post->post_type !== self::LESSON || $post->post_status !== 'publish') return false;
        $parent = absint(get_post_meta($lesson, '_kmd_parent', true));
        $release = self::release_at($lesson, $user);
        return $release !== false && (!$release || time() >= $release) && self::allowed($parent, $user);
    }

    public static function guard_capability($caps, $cap, $user, $args) {
        if (in_array($cap, ['read_post', 'edit_post', 'delete_post'], true) && !empty($args[0])
            && in_array(get_post_type((int)$args[0]), [self::TYPE, self::LESSON], true)) {
            return ['manage_options'];
        }
        return $caps;
    }

    public static function guard_query($query) {
        if ($query->get('kmd_internal') === true && !$query->is_main_query()) return;
        if (is_admin() && current_user_can('manage_options')) return;
        $type = $query->get('post_type');
        if ($type === 'any') {
            $type = array_values(get_post_types(['exclude_from_search' => false]));
        }
        if ($type) {
            $types = array_diff((array)$type, [self::TYPE, self::LESSON]);
            $query->set('post_type', $types ? array_values($types) : ['kmd_no_public_type']);
        }
    }

    public static function guard_request() {
        if (in_array(get_post_type(get_queried_object_id()), [self::TYPE, self::LESSON], true)) {
            self::private_response();
            wp_die('Not found.', '', ['response' => 404]);
        }
    }

    private static function private_response() {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: private, no-store, max-age=0', true);
        }
    }

    private static function can_render() {
        return !is_admin() && !wp_doing_ajax() && !wp_doing_cron()
            && !(defined('REST_REQUEST') && REST_REQUEST)
            && !(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            && !is_feed() && !is_embed();
    }

    private static function render_content($post) {
        // Do not execute arbitrary shortcodes or dynamic blocks in the development renderer.
        return wpautop(wp_kses_post(strip_shortcodes($post->post_content)));
    }

    public static function portal_assets() {
        wp_enqueue_script('kmd-portal', plugins_url('assets/portal.js', __FILE__), [], '0.8.0', true);
        wp_enqueue_style('kmd-portal', plugins_url('assets/portal.css', __FILE__), [], '0.8.0');
    }

    private static function portal_shell($id) {
        if (!self::can_render()) return '';
        // Only public configuration is included in the Elementor/page cache.
        return '<div class="kmd-portal" data-kmd-experience="' . (int)$id .
            '" data-kmd-endpoint="' . esc_url(admin_url('admin-ajax.php')) .
            '"><p role="status">Loading your experience…</p><noscript>Enable JavaScript to view your experience.</noscript></div>';
    }

    public static function dashboard() {
        return self::portal_shell(0);
    }

    public static function experience($attributes) {
        $attributes = shortcode_atts(['id' => 0], $attributes, 'kmd_experience');
        $id = absint($attributes['id']);
        return $id ? self::portal_shell($id) : '<p>Experience not configured.</p>';
    }

    public static function portal_request() {
        self::private_response();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_send_json_error([], 405);
        $raw = $_POST['experience'] ?? null;
        if (!is_string($raw) || !preg_match('/^[0-9]+$/D', $raw)) wp_send_json_error([], 400);
        $id = absint($raw);
        if (!get_current_user_id()) {
            $login = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
            wp_send_json_success(['html'=>'<p><a href="' . esc_url($login) . '">Sign in</a> to view your experiences.</p>']);
        }
        wp_send_json_success(['html'=>$id ? self::render_experience($id) : self::render_dashboard()]);
    }

    private static function render_dashboard() {
        $user = get_current_user_id();
        if (!$user) return '<p>Please log in to view your experiences.</p>';
        $output = '';
        foreach (get_posts(['suppress_filters' => true, 'kmd_internal' => true, 'post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
            if (self::allowed($post->ID, $user)) {
                $page = get_post(absint(get_post_meta($post->ID, '_kmd_email_page', true)));
                $url = $page && $page->post_type === 'page' && $page->post_status === 'publish' ? get_permalink($page) : '';
                $output .= '<section class="kmd-experience-card"><h2>' . esc_html($post->post_title) . '</h2>';
                $expiry = self::effective_expiry($post->ID, $user);
                if ($expiry) $output .= '<p class="kmd-status">Access through ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiry)) . '</p>';
                $output .= $url ? '<p><a href="' . esc_url($url) . '">Enter experience</a></p>' : self::render_experience($post->ID);
                $output .= '</section>';
            }
        }
        return $output ?: '<p>No active experiences.</p>';
    }

    private static function render_experience($id) {
        if (!self::allowed($id, get_current_user_id())) return '<p>This experience is not currently available to your account.</p>';
        $output = '<div class="kmd-experience">' . self::render_content(get_post($id));
        $expiry = self::effective_expiry($id, get_current_user_id());
        if ($expiry) $output .= '<p class="kmd-status">Access closes ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiry)) . '</p>';
        $lessons = get_posts([
            'kmd_internal' => true, 'post_type' => self::LESSON, 'post_status' => 'publish', 'numberposts' => -1,
            'meta_key' => '_kmd_parent', 'meta_value' => $id,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
        ]);
        $output .= '<nav class="kmd-navigation" aria-label="Experience content"><ul>';
        foreach ($lessons as $lesson) {
            $release = self::release_at($lesson->ID, get_current_user_id());
            $label = esc_html($lesson->post_title);
            if ($release !== false && (!$release || time() >= $release)) {
                $output .= '<li><a href="#kmd-lesson-' . (int)$lesson->ID . '">' . $label . '</a> — Available</li>';
            } else {
                $output .= '<li>' . $label . ' — ' . ($release === false ? 'Unavailable' : esc_html(wp_date(get_option('date_format'), $release))) . '</li>';
            }
        }
        $output .= '</ul></nav>';
        foreach ($lessons as $lesson) {
            $release = self::release_at($lesson->ID, get_current_user_id());
            $output .= '<section class="kmd-lesson" id="kmd-lesson-' . (int)$lesson->ID . '"><h3>' . esc_html($lesson->post_title) . '</h3>';
            if ($release === false) $output .= '<p>Not available.</p>';
            elseif ($release && time() < $release) $output .= '<p>Opens ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $release)) . '</p>';
            else if (self::lesson_allowed($lesson->ID, get_current_user_id())) {
                $output .= self::render_content($lesson);
                foreach (KMD_Private_Media::files($lesson->ID) as $key => $file) {
                    if (!preg_match('/^[a-f0-9]{32}$/D', (string)$key) || !is_array($file) || empty($file['name'])) continue;
                    $url = esc_url(KMD_Private_Media::url($lesson->ID, $key));
                    $name = esc_html($file['name']);
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp3', 'm4a', 'ogg', 'wav'], true)) {
                        $output .= '<p>' . $name . '</p><audio controls preload="none" src="' . $url . '"></audio>';
                    } elseif ($ext === 'pdf') {
                        $output .= '<p><a href="' . $url . '">' . $name . '</a></p>';
                    }
                }
                $output .= KMD_Sharing::render($lesson->ID);
            }
            $output .= '</section>';
        }
        return $output . '</div>';
    }
}
require_once __DIR__ . '/includes/private-media.php';
KMD_Core::boot();
KMD_Private_Media::boot();
require_once __DIR__ . '/includes/admin-tools.php';
KMD_Admin_Tools::boot();
require_once __DIR__ . '/includes/sharing.php';
KMD_Sharing::boot();
require_once __DIR__ . '/includes/notifications.php';
KMD_Notifications::boot();
require_once __DIR__ . '/includes/duplicate.php';
KMD_Duplicate::boot();
require_once __DIR__ . '/includes/preview.php';
KMD_Preview::boot();
register_deactivation_hook(__FILE__, ['KMD_Notifications', 'deactivate']);

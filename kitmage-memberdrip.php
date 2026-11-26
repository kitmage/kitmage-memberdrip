<?php
/**
 * Plugin Name: KitMage Member Drip
 * Description: Development foundation for purchase-protected seasonal experiences.
 * Version: 0.2.0-dev
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

    private static function field($id, $key, $label, $type) {
        $value = get_post_meta($id, $key, true);
        printf('<p><label for="%1$s">%2$s</label><br><input id="%1$s" name="%1$s" type="%3$s" value="%4$s"></p>',
            esc_attr($key), esc_html($label), esc_attr($type), esc_attr($value));
    }

    public static function fields($post) {
        wp_nonce_field('kmd_save', 'kmd_nonce');
        if ($post->post_type === self::TYPE) {
            self::field($post->ID, '_kmd_product', 'WooCommerce product ID', 'number');
            self::field($post->ID, '_kmd_expiry', 'Access closes (site local date/time; blank = no expiry)', 'datetime-local');
        } else {
            self::field($post->ID, '_kmd_parent', 'Experience ID', 'number');
            self::field($post->ID, '_kmd_release', 'Release (site local date/time; blank = immediately)', 'datetime-local');
        }
    }

    private static function timestamp($value) {
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
        $keys = $post->post_type === self::TYPE
            ? ['_kmd_product', '_kmd_expiry'] : ['_kmd_parent', '_kmd_release'];
        foreach ($keys as $key) {
            if (!isset($_POST[$key]) || !is_string($_POST[$key])) continue;
            $value = sanitize_text_field(wp_unslash($_POST[$key]));
            if (in_array($key, ['_kmd_product', '_kmd_parent'], true)) $value = absint($value);
            // Preserve invalid dates so access fails closed rather than opening accidentally.
            update_post_meta($id, $key, $value);
        }
    }

    public static function enrolled($experience, $user) {
        if (!$user || !function_exists('wc_get_orders') || !function_exists('wc_get_is_paid_statuses')) return false;
        $product = absint(get_post_meta($experience, '_kmd_product', true));
        if (!$product) return false;
        $page = 1;
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
                    if ($item->get_quantity() + $order->get_qty_refunded_for_item($item->get_id()) > 0) return true;
                }
            }
        } while ($page <= $result->max_num_pages);
        return false;
    }

    public static function allowed($id, $user) {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::TYPE || $post->post_status !== 'publish') return false;
        $expiry = self::timestamp((string)get_post_meta($id, '_kmd_expiry', true));
        if ($expiry === false || ($expiry && time() >= $expiry)) return false;
        return self::enrolled($id, $user);
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

    public static function dashboard() {
        if (!self::can_render()) return '';
        self::private_response();
        $user = get_current_user_id();
        if (!$user) return '<p>Please log in to view your experiences.</p>';
        $output = '';
        foreach (get_posts(['suppress_filters' => true, 'kmd_internal' => true, 'post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
            if (self::allowed($post->ID, $user)) {
                $output .= '<section><h2>' . esc_html($post->post_title) . '</h2>' . self::experience(['id' => $post->ID]) . '</section>';
            }
        }
        return $output ?: '<p>No active experiences.</p>';
    }

    public static function experience($attributes) {
        if (!self::can_render()) return '';
        self::private_response();
        $attributes = shortcode_atts(['id' => 0], $attributes, 'kmd_experience');
        $id = absint($attributes['id']);
        if (!self::allowed($id, get_current_user_id())) return '<p>This experience is not currently available to your account.</p>';
        $output = self::render_content(get_post($id));
        $lessons = get_posts([
            'kmd_internal' => true, 'post_type' => self::LESSON, 'post_status' => 'publish', 'numberposts' => -1,
            'meta_key' => '_kmd_parent', 'meta_value' => $id,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
        ]);
        foreach ($lessons as $lesson) {
            $release = self::timestamp((string)get_post_meta($lesson->ID, '_kmd_release', true));
            $output .= '<section class="kmd-lesson"><h3>' . esc_html($lesson->post_title) . '</h3>';
            if ($release === false) $output .= '<p>Not available.</p>';
            elseif ($release && time() < $release) $output .= '<p>Opens ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $release)) . '</p>';
            else $output .= self::render_content($lesson);
            $output .= '</section>';
        }
        return $output;
    }
}
KMD_Core::boot();

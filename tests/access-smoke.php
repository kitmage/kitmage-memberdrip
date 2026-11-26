<?php
// Standalone policy smoke test. Not a substitute for WordPress integration tests.
define('ABSPATH', __DIR__);
function register_deactivation_hook(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function wp_timezone() { return new DateTimeZone('America/Chicago'); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function get_user_meta($user, $key, $single) { return $GLOBALS['overrides'][$user][$key] ?? ''; }
function absint($value) { return abs((int)$value); }
function wc_get_is_paid_statuses() { return ['processing', 'completed']; }
function wc_get_orders($args) { return (object)['orders' => $GLOBALS['orders'], 'max_num_pages' => 1]; }
class TestItem {
    function get_product_id() { return 42; }
    function get_variation_id() { return 0; }
    function get_quantity() { return 1; }
    function get_id() { return 99; }
}
class TestOrder {
    public $paid = true;
    public $refunded = 0;
    public $qty_refunded = 0;
    public $customer = 5;
    function is_paid() { return $this->paid; }
    function get_date_paid() { return $this->paid ? new DateTimeImmutable() : null; }
    function get_customer_id() { return $this->customer; }
    function get_total_refunded() { return $this->refunded; }
    function get_total() { return 100; }
    function get_items($type) { return [new TestItem()]; }
    function get_qty_refunded_for_item($id) { return $this->qty_refunded; }
}
require dirname(__DIR__) . '/kitmage-memberdrip.php';
function check($expected, $actual, $name) {
    if ($expected !== $actual) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
$posts = [1 => (object)['post_type' => KMD_Core::TYPE, 'post_status' => 'publish']];
$meta = [1 => ['_kmd_product' => 42, '_kmd_expiry' => '']];
$order = new TestOrder();
$orders = [$order];
check(false, KMD_Core::allowed(1, 0), 'anonymous denied');
check(true, KMD_Core::allowed(1, 5), 'paid account allowed');
check(false, KMD_Core::allowed(1, 6), 'other account denied');
$order->paid = false;
check(false, KMD_Core::allowed(1, 5), 'unpaid denied');
$order->paid = true;
$order->refunded = 100;
check(false, KMD_Core::allowed(1, 5), 'full refund denied');
$order->refunded = 0;
$order->qty_refunded = -1;
check(false, KMD_Core::allowed(1, 5), 'line refund denied');
$order->qty_refunded = 0;
$meta[1]['_kmd_expiry'] = '2000-01-01T00:00';
check(false, KMD_Core::allowed(1, 5), 'expired denied');
$meta[1]['_kmd_expiry'] = 'invalid';
check(false, KMD_Core::allowed(1, 5), 'invalid expiry denied');
$meta[1]['_kmd_expiry'] = '2099-01-01T00:00';
check(true, KMD_Core::allowed(1, 5), 'future expiry allowed');
$overrides = [5 => ['_kmd_override_1' => 'deny'], 6 => ['_kmd_override_1' => 'grant']];
check(false, KMD_Core::allowed(1, 5), 'manual block overrides paid order');
check(true, KMD_Core::allowed(1, 6), 'manual grant without order');
$meta[1]['_kmd_expiry'] = '2000-01-01T00:00';
check(false, KMD_Core::allowed(1, 6), 'manual grant respects expiry');
$meta[1]['_kmd_expiry'] = '';
$overrides = [];
$meta[1]['_kmd_expiry'] = '2000-01-01T00:00';
$overrides = [5 => ['_kmd_extension_1' => '2099-01-01T00:00'], 6 => ['_kmd_extension_1' => '2099-01-01T00:00']];
check(true, KMD_Core::allowed(1, 5), 'extension preserves paid access');
check(false, KMD_Core::allowed(1, 6), 'extension alone does not enroll');
$overrides[5]['_kmd_override_1'] = 'deny';
check(false, KMD_Core::allowed(1, 5), 'manual block beats extension');
unset($overrides[5]['_kmd_override_1']);
$meta[1]['_kmd_expiry'] = 'configure';
check(false, KMD_Core::allowed(1, 5), 'extension cannot bypass invalid base expiry');
$meta[1]['_kmd_expiry'] = '';
$overrides = [];
$posts[2] = (object)['post_type' => KMD_Core::LESSON, 'post_status' => 'publish'];
$meta[2] = ['_kmd_parent' => 1, '_kmd_release' => '2099-01-01T00:00'];
check(false, KMD_Core::lesson_allowed(2, 5), 'future lesson denied');
$meta[2]['_kmd_release'] = 'invalid';
check(false, KMD_Core::lesson_allowed(2, 5), 'invalid release denied');
$meta[2]['_kmd_release'] = '2000-01-01T00:00';
check(true, KMD_Core::lesson_allowed(2, 5), 'released lesson allowed');
check(false, KMD_Core::lesson_allowed(2, 6), 'released lesson denied for nonparticipant');
$posts[2]->post_status = 'draft';
check(false, KMD_Core::lesson_allowed(2, 5), 'draft lesson denied');
$posts[1]->post_status = 'draft';
check(false, KMD_Core::allowed(1, 5), 'draft denied');
$meta[1]['_kmd_email_welcome_subject'] = 'Welcome to {experience}';
$meta[1]['_kmd_email_welcome_body'] = 'Hello from {experience}.';
$tokens = ['{experience}'=>'Winter','{link}'=>'https://example.test/experience'];
check(['Welcome to Winter', "Hello from Winter.\n\nhttps://example.test/experience"], KMD_Notifications::message(1, 'welcome', 'Default', 'Default', $tokens), 'custom email tokens and required access link');
check(['Default Winter', 'Visit https://example.test/experience'], KMD_Notifications::message(1, 'release', 'Default {experience}', 'Visit {link}', $tokens), 'default email tokens without duplicate link');
echo "All policy smoke checks passed.\n";

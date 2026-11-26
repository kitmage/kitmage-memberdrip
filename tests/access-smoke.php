<?php
// Standalone policy smoke test. Not a substitute for WordPress integration tests.
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function wp_timezone() { return new DateTimeZone('America/Chicago'); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
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
$posts[1]->post_status = 'draft';
check(false, KMD_Core::allowed(1, 5), 'draft denied');
echo "All policy smoke checks passed.\n";

<?php
// Run only via WP-CLI on a disposable database.
if (!defined('WP_CLI') || !WP_CLI || getenv('KMD_INTEGRATION_TEST') !== '1') exit(1);
function kmd_check($condition, $name) {
    if (!$condition) WP_CLI::error($name);
    WP_CLI::log('PASS: ' . $name);
}
function kmd_post($type, $meta) {
    $id = wp_insert_post(['post_type'=>$type,'post_title'=>'Integration fixture','post_content'=>'Private fixture',
        'post_status'=>'publish','meta_input'=>$meta], true);
    if (is_wp_error($id) || !$id) WP_CLI::error('Fixture creation failed');
    return $id;
}
$admin = get_user_by('login', 'kmdadmin');
wp_set_current_user($admin->ID);
KMD_Sharing::install();
$product = new WC_Product_Simple();
$product->set_name('Member Drip integration fixture');
$product->set_status('publish');
$product->set_regular_price('20');
$product->set_virtual(true);
$product->save();
$buyer = wp_insert_user(['user_login'=>'kmd-buyer','user_pass'=>wp_generate_password(),'user_email'=>'buyer@example.test','role'=>'customer']);
$other = wp_insert_user(['user_login'=>'kmd-other','user_pass'=>wp_generate_password(),'user_email'=>'other@example.test','role'=>'customer']);
$experience = kmd_post(KMD_Core::TYPE, ['_kmd_product'=>$product->get_id(),'_kmd_expiry'=>'']);
$lesson = kmd_post(KMD_Core::LESSON, ['_kmd_parent'=>$experience,'_kmd_release'=>'','_kmd_sharing'=>'1']);
kmd_check(!KMD_Core::allowed($experience, $buyer), 'Customer without purchase denied');
$order = wc_create_order(['customer_id'=>$buyer]);
$order->add_product($product, 1);
$order->calculate_totals();
$order->save();
kmd_check(!KMD_Core::allowed($experience, $buyer), 'Pending order denied');
$order->payment_complete();
kmd_check(KMD_Core::allowed($experience, $buyer), 'Paid WooCommerce order grants access');
kmd_check(!KMD_Core::allowed($experience, $other), 'Unrelated account denied');
kmd_check(!KMD_Core::allowed($experience, 0), 'Anonymous denied');
kmd_check(KMD_Core::lesson_allowed($lesson, $buyer), 'Released content allowed');
update_post_meta($lesson, '_kmd_release', '2099-01-01T00:00');
kmd_check(!KMD_Core::lesson_allowed($lesson, $buyer), 'Future material denied');
update_post_meta($lesson, '_kmd_release_mode', 'relative');
update_post_meta($lesson, '_kmd_release_days', '0');
kmd_check(KMD_Core::lesson_allowed($lesson, $buyer), 'Immediate relative release allowed');
update_post_meta($lesson, '_kmd_release_days', '7');
kmd_check(!KMD_Core::lesson_allowed($lesson, $buyer), 'Future relative release denied');
update_post_meta($lesson, '_kmd_release_mode', 'fixed');
update_post_meta($lesson, '_kmd_release', '');
update_post_meta($experience, '_kmd_expiry', '2000-01-01T00:00');
kmd_check(!KMD_Core::allowed($experience, $buyer), 'Expired experience denied');
update_user_meta($buyer, '_kmd_extension_' . $experience, '2099-01-01T00:00');
kmd_check(KMD_Core::allowed($experience, $buyer), 'Personal extension works');
update_user_meta($buyer, '_kmd_override_' . $experience, 'deny');
kmd_check(!KMD_Core::allowed($experience, $buyer), 'Manual block wins');
delete_user_meta($buyer, '_kmd_override_' . $experience);
update_post_meta($experience, '_kmd_expiry', '');
$refund = wc_create_refund(['amount'=>$order->get_total(),'reason'=>'Integration fixture','order_id'=>$order->get_id(),'refund_payment'=>false,'restock_items'=>false]);
kmd_check(!is_wp_error($refund), 'Refund created');
kmd_check(!KMD_Core::allowed($experience, $buyer), 'Refunded order denied');
update_user_meta($other, '_kmd_override_' . $experience, 'grant');
update_user_meta($other, '_kmd_grant_start_' . $experience, time());
kmd_check(KMD_Core::lesson_allowed($lesson, $other), 'Manual grant works');
wp_set_current_user($other);
kmd_check(!current_user_can('edit_post',$lesson), 'Customer cannot edit private content');
$type = get_post_type_object(KMD_Core::LESSON);
kmd_check(!$type->publicly_queryable && !$type->show_in_rest, 'Private post type not public or REST registered');
$visible = get_posts(['post_type'=>KMD_Core::LESSON,'post_status'=>'publish','numberposts'=>-1]);
kmd_check(!in_array($lesson, wp_list_pluck($visible,'ID')), 'Public query excludes lesson');
global $wpdb;
$table = $wpdb->prefix . 'kmd_shares';
kmd_check((bool)$wpdb->insert($table,['lesson'=>$lesson,'author'=>$other,'parent'=>0,'body'=>'Private reflection','approved'=>1,'created'=>gmdate('Y-m-d H:i:s')]),'Private reflection table available');
kmd_check(strpos(KMD_Sharing::render($lesson),'Private reflection') !== false,'Eligible participant can see reflection');
wp_set_current_user($buyer);
kmd_check(KMD_Sharing::render($lesson) === '', 'Refunded participant cannot see reflection');
$export = KMD_Sharing::export_personal('other@example.test', 1);
kmd_check(count($export['data']) === 1,'Reflection privacy export');
$erase = KMD_Sharing::erase_personal('other@example.test', 1);
kmd_check($erase['items_removed'] && !$erase['items_retained'],'Reflection privacy erase');
kmd_check(count(KMD_Sharing::export_personal('other@example.test', 1)['data']) === 0,'Erased reflection no longer linked to account');
WP_CLI::success('WordPress/WooCommerce integration checks passed.');

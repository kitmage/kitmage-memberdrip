<?php
if (!defined('WP_CLI') || !WP_CLI || getenv('KMD_INTEGRATION_TEST') !== '1') exit(1);
$admin = get_user_by('login','kmdadmin');
$member = get_user_by('login','kmd-other');
$denied = get_user_by('login','kmd-buyer');
if (!$admin || !$member || !$denied || !defined('KMD_PRIVATE_DIR')) WP_CLI::error('Missing disposable fixtures');
wp_set_current_user($admin->ID);
function kmd_http_post($type, $text, $meta = []) {
    $id = wp_insert_post(['post_type'=>$type,'post_status'=>'publish','post_title'=>'HTTP fixture',
        'post_content'=>$text,'meta_input'=>$meta], true);
    if (is_wp_error($id) || !$id) WP_CLI::error('HTTP fixture creation failed');
    return $id;
}
$experience = kmd_http_post(KMD_Core::TYPE, 'KMD_HTTP_WELCOME_SECRET');
$lesson = kmd_http_post(KMD_Core::LESSON, 'KMD_HTTP_RELEASED_SECRET', ['_kmd_parent'=>$experience,'_kmd_release'=>'']);
$future = kmd_http_post(KMD_Core::LESSON, 'KMD_HTTP_FUTURE_SECRET', ['_kmd_parent'=>$experience,'_kmd_release'=>'2099-01-01T00:00']);
$expired = kmd_http_post(KMD_Core::TYPE, 'KMD_HTTP_EXPIRED_SECRET', ['_kmd_expiry'=>'2000-01-01T00:00']);
$expired_lesson = kmd_http_post(KMD_Core::LESSON, 'KMD_HTTP_EXPIRED_LESSON_SECRET', ['_kmd_parent'=>$expired]);
foreach ([$experience,$expired] as $id) update_user_meta($member->ID,'_kmd_override_' . $id,'grant');
$keys = [];
foreach ([$lesson,$future,$expired_lesson] as $id) {
    $key = bin2hex(random_bytes(16));
    $file = KMD_PRIVATE_DIR . DIRECTORY_SEPARATOR . $key;
    if (file_put_contents($file,'KMD0123456789') !== 13 || !chmod($file,0600)) WP_CLI::error('Unable to create private fixture');
    update_post_meta($id,'_kmd_files',[$key=>['name'=>'fixture.mp3']]);
    $keys[$id] = $key;
}
$page = kmd_http_post('page', '[kmd_experience id="' . $experience . '"]');
$manifest = ['experience'=>$experience,'lesson'=>$lesson,'future'=>$future,'expired'=>$expired,
    'expired_lesson'=>$expired_lesson,'page'=>$page,'keys'=>$keys,'cookie_name'=>LOGGED_IN_COOKIE,
    'member_cookie'=>wp_generate_auth_cookie($member->ID,time()+3600,'logged_in'),
    'denied_cookie'=>wp_generate_auth_cookie($denied->ID,time()+3600,'logged_in')];
if (!file_put_contents(getenv('KMD_HTTP_MANIFEST'),wp_json_encode($manifest))) WP_CLI::error('Manifest write failed');

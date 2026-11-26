<?php
defined('ABSPATH') || exit;

final class KMD_Private_Media {
    public static function boot() {
        add_action('admin_post_kmd_media', [__CLASS__, 'serve']);
        add_action('admin_post_nopriv_kmd_media', [__CLASS__, 'deny']);
    }

    public static function deny() {
        nocache_headers();
        header('Cache-Control: private, no-store', true);
        status_header(404);
        exit;
    }

    // Storage must be explicitly configured outside every web-accessible root.
    public static function root() {
        if (!defined('KMD_PRIVATE_DIR') || !is_string(KMD_PRIVATE_DIR)) return false;
        $root = realpath(KMD_PRIVATE_DIR);
        $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $wp = realpath(ABSPATH);
        if (!$root || !$web || !$wp || !is_dir($root)) return false;
        foreach ([$web, $wp] as $base) {
            if ($root === $base || strpos($root, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0
                || strpos($base, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) return false;
        }
        return $root;
    }

    public static function files($lesson) {
        $raw = get_post_meta($lesson, '_kmd_files', true);
        return is_array($raw) ? $raw : [];
    }

    public static function url($lesson, $key) {
        return add_query_arg(['action' => 'kmd_media', 'lesson' => $lesson, 'file' => $key], admin_url('admin-post.php'));
    }

    public static function serve() {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, ['GET', 'HEAD'], true)) self::deny();
        $lesson = isset($_GET['lesson']) && is_string($_GET['lesson']) ? absint($_GET['lesson']) : 0;
        $key = isset($_GET['file']) && is_string($_GET['file']) ? wp_unslash($_GET['file']) : '';
        if (!KMD_Core::lesson_allowed($lesson, get_current_user_id())) self::deny();
        $files = self::files($lesson);
        if (!preg_match('/^[a-f0-9]{32}$/D', $key) || !isset($files[$key])) self::deny();
        $entry = $files[$key];
        $root = self::root();
        if (!$root || !is_array($entry) || empty($entry['name'])) self::deny();
        $path = realpath($root . DIRECTORY_SEPARATOR . $key);
        if (!$path || dirname($path) !== $root || !is_file($path) || !is_readable($path)) self::deny();
        $types = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'pdf' => 'application/pdf'];
        $name = sanitize_file_name($entry['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset($types[$ext])) self::deny();
        $handle = fopen($path, 'rb');
        if (!$handle) self::deny();
        $stat = fstat($handle);
        $size = $stat['size'] ?? 0;
        if ($size <= 0) { fclose($handle); self::deny(); }
        $start = 0; $end = $size - 1; $partial = false;
        $range = $_SERVER['HTTP_RANGE'] ?? '';
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0', true);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        if ($range !== '') {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $m) || ($m[1] === '' && $m[2] === '')) {
                fclose($handle); status_header(416); header("Content-Range: bytes */$size"); exit;
            }
            if ($m[1] === '') { $start = max(0, $size - (int)$m[2]); }
            else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
            if ($start >= $size || $end < $start) {
                fclose($handle); status_header(416); header("Content-Range: bytes */$size"); exit;
            }
            $partial = true;
        }
        if (fseek($handle, $start) !== 0) { fclose($handle); self::deny(); }
        while (ob_get_level()) { if (!ob_end_clean()) break; }
        status_header($partial ? 206 : 200);
        header('Content-Type: ' . $types[$ext]);
        header('Content-Disposition: ' . ($ext === 'pdf' ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($name));
        $remaining = $end - $start + 1;
        header('Content-Length: ' . $remaining);
        if ($partial) header("Content-Range: bytes $start-$end/$size");
        if ($method !== 'HEAD') {
            while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
                $chunk = fread($handle, min(65536, $remaining));
                if ($chunk === false || $chunk === '') break;
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
        }
        fclose($handle);
        exit;
    }
}

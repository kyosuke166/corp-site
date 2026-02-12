<?php
date_default_timezone_set('Asia/Tokyo');
<?php
// --- 自分のIPを除外設定 ---
$excluded_ips = [
    '133.200.11.64', // ここにstats.phpの右上に表示されている自分のIPを入れる
    '127.0.0.1'      // ローカルテスト用
];
if (in_array($_SERVER['REMOTE_ADDR'], $excluded_ips)) {
    exit; // 自分のIPなら、何もせずここで終了
}

/**
 * ログ収集プログラム
 */
$log_file = __DIR__ . '/access_log.json';

// 管理者Cookieを持っていないアクセスのみを記録
if (!isset($_COOKIE['is_admin'])) {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if ($data) {
        $data['time'] = date('Y-m-d H:i:s');
        $data['ip']   = $_SERVER['REMOTE_ADDR'];
        $data['ua']   = $_SERVER['HTTP_USER_AGENT'];

        // 既存ログの読み込み
        $current_logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
        if (!is_array($current_logs)) $current_logs = [];

        // 先頭に追加して直近1000件を保持
        array_unshift($current_logs, $data);
        $limited_logs = array_slice($current_logs, 0, 1000);

        file_put_contents($log_file, json_encode($limited_logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
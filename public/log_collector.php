<?php
date_default_timezone_set('Asia/Tokyo');

// --- 自分のIPを除外設定 ---
$excluded_ips = [
    '133.203.16.235', 
    '127.0.0.1'
];

if (in_array($_SERVER['REMOTE_ADDR'], $excluded_ips)) {
    exit; 
}

/**
 * ログ収集プログラム
 */
$log_file = __DIR__ . '/access_log.json';

// 送信されてきたJSONを取得
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data) {
    // 管理者Cookieがある場合は、ログに[ADMIN]フラグを立てるだけで保存はするように変更
    // （完全に除外すると、動作確認ができなくて不便なため）
    if (isset($_COOKIE['is_admin'])) {
        $data['admin_access'] = true;
    }

    $data['time'] = date('Y-m-d H:i:s');
    $data['ip']   = $_SERVER['REMOTE_ADDR'];
    $data['ua']   = $_SERVER['HTTP_USER_AGENT'];

    // 既存ログの読み込み
    $current_logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    if (!is_array($current_logs)) $current_logs = [];

    // 先頭に追加して直近1000件を保持
    array_unshift($current_logs, $data);
    $limited_logs = array_slice($current_logs, 0, 1000);

    // ファイル保存（失敗した時にエラーを出すように設定）
    if (file_put_contents($log_file, json_encode($limited_logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        error_log("Failed to write log to $log_file");
    }
}
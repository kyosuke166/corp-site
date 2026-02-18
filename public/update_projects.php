<?php
/**
 * update_projects.php (Mistral AI版)
 * さくらサーバのメールを取得し、Mistral AIで案件解析してJSON保存する
 */

// --- 1. 設定の読み込み ---
require_once __DIR__ . '/db-config.php'; // 階層に合わせて調整してください

// セキュリティチェック用のトークン（db-config.phpに定義がない場合はここで定義）
if (!defined('ACCESS_TOKEN')) {
    define('ACCESS_TOKEN', 'kyosuke166'); 
}

// セキュリティチェック
$is_cron = (php_sapi_name() == 'cli');
$is_valid_token = (isset($_GET['token']) && $_GET['token'] === ACCESS_TOKEN);
if (!$is_cron && !$is_valid_token) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access Denied.');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
mb_internal_encoding("UTF-8");

// db-config.php の定数を使用
$host = 'ssl://' . IMAP_HOST;
$port = 993;
$user = IMAP_USER; 
$pass = IMAP_PASS; 
$save_path = __DIR__ . '/projects.json';

// --- 2. IMAPメール取得処理 ---
$socket = fsockopen($host, $port, $errno, $errstr, 30);
if (!$socket) { die("IMAP接続失敗: $errstr"); }

function exec_cmd($socket, $cmd) {
    fputs($socket, "A01 $cmd\r\n");
    $res = "";
    while ($line = fgets($socket)) {
        $res .= $line;
        if (strpos($line, "A01 OK") !== false || strpos($line, "A01 NO") !== false || strpos($line, "A01 BAD") !== false) break;
    }
    return $res;
}

fgets($socket);
exec_cmd($socket, "LOGIN $user $pass");
exec_cmd($socket, "SELECT INBOX");
$search_res = exec_cmd($socket, "SEARCH ALL");
preg_match('/\* SEARCH (.+)/', $search_res, $m_search);
$msg_numbers = isset($m_search[1]) ? explode(' ', trim($m_search[1])) : [];
rsort($msg_numbers);

$raw_contents = "";
$count = 0;
$target_limit = 50; 

foreach ($msg_numbers as $i) {
    if ($count >= $target_limit) break; 
    
    $res = exec_cmd($socket, "FETCH $i (INTERNALDATE BODY.PEEK[HEADER.FIELDS (SUBJECT FROM)] BODY.PEEK[TEXT])");

    preg_match('/INTERNALDATE "([^"]+)"/i', $res, $m_date);
    $received_at = isset($m_date[1]) ? date('m/d H:i', strtotime($m_date[1])) : "";
    preg_match('/From:.*<([^>]+)>/i', $res, $m_from);
    $from_email = isset($m_from[1]) ? $m_from[1] : "不明";

    // --- フィルタリングロジック ---
    if (!preg_match('/案件|求人/u', $res)) continue;
    if (preg_match('/要員のご紹介|技術者紹介|稼働可能|スキルシート|候補者/u', $res)) continue;
    if (preg_match('/単価[:：]\s*(スキル見合い|相談|確認中)/u', $res)) continue;
    if (!preg_match('/([0-9０-９]{2,3})\s*(万|万円|円)/u', $res) && !preg_match('/単価/u', $res)) {
        continue; 
    }
    if (!preg_match('/駅|区|都内|リモート|在宅|テレワーク|出社/u', $res)) {
        continue;
    }
    
    $raw_contents .= "--- MAIL ID: $i [Received: $received_at] [From: $from_email] ---\n" . mb_substr($res, 0, 1000) . "\n";
    $count++;
}
exec_cmd($socket, "LOGOUT");
fclose($socket);

// --- 3. Mistral AI 連携 ---
$api_url = "https://api.mistral.ai/v1/chat/completions";

$current_month = date('Y年n月');
$prompt = "あなたはプロのIT案件キュレーターです。提供されたメール群から、情報が充実している案件を厳選して日本語のJSON形式で出力してください。

【各項目ルール】
1. 会社名は、すべて「大手企業」「DX推進企業」などの一般名詞に変換すること。
2. 案件タイトル(title)は魅力的にリライトすること。
3. 要約(summary)は、作業内容や環境を4行程度でまとめること。
4. 期間(period)は、「2026年4月～」のような西暦を含む形式で抽出してください。
5. 場所(location)は「駅名」または「リモート可否」を抽出。
6. 金額（price）の抽出：数字の前に必ず「～」を、末尾に「万」を付与。
7. スキル(skills)は、技術スタックを最大6つの配列。
8. tagは「新着」または「高還元」。
9. 受信日時を 'received_at' に、メール送信元を 'sender_email' に格納。
10.出力は必ず以下のJSON構造のみとすること。
[{\"tag\":\"新着\",\"received_at\":\"MM/DD HH:i\",\"sender_email\":\"\",\"title\":\"\",\"period\":\"\",\"location\":\"\",\"price\":\"\",\"summary\":\"\",\"skills\":[]}]";

$data = [
    "model" => "mistral-tiny", 
    "messages" => [
        ["role" => "system", "content" => "Output valid JSON array only. Skip any projects that have 'To be decided' or 'Unknown' for price or location."],
        ["role" => "user", "content" => $prompt . "\n\n" . $raw_contents]
    ],
    "response_format" => ["type" => "json_object"],
    "temperature" => 0.1
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . MISTRAL_API_KEY // db-config.phpの定数を使用
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$res_decode = json_decode($response, true);
curl_close($ch);

// --- 4. 結果の整形・保存 ---
if (isset($res_decode['choices'][0]['message']['content'])) {
    $json_raw = $res_decode['choices'][0]['message']['content'];
    $data_check = json_decode($json_raw, true);
    $final_data = isset($data_check['projects']) ? $data_check['projects'] : $data_check;
    
    foreach ($final_data as &$item) {
        // --- 期間(period)の整形：西暦削除 & 頭の0を削除 ---
        if (!empty($item['period'])) {
            // 「2026年」や「2026/」を削除
            $term = preg_replace('/^20\d{2}[年\/]/u', '', $item['period']);
            // 「04月」などの先頭の0を削除
            $item['period'] = preg_replace('/^0+(\d)/', '$1', $term);
        }
    }
    unset($item);

    // キーワードによる最終フィルタリング
    $filtered_data = array_filter($final_data, function($item) {
        $invalid_keywords = ['スキル見合い', 'プロジェクトによる', '不明', '相談'];
        foreach ($invalid_keywords as $word) {
            if (strpos($item['price'] ?? '', $word) !== false || strpos($item['location'] ?? '', $word) !== false) {
                return false;
            }
        }
        return true;
    });

    $filtered_data = array_values($filtered_data);

    if (file_exists($save_path)) { chmod($save_path, 0666); }
    file_put_contents($save_path, json_encode($filtered_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    header("Location: /projects?v=" . time());
    exit;
} else {
    echo "<h1>Mistral AI API Error</h1>";
    exit;
}
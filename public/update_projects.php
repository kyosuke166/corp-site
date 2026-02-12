<?php
/**
 * update_projects.php (Mistral AI版)
 * さくらサーバのメールを取得し、Mistral AIで案件解析してJSON保存する
 */

// --- 1. 設定 ---
define('ACCESS_TOKEN', 'kyosuke166'); 
define('MISTRAL_API_KEY', 'epfhcKKzVRhqN9WliWMpSpPNFQdWdMmY'); 

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

$host = 'ssl://sbt-inc.sakura.ne.jp';
$port = 993;
$user = 'sales@sbt-inc.co.jp'; 
$pass = 'Flowersf0rAlgernon'; 
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
foreach ($msg_numbers as $i) {
    if ($count >= 10) break; 
    
    $res = exec_cmd($socket, "FETCH $i (INTERNALDATE BODY.PEEK[HEADER.FIELDS (SUBJECT FROM)] BODY.PEEK[TEXT])");

    // 受信日時・差出人の定義
    preg_match('/INTERNALDATE "([^"]+)"/i', $res, $m_date);
    $received_at = isset($m_date[1]) ? date('m/d H:i', strtotime($m_date[1])) : "";
    preg_match('/From:.*<([^>]+)>/i', $res, $m_from);
    $from_email = isset($m_from[1]) ? $m_from[1] : "不明";

    // --- 【フィルタリングロジック】 ---
    
    // 1. 案件・求人キーワードチェック
    if (!preg_match('/案件|求人/u', $res)) continue;

    // 2. 技術者紹介・要員情報を除外
    if (preg_match('/要員のご紹介|技術者紹介|稼働可能|スキルシート|候補者/u', $res)) continue;

    // 3. ★【重要】単価が記載されているかチェック★
    // 「万」「単価」「円」などの文字が含まれていない場合は「金額不明」としてスキップ
    if (!preg_match('/([0-9０-９]{2,3})\s*(万|万円|円)/u', $res) && !preg_match('/単価/u', $res)) {
        continue; 
    }

    // 🔥 4. 【追加】場所・リモート情報がないメールを除外 🔥
    // 「駅」「区」「リモート」「在宅」「テレワーク」のいずれも含まれない場合はスキップ
    if (!preg_match('/駅|区|都内|リモート|在宅|テレワーク|出社/u', $res)) {
        continue;
    }
    
    // 条件をクリアしたメールだけを解析対象に追加
    $raw_contents .= "--- MAIL ID: $i [Received: $received_at] [From: $from_email] ---\n" . mb_substr($res, 0, 1000) . "\n";
    $count++;
}
exec_cmd($socket, "LOGOUT");
fclose($socket);

// --- 3. Mistral AI 連携 ---
$api_url = "https://api.mistral.ai/v1/chat/completions";

$current_month = date('Y年n月');
$prompt = "あなたはプロのIT案件キュレーターです。提供されたメール群から案件情報を最大10件抽出し、日本語のJSON形式で出力してください。

【厳選ルール：以下の案件は絶対に除外すること】
・単価の記載がないもの
・「スキル見合い」「相談」としか書かれていないもの
・技術者の紹介（要員提案）メール

【各項目ルール】
1. 会社名は、発注元・仲介問わずすべて削除、または「大手企業」「DX推進企業」などの一般名詞に変換すること。
2. 案件タイトル(title)は、メールの件名をそのまま使わず案件内容や概要も見ながら「〜の開発案件」「〜の構築支援」のように簡潔で魅力的な名前にリライトすること。
3. 要約(summary)は、案件の内容や概要、作業内容や開発環境をわかりやすく4行程度でまとめること。
4. 期間(period)は、「〇月～」の形式で抽出すること。
   - 【ゼロ埋め禁止】「03月」や「04月」は「3月」「4月」と1桁で出力すること。
   - 【即日判定】開始日が「現在の月（{$current_month}）」または「それ以前の月」になっている場合は、一律で「即日～」とリライトすること。
   - 長期等の補足があれば「即日～（長期）」のように含めてもよい。
5. 場所(location)は、最寄駅名とリモート可否を抽出すること。
  - 【駅名のみ】最寄駅名は「渋谷駅」ではなく「駅」を削って「渋谷」とだけ出力すること。
  - 【一言一句漏らさず】「原則フルリモート」などの補足があれば必ず併記すること（例：渋谷（原則フルリモート））。
  - 【「不明」の禁止】地名やリモート可否が一切不明な場合は、「不明」と書かずに「プロジェクトによる」または「都内近郊」と出力すること。絶対に「不明」という単語を使わないでください。
  - 地名が見当たらない場合でも、リモートの記載があれば必ずそれを書くこと。
 . 【最重要】金額（price）の抽出ロジック：
   - 140h-180hのような『時間（精算幅）』は、絶対にpriceに入れないこと。
   - まずメール内から「〜万円」「〜万」「単価：〇〇」という記述を必死に探してください。
   - 金額が見つかった場合、数字の前に必ず「～」を付与して出力すること（例：～80万、～120万）。
   - 円表記（800,000円）は「～80万」に変換し、数字だけの場合も必ず「万」を末尾につけること。
   - 見つかった数字には必ず『万』という漢字を末尾につけて出力してください。（例：80万、120万）
   - どうしても金額の記載がない場合のみ「スキル見合い」とすること（この場合は「～」は不要）。
7. スキル(skills)は、関連する技術スタックを最大6つの配列にすること。
8. tagは「新着」または「高還元」にすること。
9. 各メール冒頭の [Received: MM/DD HH:ii] を、そのままJSONの 'received_at' フィールドに格納すること。
10.出力は必ず以下のJSON構造のみとしてください。余計な挨拶や解説は一切不要です。
11.メール冒頭の [From: ...] にあるメールアドレスを、JSONの 'sender_email' フィールドに格納すること。


 
[{\"tag\":\"新着\",\"received_at\":\"MM/DD HH:i\",\"sender_email\":\"\",\"title\":\"\",\"period\":\"\",\"location\":\"\",\"price\":\"\",\"summary\":\"\",\"skills\":[]}]";

$data = [
    "model" => "mistral-tiny", // 無料枠で使える高速モデル
    "messages" => [
        ["role" => "system", "content" => "Output valid JSON array only."],
        ["role" => "user", "content" => $prompt . "\n\n" . $raw_contents]
    ],
    "response_format" => ["type" => "json_object"], // JSONモードを強制
    "temperature" => 0.2
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . MISTRAL_API_KEY
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$res_decode = json_decode($response, true);
curl_close($ch);

// --- 4. 結果の保存とリダイレクト ---
if (isset($res_decode['choices'][0]['message']['content'])) {
    $json_raw = $res_decode['choices'][0]['message']['content'];
    
    // Mistralがたまに {"projects": [...]} と返してくるのを防ぐ
    $data_check = json_decode($json_raw, true);
    $final_data = isset($data_check['projects']) ? $data_check['projects'] : $data_check;
    
    // JSONとして整形して保存
    if (file_exists($save_path)) { chmod($save_path, 0666); }
    file_put_contents($save_path, json_encode($final_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // ブラウザで叩いた場合は完了後に案件一覧へ
    header("Location: /projects?v=" . time());
    exit;
} else {
    echo "<h1>Mistral AI API Error</h1>";
    echo "<pre>";
    print_r($res_decode);
    echo "</pre>";
    exit;
}
<?php
date_default_timezone_set('Asia/Tokyo');

$user_ip = $_SERVER['REMOTE_ADDR'];
$log_file = 'access_log.json';
$logs = [];
if (file_exists($log_file)) {
    $logs = json_decode(file_get_contents($log_file), true);
}

// ページ名設定
$page_names = [
    '/' => 'トップページ',
    '/company/' => '会社情報',
    '/projects/' => '案件一覧',
    '/contact/' => 'お問い合わせ',
    '/contact-thanks/' => '問い合わせ完了',
    '/recruit/' => '採用情報',
    '/privacy/' => 'プライバシーポリシー',
    '/security/' => 'セキュリティ'
];

$week_jp = ["日", "月", "火", "水", "木", "金", "土"];

// UAを分かりやすく変換する関数
function parse_ua($ua) {
    $os = '不明OS';
    if (strpos($ua, 'iPhone') !== false) $os = 'iPhone';
    elseif (strpos($ua, 'Android') !== false) $os = 'Android';
    elseif (strpos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($ua, 'Macintosh') !== false) $os = 'Mac';

    $browser = '不明ブラウザ';
    if (strpos($ua, 'Edg') !== false) $browser = 'Edge';
    elseif (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
    elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
    elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';

    return "[$os / $browser]";
}

$sessions = [];
if (!empty($logs)) {
    foreach ($logs as $log) {
        $ip = $log['ip'];
        $time = strtotime($log['time']);
        $found = false;

        foreach ($sessions as &$session) {
            if ($session['ip'] === $ip && abs($session['last_time'] - $time) < 1800) {
                $session['actions'][] = $log;
                $session['start_time'] = min($session['start_time'], $time);
                $session['last_time'] = max($session['last_time'], $time);
                if ($log['url'] === '/contact-thanks/') $session['is_cv'] = true;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $sessions[] = [
                'ip' => $ip,
                'ua' => $log['ua'],
                'start_time' => $time,
                'last_time' => $time,
                'is_cv' => ($log['url'] === '/contact-thanks/'),
                'actions' => [$log]
            ];
        }
    }
    usort($sessions, function($a, $b) { return $b['start_time'] <=> $a['start_time']; });
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-7HKCRZ06L9"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-7HKCRZ06L9');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SBT Access Stats</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; background: #f4f7f8; color: #333; margin: 20px; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .header-controls { display: flex; align-items: center; gap: 15px; }
        .my-ip { background: #fff; border: 1px solid #ddd; padding: 5px 10px; border-radius: 4px; font-size: 12px; }
        .btn-refresh { background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #eee; padding: 12px; text-align: left; vertical-align: top; }
        th { background: #333; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        .cv-row { background-color: #e8f5e9 !important; border-left: 5px solid #4caf50; }
        .cv-badge { background: #4caf50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-bottom: 5px; display: inline-block; }
        .time-cell { white-space: nowrap; width: 120px; }
        .ip-cell { white-space: nowrap; width: 160px; font-family: monospace; }
        .stay-time { color: #888; font-size: 11px; margin-top: 4px; }
        .ua-info { font-size: 11px; color: #666; font-weight: bold; margin-bottom: 4px; display: block; }
        .ua-full { font-size: 9px; color: #ccc; display: block; margin-top: 5px; }
        .path { line-height: 1.8; }
    </style>
</head>
<body>

<div class="header">
    <h2>🚀 SBT アクセス解析</h2>
    <div class="header-controls">
        <div class="my-ip">あなたのIP: <strong><?php echo $user_ip; ?></strong></div>
        <button onclick="location.reload()" class="btn-refresh">更新</button>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>日時</th>
            <th>接続元</th>
            <th>アクセスログ (導線)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($sessions as $session): ?>
            <?php 
                $dt = $session['start_time'];
                $w = $week_jp[date('w', $dt)];
                $display_time = date('m/d', $dt) . "({$w}) " . date('H:i', $dt);
                $diff = $session['last_time'] - $session['start_time'];
                $stay_label = ($diff > 0) ? "(" . floor($diff/60) . "分" . ($diff%60) . "秒)" : "(離脱)";
            ?>
            <tr class="<?php echo $session['is_cv'] ? 'cv-row' : ''; ?>">
                <td class="time-cell"><?php echo $display_time; ?></td>
                <td class="ip-cell">
                    <?php if($session['is_cv']): ?><span class="cv-badge">CV達成</span><br><?php endif; ?>
                    <span class="ua-info"><?php echo parse_ua($session['ua']); ?></span>
                    <?php echo $session['ip']; ?>
                    <?php if($session['ip'] === $user_ip) echo " <span style='color:red;'>(自分)</span>"; ?>
                    <div class="stay-time"><?php echo $stay_label; ?></div>
                </td>
                <td class="path">
                    <?php 
                    $path_strings = [];
                    foreach ($session['actions'] as $action) {
                        $p = $action['url'];
                        $name = isset($page_names[$p]) ? $page_names[$p] : $p;
                        $icon = ($action['action'] === 'CLICK_CONTACT') ? '👉' : '📄';
                        $style = ($p === '/contact-thanks/') ? 'font-weight:bold; color:#2e7d32;' : '';
                        $detail = !empty($action['details']) ? " <span style='color:#007bff; font-size:11px;'>[{$action['details']}]</span>" : "";
                        $path_strings[] = "<span style='{$style}'>{$icon}{$name}{$detail}</span>";
                    }
                    echo implode(' → ', $path_strings);
                    ?>
                    <span class="ua-full">UA: <?php echo htmlspecialchars($session['ua']); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
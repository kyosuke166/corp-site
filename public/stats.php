<?php
/**
 * アクセスログ閲覧（管理者用）
 */
session_start();

// 合言葉をハッシュ化したもの
$hashed_password = '$2y$10$R9n3Vz60.L1mC9XG0Zp8a.qP7YkP3H/WfP87I60x2/r0lG9IuE9.G';

// ログアウト処理
if (isset($_GET['logout'])) {
    setcookie('is_admin', '', time() - 3600, '/');
    session_destroy();
    header("Location: stats.php");
    exit;
}

// 認証処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// if (password_verify($_POST['pass'] ?? '', $hashed_password)) {
// --- （直接文字が合致するかチェック） ---
if (($_POST['pass'] ?? '') === 'kyosuke166') {
            $_SESSION['auth'] = true;
        // 1年間有効な管理者Cookieをセット
        setcookie('is_admin', 'true', time() + 60 * 60 * 24 * 365, '/');
    } else {
        $error = "パスワードが正しくありません。";
    }
}

// 認証チェック
$is_authenticated = $_SESSION['auth'] ?? false;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SBT Access Stats</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; background: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        th { background: #f1f5f9; font-weight: 600; }
        .click-row { background: #fef9c3; font-weight: bold; color: #854d0e; }
        .login-box { text-align: center; margin-top: 100px; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        input[type="password"] { padding: 10px; width: 200px; border: 1px solid #cbd5e1; border-radius: 4px; margin-right: 10px; }
        button { padding: 10px 20px; background: #004a99; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .ip-info { font-size: 11px; color: #94a3b8; text-align: right; margin-bottom: 5px; }

        /* 更新ボタンのスタイル */
        .refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #ffffff;
            color: #004a99;
            border: 1px solid #004a99;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .refresh-btn:hover {
            background: #004a99;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 74, 153, 0.2);
            transform: translateY(-1px);
        }

        .refresh-btn:active {
            transform: translateY(0);
        }

        /* くるくる回るアニメーション */
        .icon-spin {
            transition: transform 0.6s ease;
        }

        .refresh-btn:hover .icon-spin {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
<div class="container">

    <?php if (!$is_authenticated): ?>
        <div class="login-box">
            <h3>SBT Admin Login</h3>
            <form method="POST">
                <input type="password" name="pass" placeholder="Password" autofocus>
                <button type="submit">認証</button>
            </form>
            <?php if (isset($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="ip-info">Your IP: <?= $_SERVER['REMOTE_ADDR'] ?> (Logged in)</div>
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <h2 style="margin: 0;">🚀 アクセスログ</h2>
                <a href="stats.php" class="refresh-btn">
                    <span class="icon-spin">🔄</span> 最新の状態に更新
                </a>
            </div>
            <a href="?logout=1" style="color:#ef4444; text-decoration:none; font-weight:bold;">ログアウト</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 100px;">時刻</th>
                    <th style="width: 80px;">種別</th>
                    <th>内容 / URL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = [];
                if (file_exists('access_log.json')) {
                    $logs = json_decode(file_get_contents('access_log.json'), true) ?: [];
                }
                foreach ($logs as $l):
                    $is_click = ($l['action'] === 'CLICK_CONTACT');
                ?>
                <tr class="<?= $is_click ? 'click-row' : '' ?>">
                    <td><?= htmlspecialchars(substr($l['time'], 5, 11)) ?></td>
                    <td><?= $is_click ? '🔥 CLICK' : '👁 PV' ?></td>
                    <td>
                        <?= htmlspecialchars($l['details'] ?: $l['url']) ?>
                        <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;">IP: <?= htmlspecialchars($l['ip']) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="3" style="text-align:center;">ログがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
</body>
</html>
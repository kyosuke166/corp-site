<?php
// 文字化け防止設定
mb_language("Japanese");
mb_internal_encoding("UTF-8");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 送信先メールアドレス
    $to = "info@sbt-inc.co.jp"; 
    $subject = "お問い合わせ";

    // 全てのフォームデータの取得（contact.astroのname属性に準拠）
    $inquiry_type = htmlspecialchars($_POST['inquiry_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $name         = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $kana         = htmlspecialchars($_POST['kana'] ?? '', ENT_QUOTES, 'UTF-8');
    $email        = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $tel          = htmlspecialchars($_POST['tel'] ?? '', ENT_QUOTES, 'UTF-8');
    $company      = htmlspecialchars($_POST['company'] ?? '', ENT_QUOTES, 'UTF-8');
    $company_url  = htmlspecialchars($_POST['company_url'] ?? '', ENT_QUOTES, 'UTF-8');
    $address      = htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8');
    $message      = htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');

    // メール本文の構築
    $body = "【お問い合わせ内容】: {$inquiry_type}\n";
    $body .= "--------------------------------------------------\n";
    $body .= "お名前: {$name} ({$kana})\n";
    $body .= "メール: {$email}\n";
    $body .= "電話番号: {$tel}\n";
    $body .= "会社名: {$company}\n";
    $body .= "会社URL: {$company_url}\n";
    $body .= "所在地: {$address}\n\n";
    $body .= "【メッセージ本文】:\n{$message}\n";
    $body .= "--------------------------------------------------\n";

    // 重要：Fromは必ず自社ドメインのアドレスにする（固定値）
    // Reply-Toを設定することで、返信ボタンを押した時はユーザーのアドレスが宛先になります
    $headers = [
        "From: info@sbt-inc.co.jp",
        "Reply-To: {$email}",
        "X-Mailer: PHP/" . phpversion()
    ];
    $header_str = implode("\r\n", $headers);

    // メール送信実行
    if (mb_send_mail($to, $subject, $body, $header_str)) {
        // 送信完了画面へリダイレクト
        header("Location: /contact-thanks"); 
        exit;
    } else {
        // 送信失敗時のエラー表示
        echo "メールの送信に失敗しました。お手数ですが直接お電話にてご連絡ください。";
    }
} else {
    // POST以外のアクセスを弾く
    header("Location: /contact");
    exit;
}
?>
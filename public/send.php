<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 送信先メールアドレス
    $to = "info@sbt-inc.jp"; 
    $subject = "【SBTサイト】お問い合わせがありました";

    // フォームデータの取得
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8');

    $body = "名前: {$name}\nメール: {$email}\n\n内容:\n{$message}";
    $headers = "From: {$email}";

    // メール送信
    if (mb_send_mail($to, $subject, $body, $headers)) {
        // 送信完了画面（またはトップへ）
        header("Location: /contact-thanks"); 
        exit;
    } else {
        echo "送信に失敗しました。直接お電話にてご連絡ください。";
    }
}
?>
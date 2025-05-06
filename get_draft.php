<?php
require_once 'config.php';

if (isset($_POST['draft_id'])) {
    $draft_id = filter_var($_POST['draft_id'], FILTER_SANITIZE_NUMBER_INT);
    $stmt = $pdo->prepare("SELECT message FROM drafts WHERE id = ? AND status = 'approved'");
    $stmt->execute([$draft_id]);
    $draft = $stmt->fetch();
    if ($draft) {
        echo $draft['message'];
    }
}
?>
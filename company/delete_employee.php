<?php
require "db.php";

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = :id");
    $stmt->execute([":id" => $id]);
}

header("Location: index.php");
exit;
?>
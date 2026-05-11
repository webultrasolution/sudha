<?php
include 'config/db.php';
try {
    $res = $pdo->query("DESCRIBE payments");
    print_r($res->fetchAll());
} catch (Exception $e) {
    echo $e->getMessage();
}
?>

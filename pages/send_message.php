<?php
include "db.php";

$sender = $_POST['sender_id'];
$receiver = $_POST['receiver_id'];
$message = $_POST['message'];

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $sender, $receiver, $message);
$stmt->execute();
?>

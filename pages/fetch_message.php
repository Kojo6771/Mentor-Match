<?php
include "db.php";

$sender = $_GET['sender_id'];
$receiver = $_GET['receiver_id'];

$query = "
SELECT * FROM messages 
WHERE (sender_id = $sender AND receiver_id = $receiver)
OR (sender_id = $receiver AND receiver_id = $sender)
ORDER BY created_at ASC
";

$result = $conn->query($query);

while($row = $result->fetch_assoc()) {
    echo "<p><strong>{$row['sender_id']}:</strong> {$row['message']}</p>";
}
?>

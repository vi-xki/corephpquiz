<?php
include 'config.php';
$id = $_GET['id'];

$sql = "DELETE FROM users WHERE id=$id";
if ($conn->query($sql)) {
    echo "User deleted. <a href='index.php'>Back to list</a>";
} else {
    echo "Error: " . $conn->error;
}
?>

<?php 
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'crud_db';

// $conn = mysqli_connect($host, $user, $pass, $db);
$conn = new mysqli($host, $user, $pass, $db);


if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// else { echo "Connection successful"; }

?>  
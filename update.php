<?php include 'config.php';

$id = $_GET['id'];
$user = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();

if (isset($_POST['update'])) {
    $name   = $_POST['name'];
    $email  = $_POST['email'];
    $gender = $_POST['gender'];

    $sql = "UPDATE users SET name='$name', email='$email', gender='$gender' WHERE id=$id";
    if ($conn->query($sql)) {
        echo "User updated. <a href='index.php'>Back to list</a>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
<form method="POST">
  Name: <input type="text" name="name" value="<?= $user['name'] ?>"><br><br>
  Email: <input type="email" name="email" value="<?= $user['email'] ?>"><br><br>
  Gender:
  <input type="radio" name="gender" value="Male" <?= $user['gender'] == 'Male' ? 'checked' : '' ?>> Male
  <input type="radio" name="gender" value="Female" <?= $user['gender'] == 'Female' ? 'checked' : '' ?>> Female<br><br>
  <input type="submit" name="update" value="Update">
</form>

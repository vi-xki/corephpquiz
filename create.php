<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
  <title>Create User</title>
</head>
<body>
<h2>Create User</h2>
<form action="" method="POST">
  Name: <input type="text" name="name" required><br><br>
  Email: <input type="email" name="email" required><br><br>
  Password: <input type="password" name="password" required><br><br>
  Gender:
  <input type="radio" name="gender" value="Male" checked> Male
  <input type="radio" name="gender" value="Female"> Female<br><br>
  DOB: <input type="date" name="dob"><br><br>
  Bio:<br>
  <textarea name="bio" rows="4" cols="50"></textarea><br><br>
  Skills:<br>
  <input type="checkbox" name="skills[]" value="HTML"> HTML
  <input type="checkbox" name="skills[]" value="CSS"> CSS
  <input type="checkbox" name="skills[]" value="PHP"> PHP<br><br>
  <input type="submit" name="submit" value="Create">
</form>

<?php
if (isset($_POST['submit'])) {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $gender   = $_POST['gender'];
    $dob      = $_POST['dob'];
    $bio      = $_POST['bio'];
    $skills   = implode(",", $_POST['skills']);
    

    $sql = "INSERT INTO users (name, email, password, gender, dob, bio, skills)
            VALUES ('$name', '$email', '$password', '$gender', '$dob', '$bio', '$skills')";

    if ($conn->query($sql)) {
        echo "User created successfully! <a href='index.php'>View Users</a>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
  <title>Create User (AJAX)</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<h2>Create User (AJAX)</h2>
<form id="userForm">
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
  <input type="submit" value="Create">
</form>

<div id="response"></div>

<script>
  $(document).ready(function() {
    $('#userForm').on('submit', function(e) {
      e.preventDefault(); // Prevent default form submission

      // console.log($(this).serialize());
      $.ajax({
        type: 'POST',
        url: 'insert.php',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {

          console.log(response);
          if (response.status === 'success') {
            $('#response').html('<p style="color: green;">' + response.message + '</p>');
            $('#userForm')[0].reset();
          } else {
            $('#response').html('<p style="color: red;">' + response.message + '</p>');
          }
        },
        error: function(xhr, status, error) {
          $('#response').html('<p style="color: red;">AJAX error: ' + error + '</p>');
        }
      });
    });
  });
</script>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
  <title>Create User (AJAX with Promise)</title>
</head>
<body>
<h2>Create User (AJAX with Promise)</h2>
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
  document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // Use fetch with Promise
    fetch('insert.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(response => {
      if (response.status === 'success') {
        document.getElementById('response').innerHTML = `<p style="color: green;">${response.message}</p>`;
        form.reset();
      } else {
        document.getElementById('response').innerHTML = `<p style="color: red;">${response.message}</p>`;
      }
    })
    .catch(error => {
      document.getElementById('response').innerHTML = `<p style="color: red;">Error: ${error}</p>`;
    });
  });
</script>
</body>
</html>





<!-- Use async/await
document.getElementById('userForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  try {
    const res = await fetch('insert.php', {
      method: 'POST',
      body: formData
    });

    const response = await res.json();

    if (response.status === 'success') {
      document.getElementById('response').innerHTML = `<p style="color: green;">${response.message}</p>`;
      form.reset();
    } else {
      document.getElementById('response').innerHTML = `<p style="color: red;">${response.message}</p>`;
    }

  } catch (error) {
    document.getElementById('response').innerHTML = `<p style="color: red;">Error: ${error}</p>`;
  }
}); -->




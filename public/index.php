<?php







?>
<!doctype html>
<html>
<head>
    <title>Job Tracker</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="loginStyle.css"/>

</head>
<body>
    <form action="mainApp.php" method="post">   
    <div class="container">
        <h1>Job Tracker</h1>
        <p>Welcome to the Job Tracker application! This tool helps you manage and track your job applications efficiently.</p>

        <label for="email"><b>Email</b></label>
        <input type="text" placeholder="mygmail@gmail.com" name="email" required><br>
        <label for="password"><b>Password</b></label>
        <input type="password" placeholder="Password" name="password" required>
        <button type="submit" class="login-btn">Login</button>
        <input type="checkbox" name="remember"> Remember me
        </form>
    </div>

</body>
</html>
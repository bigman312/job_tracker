<?php







?>
<!doctype html>
<html>
<head>
    <title>Job Tracker</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="style/listStyle.css"/>

</head>
<body>
    <form action="mainApp.php" method="post">   
    <div class="container">
        <h1>Job Tracker</h1>
        <p>Welcome to the Job Tracker application! This tool helps you manage and track your job applications efficiently.</p>

        <input type="text" placeholder="mygmail@gmail.com" name="email" required>
        <input type="password" placeholder="Password" name="password" required>
    
        <button type="submit" class="btn" href="mainApp.php">Login</button>
        <input type="checkbox" name="remember"> Remember me
        </form>
    </div>
</body>



</html>
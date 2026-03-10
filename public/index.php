<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Tracker</title>
</head>
<body>
    <form method="POST" action="">
    <h1>Welcome to Job Tracker</h1>


    <label for="username">Username</label>
    <input type="text" id="username" name="username">
    <label for="password">Password</label>
    <input type="password" id="password" name="password">
    <button id="login-btn" name="login-btn">Login</button>

    <a href="/public/register.php">Don't have an account? Register here.</a>
    <?php
    // Include the database connection file
    include '../db/conn/connection.php';
    if(isset($_POST['login-btn'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Prepare and execute the SQL statement to fetch the user
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if($user && password_verify($password, $user['password_hash'])) {
            echo "Login successful!";
            // Here you would typically start a session and redirect the user to their dashboard
        } else {
            echo "Invalid username or password.";
        }
    }

    ?>

</body>
</html>
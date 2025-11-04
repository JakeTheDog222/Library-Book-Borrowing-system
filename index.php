<?php
require 'helpers.php';
if (is_logged_in()) {
    if (is_admin()) header('Location: admin/dashboard.php');
    else header('Location: student/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') $error = 'Enter credentials';

    if (!isset($error)) {
        // Hardcoded credentials
        $credentials = [
            'Admin@wmsu.admin.com' => ['password' => 'admin123', 'role' => 'admin'],
            'Student@wmsu.com' => ['password' => 'student12345', 'role' => 'student', 'student_id' => '123456789', 'full_name' => 'Student', 'course' => 'BSIT'],
            'Student1@wmsu.com' => ['password' => 'student22222', 'role' => 'student', 'student_id' => '987654321', 'full_name' => 'Student1', 'course' => 'BSCS']
        ];

        if (isset($credentials[$username]) && $credentials[$username]['password'] === $password) {
            $user = $credentials[$username];
            // Get actual user ID from database
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $db_user = $stmt->fetch();
            $_SESSION['user'] = [
                'id' => $db_user['id'],
                'username' => $username,
                'role' => $user['role'],
                'full_name' => $user['full_name'] ?? 'Admin',
                'student_id' => $user['student_id'] ?? null,
                'course' => $user['course'] ?? null
            ];
            if ($user['role'] === 'admin') header('Location: admin/dashboard.php');
            else header('Location: student/dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Library System - Login</title>
  <link rel="stylesheet" href="assets/css/custom.css">
  <style>
    body {
      position: relative;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding-bottom: 60px;
    }
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('image/background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      filter: blur(3px);
      z-index: -1;
    }
    .login-form {
      height: auto !important;
      padding-bottom: 20px !important;
    }
  </style>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

  <!-- Header -->
  <header class="header">
    <h1>Library Book Borrowing System</h1>
  </header>

  <!-- Main content -->
  <main>
    <form method="post" class="login-form">
      <div class="form-header">
        <img src="image\wmsulogo.png" alt="WMSU Logo" class="logo">
        <h2>LOGIN</h2>
      </div>

      <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <label for="username">Username</label>
      <input id="username" name="username" type="text" required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>



      <button type="submit">Login</button>
    </form>
  </main>

  <!-- Footer -->
  <footer class="footer">
    © 2025 WMSU Library — All Rights Reserved
  </footer>

</body>
</html>

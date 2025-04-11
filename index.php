<?php
// Start session and include database connection if needed
session_start();

// Authentication logic
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Simple validation
    if (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } else {
        // Check credentials (in a real app, this would check against database)
        if ($email === 'admin@admin.com' && $password === 'admin123') {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_email'] = $email;
            header('Location: views/dashboard.php');
            exit();
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }

        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .form-header img {
            width: 80px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-container">
            <div class="form-header">
                <img src="https://img.freepik.com/premium-vector/school-vector-illustration-background_642050-144.jpg"
                    alt="School Logo" class="img-fluid">
                <h4>School Management System</h4>
                <p class="text-muted">Please sign in to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email"
                        class="form-control <?php echo isset($error) && (strpos($error, 'email') !== false) ? 'is-invalid' : ''; ?>"
                        id="email" name="email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required
                        autofocus>
                    <div class="invalid-feedback">
                        Please provide a valid email.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password"
                        class="form-control <?php echo isset($error) && (strpos($error, 'password') !== false || strpos($error, 'credentials') !== false) ? 'is-invalid' : ''; ?>"
                        id="password" name="password" required>
                    <div class="invalid-feedback">
                        Please provide your password.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Sign in</button>
            </form>


        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
session_start();

// Handle logout confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Destroy the session
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit();
}

// If user is not authenticated, redirect to login directly
if (!isset($_SESSION['authenticated'])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Logout Confirmation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .logout-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .logout-container h4 {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logout-container">
            <h4>Are you sure you want to logout?</h4>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="confirm_logout" value="1">
                <button type="submit" class="btn btn-danger me-2">Yes, Logout</button>
            </form>
            <a href="./dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
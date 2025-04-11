<?php
session_start();

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../index.php');
    exit();
}

// Database connection
try {
    require_once 'db-conn.php';
} catch (\Throwable $th) {
    echo $th;
}
$db = getDBConnection();

// Count data for dashboard cards
try {
    // Count teachers
    $teacherCount = $db->query("SELECT COUNT(*) FROM teacher")->fetchColumn();

    // Count classes
    $classCount = $db->query("SELECT COUNT(*) FROM class")->fetchColumn();

    // Count pupils
    $pupilCount = $db->query("SELECT COUNT(*) FROM pupil")->fetchColumn();

    // Count parents
    $parentCount = $db->query("SELECT COUNT(*) FROM parent_guardian")->fetchColumn();

    // Get recent pupils (last 5)
    $recentPupils = $db->query("SELECT p.first_name, p.last_name, c.name as class_name 
                               FROM pupil p JOIN class c ON p.class_id = c.id 
                               ORDER BY p.enrollment_date DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Error loading dashboard data";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }

        body {
            background-color: #f5f5f5;
        }

        .sidebar {
            background-color: var(--secondary-color);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 2rem;
            opacity: 0.7;
        }

        .recent-pupils img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="text-center mb-4">
                    <h4>School Management</h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teachers/teachers.php">
                            <i class="fas fa-chalkboard-teacher"></i> Teachers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="classes/classes.php">
                            <i class="fas fa-door-open"></i> Classes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pupils/pupils.php">
                            <i class="fas fa-user-graduate"></i> Pupils
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="parents/parents.php">
                            <i class="fas fa-users"></i> Parents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link >" href="teaching_assistants/teaching_assistants.php">
                            <i class="fas fa-users"></i> Teaching Assistants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="salaries/salaries.php">
                            <i class="fas fa-dollar"></i> Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="books/books.php">
                            <i class="fas fa-book"></i> Library Books
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div
                    class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>

                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Dashboard Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Teachers</h5>
                                        <h2 class="mb-0"><?php echo htmlspecialchars($teacherCount); ?></h2>
                                    </div>
                                    <div class="card-icon">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    </div>
                                </div>
                                <a href="teachers/teachers.php" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Classes</h5>
                                        <h2 class="mb-0"><?php echo htmlspecialchars($classCount); ?></h2>
                                    </div>
                                    <div class="card-icon">
                                        <i class="fas fa-door-open"></i>
                                    </div>
                                </div>
                                <a href="classes/classes.php" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Pupils</h5>
                                        <h2 class="mb-0"><?php echo htmlspecialchars($pupilCount); ?></h2>
                                    </div>
                                    <div class="card-icon">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                </div>
                                <a href="pupils/pupils.php" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning dashboard-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Parents</h5>
                                        <h2 class="mb-0"><?php echo htmlspecialchars($parentCount); ?></h2>
                                    </div>
                                    <div class="card-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <a href="parents/parents.php" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Pupils -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recently Enrolled Pupils</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentPupils)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Pupil</th>
                                            <th>Class</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentPupils as $index => $pupil): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($pupil['first_name'] . '+' . $pupil['last_name']); ?>&background=random"
                                                            alt="Pupil" class="me-2">
                                                        <?php echo htmlspecialchars($pupil['first_name']) . ' ' . htmlspecialchars($pupil['last_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($pupil['class_name']); ?></td>
                                                <td>
                                                    <a href="pupils/pupils.php" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No recent pupils found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
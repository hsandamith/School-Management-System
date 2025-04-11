<?php
session_start();

// Authentication check
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: ../../index.php');
    exit();
}

// Database connection
require_once '../db-conn.php';
$db = getDBConnection();

// Initialize variables
$class = ['id' => '', 'name' => '', 'capacity' => ''];
$errors = [];
$isEdit = false;

// Check if editing existing class
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM class WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $class = $stmt->fetch();

        if (!$class) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Class not found'];
            header('Location: classes.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading class: ' . $e->getMessage()];
        header('Location: classes.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $class['name'] = trim($_POST['name'] ?? '');
    $class['capacity'] = trim($_POST['capacity'] ?? '');

    // Validation
    if (empty($class['name'])) {
        $errors['name'] = 'Class name is required';
    }

    if (empty($class['capacity'])) {
        $errors['capacity'] = 'Capacity is required';
    } elseif (!is_numeric($class['capacity']) || $class['capacity'] <= 0) {
        $errors['capacity'] = 'Capacity must be a positive number';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $class['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE class SET name = ?, capacity = ? WHERE id = ?");
                $stmt->execute([$class['name'], $class['capacity'], $class['id']]);
                $message = 'Class updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO class (name, capacity) VALUES (?, ?)");
                $stmt->execute([$class['name'], $class['capacity']]);
                $message = 'Class created successfully';
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: classes.php');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = 'Error saving class: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Class</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
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

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 1.5rem;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div
                    class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Class</h1>
                    <a href="classes.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Classes
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="classForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($class['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Class Name</label>
                            <select class="form-select <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                                id="name" name="name" required>
                                <option value="">Select a class</option>
                                <option value="Reception" <?php echo $class['name'] === 'Reception' ? 'selected' : ''; ?>>
                                    Reception</option>
                                <option value="Year One" <?php echo $class['name'] === 'Year One' ? 'selected' : ''; ?>>
                                    Year One</option>
                                <option value="Year Two" <?php echo $class['name'] === 'Year Two' ? 'selected' : ''; ?>>
                                    Year Two</option>
                                <option value="Year Three" <?php echo $class['name'] === 'Year Three' ? 'selected' : ''; ?>>Year Three</option>
                                <option value="Year Four" <?php echo $class['name'] === 'Year Four' ? 'selected' : ''; ?>>
                                    Year Four</option>
                                <option value="Year Five" <?php echo $class['name'] === 'Year Five' ? 'selected' : ''; ?>>
                                    Year Five</option>
                                <option value="Year Six" <?php echo $class['name'] === 'Year Six' ? 'selected' : ''; ?>>
                                    Year Six</option>
                            </select>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['name']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a class name.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number"
                                class="form-control <?php echo isset($errors['capacity']) ? 'is-invalid' : ''; ?>"
                                id="capacity" name="capacity" min="1"
                                value="<?php echo htmlspecialchars($class['capacity']); ?>" required>
                            <?php if (isset($errors['capacity'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['capacity']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter a valid capacity (minimum 1).
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Class
                        </button>
                        <a href="classes.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Processing...</p>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            const forms = document.querySelectorAll('.needs-validation')

            // Loop over them and prevent submission
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    } else {
                        // Show loading overlay when form is valid and submitting
                        document.querySelector('.loading-overlay').style.display = 'flex';
                    }

                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Show loading state on all links except logout
        document.querySelectorAll('a:not([href*="logout"])').forEach(link => {
            link.addEventListener('click', function () {
                document.querySelector('.loading-overlay').style.display = 'flex';
            });
        });
    </script>
</body>

</html>
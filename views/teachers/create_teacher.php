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
$teacher = [
    'id' => '',
    'first_name' => '',
    'last_name' => '',
    'address' => '',
    'phone_number' => '',
    'email' => '',
    'annual_salary' => '',
    'background_check_status' => 'pending',
    'background_check_date' => '',
    'class_id' => ''
];
$errors = [];
$isEdit = false;

// Get available classes for dropdown
try {
    $classStmt = $db->query("SELECT id, name FROM class WHERE id NOT IN (SELECT class_id FROM teacher WHERE class_id IS NOT NULL) OR id = 0");
    $availableClasses = $classStmt->fetchAll();
} catch (PDOException $e) {
    $errors['database'] = 'Error loading classes: ' . $e->getMessage();
}

// Check if editing existing teacher
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM teacher WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $teacher = $stmt->fetch();

        if (!$teacher) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Teacher not found'];
            header('Location: teachers.php');
            exit();
        }

        // If editing, also include the teacher's current class in available classes
        if ($teacher['class_id']) {
            $currentClassStmt = $db->prepare("SELECT id, name FROM class WHERE id = ?");
            $currentClassStmt->execute([$teacher['class_id']]);
            $currentClass = $currentClassStmt->fetch();
            if ($currentClass) {
                $availableClasses[] = $currentClass;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading teacher: ' . $e->getMessage()];
        header('Location: teachers.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $teacher['first_name'] = trim($_POST['first_name'] ?? '');
    $teacher['last_name'] = trim($_POST['last_name'] ?? '');
    $teacher['address'] = trim($_POST['address'] ?? '');
    $teacher['phone_number'] = trim($_POST['phone_number'] ?? '');
    $teacher['email'] = trim($_POST['email'] ?? '');
    $teacher['annual_salary'] = trim($_POST['annual_salary'] ?? '');
    $teacher['background_check_status'] = $_POST['background_check_status'] ?? 'pending';
    $teacher['background_check_date'] = !empty($_POST['background_check_date']) ? $_POST['background_check_date'] : null;
    $teacher['class_id'] = !empty($_POST['class_id']) ? $_POST['class_id'] : null;

    // Validation
    if (empty($teacher['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }

    if (empty($teacher['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }

    if (empty($teacher['address'])) {
        $errors['address'] = 'Address is required';
    }

    if (empty($teacher['phone_number'])) {
        $errors['phone_number'] = 'Phone number is required';
    }

    if (!empty($teacher['email']) && !filter_var($teacher['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($teacher['annual_salary'])) {
        $errors['annual_salary'] = 'Annual salary is required';
    } elseif (!is_numeric($teacher['annual_salary']) || $teacher['annual_salary'] <= 0) {
        $errors['annual_salary'] = 'Salary must be a positive number';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $teacher['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE teacher SET first_name = ?, last_name = ?, address = ?, phone_number = ?, 
                                      email = ?, annual_salary = ?, background_check_status = ?, 
                                      background_check_date = ?, class_id = ? WHERE id = ?");
                $stmt->execute([
                    $teacher['first_name'],
                    $teacher['last_name'],
                    $teacher['address'],
                    $teacher['phone_number'],
                    $teacher['email'],
                    $teacher['annual_salary'],
                    $teacher['background_check_status'],
                    $teacher['background_check_date'],
                    $teacher['class_id'],
                    $teacher['id']
                ]);
                $message = 'Teacher updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO teacher (first_name, last_name, address, phone_number, email, 
                                     annual_salary, background_check_status, background_check_date, class_id) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $teacher['first_name'],
                    $teacher['last_name'],
                    $teacher['address'],
                    $teacher['phone_number'],
                    $teacher['email'],
                    $teacher['annual_salary'],
                    $teacher['background_check_status'],
                    $teacher['background_check_date'],
                    $teacher['class_id']
                ]);
                $message = 'Teacher created successfully';
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: teachers.php');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = 'Error saving teacher: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Teacher</title>
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
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Teacher</h1>
                    <a href="teachers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Teachers
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="teacherForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($teacher['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                id="first_name" name="first_name"
                                value="<?php echo htmlspecialchars($teacher['first_name']); ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['first_name']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter a first name.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                id="last_name" name="last_name"
                                value="<?php echo htmlspecialchars($teacher['last_name']); ?>" required>
                            <?php if (isset($errors['last_name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['last_name']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter a last name.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>"
                            id="address" name="address" rows="3"
                            required><?php echo htmlspecialchars($teacher['address']); ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['address']; ?>
                            </div>
                        <?php else: ?>
                            <div class="invalid-feedback">
                                Please enter an address.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['phone_number']) ? 'is-invalid' : ''; ?>"
                                id="phone_number" name="phone_number"
                                value="<?php echo htmlspecialchars($teacher['phone_number']); ?>" required>
                            <?php if (isset($errors['phone_number'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['phone_number']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter a phone number.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email (Optional)</label>
                            <input type="email"
                                class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['email']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="annual_salary" class="form-label">Annual Salary</label>
                            <div class="input-group">
                                <span class="input-group-text">LKR</span>
                                <input type="number" step="0.01"
                                    class="form-control <?php echo isset($errors['annual_salary']) ? 'is-invalid' : ''; ?>"
                                    id="annual_salary" name="annual_salary"
                                    value="<?php echo htmlspecialchars($teacher['annual_salary']); ?>" required>
                                <?php if (isset($errors['annual_salary'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['annual_salary']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please enter a valid salary amount.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="background_check_status" class="form-label">Background Check Status</label>
                            <select class="form-select" id="background_check_status" name="background_check_status">
                                <option value="pending" <?php echo $teacher['background_check_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="complete" <?php echo $teacher['background_check_status'] === 'complete' ? 'selected' : ''; ?>>Complete</option>
                                <option value="failed" <?php echo $teacher['background_check_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="background_check_date" class="form-label">Background Check Date</label>
                            <input type="date" class="form-control" id="background_check_date"
                                name="background_check_date"
                                value="<?php echo $teacher['background_check_date'] ? htmlspecialchars($teacher['background_check_date']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="class_id" class="form-label">Assigned Class (Optional)</label>
                        <select class="form-select" id="class_id" name="class_id">
                            <option value="">No class assigned</option>
                            <?php foreach ($availableClasses as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $teacher['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Note: Each class can only be assigned to one teacher</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Teacher
                        </button>
                        <a href="teachers.php" class="btn btn-outline-secondary">Cancel</a>
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

        // Update background check date requirements based on status
        document.getElementById('background_check_status').addEventListener('change', function () {
            const dateField = document.getElementById('background_check_date');
            if (this.value === 'complete' || this.value === 'failed') {
                dateField.setAttribute('required', 'required');
            } else {
                dateField.removeAttribute('required');
            }
        });
    </script>
</body>

</html>
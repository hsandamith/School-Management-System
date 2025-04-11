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
$teachingAssistant = [
    'id' => '',
    'first_name' => '',
    'last_name' => '',
    'address' => '',
    'email' => '',
    'phone_number' => '',
    'hourly_rate' => '',
    'background_check_status' => 'pending',
    'background_check_date' => '',
    'assigned_classes' => []
];
$errors = [];
$isEdit = false;

// Fetch all classes for assignment dropdown
try {
    $classes = $db->query("SELECT * FROM class ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = 'Error loading classes: ' . $e->getMessage();
}

// Check if editing existing teaching assistant
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM teaching_assistant WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $teachingAssistant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$teachingAssistant) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Teaching assistant not found'];
            header('Location: teaching_assistants.php');
            exit();
        }
        
        // Get assigned classes
        $stmt = $db->prepare("SELECT class_id FROM class_teaching_assistant WHERE teaching_assistant_id = ?");
        $stmt->execute([$_GET['id']]);
        $teachingAssistant['assigned_classes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading teaching assistant: ' . $e->getMessage()];
        header('Location: teaching_assistants.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $teachingAssistant['first_name'] = trim($_POST['first_name'] ?? '');
    $teachingAssistant['last_name'] = trim($_POST['last_name'] ?? '');
    $teachingAssistant['address'] = trim($_POST['address'] ?? '');
    $teachingAssistant['email'] = trim($_POST['email'] ?? '');
    $teachingAssistant['phone_number'] = trim($_POST['phone_number'] ?? '');
    $teachingAssistant['hourly_rate'] = trim($_POST['hourly_rate'] ?? '');
    $teachingAssistant['background_check_status'] = $_POST['background_check_status'] ?? 'pending';
    $teachingAssistant['background_check_date'] = !empty($_POST['background_check_date']) ? $_POST['background_check_date'] : null;
    $assignedClasses = $_POST['assigned_classes'] ?? [];

    // Validation
    if (empty($teachingAssistant['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }

    if (empty($teachingAssistant['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }

    if (empty($teachingAssistant['address'])) {
        $errors['address'] = 'Address is required';
    }

    if (empty($teachingAssistant['phone_number'])) {
        $errors['phone_number'] = 'Phone number is required';
    }

    if (empty($teachingAssistant['hourly_rate'])) {
        $errors['hourly_rate'] = 'Hourly rate is required';
    } elseif (!is_numeric($teachingAssistant['hourly_rate']) || $teachingAssistant['hourly_rate'] <= 0) {
        $errors['hourly_rate'] = 'Hourly rate must be a positive number';
    }

    if (!empty($teachingAssistant['email']) && !filter_var($teachingAssistant['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if ($isEdit) {
                $teachingAssistant['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE teaching_assistant SET 
                    first_name = ?, last_name = ?, address = ?, email = ?, 
                    phone_number = ?, hourly_rate = ?, background_check_status = ?, 
                    background_check_date = ? WHERE id = ?");
                $stmt->execute([
                    $teachingAssistant['first_name'],
                    $teachingAssistant['last_name'],
                    $teachingAssistant['address'],
                    $teachingAssistant['email'],
                    $teachingAssistant['phone_number'],
                    $teachingAssistant['hourly_rate'],
                    $teachingAssistant['background_check_status'],
                    $teachingAssistant['background_check_date'],
                    $teachingAssistant['id']
                ]);
                $message = 'Teaching assistant updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO teaching_assistant 
                    (first_name, last_name, address, email, phone_number, 
                    hourly_rate, background_check_status, background_check_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $teachingAssistant['first_name'],
                    $teachingAssistant['last_name'],
                    $teachingAssistant['address'],
                    $teachingAssistant['email'],
                    $teachingAssistant['phone_number'],
                    $teachingAssistant['hourly_rate'],
                    $teachingAssistant['background_check_status'],
                    $teachingAssistant['background_check_date']
                ]);
                $teachingAssistant['id'] = $db->lastInsertId();
                $message = 'Teaching assistant created successfully';
            }
            
            // Handle class assignments
            if ($isEdit) {
                // Remove existing assignments
                $stmt = $db->prepare("DELETE FROM class_teaching_assistant WHERE teaching_assistant_id = ?");
                $stmt->execute([$teachingAssistant['id']]);
            }
            
            // Add new assignments
            if (!empty($assignedClasses)) {
                $stmt = $db->prepare("INSERT INTO class_teaching_assistant (class_id, teaching_assistant_id) VALUES (?, ?)");
                foreach ($assignedClasses as $classId) {
                    $stmt->execute([$classId, $teachingAssistant['id']]);
                }
            }
            
            $db->commit();
            
            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: teaching_assistants.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors['database'] = 'Error saving teaching assistant: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Teaching Assistant</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
 
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
            background-color: rgba(0,0,0,0.5);
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
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Teaching Assistant</h1>
                    <a href="teaching_assistants.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Teaching Assistants
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="taForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($teachingAssistant['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                id="first_name" name="first_name"
                                value="<?php echo htmlspecialchars($teachingAssistant['first_name']); ?>" required>
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
                                value="<?php echo htmlspecialchars($teachingAssistant['last_name']); ?>" required>
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
                            required><?php echo htmlspecialchars($teachingAssistant['address']); ?></textarea>
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
                                value="<?php echo htmlspecialchars($teachingAssistant['phone_number']); ?>" required>
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
                                id="email" name="email" value="<?php echo htmlspecialchars($teachingAssistant['email']); ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['email']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hourly_rate" class="form-label">Hourly Rate (£)</label>
                            <div class="input-group">
                                <span class="input-group-text">£</span>
                                <input type="number" step="0.01"
                                    class="form-control <?php echo isset($errors['hourly_rate']) ? 'is-invalid' : ''; ?>"
                                    id="hourly_rate" name="hourly_rate"
                                    value="<?php echo htmlspecialchars($teachingAssistant['hourly_rate']); ?>" required>
                                <?php if (isset($errors['hourly_rate'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['hourly_rate']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please enter a valid hourly rate.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="background_check_status" class="form-label">Background Check Status</label>
                            <select class="form-select" id="background_check_status" name="background_check_status">
                                <option value="pending" <?php echo $teachingAssistant['background_check_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="complete" <?php echo $teachingAssistant['background_check_status'] === 'complete' ? 'selected' : ''; ?>>Complete</option>
                                <option value="failed" <?php echo $teachingAssistant['background_check_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="background_check_date" class="form-label">Background Check Date (if applicable)</label>
                            <input type="date" class="form-control" id="background_check_date" name="background_check_date"
                                value="<?php echo !empty($teachingAssistant['background_check_date']) ? htmlspecialchars($teachingAssistant['background_check_date']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Assign to Classes</label>
                        <div class="row">
                            <?php foreach ($classes as $class): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
       name="assigned_classes[]" 
       value="<?php echo $class['id']; ?>"
       id="class_<?php echo $class['id']; ?>"
       <?php echo in_array($class['id'], $teachingAssistant['assigned_classes']) ? 'checked' : ''; ?>>

<label class="form-check-label" for="class_<?php echo $class['id']; ?>">
    <?php echo htmlspecialchars($class['name']); ?>
</label>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Teaching Assistant
                        </button>
                        <a href="teaching_assistants.php" class="btn btn-outline-secondary">Cancel</a>
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
    <!-- Flatpickr for date input -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr("#background_check_date", {
            dateFormat: "Y-m-d",
            allowInput: true
        });

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
            link.addEventListener('click', function() {
                document.querySelector('.loading-overlay').style.display = 'flex';
            });
        });
    </script>
</body>
</html>
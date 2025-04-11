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
$parent = [
    'id' => '',
    'first_name' => '',
    'last_name' => '',
    'address' => '',
    'email' => '',
    'phone_number' => '',
    'relationship_to_pupil' => ''
];
$errors = [];
$isEdit = false;

// Check if editing existing parent/guardian
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM parent_guardian WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $parent = $stmt->fetch();

        if (!$parent) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Parent/Guardian not found'];
            header('Location: parents.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading parent/guardian: ' . $e->getMessage()];
        header('Location: parents.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $parent['first_name'] = trim($_POST['first_name'] ?? '');
    $parent['last_name'] = trim($_POST['last_name'] ?? '');
    $parent['address'] = trim($_POST['address'] ?? '');
    $parent['email'] = trim($_POST['email'] ?? '');
    $parent['phone_number'] = trim($_POST['phone_number'] ?? '');
    $parent['relationship_to_pupil'] = trim($_POST['relationship_to_pupil'] ?? '');

    // Validation
    if (empty($parent['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }

    if (empty($parent['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }

    if (empty($parent['address'])) {
        $errors['address'] = 'Address is required';
    }

    if (empty($parent['phone_number'])) {
        $errors['phone_number'] = 'Phone number is required';
    }

    if (!empty($parent['email']) && !filter_var($parent['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $parent['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE parent_guardian SET first_name = ?, last_name = ?, address = ?, 
                                     email = ?, phone_number = ?, relationship_to_pupil = ? WHERE id = ?");
                $stmt->execute([
                    $parent['first_name'],
                    $parent['last_name'],
                    $parent['address'],
                    $parent['email'],
                    $parent['phone_number'],
                    $parent['relationship_to_pupil'],
                    $parent['id']
                ]);
                $message = 'Parent/Guardian updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO parent_guardian (first_name, last_name, address, email, 
                                     phone_number, relationship_to_pupil) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $parent['first_name'],
                    $parent['last_name'],
                    $parent['address'],
                    $parent['email'],
                    $parent['phone_number'],
                    $parent['relationship_to_pupil']
                ]);
                $message = 'Parent/Guardian created successfully';
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: parents.php');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = 'Error saving parent/guardian: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Parent/Guardian</title>
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
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Parent/Guardian</h1>
                    <a href="parents" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Parents/Guardians
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="parentForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($parent['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                id="first_name" name="first_name"
                                value="<?php echo htmlspecialchars($parent['first_name']); ?>" required>
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
                                value="<?php echo htmlspecialchars($parent['last_name']); ?>" required>
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
                            required><?php echo htmlspecialchars($parent['address']); ?></textarea>
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
                                value="<?php echo htmlspecialchars($parent['phone_number']); ?>" required>
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
                                id="email" name="email" value="<?php echo htmlspecialchars($parent['email']); ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['email']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="relationship_to_pupil" class="form-label">Relationship to Pupil</label>
                        <select class="form-select" id="relationship_to_pupil" name="relationship_to_pupil">
                            <option value="" <?php echo empty($parent['relationship_to_pupil']) ? 'selected' : ''; ?>>
                                Select relationship</option>
                            <option value="Mother" <?php echo $parent['relationship_to_pupil'] === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                            <option value="Father" <?php echo $parent['relationship_to_pupil'] === 'Father' ? 'selected' : ''; ?>>Father</option>
                            <option value="Guardian" <?php echo $parent['relationship_to_pupil'] === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                            <option value="Grandparent" <?php echo $parent['relationship_to_pupil'] === 'Grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                            <option value="Aunt" <?php echo $parent['relationship_to_pupil'] === 'Aunt' ? 'selected' : ''; ?>>Aunt</option>
                            <option value="Uncle" <?php echo $parent['relationship_to_pupil'] === 'Uncle' ? 'selected' : ''; ?>>Uncle</option>
                            <option value="Other" <?php echo $parent['relationship_to_pupil'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Parent/Guardian
                        </button>
                        <a href="parents" class="btn btn-outline-secondary">Cancel</a>
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
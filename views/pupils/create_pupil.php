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
$pupil = [
    'id' => '',
    'first_name' => '',
    'last_name' => '',
    'date_of_birth' => '',
    'address' => '',
    'medical_information' => '',
    'enrollment_date' => date('Y-m-d'),
    'dinner_money_balance' => '0.00',
    'class_id' => '',
    'registration_id' => ''
];

$registration = [
    'id' => '',
    'registration_date' => date('Y-m-d'),
    'status' => 'pending',
    'application_form_reference' => '',
    'interview_date' => '',
    'enrollment_date' => date('Y-m-d')
];

$parents = [];
$errors = [];
$isEdit = false;

// Get available classes for dropdown
try {
    $classStmt = $db->query("SELECT id, name, capacity FROM class");
    $availableClasses = $classStmt->fetchAll();
} catch (PDOException $e) {
    $errors['database'] = 'Error loading classes: ' . $e->getMessage();
}

// Check if editing existing pupil
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        // Fetch pupil data
        $stmt = $db->prepare("SELECT * FROM pupil WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $pupil = $stmt->fetch();

        if (!$pupil) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Pupil not found'];
            header('Location: pupils.php');
            exit();
        }

        // Fetch registration data
        $registrationStmt = $db->prepare("SELECT * FROM school_registration WHERE id = ?");
        $registrationStmt->execute([$pupil['registration_id']]);
        $registration = $registrationStmt->fetch();

        // Fetch associated parents/guardians
        $parentsStmt = $db->prepare("
            SELECT pg.*, ppg.relationship_type
            FROM parent_guardian pg
            JOIN pupil_parent_guardian ppg ON pg.id = ppg.parent_guardian_id
            WHERE ppg.pupil_id = ?
        ");
        $parentsStmt->execute([$pupil['id']]);
        $parents = $parentsStmt->fetchAll();
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading pupil: ' . $e->getMessage()];
        header('Location: pupils.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    // Pupil details
    $pupil['first_name'] = trim($_POST['first_name'] ?? '');
    $pupil['last_name'] = trim($_POST['last_name'] ?? '');
    $pupil['date_of_birth'] = trim($_POST['date_of_birth'] ?? '');
    $pupil['address'] = trim($_POST['address'] ?? '');
    $pupil['medical_information'] = trim($_POST['medical_information'] ?? '');
    $pupil['enrollment_date'] = trim($_POST['enrollment_date'] ?? '');
    $pupil['dinner_money_balance'] = trim($_POST['dinner_money_balance'] ?? '0.00');
    $pupil['class_id'] = $_POST['class_id'] ?? '';

    // Registration details
    $registration['registration_date'] = trim($_POST['registration_date'] ?? '');
    $registration['status'] = $_POST['registration_status'] ?? 'pending';
    $registration['application_form_reference'] = trim($_POST['application_form_reference'] ?? '');
    $registration['interview_date'] = !empty($_POST['interview_date']) ? $_POST['interview_date'] : null;
    $registration['enrollment_date'] = trim($_POST['enrollment_date'] ?? '');

    // Parent details (we'll handle up to 2 parents)
    $parentData = [];
    if (!empty($_POST['parent_first_name'])) {
        for ($i = 0; $i < count($_POST['parent_first_name']); $i++) {
            if (!empty($_POST['parent_first_name'][$i]) && !empty($_POST['parent_last_name'][$i])) {
                $parentData[] = [
                    'id' => $_POST['parent_id'][$i] ?? '',
                    'first_name' => trim($_POST['parent_first_name'][$i]),
                    'last_name' => trim($_POST['parent_last_name'][$i]),
                    'address' => trim($_POST['parent_address'][$i]),
                    'email' => trim($_POST['parent_email'][$i] ?? ''),
                    'phone_number' => trim($_POST['parent_phone'][$i]),
                    'relationship_type' => trim($_POST['parent_relationship'][$i])
                ];
            }
        }
    }

    // Validation
    if (empty($pupil['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }

    if (empty($pupil['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }

    if (empty($pupil['date_of_birth'])) {
        $errors['date_of_birth'] = 'Date of birth is required';
    }

    if (empty($pupil['address'])) {
        $errors['address'] = 'Address is required';
    }

    if (empty($pupil['enrollment_date'])) {
        $errors['enrollment_date'] = 'Enrollment date is required';
    }

    if (empty($pupil['class_id'])) {
        $errors['class_id'] = 'Class is required';
    } else {
        // Check class capacity if not editing or changing class
        if (!$isEdit || ($isEdit && $pupil['class_id'] != $_POST['original_class_id'])) {
            try {
                // Get class capacity
                $capacityStmt = $db->prepare("SELECT capacity FROM class WHERE id = ?");
                $capacityStmt->execute([$pupil['class_id']]);
                $classData = $capacityStmt->fetch();

                // Count current pupils in class
                $countStmt = $db->prepare("SELECT COUNT(*) as count FROM pupil WHERE class_id = ?");
                $countStmt->execute([$pupil['class_id']]);
                $countData = $countStmt->fetch();

                if ($countData['count'] >= $classData['capacity']) {
                    $errors['class_id'] = 'Selected class has reached its capacity';
                }
            } catch (PDOException $e) {
                $errors['database'] = 'Error checking class capacity: ' . $e->getMessage();
            }
        }
    }

    if (empty($registration['registration_date'])) {
        $errors['registration_date'] = 'Registration date is required';
    }

    // Check that at least one parent is provided
    if (empty($parentData)) {
        $errors['parent'] = 'At least one parent/guardian is required';
    } else {
        // Validate parent data
        foreach ($parentData as $index => $parent) {
            if (empty($parent['phone_number'])) {
                $errors["parent_phone_{$index}"] = 'Phone number is required';
            }

            if (!empty($parent['email']) && !filter_var($parent['email'], FILTER_VALIDATE_EMAIL)) {
                $errors["parent_email_{$index}"] = 'Invalid email format';
            }
        }
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            $db->beginTransaction();

            if ($isEdit) {
                // Update existing registration
                $registration['id'] = $pupil['registration_id'];
                $regStmt = $db->prepare("UPDATE school_registration SET 
                                   registration_date = ?, status = ?, application_form_reference = ?, 
                                   interview_date = ?, enrollment_date = ? WHERE id = ?");
                $regStmt->execute([
                    $registration['registration_date'],
                    $registration['status'],
                    $registration['application_form_reference'],
                    $registration['interview_date'],
                    $registration['enrollment_date'],
                    $registration['id']
                ]);
            } else {
                // Create new registration
                $regStmt = $db->prepare("INSERT INTO school_registration 
                                  (registration_date, status, application_form_reference, interview_date, enrollment_date) 
                                  VALUES (?, ?, ?, ?, ?)");
                $regStmt->execute([
                    $registration['registration_date'],
                    $registration['status'],
                    $registration['application_form_reference'],
                    $registration['interview_date'],
                    $registration['enrollment_date']
                ]);
                $registration['id'] = $db->lastInsertId();
            }

            if ($isEdit) {
                // Update existing pupil
                $pupil['id'] = $_POST['id'];
                $pupilStmt = $db->prepare("UPDATE pupil SET 
                                    first_name = ?, last_name = ?, date_of_birth = ?, 
                                    address = ?, medical_information = ?, enrollment_date = ?, 
                                    dinner_money_balance = ?, class_id = ? WHERE id = ?");
                $pupilStmt->execute([
                    $pupil['first_name'],
                    $pupil['last_name'],
                    $pupil['date_of_birth'],
                    $pupil['address'],
                    $pupil['medical_information'],
                    $pupil['enrollment_date'],
                    $pupil['dinner_money_balance'],
                    $pupil['class_id'],
                    $pupil['id']
                ]);
            } else {
                // Create new pupil
                $pupilStmt = $db->prepare("INSERT INTO pupil 
                                   (first_name, last_name, date_of_birth, address, medical_information, 
                                   enrollment_date, dinner_money_balance, class_id, registration_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $pupilStmt->execute([
                    $pupil['first_name'],
                    $pupil['last_name'],
                    $pupil['date_of_birth'],
                    $pupil['address'],
                    $pupil['medical_information'],
                    $pupil['enrollment_date'],
                    $pupil['dinner_money_balance'],
                    $pupil['class_id'],
                    $registration['id']
                ]);
                $pupil['id'] = $db->lastInsertId();
            }

            // Handle parents - first remove existing relationships if editing
            if ($isEdit) {
                $deleteRelStmt = $db->prepare("DELETE FROM pupil_parent_guardian WHERE pupil_id = ?");
                $deleteRelStmt->execute([$pupil['id']]);
            }

            // Process each parent
            foreach ($parentData as $parent) {
                if (!empty($parent['id'])) {
                    // Update existing parent
                    $parentStmt = $db->prepare("UPDATE parent_guardian SET 
                                      first_name = ?, last_name = ?, address = ?, 
                                      email = ?, phone_number = ? WHERE id = ?");
                    $parentStmt->execute([
                        $parent['first_name'],
                        $parent['last_name'],
                        $parent['address'],
                        $parent['email'],
                        $parent['phone_number'],
                        $parent['id']
                    ]);
                    $parentId = $parent['id'];
                } else {
                    // Create new parent
                    $parentStmt = $db->prepare("INSERT INTO parent_guardian 
                                      (first_name, last_name, address, email, phone_number, relationship_to_pupil) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                    $parentStmt->execute([
                        $parent['first_name'],
                        $parent['last_name'],
                        $parent['address'],
                        $parent['email'],
                        $parent['phone_number'],
                        $parent['relationship_type']
                    ]);
                    $parentId = $db->lastInsertId();
                }

                // Create relationship
                $relStmt = $db->prepare("INSERT INTO pupil_parent_guardian 
                                (pupil_id, parent_guardian_id, relationship_type) 
                                VALUES (?, ?, ?)");
                $relStmt->execute([
                    $pupil['id'],
                    $parentId,
                    $parent['relationship_type']
                ]);
            }

            $db->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => ($isEdit ? 'Pupil updated' : 'Pupil created') . ' successfully'];
            header('Location: pupils.php');
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $errors['database'] = 'Error saving data: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Pupil</title>
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

        .card {
            margin-bottom: 20px;
        }

        .parent-section {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }

        .remove-parent {
            position: absolute;
            top: 10px;
            right: 10px;
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
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Pupil</h1>
                    <a href="pupils.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Pupils
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $errors['database']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                <?php endif; ?>

                <form method="POST" id="pupilForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($pupil['id']); ?>">
                            <input type="hidden" name="original_class_id" value="<?php echo htmlspecialchars($pupil['class_id']); ?>">
                    <?php endif; ?>

                    <!-- Pupil Information Card -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Pupil Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                        id="first_name" name="first_name" value="<?php echo htmlspecialchars($pupil['first_name']); ?>" required>
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

                                <div class="col-md-4">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                        id="last_name" name="last_name" value="<?php echo htmlspecialchars($pupil['last_name']); ?>" required>
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

                                <div class="col-md-4">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control <?php echo isset($errors['date_of_birth']) ? 'is-invalid' : ''; ?>"
                                        id="date_of_birth" name="date_of_birth" 
                                        value="<?php echo htmlspecialchars($pupil['date_of_birth']); ?>" required>
                                    <?php if (isset($errors['date_of_birth'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo $errors['date_of_birth']; ?>
                                            </div>
                                    <?php else: ?>
                                            <div class="invalid-feedback">
                                                Please select a date of birth.
                                            </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>"
                                    id="address" name="address" rows="3" required><?php echo htmlspecialchars($pupil['address']); ?></textarea>
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

                            <div class="mb-3">
                                <label for="medical_information" class="form-label">Medical Information (Optional)</label>
                                <textarea class="form-control" id="medical_information" name="medical_information" 
                                    rows="2"><?php echo htmlspecialchars($pupil['medical_information']); ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="class_id" class="form-label">Class</label>
                                    <select class="form-select <?php echo isset($errors['class_id']) ? 'is-invalid' : ''; ?>"
                                        id="class_id" name="class_id" required>
                                        <option value="">Select Class</option>
                                        <?php foreach ($availableClasses as $class): ?>
                                                <option value="<?php echo $class['id']; ?>" 
                                                    <?php echo $pupil['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['name']); ?> 
                                                    (Capacity: <?php echo $class['capacity']; ?>)
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['class_id'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo $errors['class_id']; ?>
                                            </div>
                                    <?php else: ?>
                                            <div class="invalid-feedback">
                                                Please select a class.
                                            </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="enrollment_date" class="form-label">Enrollment Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['enrollment_date']) ? 'is-invalid' : ''; ?>"
                                        id="enrollment_date" name="enrollment_date" 
                                        value="<?php echo htmlspecialchars($pupil['enrollment_date']); ?>" required>
                                    <?php if (isset($errors['enrollment_date'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo $errors['enrollment_date']; ?>
                                            </div>
                                    <?php else: ?>
                                            <div class="invalid-feedback">
                                                Please select an enrollment date.
                                            </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-4">
                                    <label for="dinner_money_balance" class="form-label">Dinner Money Balance</label>
                                    <div class="input-group">
                                        <span class="input-group-text">LKR</span>
                                        <input type="number" step="0.01" class="form-control"
                                            id="dinner_money_balance" name="dinner_money_balance" 
                                            value="<?php echo htmlspecialchars($pupil['dinner_money_balance']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registration Information Card -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Registration Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="registration_date" class="form-label">Registration Date</label>
                                    <input type="date" class="form-control <?php echo isset($errors['registration_date']) ? 'is-invalid' : ''; ?>"
                                        id="registration_date" name="registration_date" 
                                        value="<?php echo htmlspecialchars($registration['registration_date']); ?>" required>
                                    <?php if (isset($errors['registration_date'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo $errors['registration_date']; ?>
                                            </div>
                                    <?php else: ?>
                                            <div class="invalid-feedback">
                                                Please select a registration date.
                                            </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-3">
                                    <label for="registration_status" class="form-label">Registration Status</label>
                                    <select class="form-select" id="registration_status" name="registration_status">
                                        <option value="pending" <?php echo $registration['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $registration['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $registration['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="application_form_reference" class="form-label">Application Reference</label>
                                    <input type="text" class="form-control" id="application_form_reference" 
                                        name="application_form_reference" 
                                        value="<?php echo htmlspecialchars($registration['application_form_reference']); ?>">
                                </div>

                                <div class="col-md-3">
                                    <label for="interview_date" class="form-label">Interview Date </label>
                                    <input type="date" class="form-control" id="interview_date" name="interview_date" 
                                        value="<?php echo $registration['interview_date'] ? htmlspecialchars($registration['interview_date']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parents/Guardians Card -->
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Parents/Guardians</h5>
                            <button type="button" class="btn btn-light btn-sm" id="addParentBtn" 
                                <?php echo (count($parents) >= 2 || (isset($parentData) && count($parentData) >= 2)) ? 'disabled' : ''; ?>>
                                <i class="fas fa-plus"></i> Add Parent/Guardian
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (isset($errors['parent'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $errors['parent']; ?>
                                    </div>
                            <?php endif; ?>

                            <div id="parentsContainer">
                                <?php
                                // Display existing parents if editing
                                if ($isEdit && !empty($parents)):
                                    foreach ($parents as $index => $parent):
                                        ?>
                                        <div class="parent-section">
                                            <button type="button" class="btn btn-close remove-parent" 
                                                <?php echo count($parents) <= 1 ? 'disabled' : ''; ?>></button>
                                    
                                            <input type="hidden" name="parent_id[]" value="<?php echo $parent['id']; ?>">
                                    
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">First Name</label>
                                                    <input type="text" class="form-control" name="parent_first_name[]" 
                                                        value="<?php echo htmlspecialchars($parent['first_name']); ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Last Name</label>
                                                    <input type="text" class="form-control" name="parent_last_name[]" 
                                                        value="<?php echo htmlspecialchars($parent['last_name']); ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Relationship</label>
                                                    <input type="text" class="form-control" name="parent_relationship[]" 
                                                        value="<?php echo htmlspecialchars($parent['relationship_type']); ?>" required>
                                                </div>
                                            </div>
                                    
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="parent_address[]" rows="2" required><?php echo htmlspecialchars($parent['address']); ?></textarea>
                                            </div>
                                    
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control <?php echo isset($errors["parent_phone_{$index}"]) ? 'is-invalid' : ''; ?>" 
                                                        name="parent_phone[]" value="<?php echo htmlspecialchars($parent['phone_number']); ?>" required>
                                                    <?php if (isset($errors["parent_phone_{$index}"])): ?>
                                                            <div class="invalid-feedback">
                                                                <?php echo $errors["parent_phone_{$index}"]; ?>
                                                            </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Email (Optional)</label>
                                                    <input type="email" class="form-control <?php echo isset($errors["parent_email_{$index}"]) ? 'is-invalid' : ''; ?>" 
                                                        name="parent_email[]" value="<?php echo htmlspecialchars($parent['email']); ?>">
                                                    <?php if (isset($errors["parent_email_{$index}"])): ?>
                                                            <div class="invalid-feedback">
                                                                <?php echo $errors["parent_email_{$index}"]; ?>
                                                            </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    endforeach;
                                else:
                                    // Default parent form for new pupil
                                    ?>
                                    <div class="parent-section">
                                        <button type="button" class="btn btn-close remove-parent" disabled></button>
                                    
                                        <input type="hidden" name="parent_id[]" value="">
                                    
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" name="parent_first_name[]" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" name="parent_last_name[]" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Relationship</label>
                                                <input type="text" class="form-control" name="parent_relationship[]" 
                                                    placeholder="e.g. Mother, Father, Guardian" required>
                                            </div>
                                        </div>
                                    
                                        <div class="mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="parent_address[]" rows="2" required></textarea>
                                        </div>
                                    
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="parent_phone[]" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email (Optional)</label>
                                                <input type="email" class="form-control" name="parent_email[]">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Pupil
                        </button>
                        <a href="pupils.php" class="btn btn-outline-secondary">Cancel</a>
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

    <!-- Parent Template (Hidden) -->
    <template id="parentTemplate">
        <div class="parent-section">
            <button type="button" class="btn btn-close remove-parent"></button>
            
            <input type="hidden" name="parent_id[]" value="">
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="parent_first_name[]" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="parent_last_name[]" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Relationship</label>
                    <input type="text" class="form-control" name="parent_relationship[]" 
                        placeholder="e.g. Mother, Father, Guardian" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="parent_address[]" rows="2" required></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="parent_phone[]" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email (Optional)</label>
                    <input type="email" class="form-control" name="parent_email[]">
                </div>
            </div>
        </div>
    </template>

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

        // Add parent
        document.getElementById('addParentBtn').addEventListener('click', function() {
            const container = document.getElementById('parentsContainer');
            const template = document.getElementById('parentTemplate');
            const clone = document.importNode(template.content, true);
            
            container.appendChild(clone);
            
            // Update parent count and potentially disable add button
            const parentSections = container.querySelectorAll('.parent-section');
            if (parentSections.length >= 2) {
                this.disabled = true;
            }
            
            // Enable remove buttons
            parentSections.forEach(section => {
                section.querySelector('.remove-parent').disabled = false;
            });
        });

        // Remove parent (using event delegation)
        document.getElementById('parentsContainer').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-parent')) {
                const parentSection = e.target.closest('.parent-section');
                parentSection.remove();
                
                // Update parent count and potentially enable add button
                const container = document.getElementById('parentsContainer');
                const parentSections = container.querySelectorAll('.parent-section');
                
                document.getElementById('addParentBtn').disabled = parentSections.length >= 2;
                
                // If only one parent left, disable its remove button
                if (parentSections.length === 1) {
                    parentSections[0].querySelector('.remove-parent').disabled = true;
                }
            }
        });

        // Update registration status form behavior
        document.getElementById('registration_status').addEventListener('change', function() {
            const interviewDateField = document.getElementById('interview_date');
            
            if (this.value === 'approved') {
                interviewDateField.setAttribute('required', 'required');
            } else {
                interviewDateField.removeAttribute('required');
            }
        });

        // Synchronize enrollment date on both forms
        document.getElementById('enrollment_date').addEventListener('change', function() {
            document.getElementById('registration_status').value = 'approved';
        });
    </script>
</body>

</html>
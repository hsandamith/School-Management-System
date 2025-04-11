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
$salary = [
    'id' => '',
    'employee_id' => '',
    'employee_type' => 'teacher',
    'amount' => '',
    'payment_date' => date('Y-m-d'),
    'tax_information' => '',
    'payment_period' => 'monthly'
];
$errors = [];
$isEdit = false;

// Fetch all teachers and teaching assistants for dropdowns
try {
    $teachers = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM teacher ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
    $teachingAssistants = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM teaching_assistant ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = 'Error loading employees: ' . $e->getMessage();
}

// Check if editing existing salary record
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM salary WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $salary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$salary) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Salary record not found'];
            header('Location: salaries.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading salary record: ' . $e->getMessage()];
        header('Location: salaries.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $salary['employee_id'] = trim($_POST['employee_id'] ?? '');
    $salary['employee_type'] = $_POST['employee_type'] ?? 'teacher';
    $salary['amount'] = trim($_POST['amount'] ?? '');
    $salary['payment_date'] = trim($_POST['payment_date'] ?? '');
    $salary['tax_information'] = trim($_POST['tax_information'] ?? '');
    $salary['payment_period'] = $_POST['payment_period'] ?? 'monthly';

    // Validation
    if (empty($salary['employee_id'])) {
        $errors['employee_id'] = 'Employee is required';
    }

    if (empty($salary['amount'])) {
        $errors['amount'] = 'Amount is required';
    } elseif (!is_numeric($salary['amount']) || $salary['amount'] <= 0) {
        $errors['amount'] = 'Amount must be a positive number';
    }

    if (empty($salary['payment_date'])) {
        $errors['payment_date'] = 'Payment date is required';
    }

    if (empty($salary['payment_period'])) {
        $errors['payment_period'] = 'Payment period is required';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $salary['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE salary SET 
                    employee_id = ?, employee_type = ?, amount = ?, 
                    payment_date = ?, tax_information = ?, payment_period = ?
                    WHERE id = ?");
                $stmt->execute([
                    $salary['employee_id'],
                    $salary['employee_type'],
                    $salary['amount'],
                    $salary['payment_date'],
                    $salary['tax_information'],
                    $salary['payment_period'],
                    $salary['id']
                ]);
                $message = 'Salary record updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO salary 
                    (employee_id, employee_type, amount, payment_date, tax_information, payment_period) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $salary['employee_id'],
                    $salary['employee_type'],
                    $salary['amount'],
                    $salary['payment_date'],
                    $salary['tax_information'],
                    $salary['payment_period']
                ]);
                $message = 'Salary record created successfully';
            }
            
            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: salaries.php');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = 'Error saving salary record: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Create'; ?> Salary Record</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Flatpickr for date input -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Create'; ?> Salary Record</h1>
                    <a href="salaries.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Salaries
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="salaryForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($salary['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="employee_type" class="form-label">Employee Type</label>
                            <select  disabled class="form-select" id="employee_type" name="employee_type" required>
                                <option value="teacher" <?php echo $salary['employee_type'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="teaching_assistant" <?php echo $salary['employee_type'] === 'teaching_assistant' ? 'selected' : ''; ?>>Teaching Assistant</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="employee_id" class="form-label">Employee</label>
                            <select class="form-select <?php echo isset($errors['employee_id']) ? 'is-invalid' : ''; ?>" 
                                    id="employee_id" name="employee_id" required>
                                <option value="">Select an employee</option>
                                <?php if ($salary['employee_type'] === 'teacher'): ?>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>"
                                            <?php echo $salary['employee_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($teachingAssistants as $ta): ?>
                                        <option value="<?php echo $ta['id']; ?>"
                                            <?php echo $salary['employee_id'] == $ta['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ta['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($errors['employee_id'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['employee_id']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select an employee.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (£)</label>
                            <div class="input-group">
                                <span class="input-group-text">£</span>
                                <input type="number" step="0.01"
                                    class="form-control <?php echo isset($errors['amount']) ? 'is-invalid' : ''; ?>"
                                    id="amount" name="amount"
                                    value="<?php echo htmlspecialchars($salary['amount']); ?>" required>
                                <?php if (isset($errors['amount'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['amount']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="invalid-feedback">
                                        Please enter a valid amount.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="payment_date" class="form-label">Payment Date</label>
                            <input type="date"
                                class="form-control <?php echo isset($errors['payment_date']) ? 'is-invalid' : ''; ?>"
                                id="payment_date" name="payment_date"
                                value="<?php echo htmlspecialchars($salary['payment_date']); ?>" required>
                            <?php if (isset($errors['payment_date'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['payment_date']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a payment date.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="payment_period" class="form-label">Payment Period</label>
                            <select class="form-select <?php echo isset($errors['payment_period']) ? 'is-invalid' : ''; ?>"
                                    id="payment_period" name="payment_period" required>
                                <option value="weekly" <?php echo $salary['payment_period'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo $salary['payment_period'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="termly" <?php echo $salary['payment_period'] === 'termly' ? 'selected' : ''; ?>>Termly</option>
                                <option value="annually" <?php echo $salary['payment_period'] === 'annually' ? 'selected' : ''; ?>>Annually</option>
                            </select>
                            <?php if (isset($errors['payment_period'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['payment_period']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a payment period.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tax_information" class="form-label">Tax Information (Optional)</label>
                        <textarea class="form-control" id="tax_information" name="tax_information" rows="3"><?php echo htmlspecialchars($salary['tax_information']); ?></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Salary Record
                        </button>
                        <a href="salaries.php" class="btn btn-outline-secondary">Cancel</a>
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr for date input -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date picker
        flatpickr("#payment_date", {
            dateFormat: "Y-m-d",
            allowInput: true
        });

        // Update employee dropdown when type changes
        $('#employee_type').change(function() {
            $.ajax({
                url: 'get_employees.php',
                type: 'GET',
                data: {
                    type: $(this).val()
                },
                success: function(data) {
                    $('#employee_id').html(data);
                },
                error: function() {
                    $('#employee_id').html('<option value="">Error loading employees</option>');
                }
            });
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
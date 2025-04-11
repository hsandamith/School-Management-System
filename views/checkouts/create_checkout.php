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
$checkout = [
    'id' => '',
    'book_id' => '',
    'pupil_id' => '',
    'due_date' => date('Y-m-d', strtotime('+2 weeks'))
];
$errors = [];
$isEdit = false;

// Fetch available books and pupils
try {
    $availableBooks = $db->query("SELECT * FROM library_book WHERE available_status = 1 ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
    $pupils = $db->query("SELECT p.id, CONCAT(p.first_name, ' ', p.last_name) as name, c.name as class_name 
                         FROM pupil p JOIN class c ON p.class_id = c.id 
                         ORDER BY p.last_name, p.first_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = 'Error loading data: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $checkout['book_id'] = trim($_POST['book_id'] ?? '');
    $checkout['pupil_id'] = trim($_POST['pupil_id'] ?? '');
    $checkout['due_date'] = trim($_POST['due_date'] ?? '');

    // Validation
    if (empty($checkout['book_id'])) {
        $errors['book_id'] = 'Book is required';
    }

    if (empty($checkout['pupil_id'])) {
        $errors['pupil_id'] = 'Pupil is required';
    }

    if (empty($checkout['due_date'])) {
        $errors['due_date'] = 'Due date is required';
    } elseif (strtotime($checkout['due_date']) < strtotime(date('Y-m-d'))) {
        $errors['due_date'] = 'Due date cannot be in the past';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Create the checkout record
            $stmt = $db->prepare("INSERT INTO book_checkout_record 
                (book_id, pupil_id, checkout_date, due_date) 
                VALUES (?, ?, NOW(), ?)");
            $stmt->execute([
                $checkout['book_id'],
                $checkout['pupil_id'],
                $checkout['due_date']
            ]);
            
            // Update book availability
            $stmt = $db->prepare("UPDATE library_book SET available_status = 0 WHERE id = ?");
            $stmt->execute([$checkout['book_id']]);
            
            $db->commit();
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Book checked out successfully'];
            header('Location: checkouts.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors['database'] = 'Error checking out book: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Out Book</title>
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
                    <h1 class="h2">Check Out Book</h1>
                    <a href="checkouts.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Checkouts
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="checkoutForm" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="book_id" class="form-label">Book</label>
                            <select class="form-select <?php echo isset($errors['book_id']) ? 'is-invalid' : ''; ?>" 
                                    id="book_id" name="book_id" required>
                                <option value="">Select a book</option>
                                <?php foreach ($availableBooks as $book): ?>
                                    <option value="<?php echo $book['id']; ?>"
                                        <?php echo $checkout['book_id'] == $book['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($book['title']) . ' by ' . htmlspecialchars($book['author']); ?>
                                        <?php if (!empty($book['isbn'])): ?>
                                            (ISBN: <?php echo htmlspecialchars($book['isbn']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['book_id'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['book_id']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a book.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="pupil_id" class="form-label">Pupil</label>
                            <select class="form-select <?php echo isset($errors['pupil_id']) ? 'is-invalid' : ''; ?>" 
                                    id="pupil_id" name="pupil_id" required>
                                <option value="">Select a pupil</option>
                                <?php foreach ($pupils as $pupil): ?>
                                    <option value="<?php echo $pupil['id']; ?>"
                                        <?php echo $checkout['pupil_id'] == $pupil['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pupil['name']) . ' (' . htmlspecialchars($pupil['class_name']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['pupil_id'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['pupil_id']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a pupil.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date"
                                class="form-control <?php echo isset($errors['due_date']) ? 'is-invalid' : ''; ?>"
                                id="due_date" name="due_date"
                                value="<?php echo htmlspecialchars($checkout['due_date']); ?>" required>
                            <?php if (isset($errors['due_date'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['due_date']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please select a valid due date.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-book"></i> Check Out Book
                        </button>
                        <a href="checkouts.php" class="btn btn-outline-secondary">Cancel</a>
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
        // Initialize date picker with minimum date of today
        flatpickr("#due_date", {
            dateFormat: "Y-m-d",
            minDate: "today",
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
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
$book = [
    'id' => '',
    'title' => '',
    'author' => '',
    'isbn' => '',
    'publication_date' => '',
    'available_status' => 1,
    'book_condition' => 'good'
];
$errors = [];
$isEdit = false;

// Check if editing existing book
if (isset($_GET['id'])) {
    $isEdit = true;
    try {
        $stmt = $db->prepare("SELECT * FROM library_book WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $book = $stmt->fetch();

        if (!$book) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => 'Book not found'];
            header('Location: books.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading book: ' . $e->getMessage()];
        header('Location: books.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $book['title'] = trim($_POST['title'] ?? '');
    $book['author'] = trim($_POST['author'] ?? '');
    $book['isbn'] = trim($_POST['isbn'] ?? '');
    $book['publication_date'] = !empty($_POST['publication_date']) ? $_POST['publication_date'] : null;
    $book['available_status'] = isset($_POST['available_status']) ? 1 : 0;
    $book['book_condition'] = $_POST['book_condition'] ?? 'good';

    // Validation
    if (empty($book['title'])) {
        $errors['title'] = 'Title is required';
    }

    if (empty($book['author'])) {
        $errors['author'] = 'Author is required';
    }

    // ISBN validation - optional but must be unique if provided
    if (!empty($book['isbn'])) {
        try {
            // Check if ISBN exists for a different book
            $stmt = $db->prepare("SELECT id FROM library_book WHERE isbn = ? AND id != ?");
            $stmt->execute([$book['isbn'], $isEdit ? $book['id'] : 0]);
            if ($stmt->rowCount() > 0) {
                $errors['isbn'] = 'ISBN already exists in the database';
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Error checking ISBN: ' . $e->getMessage();
        }
    }

    // Condition validation
    $allowedConditions = ['new', 'good', 'fair', 'poor', 'withdrawn'];
    if (!in_array($book['book_condition'], $allowedConditions)) {
        $errors['book_condition'] = 'Invalid book condition selected';
    }

    // If no errors, proceed with save
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $book['id'] = $_POST['id'];
                $stmt = $db->prepare("UPDATE library_book SET title = ?, author = ?, isbn = ?, 
                                      publication_date = ?, available_status = ?, `book_condition` = ? WHERE id = ?");
                $stmt->execute([
                    $book['title'],
                    $book['author'],
                    $book['isbn'],
                    $book['publication_date'],
                    $book['available_status'],
                    $book['book_condition'],
                    $book['id']
                ]);
                $message = 'Book updated successfully';
            } else {
                $stmt = $db->prepare("INSERT INTO library_book (title, author, isbn, publication_date, 
                                     available_status, `book_condition`) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $book['title'],
                    $book['author'],
                    $book['isbn'],
                    $book['publication_date'],
                    $book['available_status'],
                    $book['book_condition']
                ]);
                $message = 'Book added successfully';
            }

            $_SESSION['message'] = ['type' => 'success', 'text' => $message];
            header('Location: books.php');
            exit();
        } catch (PDOException $e) {
            $errors['database'] = 'Error saving book: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Add New'; ?> Book</title>
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
                    <h1 class="h2"><?php echo $isEdit ? 'Edit' : 'Add New'; ?> Book</h1>
                    <a href="books.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Books
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($errors['database'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errors['database']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="bookForm" class="needs-validation" novalidate>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($book['id']); ?>">
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">Title</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>"
                                id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>"
                                required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['title']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter a book title.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="author" class="form-label">Author</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['author']) ? 'is-invalid' : ''; ?>"
                                id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>"
                                required>
                            <?php if (isset($errors['author'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['author']; ?>
                                </div>
                            <?php else: ?>
                                <div class="invalid-feedback">
                                    Please enter an author name.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="isbn" class="form-label">ISBN (Optional)</label>
                            <input type="text"
                                class="form-control <?php echo isset($errors['isbn']) ? 'is-invalid' : ''; ?>" id="isbn"
                                name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>">
                            <?php if (isset($errors['isbn'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['isbn']; ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-text">International Standard Book Number (unique identifier)</div>
                        </div>

                        <div class="col-md-6">
                            <label for="publication_date" class="form-label">Publication Date (Optional)</label>
                            <input type="date" class="form-control" id="publication_date" name="publication_date"
                                value="<?php echo $book['publication_date'] ? htmlspecialchars($book['publication_date']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="book_condition" class="form-label">Book Condition</label>
                            <select
                                class="form-select <?php echo isset($errors['book_condition']) ? 'is-invalid' : ''; ?>"
                                id="book_condition" name="book_condition" required>
                                <option value="new" <?php echo $book['book_condition'] === 'new' ? 'selected' : ''; ?>>New
                                </option>
                                <option value="good" <?php echo $book['book_condition'] === 'good' ? 'selected' : ''; ?>>
                                    Good
                                </option>
                                <option value="fair" <?php echo $book['book_condition'] === 'fair' ? 'selected' : ''; ?>>
                                    Fair
                                </option>
                                <option value="poor" <?php echo $book['book_condition'] === 'poor' ? 'selected' : ''; ?>>
                                    Poor
                                </option>
                                <option value="withdrawn" <?php echo $book['book_condition'] === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                            </select>
                            <?php if (isset($errors['book_condition'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['book_condition']; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="available_status"
                                    name="available_status" <?php echo $book['available_status'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="available_status">
                                    Available for checkout
                                </label>
                            </div>
                            <div class="form-text">Uncheck if book is currently checked out or unavailable</div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Save'; ?> Book
                        </button>
                        <a href="books.php" class="btn btn-outline-secondary">Cancel</a>
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
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

// Handle delete request
if (isset($_GET['delete'])) {
    try {
        $stmt = $db->prepare("DELETE FROM library_book WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Book deleted successfully'];
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting book: ' . $e->getMessage()];
    }

    header('Location: books.php');
    exit();
}

// Fetch all books
try {
    $query = "SELECT * FROM library_book ORDER BY title";
    $stmt = $db->query($query);
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading books: ' . $e->getMessage()];
    $books = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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

        .badge-condition-new {
            background-color: #28a745;
        }

        .badge-condition-good {
            background-color: #17a2b8;
        }

        .badge-condition-fair {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-condition-poor {
            background-color: #fd7e14;
        }

        .badge-condition-withdrawn {
            background-color: #dc3545;
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
                    <h1 class="h2">Books Management</h1>
                    <a href="create_book.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Book
                    </a>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show">
                        <?php echo $_SESSION['message']['text']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <!-- Books Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="booksTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>ISBN</th>
                                    <th>Publication Date</th>
                                    <th>Status</th>
                                    <th>Condition</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td><?php echo $book['id']; ?></td>
                                        <td><?php echo htmlspecialchars($book['title']); ?></td>
                                        <td><?php echo htmlspecialchars($book['author']); ?></td>
                                        <td>
                                            <?php if ($book['isbn']): ?>
                                                <?php echo htmlspecialchars($book['isbn']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($book['publication_date']): ?>
                                                <?php echo date('d/m/Y', strtotime($book['publication_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($book['available_status']): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $conditionClass = 'badge-condition-' . $book['book_condition'];
                                            $conditionLabel = ucfirst($book['book_condition']);
                                            ?>
                                            <span class="badge <?php echo $conditionClass; ?>">
                                                <?php echo $conditionLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="create_book.php?id=<?php echo $book['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                    data-id="<?php echo $book['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                                    title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the book <span id="bookTitle" class="fw-bold"></span>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
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
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize DataTable
            $('#booksTable').DataTable({
                "order": [[1, "asc"]], // Sort by title by default
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search books:"
                }
            });

            // Handle delete confirmation
            $('#deleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var title = button.data('title');

                $('#bookTitle').text(title);
                $('#confirmDelete').attr('href', 'books.php?delete=' + id);
            });

            // Show loading state on specific actions
            $('#confirmDelete').on('click', function () {
                $('.loading-overlay').css('display', 'flex');
            });

            $('a:not([data-bs-toggle="modal"]):not([href*="logout"])').on('click', function () {
                $('.loading-overlay').css('display', 'flex');
            });
        });
    </script>
</body>

</html>
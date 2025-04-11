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

// Handle return book action
if (isset($_GET['return'])) {
    try {
        $currentDate = date('Y-m-d H:i:s');

        // Calculate fine if overdue (assuming £0.50 per day overdue)
        $stmt = $db->prepare("SELECT due_date FROM book_checkout_record WHERE id = ?");
        $stmt->execute([$_GET['return']]);
        $dueDate = $stmt->fetchColumn();

        $fine = 0;
        if (strtotime($currentDate) > strtotime($dueDate)) {
            $daysOverdue = floor((strtotime($currentDate) - strtotime($dueDate)) / (60 * 60 * 24));
            $fine = $daysOverdue * 0.50; // £0.50 per day
        }

        // Update the record
        $stmt = $db->prepare("UPDATE book_checkout_record 
                             SET return_date = ?, fine_amount = ?
                             WHERE id = ?");
        $stmt->execute([$currentDate, $fine, $_GET['return']]);

        // Update book availability
        $stmt = $db->prepare("UPDATE library_book SET available_status = 1 
                             WHERE id = (SELECT book_id FROM book_checkout_record WHERE id = ?)");
        $stmt->execute([$_GET['return']]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Book returned successfully' . ($fine > 0 ? " with £$fine fine" : '')];
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error returning book: ' . $e->getMessage()];
    }

    header('Location: checkouts.php');
    exit();
}

// Fetch all checkout records with book and pupil info
try {
    $query = "SELECT cr.*, 
              b.title as book_title, b.author as book_author, b.isbn as book_isbn,
              CONCAT(p.first_name, ' ', p.last_name) as pupil_name,
              c.name as class_name
              FROM book_checkout_record cr
              JOIN library_book b ON cr.book_id = b.id
              JOIN pupil p ON cr.pupil_id = p.id
              JOIN class c ON p.class_id = c.id
              ORDER BY cr.return_date IS NULL DESC, cr.due_date ASC";

    $stmt = $db->query($query);
    $checkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading checkouts: ' . $e->getMessage()];
    $checkouts = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Checkouts</title>
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

        .badge-overdue {
            background-color: #dc3545;
        }

        .badge-returned {
            background-color: #28a745;
        }

        .badge-checkedout {
            background-color: #ffc107;
            color: #212529;
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
                    <h1 class="h2">Library Book Checkouts</h1>
                    <a href="create_checkout.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Checkout
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

                <!-- Checkouts Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="checkoutsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Book</th>
                                    <th>Pupil</th>
                                    <th>Checkout Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checkouts as $checkout): ?>
                                    <?php
                                    $isOverdue = is_null($checkout['return_date']) && strtotime(date('Y-m-d')) > strtotime($checkout['due_date']);
                                    $isReturned = !is_null($checkout['return_date']);
                                    ?>
                                    <tr class="<?php echo $isOverdue ? 'table-warning' : ''; ?>">
                                        <td><?php echo $checkout['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($checkout['book_title']); ?></strong><br>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($checkout['book_author']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($checkout['pupil_name']); ?><br>
                                            <small
                                                class="text-muted"><?php echo htmlspecialchars($checkout['class_name']); ?></small>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($checkout['checkout_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($checkout['due_date'])); ?></td>
                                        <td>
                                            <?php if ($isReturned): ?>
                                                <span class="badge badge-returned">Returned</span>
                                            <?php elseif ($isOverdue): ?>
                                                <span class="badge badge-overdue">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge badge-checkedout">Checked Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($checkout['fine_amount'] > 0): ?>
                                                £<?php echo number_format($checkout['fine_amount'], 2); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$isReturned): ?>
                                                <a href="checkouts.php?return=<?php echo $checkout['id']; ?>"
                                                    class="btn btn-sm btn-outline-success"
                                                    onclick="return confirm('Are you sure you want to mark this book as returned?')">
                                                    <i class="fas fa-book"></i> Return
                                                </a>
                                            <?php endif; ?>
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
            $('#checkoutsTable').DataTable({
                "order": [[3, "desc"]], // Sort by checkout date by default
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search checkouts:"
                }
            });

            // Show loading state on all links except logout
            $('a:not([href*="logout"])').on('click', function () {
                $('.loading-overlay').css('display', 'flex');
            });
        });
    </script>
</body>

</html>
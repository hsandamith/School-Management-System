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
        $stmt = $db->prepare("DELETE FROM salary WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Salary record deleted successfully'];
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting salary record: ' . $e->getMessage()];
    }

    header('Location: salaries.php');
    exit();
}

// Fetch all salary records with employee names
try {
    $query = "SELECT s.*, 
              CASE 
                WHEN s.employee_type = 'teacher' THEN CONCAT(t.first_name, ' ', t.last_name)
                WHEN s.employee_type = 'teaching_assistant' THEN CONCAT(ta.first_name, ' ', ta.last_name)
              END as employee_name,
              CASE 
                WHEN s.employee_type = 'teacher' THEN 'Teacher'
                WHEN s.employee_type = 'teaching_assistant' THEN 'Teaching Assistant'
              END as employee_type_name
              FROM salary s
              LEFT JOIN teacher t ON s.employee_type = 'teacher' AND s.employee_id = t.id
              LEFT JOIN teaching_assistant ta ON s.employee_type = 'teaching_assistant' AND s.employee_id = ta.id
              ORDER BY s.payment_date DESC";

    $stmt = $db->query($query);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading salary records: ' . $e->getMessage()];
    $salaries = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management</title>
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

        .badge-payment {
            background-color: #6c757d;
            color: white;
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
                    <h1 class="h2">Salary Management</h1>
                    <a href="create_salary.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Salary Record
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

                <!-- Salaries Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="salariesTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Period</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salaries as $salary): ?>
                                    <tr>
                                        <td><?php echo $salary['id']; ?></td>
                                        <td><?php echo htmlspecialchars($salary['employee_name']); ?></td>
                                        <td>
                                            <span
                                                class="badge bg-<?php echo $salary['employee_type'] === 'teacher' ? 'primary' : 'info'; ?>">
                                                <?php echo htmlspecialchars($salary['employee_type_name']); ?>
                                            </span>
                                        </td>
                                        <td>£<?php echo number_format($salary['amount'], 2); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($salary['payment_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-payment">
                                                <?php echo ucfirst($salary['payment_period']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="create_salary.php?id=<?php echo $salary['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                    data-id="<?php echo $salary['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($salary['employee_name'] . ' (' . $salary['employee_type_name'] . ') - ' . date('d/m/Y', strtotime($salary['payment_date']))); ?>"
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
                    Are you sure you want to delete the salary record for <span id="salaryName" class="fw-bold"></span>?
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
            $('#salariesTable').DataTable({
                "order": [[4, "desc"]], // Sort by payment date by default
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search salary records:"
                }
            });

            // Handle delete confirmation
            $('#deleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var name = button.data('name');

                $('#salaryName').text(name);
                $('#confirmDelete').attr('href', 'salaries.php?delete=' + id);
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
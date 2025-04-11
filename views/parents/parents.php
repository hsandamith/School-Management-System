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
        $stmt = $db->prepare("DELETE FROM parent_guardian WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Parent/Guardian deleted successfully'];
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting parent/guardian: ' . $e->getMessage()];
    }

    header('Location: parents.php');
    exit();
}

// Fetch all parents/guardians
try {
    $query = "SELECT * FROM parent_guardian ORDER BY last_name, first_name";
    $stmt = $db->query($query);
    $parents = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading parents/guardians: ' . $e->getMessage()];
    $parents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents/Guardians Management</title>
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
                    <h1 class="h2">Parents/Guardians Management</h1>
                    <a href="create_parent_guardian.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Parent/Guardian
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

                <!-- Parents/Guardians Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="parentsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact Information</th>
                                    <th>Address</th>
                                    <th>Relationship</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parents as $parent): ?>
                                    <tr>
                                        <td><?php echo $parent['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($parent['first_name']) . ' ' . htmlspecialchars($parent['last_name']); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone-alt text-muted me-1"></i>
                                            <?php echo htmlspecialchars($parent['phone_number']); ?>
                                            <?php if (!empty($parent['email'])): ?>
                                                <br>
                                                <i class="fas fa-envelope text-muted me-1"></i>
                                                <?php echo htmlspecialchars($parent['email']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($parent['address']); ?></td>
                                        <td>
                                            <?php if (!empty($parent['relationship_to_pupil'])): ?>
                                                <?php echo htmlspecialchars($parent['relationship_to_pupil']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="create_parent_guardian.php?id=<?php echo $parent['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                    data-id="<?php echo $parent['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>"
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
                    Are you sure you want to delete the parent/guardian <span id="parentName" class="fw-bold"></span>?
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
            $('#parentsTable').DataTable({
                "order": [[1, "asc"]], // Sort by name by default
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search parents/guardians:"
                }
            });

            // Handle delete confirmation
            $('#deleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var name = button.data('name');

                $('#parentName').text(name);
                $('#confirmDelete').attr('href', 'parents.php?delete=' + id);
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
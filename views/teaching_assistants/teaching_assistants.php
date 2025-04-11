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
        // First remove any class associations
        $stmt = $db->prepare("DELETE FROM class_teaching_assistant WHERE teaching_assistant_id = ?");
        $stmt->execute([$_GET['delete']]);

        // Then delete the teaching assistant
        $stmt = $db->prepare("DELETE FROM teaching_assistant WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Teaching assistant deleted successfully'];
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error deleting teaching assistant: ' . $e->getMessage()];
    }

    header('Location: teaching_assistants.php');
    exit();
}

// Fetch all teaching assistants with their assigned classes
try {
    $query = "SELECT ta.*, 
              GROUP_CONCAT(c.name SEPARATOR ', ') as assigned_classes
              FROM teaching_assistant ta
              LEFT JOIN class_teaching_assistant cta ON ta.id = cta.teaching_assistant_id
              LEFT JOIN class c ON cta.class_id = c.id
              GROUP BY ta.id
              ORDER BY ta.last_name, ta.first_name";

    $stmt = $db->query($query);
    $teachingAssistants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => 'Error loading teaching assistants: ' . $e->getMessage()];
    $teachingAssistants = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teaching Assistants Management</title>
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

        .badge-class {
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
                    <h1 class="h2">Teaching Assistants Management</h1>
                    <a href="create_teaching_assistant.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Teaching Assistant
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

                <!-- Teaching Assistants Table -->
                <div class="card">
                    <div class="card-body">
                        <table id="teachingAssistantsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact Information</th>
                                    <th>Hourly Rate</th>
                                    <th>Background Check</th>
                                    <th>Assigned Classes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachingAssistants as $ta): ?>
                                    <tr>
                                        <td><?php echo $ta['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($ta['first_name']) . ' ' . htmlspecialchars($ta['last_name']); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone-alt text-muted me-1"></i>
                                            <?php echo htmlspecialchars($ta['phone_number']); ?>
                                            <?php if (!empty($ta['email'])): ?>
                                                <br>
                                                <i class="fas fa-envelope text-muted me-1"></i>
                                                <?php echo htmlspecialchars($ta['email']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo '£' . number_format($ta['hourly_rate'], 2); ?></td>
                                        <td>
                                            <?php
                                            $checkStatus = $ta['background_check_status'];
                                            $badgeClass = [
                                                'pending' => 'bg-warning',
                                                'complete' => 'bg-success',
                                                'failed' => 'bg-danger'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $badgeClass[$checkStatus]; ?>">
                                                <?php echo ucfirst($checkStatus); ?>
                                                <?php if (!empty($ta['background_check_date'])): ?>
                                                    <br><small><?php echo date('d/m/Y', strtotime($ta['background_check_date'])); ?></small>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($ta['assigned_classes'])): ?>
                                                <?php
                                                $classes = explode(', ', $ta['assigned_classes']);
                                                foreach ($classes as $class): ?>
                                                    <span
                                                        class="badge badge-class mb-1"><?php echo htmlspecialchars($class); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="create_teaching_assistant.php?id=<?php echo $ta['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                    data-id="<?php echo $ta['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($ta['first_name'] . ' ' . $ta['last_name']); ?>"
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
                    Are you sure you want to delete the teaching assistant <span id="taName" class="fw-bold"></span>?
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
            $('#teachingAssistantsTable').DataTable({
                "order": [[1, "asc"]], // Sort by name by default
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search teaching assistants:"
                }
            });

            // Handle delete confirmation
            $('#deleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var name = button.data('name');

                $('#taName').text(name);
                $('#confirmDelete').attr('href', 'teaching_assistants.php?delete=' + id);
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
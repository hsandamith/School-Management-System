<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
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

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 5px;
        border-radius: 5px;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background-color: var(--primary-color);
        color: white;
    }

    .sidebar .nav-link i {
        margin-right: 10px;
    }

    .main-content {
        margin-left: 250px;
        padding: 20px;
    }

    .dashboard-card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
    }

    .card-icon {
        font-size: 2rem;
        opacity: 0.7;
    }

    .recent-pupils img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
</style>
<?php

function isActive($page)
{
    $currentPage = $_SERVER['PHP_SELF'];
    if (strpos($currentPage, $page) !== false) {
        return 'active';
    }
    return '';
}
?>
<div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="text-center mb-4">
        <h4>School Management</h4>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('teachers.php'); ?>" href="../teachers/teachers.php">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('classes.php'); ?>" href="../classes/classes.php">
                <i class="fas fa-door-open"></i> Classes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('pupils.php'); ?>" href="../pupils/pupils.php">
                <i class="fas fa-user-graduate"></i> Pupils
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('parents.php'); ?>" href="../parents/parents.php">
                <i class="fas fa-users"></i> Parents
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('teaching_assistants.php'); ?>"
                href="../teaching_assistants/teaching_assistants.php">
                <i class="fas fa-users"></i> Teaching Assistants
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('salaries.php'); ?>" href="../salaries/salaries.php">
                <i class="fas fa-dollar"></i> Salaries
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('books.php'); ?>" href="../books/books.php">
                <i class="fas fa-book"></i> Library Books
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isActive('checkouts.php'); ?>" href="../checkouts/checkouts.php">
                <i class="fas fa-book"></i> Checkouts
            </a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>
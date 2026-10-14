<?php 
require_once("adminOnly.php");
include('../database/db_connect.php'); 

$message = "";

// Handle Adding/Updating Fee Structure
if(isset($_POST['save_fee'])) {
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $term = mysqli_real_escape_string($conn, $_POST['term']);
    $tuition = mysqli_real_escape_string($conn, $_POST['tuition']);
    $boarding = mysqli_real_escape_string($conn, $_POST['boarding']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity']);
    $others = mysqli_real_escape_string($conn, $_POST['others']);
    $total = $tuition + $boarding + $activity + $others;

    // UPSERT Logic: Update if exists, otherwise Insert
    $check = $conn->query("SELECT fee_id FROM fee_structure WHERE form='$form' AND term='$term'");
    
    if($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE fee_structure SET tuition=?, boarding=?, activity=?, others=?, total_amount=? WHERE form=? AND term=?");
        $stmt->bind_param("ddddsss", $tuition, $boarding, $activity, $others, $total, $form, $term);
    } else {
        $stmt = $conn->prepare("INSERT INTO fee_structure (form, term, tuition, boarding, activity, others, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddddd", $form, $term, $tuition, $boarding, $activity, $others, $total);
    }
    
    if($stmt->execute()) {
        $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__fadeIn'>Fee structure updated successfully!</div>";
    }
    $stmt->close();
}

// Handle Delete
if(isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $conn->query("DELETE FROM fee_structure WHERE fee_id = '$id'");
    header("Location: fee_structure.php");
    exit();
}

$result = $conn->query("SELECT * FROM fee_structure ORDER BY form ASC, term ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure | BokinceX School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --sidebar-bg: #34445c; 
            --accent-yellow: #b38f00;
            --submenu-bg: #2c3a4d;
        }
        
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; }

        .sidebar { width: 240px; height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease-in-out; }
        .sidebar-header { background: #dee2e6; padding: 12px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; }
        .sidebar-header span { color: #1a3066; font-weight: 800; font-size: 0.9rem; }
        
        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: 240px; background: #f8f9fa; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #ddd; transition: 0.3s; }
        .main-content { margin-left: 240px; padding: 40px; transition: 0.3s; }
        .user-dropdown { background: var(--accent-yellow); color: white; padding: 8px 15px; font-size: 0.8rem; border: none; border-radius: 5px; font-weight: bold; }
        
        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1100; opacity: 0; transition: opacity 0.3s; }

        .table td, .table th { white-space: nowrap; width: 1%; }
        .table td.px-4, .table th.px-4 { width: auto; }

        /* Search bar styling */
        .search-area { background: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

        @media (max-width: 992px) {
            .sidebar { left: -240px; }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .overlay.active { display: block; opacity: 1; }
        }

        @media print {
            .sidebar, .topbar, .btn, .action-col, .search-area { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../images/logo.jpg" alt="Logo" onerror="this.src='https://via.placeholder.com/40'">
        <span>ADMIN PORTAL</span>
    </div>
    
    <div class="nav-item">
        <a href="index.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    </div>
    
    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-people-fill"></i> Students</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="admission.php">New Admission</a></li>
            <li><a href="student_list.php">Manage Students</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn">
            <span><i class="bi bi-journal-bookmark-fill"></i> Academics</span>
            <i class="bi bi-plus small"></i>
        </a>
        <ul class="submenu">
            <li><a href="forms.php">Manage Forms</a></li>
            <li><a href="subjects.php">Manage Subjects</a></li>
            <li><a href="exams.php">Exam Schedules</a></li>
        </ul>
    </div>

    <div class="nav-item">
        <a class="nav-link dropdown-toggle-btn active">
            <span><i class="bi bi-cash-stack"></i> Finance</span>
            <i class="bi bi-dash small"></i>
        </a>
        <ul class="submenu" style="display: block;">
            <li><a href="fee_structure.php" style="color: #f6e05e;">Fee Structure</a></li>
            <li><a href="collections.php">Fee Collections</a></li>
            <li><a href="reports.php">Financial Reports</a></li>
        </ul>
    </div>
</div>

<div class="topbar">
    <i class="bi bi-list fs-3" id="hamburger" style="cursor:pointer;"></i>
    
    <div class="dropdown">
        <button class="user-dropdown dropdown-toggle text-uppercase" type="button" data-bs-toggle="dropdown">
            ADMIN
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-person me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="adminLogOut.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-1">Fee Structure</h2>
            <p class="text-muted small">Configure and manage mandatory school fees</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>Print
            </button>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="resetModal()">
                <i class="bi bi-plus-lg me-2"></i>New Structure
            </button>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-area">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 rounded-start-pill text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-pill" placeholder="Search by amount or keyword...">
                </div>
            </div>
            <div class="col-md-3">
                <select id="filterForm" class="form-select rounded-pill">
                    <option value="">All Forms</option>
                    <option value="Form 1">Form 1</option>
                    <option value="Form 2">Form 2</option>
                    <option value="Form 3">Form 3</option>
                    <option value="Form 4">Form 4</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="filterTerm" class="form-select rounded-pill">
                    <option value="">All Terms</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Term 2">Term 2</option>
                    <option value="Term 3">Term 3</option>
                </select>
            </div>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="feeTable">
                <thead>
                    <tr class="text-muted small text-uppercase">
                        <th class="px-4">Form</th>
                        <th>Term</th>
                        <th>Tuition</th>
                        <th>Boarding</th>
                        <th>Activity</th>
                        <th>Others</th>
                        <th class="fw-bold">Total (KES)</th>
                        <th class="text-center action-col">Action</th>
                    </tr>
                </thead>
                <tbody id="feeTableBody">
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="fee-row">
                        <td class="px-4 fw-bold row-form">Form <?php echo $row['form']; ?></td>
                        <td class="row-term"><span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?php echo $row['term']; ?></span></td>
                        <td><?php echo number_format($row['tuition'], 2); ?></td>
                        <td><?php echo number_format($row['boarding'], 2); ?></td>
                        <td><?php echo number_format($row['activity'], 2); ?></td>
                        <td><?php echo number_format($row['others'], 2); ?></td>
                        <td class="fw-bold text-primary"><?php echo number_format($row['total_amount'], 2); ?></td>
                        <td class="text-center action-col">
                            <button class="btn btn-sm btn-light text-primary border-0 edit-btn" 
                                data-bs-toggle="modal" data-bs-target="#feeModal"
                                data-form="<?php echo $row['form']; ?>" 
                                data-term="<?php echo $row['term']; ?>"
                                data-tuition="<?php echo $row['tuition']; ?>" 
                                data-boarding="<?php echo $row['boarding']; ?>"
                                data-activity="<?php echo $row['activity']; ?>" 
                                data-others="<?php echo $row['others']; ?>">
                                <i class="bi bi-pencil-square fs-5"></i>
                            </button>
                            <a href="?delete=<?php echo $row['fee_id']; ?>" class="btn btn-sm btn-light text-danger border-0" onclick="return confirm('Delete this record?')">
                                <i class="bi bi-trash3 fs-5"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- FEE MODAL -->
<div class="modal fade" id="feeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add/Edit Fee Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Form</label>
                            <select name="form" id="m_form" class="form-select rounded-3" required>
                                <option value="1">Form 1</option>
                                <option value="2">Form 2</option>
                                <option value="3">Form 3</option>
                                <option value="4">Form 4</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Term</label>
                            <select name="term" id="m_term" class="form-select rounded-3" required>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tuition Fee</label>
                            <input type="number" step="0.01" name="tuition" id="m_tuition" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Boarding Fee</label>
                            <input type="number" step="0.01" name="boarding" id="m_boarding" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Activity Fee</label>
                            <input type="number" step="0.01" name="activity" id="m_activity" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Other Charges</label>
                            <input type="number" step="0.01" name="others" id="m_others" class="form-control rounded-3" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_fee" class="btn btn-primary rounded-pill px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Filtering Logic
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    const filterTerm = document.getElementById('filterTerm');
    const tableRows = document.querySelectorAll('.fee-row');

    function performFilter() {
        const query = searchInput.value.toLowerCase();
        const f_form = filterForm.value;
        const f_term = filterTerm.value;

        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowForm = row.querySelector('.row-form').innerText;
            const rowTerm = row.querySelector('.row-term').innerText;

            const matchesSearch = text.includes(query);
            const matchesForm = f_form === "" || rowForm === f_form;
            const matchesTerm = f_term === "" || rowTerm === f_term;

            row.style.display = (matchesSearch && matchesForm && matchesTerm) ? "" : "none";
        });
    }

    searchInput.addEventListener('input', performFilter);
    filterForm.addEventListener('change', performFilter);
    filterTerm.addEventListener('change', performFilter);

    // Existing UI Logic
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburger = document.getElementById('hamburger');

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const sub = btn.nextElementSibling;
            const icon = btn.querySelector('.bi-plus, .bi-dash');
            const show = sub.style.display !== "block";
            sub.style.display = show ? "block" : "none";
            if(icon) icon.classList.replace(show ? 'bi-plus' : 'bi-dash', show ? 'bi-dash' : 'bi-plus');
        });
    });

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modalTitle').innerText = "Edit Fee Structure";
            document.getElementById('m_form').value = this.dataset.form;
            document.getElementById('m_term').value = this.dataset.term;
            document.getElementById('m_tuition').value = this.dataset.tuition;
            document.getElementById('m_boarding').value = this.dataset.boarding;
            document.getElementById('m_activity').value = this.dataset.activity;
            document.getElementById('m_others').value = this.dataset.others;
        });
    });

    function resetModal() {
        document.getElementById('modalTitle').innerText = "New Fee Structure";
        document.querySelector('#feeModal form').reset();
    }
</script>
</body>
</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staffLogin.php");
    exit();
}

$staff_id = (int) $_SESSION['user_id'];
$staff_query = $conn->query("SELECT full_name FROM teachers WHERE teacher_id = $staff_id");
$staff_data = $staff_query->fetch_assoc() ?: [];
$staff_name = $staff_data['full_name'] ?? 'Staff';

// If a specific assignment is clicked, we show the submission list
$view_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch the list of all assignments this teacher has posted
$my_assignments = [];
$stmt = $conn->prepare("
    SELECT a.*, s.subject_name 
    FROM assignments a 
    LEFT JOIN subjects s ON a.subject_id = s.subject_id 
    WHERE a.teacher_id = ? 
    ORDER BY a.created_at DESC
");
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $my_assignments[] = $row;
$stmt->close();

$active_page = 'assignments';
$extra_label = 'Submissions Tracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #000a66;
            --primary-soft: #1a2590;
            --primary-tint: #eef0ff;
            --accent: #facc15;
            --accent-tint: #fff6cc;
            --accent-deep: #8a6a00;
            --bg-light: #f6f7fb;
            --white: #ffffff;
            --ink: #14183a;
            --text-muted: #5b6280;
            --line: #e3e6f2;
            --radius-lg: 24px;
        }

        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }

        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); }

        .ui-table-wrap { overflow-x: auto; }
        .ui-table { width: 100%; border-collapse: collapse; }
        .ui-table th { padding: 14px 18px; text-align: left; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); background: var(--bg-light); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .ui-table td { padding: 16px 18px; border-bottom: 1px solid var(--line); font-size: 0.92rem; font-weight: 500; color: var(--text-muted); vertical-align: middle; }
        .ui-table tbody tr:hover { background: var(--primary-tint); }
        .ui-name { display: block; color: var(--ink); font-weight: 700; font-size: 0.95rem; }
        
        .badge { padding: 6px 12px; border-radius: 50px; font-weight: 800; font-size: 0.75rem; }
        .badge-pending { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-submitted { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

        @media (max-width: 992px) { .main-content { padding: 20px; } }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">
    
    <?php if ($view_id === 0): ?>
        <!-- View 1: List of All Assignments -->
        <div class="ui-head">
            <div>
                <h1>Track Submissions</h1>
                <p>Select an assignment or CAT below to see who has submitted.</p>
            </div>
        </div>

        <div class="panel">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Class</th>
                            <th>Due Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_assignments)): ?>
                            <tr><td colspan="5" class="text-center py-4">No assignments posted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($my_assignments as $a): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo strtolower($a['assignment_type']) === 'cat' ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
                                            <?php echo htmlspecialchars($a['assignment_type']); ?>
                                        </span>
                                    </td>
                                    <td><span class="ui-name"><?php echo htmlspecialchars($a['title']); ?></span></td>
                                    <td><?php echo htmlspecialchars($a['class_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($a['due_date'])); ?></td>
                                    <td class="text-end">
                                        <a href="?id=<?php echo $a['assignment_id']; ?>" class="ui-btn is-primary is-sm" style="padding: 8px 16px; font-size: 0.8rem;">
                                            View List
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- View 2: Specific Assignment Submissions -->
        <?php
        // Fetch assignment details
        $a_info = null;
        foreach ($my_assignments as $a) {
            if ($a['assignment_id'] == $view_id) { $a_info = $a; break; }
        }
        
        if (!$a_info) {
            echo "<div class='alert alert-danger'>Assignment not found.</div>";
            exit;
        }

        // Fetch all students in that class
        $class_name = $a_info['class_name'];
        $students = [];
        $st_stmt = $conn->prepare("SELECT student_id, full_name, adm_no FROM students WHERE form = ? ORDER BY full_name ASC");
        $st_stmt->bind_param("s", $class_name);
        $st_stmt->execute();
        $st_res = $st_stmt->get_result();
        while($row = $st_res->fetch_assoc()) $students[] = $row;
        $st_stmt->close();

        // Fetch submissions for this assignment
        $submissions = [];
        $sub_stmt = $conn->prepare("SELECT student_id, file_path, submitted_at, status FROM assignment_submissions WHERE assignment_id = ?");
        $sub_stmt->bind_param("i", $view_id);
        $sub_stmt->execute();
        $sub_res = $sub_stmt->get_result();
        while($row = $sub_res->fetch_assoc()) {
            $submissions[$row['student_id']] = $row;
        }
        $sub_stmt->close();
        ?>

        <div class="ui-head">
            <div>
                <a href="assignment_submissions.php" class="text-decoration-none text-muted fw-bold mb-2 d-inline-block" style="font-size: 0.85rem;">
                    <i class="bi bi-arrow-left me-1"></i> Back to Assignments
                </a>
                <h1><?php echo htmlspecialchars($a_info['title']); ?></h1>
                <p>Tracking submissions for <strong><?php echo htmlspecialchars($class_name); ?></strong>.</p>
            </div>
        </div>

        <div class="panel">
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Adm No</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th class="text-end">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): 
                            $sid = $st['student_id'];
                            $has_sub = isset($submissions[$sid]);
                        ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($st['adm_no']); ?></span></td>
                                <td><span class="ui-name"><?php echo htmlspecialchars($st['full_name']); ?></span></td>
                                <td>
                                    <?php if ($has_sub): ?>
                                        <span class="badge badge-submitted"><i class="bi bi-check-circle-fill me-1"></i>Submitted</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending"><i class="bi bi-clock-fill me-1"></i>Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo $has_sub ? date('d M Y, H:i', strtotime($submissions[$sid]['submitted_at'])) : '—'; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($has_sub && !empty($submissions[$sid]['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($submissions[$sid]['file_path']); ?>" class="ui-btn is-ghost is-sm" download>
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">No file</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
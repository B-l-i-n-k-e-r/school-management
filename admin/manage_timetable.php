<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// Selected form from URL (passed from forms.php)
$selected_form = $_GET['form'] ?? '';

// ---- Handle Entry Deletion ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM timetable WHERE timetable_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_timetable.php?form=" . urlencode($selected_form) . "&deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Lesson removed successfully.";
    $message_type = 'info';
}

// ---- Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $form   = trim($_POST['form'] ?? '');
    $day    = trim($_POST['day_of_week'] ?? '');
    $start  = trim($_POST['start_time'] ?? '');
    $end    = trim($_POST['end_time'] ?? '');
    $sub_id = intval($_POST['subject_id'] ?? 0);
    $room   = trim($_POST['room_no'] ?? '');

    if ($form === '' || $day === '' || $start === '' || $end === '' || $sub_id <= 0) {
        $message = "All fields are required.";
        $message_type = 'warning';
    } elseif (strtotime($end) <= strtotime($start)) {
        $message = "End time must be after start time.";
        $message_type = 'danger';
    } else {
        $insert = $conn->prepare("INSERT INTO timetable (form, day_of_week, start_time, end_time, subject_id, room_no) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("ssssis", $form, $day, $start, $end, $sub_id, $room);
        if ($insert->execute()) {
            $message = "Lesson added to <strong>" . htmlspecialchars($form) . "</strong> schedule.";
            $message_type = 'success';
        } else {
            $message = "Error adding lesson: " . htmlspecialchars($conn->error);
            $message_type = 'danger';
        }
        $insert->close();
    }
}

// ---- Fetch subjects for dropdown ----
$subjects = [];
$s_res = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
if ($s_res) {
    while ($s = $s_res->fetch_assoc()) $subjects[] = $s;
}

// ---- Fetch classes for dropdown ----
$classes = [];
$c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($c = $c_res->fetch_assoc()) $classes[] = $c['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

// ---- Fetch timetable entries ----
$entries = [];
if ($selected_form !== '') {
    $stmt = $conn->prepare("
        SELECT t.*, s.subject_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.subject_id
        WHERE t.form = ?
        ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
    $stmt->bind_param("s", $selected_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $entries[] = $row;
    $stmt->close();
} else {
    $res = $conn->query("
        SELECT t.*, s.subject_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id = s.subject_id
        ORDER BY t.form, FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) $entries[] = $row;
    }
}

$active_page = 'manage_timetable';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-bg: #34445c;
            --sidebar-header: #dee2e6;
            --primary-blue: #1a3066;
            --sidebar-width: 260px;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar {
            width: var(--sidebar-width); height: 100vh;
            background: var(--sidebar-bg); color: white;
            position: fixed; left: 0; top: 0;
            z-index: 1200; transition: all 0.3s ease;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #4b5a71 var(--sidebar-bg);
        }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }

        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar,
        body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header {
            background: var(--sidebar-header); padding: 12px 20px;
            display: flex; align-items: center; gap: 12px; height: 70px;
        }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link {
            color: #d1d5db; padding: 15px 20px; font-size: 0.95rem;
            display: flex; align-items: center; justify-content: space-between;
            text-decoration: none; transition: 0.2s; cursor: pointer;
        }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar {
            margin-left: var(--sidebar-width); background: #fff;
            height: 70px; display: flex; justify-content: space-between;
            align-items: center; padding: 0 30px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select {
            border-radius: 12px; padding: 12px 15px;
            border: 1px solid #e2e8f0; background-color: #f8fafc;
            font-size: 0.9rem; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12);
            border-color: var(--primary-blue);
            background: #fff;
        }

        .btn-primary {
            background: var(--primary-blue); border: none;
            padding: 14px; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .badge-time {
            background: #eef2ff;
            color: var(--primary-blue);
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .badge-day {
            background: var(--primary-blue);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.4);
            z-index: 1150;
        }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Timetable Management</h2>
            <p class="text-muted small mb-0">
                Viewing schedule for:
                <strong><?php echo $selected_form !== '' ? htmlspecialchars($selected_form) : 'All Classes'; ?></strong>
            </p>
        </div>
        <a href="forms.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>Back to Forms
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Add lesson form -->
        <div class="col-lg-4">
            <div class="card adm-card p-4">
                <h5 class="fw-bold mb-4">Add New Lesson</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Class / Form</label>
                        <select name="form" class="form-select" required>
                            <?php foreach ($classes as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>"
                                        <?php echo ($cname === $selected_form) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Day of Week</label>
                        <select name="day_of_week" class="form-select" required>
                            <option>Monday</option>
                            <option>Tuesday</option>
                            <option>Wednesday</option>
                            <option>Thursday</option>
                            <option>Friday</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject...</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo intval($s['subject_id']); ?>">
                                    <?php echo htmlspecialchars($s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Room / Venue</label>
                        <input type="text" name="room_no" class="form-control" placeholder="e.g. Room 10 or Lab">
                    </div>

                    <button type="submit" name="add_lesson" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add to Schedule
                    </button>
                </form>
            </div>
        </div>

        <!-- Current schedule table -->
        <div class="col-lg-8">
            <div class="card adm-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Current Schedule</h5>
                    <span class="badge rounded-pill bg-light text-dark border">
                        <?php echo count($entries); ?> lesson<?php echo count($entries) === 1 ? '' : 's'; ?>
                    </span>
                </div>

                <?php if (empty($entries)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                        <p class="text-muted mb-0">
                            No lessons scheduled<?php echo $selected_form !== '' ? ' for this form' : ''; ?> yet.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="small text-muted text-uppercase">
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Room</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="badge-day"><?php echo htmlspecialchars($row['day_of_week']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge-time">
                                                <?php echo date("h:i A", strtotime($row['start_time'])); ?>
                                                &ndash;
                                                <?php echo date("h:i A", strtotime($row['end_time'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['subject_name'] ?? '—'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($row['form'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <span class="text-muted small">
                                                <?php echo !empty($row['room_no']) ? htmlspecialchars($row['room_no']) : '—'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="?delete=<?php echo intval($row['timetable_id']); ?>&form=<?php echo urlencode($selected_form); ?>"
                                               class="btn btn-sm btn-outline-danger border-0"
                                               onclick="return confirm('Remove this lesson?');">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<?php include("footer.php"); ?>
</body>
</html>
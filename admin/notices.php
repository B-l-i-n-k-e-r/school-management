<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// Get the form/class from the URL
$form_name = $_GET['form'] ?? '';

if (empty($form_name)) {
    header("Location: forms.php");
    exit();
}

// 1. Create the timetable table if it doesn't exist
// This ensures the structure perfectly matches what the staff page expects
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `timetable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `day_of_week` varchar(20) NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `teacher_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($create_table_sql);

// 2. Handle adding a new lesson
if (isset($_POST['add_lesson'])) {
    $day        = trim($_POST['day_of_week'] ?? '');
    $time       = trim($_POST['time_slot'] ?? '');
    $subject    = trim($_POST['subject_name'] ?? '');
    $teacher    = trim($_POST['teacher_name'] ?? '');

    if (empty($day) || empty($time) || empty($subject)) {
        $message = "Day, Time, and Subject are required.";
        $message_type = 'warning';
    } else {
        // Check for double booking (same class, same day, same time)
        $check = $conn->prepare("SELECT id FROM timetable WHERE class_name = ? AND day_of_week = ? AND time_slot = ?");
        $check->bind_param("sss", $form_name, $day, $time);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $message = "A lesson already exists for <strong>$form_name</strong> on <strong>$day</strong> at <strong>$time</strong>. Please delete the old one first.";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO timetable (class_name, day_of_week, time_slot, subject_name, teacher_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $form_name, $day, $time, $subject, $teacher);
            
            if ($stmt->execute()) {
                $message = "Lesson added successfully!";
                $message_type = 'success';
            } else {
                $message = "Error: " . htmlspecialchars($stmt->error);
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check->close();
    }
}

// 3. Handle deletion
if (isset($_GET['delete_lesson'])) {
    $id = intval($_GET['delete_lesson']);
    $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ? AND class_name = ?");
    $stmt->bind_param("is", $id, $form_name);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_timetable.php?form=" . urlencode($form_name) . "&deleted=1");
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Lesson removed.";
    $message_type = 'info';
}

// 4. Fetch existing timetable for this class
$timetable = [];
$stmt = $conn->prepare("SELECT * FROM timetable WHERE class_name = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), time_slot ASC");
$stmt->bind_param("s", $form_name);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $timetable[] = $row;
$stmt->close();

$active_page = 'forms';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable: <?php echo htmlspecialchars($form_name); ?> | Admin Portal</title>

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

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; }
        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }
        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        /* Timetable Grid */
        .timetable-table { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .timetable-table th { background: var(--primary-blue); color: white; padding: 12px; border-radius: 8px; text-align: center; font-size: 0.85rem; text-transform: uppercase; }
        .timetable-table td { background: #f8fafc; border-radius: 8px; padding: 10px; text-align: center; border: 1px solid #e2e8f0; position: relative; }
        .time-slot-col { background: #e2e8f0 !important; font-weight: 700; color: #475569; width: 120px; font-size: 0.85rem; }
        
        .lesson-box { background: #eef2ff; padding: 10px; border-radius: 6px; border-left: 3px solid var(--primary-blue); text-align: left; }
        .lesson-box .subj { font-weight: 700; color: var(--primary-blue); display: block; font-size: 0.85rem; margin-bottom: 4px; }
        .lesson-box .teach { font-size: 0.7rem; color: #64748b; }
        .delete-btn { position: absolute; top: -5px; right: -5px; width: 20px; height: 20px; border-radius: 50%; background: #ef4444; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; text-decoration: none; opacity: 0; transition: 0.2s; }
        .timetable-table td:hover .delete-btn { opacity: 1; }
        .delete-btn:hover { background: #dc2626; color: white; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .table-responsive { overflow-x: auto; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <a href="forms.php" class="text-decoration-none text-muted small mb-2 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Levels</a>
            <h2 class="fw-bold mb-1">Manage Timetable</h2>
            <p class="text-muted small mb-0">Schedule for <strong><?php echo htmlspecialchars($form_name); ?></strong></p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addLessonModal">
            <i class="bi bi-plus-lg me-2"></i> Add Lesson
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <div class="table-responsive">
            <?php if (empty($timetable)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-muted">No lessons scheduled</h5>
                    <p class="text-muted mb-0">Click "Add Lesson" to build the timetable for <?php echo htmlspecialchars($form_name); ?>.</p>
                </div>
            <?php else: ?>
                <?php
                // Organize data into a grid: [Day][Time] = Lesson Details
                $grid = [];
                $times = [];
                foreach ($timetable as $row) {
                    $grid[$row['day_of_week']][$row['time_slot']] = $row;
                    $times[$row['time_slot']] = $row['time_slot'];
                }
                ksort($times);
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                ?>
                <table class="timetable-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <?php foreach ($days as $d) echo "<th>$d</th>"; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($times as $t): ?>
                            <tr>
                                <td class="time-slot-col"><?php echo htmlspecialchars($t); ?></td>
                                <?php foreach ($days as $d): ?>
                                    <td>
                                        <?php if (isset($grid[$d][$t])): 
                                            $lesson = $grid[$d][$t];
                                        ?>
                                            <div class="lesson-box">
                                                <a href="?form=<?php echo urlencode($form_name); ?>&delete_lesson=<?php echo $lesson['id']; ?>" class="delete-btn" onclick="return confirm('Remove this lesson?');"><i class="bi bi-x"></i></a>
                                                <span class="subj"><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                                                <span class="teach"><i class="bi bi-person-fill"></i> <?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Lesson Modal -->
<div class="modal fade" id="addLessonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold mb-0">Add Lesson to <?php echo htmlspecialchars($form_name); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Day of Week</label>
                        <select name="day_of_week" class="form-select" required>
                            <option value="" disabled selected>-- Select Day --</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time Slot</label>
                        <input type="text" name="time_slot" class="form-control" placeholder="e.g. 08:00 - 09:00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. Mathematics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher (Optional)</label>
                        <input type="text" name="teacher_name" class="form-control" placeholder="e.g. Mr. John Doe">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_lesson" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-calendar-plus me-1"></i> Save Lesson
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>
</body>
</html>
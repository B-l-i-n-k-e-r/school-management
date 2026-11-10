<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

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

// ---- Fetch data ----
$subjects = [];
$s_res = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
if ($s_res) { while ($s = $s_res->fetch_assoc()) $subjects[] = $s; }

$classes = [];
$c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) { while ($c = $c_res->fetch_assoc()) $classes[] = $c['name']; }
if (empty($classes)) { $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4']; }

$entries = [];
if ($selected_form !== '') {
    $stmt = $conn->prepare("SELECT t.*, s.subject_name FROM timetable t LEFT JOIN subjects s ON t.subject_id = s.subject_id WHERE t.form = ? ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time");
    $stmt->bind_param("s", $selected_form);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $entries[] = $row;
    $stmt->close();
} else {
    $res = $conn->query("SELECT t.*, s.subject_name FROM timetable t LEFT JOIN subjects s ON t.subject_id = s.subject_id ORDER BY t.form, FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time");
    if ($res) { while ($row = $res->fetch_assoc()) $entries[] = $row; }
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
        .badge-day { background: var(--primary-tint); color: var(--primary); padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
        .badge-time { background: var(--accent-tint); color: var(--accent-deep); padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; display: inline-block; }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <a href="forms.php" class="text-decoration-none text-muted fw-bold mb-2 d-inline-block" style="font-size: 0.85rem;">
                <i class="bi bi-arrow-left me-1"></i> Back to Forms
            </a>
            <h1>Timetable Management</h1>
            <p>Viewing schedule for: <strong style="color: var(--primary);"><?php echo $selected_form !== '' ? htmlspecialchars($selected_form) : 'All Classes'; ?></strong></p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>">
            <i class="bi bi-info-circle-fill"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Add lesson form -->
        <div class="col-lg-4">
            <div class="panel h-100">
                <div class="ui-panel-head mb-4" style="padding: 0;">
                    <h2>Add New Lesson</h2>
                </div>
                <form method="POST">
                    <div class="ui-field mb-3">
                        <label>Class / Form</label>
                        <select name="form" class="ui-input form-select" required>
                            <?php foreach ($classes as $cname): ?>
                                <option value="<?php echo htmlspecialchars($cname); ?>" <?php echo ($cname === $selected_form) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ui-field mb-3">
                        <label>Day of Week</label>
                        <select name="day_of_week" class="ui-input form-select" required>
                            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
                            <option>Thursday</option><option>Friday</option>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col ui-field">
                            <label>Start Time</label>
                            <input type="time" name="start_time" class="ui-input" required>
                        </div>
                        <div class="col ui-field">
                            <label>End Time</label>
                            <input type="time" name="end_time" class="ui-input" required>
                        </div>
                    </div>

                    <div class="ui-field mb-3">
                        <label>Subject</label>
                        <select name="subject_id" class="ui-input form-select" required>
                            <option value="">Select Subject...</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo intval($s['subject_id']); ?>">
                                    <?php echo htmlspecialchars($s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ui-field mb-4">
                        <label>Room / Venue</label>
                        <input type="text" name="room_no" class="ui-input" placeholder="e.g. Room 10 or Lab">
                    </div>

                    <button type="submit" name="add_lesson" class="ui-btn is-primary w-100">
                        <i class="bi bi-plus-circle"></i> Add to Schedule
                    </button>
                </form>
            </div>
        </div>

        <!-- Current schedule table -->
        <div class="col-lg-8">
            <div class="panel is-flush h-100">
                <div class="ui-panel-head">
                    <div class="ui-panel-title">
                        <h2>Current Schedule</h2>
                        <span class="ui-count"><?php echo count($entries); ?> Lessons</span>
                    </div>
                </div>

                <?php if (empty($entries)): ?>
                    <div class="ui-empty">
                        <i class="bi bi-calendar-x"></i>
                        <p>No lessons scheduled</p>
                        <small>Use the form to add a lesson to the timetable.</small>
                    </div>
                <?php else: ?>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Subject</th>
                                    <th>Room</th>
                                    <th class="is-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $row): ?>
                                    <tr>
                                        <td><span class="badge-day"><?php echo htmlspecialchars($row['day_of_week']); ?></span></td>
                                        <td>
                                            <span class="badge-time">
                                                <?php echo date("h:i A", strtotime($row['start_time'])); ?> &ndash; <?php echo date("h:i A", strtotime($row['end_time'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ui-name"><?php echo htmlspecialchars($row['subject_name'] ?? '—'); ?></span>
                                            <span class="ui-sub"><?php echo htmlspecialchars($row['form'] ?? ''); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted fw-semibold" style="font-size: 0.9rem;">
                                                <?php echo !empty($row['room_no']) ? htmlspecialchars($row['room_no']) : '—'; ?>
                                            </span>
                                        </td>
                                        <td class="is-right">
                                            <div class="ui-row-actions">
                                                <a href="?delete=<?php echo intval($row['timetable_id']); ?>&form=<?php echo urlencode($selected_form); ?>" class="ui-icon is-danger" onclick="return confirm('Remove this lesson?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
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
<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Protection & Connection
require_once("adminOnly.php"); // Uses the same login logic as forms.php
include('../database/db_connect.php');

$success_msg = "";
$error_msg = "";

// Get the form from the URL (passed from forms.php)
$selected_form = isset($_GET['form']) ? $_GET['form'] : '';

// 2. Handle Entry Deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM timetable WHERE timetable_id = $id");
    $success_msg = "Lesson removed successfully.";
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    $form = $_POST['form'];
    $day = $_POST['day_of_week'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $sub_id = $_POST['subject_id'];
    $room = $_POST['room_no'];

    $insert = $conn->prepare("INSERT INTO timetable (form, day_of_week, start_time, end_time, subject_id, room_no) VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param("ssssis", $form, $day, $start, $end, $sub_id, $room);
    
    if ($insert->execute()) {
        $success_msg = "Lesson added to $form schedule.";
    } else {
        $error_msg = "Error adding lesson: " . $conn->error;
    }
}

// 4. Fetch Subjects for Dropdown
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");

// 5. Fetch Existing Timetable Entries (Filtered by the selected form if applicable)
$where_clause = $selected_form ? "WHERE t.form = '" . mysqli_real_escape_string($conn, $selected_form) . "'" : "";
$all_tt = $conn->query("SELECT t.*, s.subject_name 
                        FROM timetable t 
                        JOIN subjects s ON t.subject_id = s.subject_id 
                        $where_clause
                        ORDER BY t.day_of_week, t.start_time");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable | BokinceX Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
        .admin-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .table thead th { background-color: #f8fafc; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #64748b; border-top: none; }
        .btn-primary { background-color: #6366f1; border: none; padding: 10px 20px; border-radius: 10px; }
        .btn-primary:hover { background-color: #4f46e5; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #475569; }
        .badge-time { background: #eef2ff; color: #6366f1; font-weight: 600; padding: 5px 10px; border-radius: 6px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Timetable Management</h2>
            <p class="text-muted">Viewing schedule for: <strong><?php echo $selected_form ?: 'All Classes'; ?></strong></p>
        </div>
        <a href="forms.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>Back to Forms
        </a>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4"><?php echo $success_msg; ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="admin-card p-4">
                <h5 class="fw-bold mb-4">Add New Lesson</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Class / Form</label>
                        <select name="form" class="form-select" required>
                            <?php 
                            $forms_list = $conn->query("SELECT form FROM standard ORDER BY form ASC");
                            while($f = $forms_list->fetch_assoc()): 
                                $selected = ($f['form'] == $selected_form) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $f['form']; ?>" <?php echo $selected; ?>>
                                    <?php echo $f['form']; ?>
                                </option>
                            <?php endwhile; ?>
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
                            <?php 
                            $subjects->data_seek(0); // Reset subject pointer
                            while($s = $subjects->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $s['subject_id']; ?>"><?php echo $s['subject_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Room / Venue</label>
                        <input type="text" name="room_no" class="form-control" placeholder="e.g. Room 10 or Lab">
                    </div>

                    <button type="submit" name="add_lesson" class="btn btn-primary w-100 fw-bold">
                        Add to Schedule
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="admin-card p-4">
                <h5 class="fw-bold mb-4">Current Schedule</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Room</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($all_tt->num_rows > 0): ?>
                                <?php while($row = $all_tt->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo $row['day_of_week']; ?></td>
                                    <td>
                                        <span class="badge-time small">
                                            <?php echo date("h:i A", strtotime($row['start_time'])); ?> - <?php echo date("h:i A", strtotime($row['end_time'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                        <div class="text-muted small"><?php echo $row['form']; ?></div>
                                    </td>
                                    <td><span class="text-muted small"><?php echo htmlspecialchars($row['room_no']); ?></span></td>
                                    <td class="text-end">
                                        <a href="?delete=<?php echo $row['timetable_id']; ?>&form=<?php echo urlencode($selected_form); ?>" 
                                           class="btn btn-sm btn-outline-danger border-0" 
                                           onclick="return confirm('Remove this lesson?')">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                        No lessons scheduled for this form yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
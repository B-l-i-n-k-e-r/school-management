<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// --- 1. HANDLE SAVING (NEW OR UPDATE) ---
if (isset($_POST['enroll_student'])) {
    $full_name      = trim($_POST['full_name']      ?? '');
    $email          = trim($_POST['email']          ?? '');
    $gender         = trim($_POST['gender']         ?? '');
    $form           = trim($_POST['form']           ?? '');
    $parent_name    = trim($_POST['parent_name']    ?? '');
    $parent_contact = trim($_POST['parent_contact'] ?? '');
    $address        = trim($_POST['address']        ?? '');

    $student_id = isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int)$_POST['student_id'] : null;

    if ($student_id) {
        // --- UPDATE existing student ---
        $stmt = $conn->prepare("UPDATE students SET full_name=?, email=?, gender=?, form=?, parent_name=?, parent_contact=?, address=? WHERE student_id=?");
        $stmt->bind_param("sssssssi", $full_name, $email, $gender, $form, $parent_name, $parent_contact, $address, $student_id);
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Update failed.";
            $message_type = 'danger';
        }
        $stmt->close();
    } else {
        // --- INSERT new student ---
        $adm_no      = "ADM-" . rand(1000, 9999);
        $hashed_pass = password_hash("123456", PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            // Create user
            $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
            $stmt1->bind_param("sss", $adm_no, $email, $hashed_pass);
            $stmt1->execute();
            $user_id = $conn->insert_id;
            $stmt1->close();

            // Create student
            $stmt2 = $conn->prepare("
                INSERT INTO students
                (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, address, password)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt2->bind_param("isssssssss",
                $user_id, $full_name, $adm_no, $gender, $form,
                $parent_name, $parent_contact, $email, $address, $hashed_pass
            );
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            $message = "Enrollment successful! ADM No: <strong>" . htmlspecialchars($adm_no) . "</strong>";
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: Duplicate email or database issue.";
            $message_type = 'danger';
        }
    }
}

// --- 2. HANDLE DELETION ---
if (isset($_POST['delete_student_id'])) {
    $sid = (int) $_POST['delete_student_id'];
    $res = $conn->query("SELECT user_id FROM students WHERE student_id = $sid");
    if ($res && $res->num_rows > 0) {
        $uid = (int) $res->fetch_assoc()['user_id'];
        $conn->query("DELETE FROM students WHERE student_id = $sid");
        $conn->query("DELETE FROM users WHERE user_id = $uid");
        $message = "Student record removed.";
        $message_type = 'info';
    }
}

// --- 3. FETCH ALL STUDENTS ---
$students = [];
$s_res = $conn->query("SELECT * FROM students ORDER BY student_id DESC");
if ($s_res) {
    while ($row = $s_res->fetch_assoc()) $students[] = $row;
}

$active_page = 'student_list';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';

// Icon for the message box
$message_icons = [
    'success' => 'bi-check-circle-fill',
    'danger'  => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];

// Only these fields are handed to the edit pop-up (never the password hash)
$edit_fields = array_flip(['student_id', 'full_name', 'email', 'gender', 'form', 'parent_name', 'parent_contact', 'address']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Sidebar, top bar and shared components (buttons, tables, alerts, forms) come from includes/sidebar.php and includes/navbar.php -->
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Student directory</h1>
            <p>View and manage all registered students</p>
        </div>
        <div class="ui-actions no-print">
            <button type="button" class="ui-btn is-ghost" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="ui-btn is-primary" onclick="openEnrollModal()">
                <i class="bi bi-plus-lg"></i> Enroll student
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi <?php echo $message_icons[$message_type] ?? 'bi-info-circle-fill'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Enroll / Edit Modal -->
    <div class="modal fade" id="enrollModal" tabindex="-1" aria-labelledby="enrollModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content ui-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="enrollModalLabel">Student enrollment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="student_id" id="form_student_id">
                    <div class="modal-body">
                        <div class="ui-form-grid">
                            <div class="ui-field">
                                <label for="form_full_name">Full name</label>
                                <input type="text" name="full_name" id="form_full_name" class="form-control ui-input" required>
                            </div>
                            <div class="ui-field">
                                <label for="form_email">Email address</label>
                                <input type="email" name="email" id="form_email" class="form-control ui-input" required>
                            </div>
                            <div class="ui-field">
                                <label for="form_gender">Gender</label>
                                <select name="gender" id="form_gender" class="form-select ui-input" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="ui-field">
                                <label for="form_form">Level / form</label>
                                <select name="form" id="form_form" class="form-select ui-input" required>
                                    <?php
                                    $classes = [];
                                    $c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
                                    if ($c_res) {
                                        while ($c = $c_res->fetch_assoc()) $classes[] = $c['name'];
                                    }
                                    if (empty($classes)) {
                                        $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
                                    }
                                    foreach ($classes as $cname) {
                                        echo '<option>' . htmlspecialchars($cname) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="ui-field">
                                <label for="form_parent_name">Parent name</label>
                                <input type="text" name="parent_name" id="form_parent_name" class="form-control ui-input" required>
                            </div>
                            <div class="ui-field">
                                <label for="form_parent_contact">Parent contact</label>
                                <input type="text" name="parent_contact" id="form_parent_contact" class="form-control ui-input" required>
                            </div>
                            <div class="ui-field is-full">
                                <label for="form_address">Address</label>
                                <input type="text" name="address" id="form_address" class="form-control ui-input" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="enroll_student" id="form_submit_btn" class="ui-btn is-primary">Save student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Registry table -->
    <section class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Student registry</h2>
                <span class="ui-count">
                    <?php echo count($students); ?> student<?php echo count($students) === 1 ? '' : 's'; ?>
                </span>
            </div>
            <label class="ui-search no-print">
                <i class="bi bi-search"></i>
                <input type="text" id="studentSearch" placeholder="Search registry..." aria-label="Search registry">
            </label>
        </div>

        <?php if (empty($students)): ?>
            <div class="ui-empty">
                <i class="bi bi-people"></i>
                <p>No students enrolled yet.</p>
                <small>Click <strong>Enroll student</strong> to add the first one.</small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table" id="studentTable">
                    <thead>
                        <tr>
                            <th>ADM no</th>
                            <th>Student details</th>
                            <th>Form</th>
                            <th>Parent / guardian</th>
                            <th class="is-center no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $row): ?>
                            <tr>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span></td>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span>
                                    <span class="ui-sub">
                                        <?php echo htmlspecialchars($row['email'] ?? ''); ?>
                                        <?php if (!empty($row['gender'])): ?>
                                            &middot; <?php echo htmlspecialchars($row['gender']); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td><span class="ui-badge is-gold"><?php echo htmlspecialchars($row['form'] ?? ''); ?></span></td>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($row['parent_name'] ?? ''); ?></span>
                                    <span class="ui-sub"><?php echo htmlspecialchars($row['parent_contact'] ?? ''); ?></span>
                                </td>
                                <td class="is-center no-print">
                                    <div class="ui-row-actions" style="justify-content:center;">
                                        <button type="button" class="ui-icon" title="Edit student" aria-label="Edit student"
                                                onclick='editStudent(<?php echo json_encode(array_intersect_key($row, $edit_fields), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>)'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this student?');">
                                            <input type="hidden" name="delete_student_id" value="<?php echo intval($row['student_id']); ?>">
                                            <button type="submit" class="ui-icon is-danger" title="Delete student" aria-label="Delete student">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include("footer.php"); ?>

<script>
    const enrollModal = new bootstrap.Modal(document.getElementById('enrollModal'));

    function openEnrollModal() {
        document.getElementById('form_student_id').value = "";
        document.getElementById('form_full_name').value = "";
        document.getElementById('form_email').value = "";
        document.getElementById('form_gender').value = "Male";
        document.getElementById('form_parent_name').value = "";
        document.getElementById('form_parent_contact').value = "";
        document.getElementById('form_address').value = "";

        document.getElementById('enrollModalLabel').innerText = "New student enrollment";
        document.getElementById('form_submit_btn').innerText = "Complete enrollment";
        enrollModal.show();
    }

    function editStudent(data) {
        document.getElementById('form_student_id').value      = data.student_id;
        document.getElementById('form_full_name').value       = data.full_name;
        document.getElementById('form_email').value           = data.email;
        document.getElementById('form_gender').value          = data.gender;
        document.getElementById('form_form').value            = data.form;
        document.getElementById('form_parent_name').value     = data.parent_name;
        document.getElementById('form_parent_contact').value  = data.parent_contact;
        document.getElementById('form_address').value         = data.address;

        document.getElementById('enrollModalLabel').innerText = "Edit student profile";
        document.getElementById('form_submit_btn').innerText = "Update record";
        enrollModal.show();
    }

    // Live search
    const studentSearch = document.getElementById('studentSearch');
    if (studentSearch) {
        studentSearch.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#studentTable tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$default_pass = "mariba2026";
$school_domain = "michaelson.sc.ke";

// ---- Compute the NEXT staff ID ----
function getNextStaffId($conn) {
    $year  = date('Y');
    $like  = "STF/%/{$year}";
    $stmt = $conn->prepare("SELECT employee_id FROM teachers WHERE employee_id LIKE ? ORDER BY teacher_id DESC LIMIT 1");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $next = 1;
    if ($row && preg_match('/STF\/(\d+)\//', $row['employee_id'], $m)) {
        $next = intval($m[1]) + 1;
    }
    return sprintf('STF/%03d/%d', $next, $year);
}

$next_employee_id = getNextStaffId($conn);
$next_email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $next_employee_id)) . '@' . $school_domain;

// ---- Handle submission ----
if (isset($_POST['register_staff'])) {
    $full_name     = trim($_POST['full_name']     ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    
    $assignments_json = $_POST['assignments_json'] ?? '[]';
    $assignments = json_decode($assignments_json, true);

    $password = password_hash($default_pass, PASSWORD_DEFAULT);
    $role     = "teacher";

    $employee_id = getNextStaffId($conn);
    $email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $employee_id)) . '@' . $school_domain;

    if ($full_name === '' || $phone === '' || $qualification === '') {
        $message = "Please fill all required fields.";
        $message_type = 'warning';
    } elseif (empty($assignments) || !is_array($assignments)) {
        $message = "You must add at least one Class & Subject assignment.";
        $message_type = 'warning';
    } else {
        $conn->begin_transaction();
        try {
            // 1. Insert into users
            $stmt_user = $conn->prepare("INSERT INTO users (username, employee_id, email, full_name, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_user->bind_param("ssssss", $employee_id, $employee_id, $email, $full_name, $password, $role);
            $stmt_user->execute();
            $user_id = $conn->insert_id;
            $stmt_user->close();

            // 2. Insert into teachers
            $stmt_staff = $conn->prepare("INSERT INTO teachers (user_id, employee_id, full_name, email, phone, qualification) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_staff->bind_param("isssss", $user_id, $employee_id, $full_name, $email, $phone, $qualification);
            $stmt_staff->execute();
            $teacher_id = $conn->insert_id;
            $stmt_staff->close();

            // 3. Insert exact pairs
            $stmt_assign = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, class_name, subject_id) VALUES (?, ?, ?)");
            foreach ($assignments as $pair) {
                $cls = $pair['class'] ?? '';
                $sub_id = (int)($pair['subject'] ?? 0);
                if ($cls !== '' && $sub_id > 0) {
                    $stmt_assign->bind_param("isi", $teacher_id, $cls, $sub_id);
                    $stmt_assign->execute();
                }
            }
            $stmt_assign->close();

            $conn->commit();

            $message = "
                <div class='fw-bold mb-2'><i class='bi bi-check-circle-fill me-2'></i>Staff member registered successfully!</div>
                <div class='small'>
                    <strong>Employee ID:</strong> " . htmlspecialchars($employee_id) . "<br>
                    <strong>Login Email:</strong> " . htmlspecialchars($email) . "<br>
                    <strong>Password:</strong> " . htmlspecialchars($default_pass) . "<br>
                    <strong>Assignments Added:</strong> " . count($assignments) . " exact pairs.
                </div>
            ";
            $message_type = 'success';

            $next_employee_id = getNextStaffId($conn);
            $next_email       = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $next_employee_id)) . '@' . $school_domain;

        } catch (Exception $e) {
            $conn->rollback();
            $message = ($e->getCode() == 1062) ? "The generated ID or email is already in use." : "System Error: " . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    }
}

// Fetch classes and subjects
$all_classes = [];
$c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) { while ($c = $c_res->fetch_assoc()) $all_classes[] = $c['name']; }
if (empty($all_classes)) { $all_classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4']; }

$all_subjects = [];
$s_res = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC");
if ($s_res) { while ($s = $s_res->fetch_assoc()) $all_subjects[] = $s; }

$active_page = 'staff';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Assignment Builder Custom UI */
        .assign-builder { background: var(--bg-light); border: 1.5px solid var(--line); border-radius: 16px; padding: 20px; }
        .assign-list { list-style: none; padding: 0; margin: 0; }
        .assign-item { display: flex; justify-content: space-between; align-items: center; background: var(--white); border: 1.5px solid var(--line); border-radius: 10px; padding: 12px 16px; margin-bottom: 8px; }
        .assign-item .badge { font-size: 0.85rem; padding: 6px 12px; }
        .remove-assign { color: #b42318; cursor: pointer; background: none; border: none; font-size: 1.2rem; transition: 0.2s; }
        .remove-assign:hover { color: #911b12; transform: scale(1.1); }
        
        /* Multi-select styling */
        select[multiple] { min-height: 120px; background-image: none; }
        select[multiple] option { padding: 8px 12px; border-radius: 6px; margin-bottom: 2px; }
        select[multiple] option:checked { background-color: var(--primary) !important; color: white !important; }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="ui-head">
                <div>
                    <a href="staff.php" class="text-decoration-none text-muted fw-bold mb-2 d-inline-block" style="font-size: 0.85rem;">
                        <i class="bi bi-arrow-left me-1"></i> Back to Directory
                    </a>
                    <h1>Staff Onboarding</h1>
                    <p>Create the staff profile and map out their exact teaching classes.</p>
                </div>
            </div>

            <!-- Preview Card -->
            <div class="panel mb-4" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%); color: white;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-magic fs-5" style="color: var(--accent);"></i>
                    <span class="fw-bold fs-5">Next new staff member will receive:</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; opacity: 0.75; letter-spacing: 0.1em;">Employee ID</div>
                        <div class="fw-bold fs-5 font-monospace mt-1"><?php echo htmlspecialchars($next_employee_id); ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; opacity: 0.75; letter-spacing: 0.1em;">Login Email</div>
                        <div class="fw-bold fs-5 font-monospace mt-1"><?php echo htmlspecialchars($next_email); ?></div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="ui-alert is-<?php echo $message_type; ?>">
                    <i class="bi bi-info-circle-fill"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>

            <form method="post" class="panel p-4 p-md-5" id="staffForm">
                <div class="row g-4">

                    <div class="col-12 mb-2">
                        <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                            <h5 class="fw-bold mb-0" style="color: var(--primary);">Personal Information</h5>
                        </div>
                    </div>

                    <div class="col-md-6 ui-field">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="ui-input" placeholder="Enter Official Name" required>
                    </div>

                    <div class="col-md-6 ui-field">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="ui-input" placeholder="e.g. 07..." required>
                    </div>

                    <div class="col-12 ui-field">
                        <label>Qualification / Primary Department</label>
                        <input type="text" name="qualification" class="ui-input" placeholder="e.g. B.Ed Arts" required>
                    </div>

                    <div class="col-12 mt-5 mb-2">
                        <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                            <h5 class="fw-bold mb-0" style="color: var(--primary);">Class & Subject Assignments</h5>
                        </div>
                        <p class="text-muted small mt-2 mb-3">Select a class, then select one or more subjects using <strong>Ctrl</strong> (or Cmd on Mac) and click Add.</p>
                    </div>

                    <div class="col-12">
                        <div class="assign-builder">
                            <div class="row g-3 align-items-end mb-4">
                                <div class="col-md-4 ui-field">
                                    <label>Select Class</label>
                                    <select id="selectClass" class="ui-input form-select">
                                        <option value="" disabled selected>-- Choose Class --</option>
                                        <?php foreach ($all_classes as $cname): ?>
                                            <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 ui-field">
                                    <label>Select Subject(s)</label>
                                    <select id="selectSubject" class="ui-input form-select" multiple>
                                        <?php foreach ($all_subjects as $sub): ?>
                                            <option value="<?php echo intval($sub['subject_id']); ?>" data-name="<?php echo htmlspecialchars($sub['subject_name']); ?>">
                                                <?php echo htmlspecialchars($sub['subject_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="ui-btn is-primary w-100 py-3" onclick="addAssignment()">
                                        <i class="bi bi-plus-lg"></i> Add
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" name="assignments_json" id="assignmentsJson" value="[]">

                            <h6 class="fw-bold text-muted text-uppercase small mb-3">Assigned Pairs:</h6>
                            <ul class="assign-list" id="assignmentList">
                                <li class="text-muted small fst-italic" id="emptyMsg">No assignments added yet.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-12 mt-5 mb-2">
                        <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                            <h5 class="fw-bold mb-0" style="color: var(--primary);">System Access (auto-generated)</h5>
                        </div>
                    </div>

                    <div class="col-md-6 ui-field">
                        <label>Employee ID</label>
                        <input type="text" class="ui-input fw-bold" value="<?php echo htmlspecialchars($next_employee_id); ?>" readonly style="background: var(--bg-light);">
                    </div>

                    <div class="col-md-6 ui-field">
                        <label>Login Email</label>
                        <input type="email" class="ui-input fw-bold" value="<?php echo htmlspecialchars($next_email); ?>" readonly style="background: var(--bg-light);">
                    </div>

                    <div class="col-md-6 ui-field">
                        <label>Temporary Password</label>
                        <input type="text" class="ui-input fw-bold" value="<?php echo htmlspecialchars($default_pass); ?>" readonly style="background: var(--bg-light);">
                    </div>

                    <div class="col-12 mt-5 text-end">
                        <button type="submit" name="register_staff" class="ui-btn is-primary px-5 py-3" id="submitBtn">
                            <i class="bi bi-person-plus-fill"></i> Register &amp; Generate Account
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</main>

<?php include("footer.php"); ?>

<script>
    let assignments = [];

    function addAssignment() {
        const classSelect = document.getElementById('selectClass');
        const subjectSelect = document.getElementById('selectSubject');
        const selectedClass = classSelect.value;
        const selectedOptions = Array.from(subjectSelect.selectedOptions);

        if (!selectedClass) { alert('Please select a class first.'); return; }
        if (selectedOptions.length === 0) { alert('Please select at least one subject.'); return; }

        let addedCount = 0;
        let duplicateCount = 0;

        selectedOptions.forEach(opt => {
            const subId = opt.value;
            const subName = opt.getAttribute('data-name');
            const exists = assignments.find(a => a.class === selectedClass && a.subject === subId);
            
            if (exists) {
                duplicateCount++;
            } else {
                assignments.push({ class: selectedClass, subject: subId, subjectName: subName });
                addedCount++;
            }
        });

        classSelect.value = '';
        subjectSelect.value = '';
        updateUI();

        if (duplicateCount > 0) alert(`Skipped ${duplicateCount} subject(s) that were already assigned to ${selectedClass}.`);
    }

    function removeAssignment(index) {
        assignments.splice(index, 1);
        updateUI();
    }

    function updateUI() {
        const list = document.getElementById('assignmentList');
        const hiddenInput = document.getElementById('assignmentsJson');
        list.innerHTML = '';

        if (assignments.length === 0) {
            list.innerHTML = '<li class="text-muted small fst-italic" id="emptyMsg">No assignments added yet.</li>';
            hiddenInput.value = '[]';
            return;
        }

        const grouped = {};
        assignments.forEach((item, index) => {
            if (!grouped[item.class]) grouped[item.class] = [];
            grouped[item.class].push({ ...item, originalIndex: index });
        });

        for (const className in grouped) {
            const classHeader = document.createElement('div');
            classHeader.className = 'mb-2 mt-3';
            classHeader.innerHTML = `<strong class="text-dark" style="font-size: 0.9rem;"><i class="bi bi-bookmark-fill" style="color: var(--accent); margin-right: 6px;"></i>${className}</strong>`;
            list.appendChild(classHeader);

            grouped[className].forEach(item => {
                const li = document.createElement('li');
                li.className = 'assign-item ms-3';
                li.innerHTML = `
                    <div class="fw-bold" style="color: var(--primary);">${item.subjectName}</div>
                    <button type="button" class="remove-assign" onclick="removeAssignment(${item.originalIndex})" title="Remove">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                `;
                list.appendChild(li);
            });
        }
        hiddenInput.value = JSON.stringify(assignments);
    }

    document.getElementById('staffForm').addEventListener('submit', function(e) {
        if (assignments.length === 0) {
            e.preventDefault();
            alert('You must add at least one Class & Subject assignment before registering.');
        }
    });
</script>
</body>
</html>
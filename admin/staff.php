<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Assignment (Experience, Employee ID, and specific Class/Subject Pairs) ----
if (isset($_POST['assign_details'])) {
    $staff_id         = intval($_POST['staff_id']);
    $new_employee_id  = trim($_POST['employee_id'] ?? '');
    $experience       = trim($_POST['experience'] ?? '');
    $assignments_json = $_POST['assignments_json'] ?? '[]';
    $assignments      = json_decode($assignments_json, true);

    // 1. Get the OLD employee_id first (to sync the users table)
    $old_id = null;
    $get_old = $conn->prepare("SELECT employee_id FROM teachers WHERE teacher_id = ?");
    $get_old->bind_param("i", $staff_id);
    $get_old->execute();
    $old_result = $get_old->get_result();
    if ($old_row = $old_result->fetch_assoc()) {
        $old_id = $old_row['employee_id'];
    }
    $get_old->close();

    $conn->begin_transaction();
    try {
        // Step A: Sync users.employee_id
        if ($old_id !== null) {
            $stmt_user = $conn->prepare("UPDATE users SET employee_id = ? WHERE employee_id = ?");
            $stmt_user->bind_param("ss", $new_employee_id, $old_id);
            $stmt_user->execute();
            $stmt_user->close();
        }

        // Step B: Update basic teacher info
        $stmt_staff = $conn->prepare("UPDATE teachers SET employee_id = ?, experience = ? WHERE teacher_id = ?");
        $stmt_staff->bind_param("ssi", $new_employee_id, $experience, $staff_id);
        $stmt_staff->execute();
        $stmt_staff->close();

        // Step C: Wipe old assignments and insert the new ones
        $del_assign = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ?");
        $del_assign->bind_param("i", $staff_id);
        $del_assign->execute();
        $del_assign->close();

        if (!empty($assignments) && is_array($assignments)) {
            $stmt_assign = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, class_name, subject_id) VALUES (?, ?, ?)");
            foreach ($assignments as $pair) {
                $cls = $pair['class'] ?? '';
                $sub_id = (int)($pair['subject'] ?? 0);
                if ($cls !== '' && $sub_id > 0) {
                    $stmt_assign->bind_param("isi", $staff_id, $cls, $sub_id);
                    $stmt_assign->execute();
                }
            }
            $stmt_assign->close();
        }

        $conn->commit();
        $message = "Profile updated successfully.";
        $message_type = 'success';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Update failed: " . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// ---- Handle Delete ----
if (isset($_POST['delete_id'])) {
    $did = intval($_POST['delete_id']);

    $get_user = $conn->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
    $get_user->bind_param("i", $did);
    $get_user->execute();
    $u_res = $get_user->get_result();
    $uid = ($u_row = $u_res->fetch_assoc()) ? intval($u_row['user_id']) : 0;
    $get_user->close();

    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM teacher_assignments WHERE teacher_id = $did");
        $stmt1 = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
        $stmt1->bind_param("i", $did);
        $stmt1->execute();
        $stmt1->close();

        if ($uid > 0) {
            $stmt2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt2->bind_param("i", $uid);
            $stmt2->execute();
            $stmt2->close();
        }
        $conn->commit();
        $message = "Staff record removed successfully.";
        $message_type = 'info';
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Delete failed: " . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// ---- Fetch staff and their assignments ----
$staff = [];
$s_res = mysqli_query($conn, "SELECT * FROM teachers ORDER BY teacher_id DESC");
if ($s_res) {
    while ($row = mysqli_fetch_assoc($s_res)) {
        $tid = $row['teacher_id'];
        $row['assignments'] = [];
        
        $a_res = $conn->query("
            SELECT ta.class_name, ta.subject_id, s.subject_name 
            FROM teacher_assignments ta 
            JOIN subjects s ON ta.subject_id = s.subject_id 
            WHERE ta.teacher_id = $tid
        ");
        if ($a_res) {
            while ($a = $a_res->fetch_assoc()) {
                $row['assignments'][] = $a;
            }
        }
        $staff[] = $row;
    }
}

// ---- Fetch classes and subjects for the modal ----
$forms_list = [];
$res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order");
if ($res) { while ($f = mysqli_fetch_assoc($res)) $forms_list[] = $f['name']; }

$subjects_list = [];
$res = mysqli_query($conn, "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
if ($res) { while ($s = mysqli_fetch_assoc($res)) $subjects_list[] = $s; }

$active_page = 'staff';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        .sf-person { display: flex; align-items: center; gap: 14px; white-space: nowrap; }
        .sf-avatar {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--primary);
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 800;
        }

        /* what a teacher teaches, one line per form */
        .sf-teaches { min-width: 240px; max-width: 360px; }
        .sf-line {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 3px 0;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .sf-line .ui-badge { flex-shrink: 0; }
        .sf-line span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sf-more { font-size: 0.8rem; font-weight: 700; color: var(--primary); }

        /* ---------- Assignment builder in the pop-up ---------- */
        .sf-step {
            margin: 22px 0 0;
            padding-top: 22px;
            border-top: 1px solid var(--line);
        }
        .sf-step h3 { margin: 0 0 4px; font-size: 1rem; font-weight: 800; color: var(--primary); }
        .sf-step p.sub { margin: 0 0 14px; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

        .sf-builder {
            padding: 16px;
            border-radius: 18px;
            background: var(--bg-light);
            border: 1px solid var(--line);
        }
        .sf-picks { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0; }
        .sf-pick { position: relative; }
        .sf-pick input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
        .sf-pick span {
            display: inline-block;
            padding: 8px 14px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background: var(--white);
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .sf-pick input:checked + span { background: var(--primary); border-color: var(--primary); color: var(--white); }
        .sf-pick input:focus-visible + span { outline: 3px solid var(--accent); outline-offset: 2px; }
        .sf-pick-note { margin: 12px 0; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
        .sf-pick-note a { color: var(--primary); font-weight: 800; }
        .sf-builder-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

        .sf-current { margin-top: 18px; }
        .sf-group {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }
        .sf-group:last-child { border-bottom: none; }
        .sf-group > .ui-badge { margin-top: 5px; }
        .sf-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .sf-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 8px 6px 12px;
            border-radius: 99px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 0.82rem;
            font-weight: 700;
        }
        .sf-chip button {
            width: 20px;
            height: 20px;
            display: grid;
            place-items: center;
            border: none;
            border-radius: 50%;
            background: transparent;
            color: var(--primary);
            font-size: 0.7rem;
            cursor: pointer;
        }
        .sf-chip button:hover { background: #fdeaea; color: #b42318; }
        .sf-empty { margin: 0; font-size: 0.88rem; font-weight: 600; font-style: italic; color: var(--text-muted); }

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

    <div class="ui-head">
        <div>
            <h1>Staff directory</h1>
            <p>Manage faculty profiles and which subjects each teacher takes in each form</p>
        </div>
        <div class="ui-actions">
            <a href="add_staff.php" class="ui-btn is-primary">
                <i class="bi bi-person-plus-fill"></i> Register staff
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi bi-info-circle-fill"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <section class="panel is-flush">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Faculty</h2>
                <span class="ui-count"><?php echo count($staff); ?> member<?php echo count($staff) === 1 ? '' : 's'; ?></span>
            </div>
            <?php if (!empty($staff)): ?>
                <label class="ui-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="staffSearch" placeholder="Search staff..." aria-label="Search staff">
                </label>
            <?php endif; ?>
        </div>

        <?php if (empty($staff)): ?>
            <div class="ui-empty">
                <i class="bi bi-people"></i>
                <p>No staff members yet</p>
                <small>Click <strong>Register staff</strong> to add the first one.</small>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table" id="staffTable">
                    <thead>
                        <tr>
                            <th>Staff member</th>
                            <th>Employee ID</th>
                            <th>Experience</th>
                            <th>Teaches</th>
                            <th class="is-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $row):
                            $tid = intval($row['teacher_id']);
                            $classes_taught = [];
                            foreach ($row['assignments'] as $a) {
                                $classes_taught[$a['class_name']][$a['subject_name']] = true;
                            }
                            $shown = 0;
                        ?>
                            <tr>
                                <td>
                                    <div class="sf-person">
                                        <span class="sf-avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($row['full_name'] ?? '?', 0, 2))); ?></span>
                                        <div>
                                            <span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span>
                                            <span class="ui-sub">Staff ID: #<?php echo $tid; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['employee_id'])): ?>
                                        <span class="ui-badge is-navy"><?php echo htmlspecialchars($row['employee_id']); ?></span>
                                    <?php else: ?>
                                        <span class="ui-badge is-soft">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap"><?php echo !empty($row['experience']) ? htmlspecialchars($row['experience']) : '0 Years'; ?></td>
                                <td>
                                    <?php if (empty($classes_taught)): ?>
                                        <span class="ui-badge is-soft">Not assigned</span>
                                    <?php else: ?>
                                        <div class="sf-teaches">
                                            <?php foreach ($classes_taught as $cls => $names):
                                                if ($shown >= 3) break;
                                                $shown++;
                                            ?>
                                                <div class="sf-line">
                                                    <span class="ui-badge is-gold"><?php echo htmlspecialchars($cls); ?></span>
                                                    <span title="<?php echo htmlspecialchars(implode(', ', array_keys($names))); ?>">
                                                        <?php echo htmlspecialchars(implode(', ', array_keys($names))); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($classes_taught) > 3): ?>
                                                <span class="sf-more">+ <?php echo count($classes_taught) - 3; ?> more form<?php echo count($classes_taught) - 3 === 1 ? '' : 's'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="is-center">
                                    <div class="ui-row-actions" style="justify-content:center;">
                                        <button type="button" class="ui-icon" title="Edit staff" aria-label="Edit <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>"
                                                onclick="openStaff(<?php echo $tid; ?>)">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="post" style="margin:0;" onsubmit="return confirm('Remove this staff member?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $tid; ?>">
                                            <button type="submit" class="ui-icon is-danger" title="Remove staff" aria-label="Remove <?php echo htmlspecialchars($row['full_name'] ?? ''); ?>">
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

<!-- One edit pop-up for everyone -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content ui-modal">
            <form method="post" id="staffForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="staffModalTitle">Edit staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="staff_id" id="f_staff_id">
                    <input type="hidden" name="assignments_json" id="f_json" value="[]">

                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="f_employee">Employee ID</label>
                            <input type="text" name="employee_id" id="f_employee" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="f_experience">Years of experience</label>
                            <input type="text" name="experience" id="f_experience" class="form-control ui-input" placeholder="e.g. 5 Years" required>
                        </div>
                    </div>

                    <div class="sf-step">
                        <h3>What they teach</h3>
                        <p class="sub">Select a class, then select one or more subjects using <strong>Ctrl</strong> (or Cmd on Mac) and click Add.</p>

                        <div class="sf-builder">
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-md-4 ui-field">
                                    <label>Select Class</label>
                                    <select id="selectClass" class="ui-input form-select">
                                        <option value="" disabled selected>-- Choose Class --</option>
                                        <?php foreach ($forms_list as $fm): ?>
                                            <option value="<?php echo htmlspecialchars($fm); ?>"><?php echo htmlspecialchars($fm); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 ui-field">
                                    <label>Select Subject(s)</label>
                                    <select id="selectSubject" class="ui-input form-select" multiple>
                                        <?php foreach ($subjects_list as $sub): ?>
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

                            <div class="sf-current" id="f_current">
                                <!-- Rendered by JS -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_details" class="ui-btn is-primary">
                        <i class="bi bi-check-lg"></i> Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    const staffData       = <?php echo json_encode($staff, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const modalEl = document.getElementById('staffModal');
    const modal   = new bootstrap.Modal(modalEl);
    let assignments = [];   

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function openStaff(id) {
        const t = staffData.find(s => s.teacher_id == id);
        if (!t) return;
        
        document.getElementById('staffModalTitle').textContent = 'Edit staff: ' + t.full_name;
        document.getElementById('f_staff_id').value   = t.teacher_id;
        document.getElementById('f_employee').value   = t.employee_id;
        document.getElementById('f_experience').value = t.experience;
        
        // Map existing DB assignments to the JS array format
        assignments = (t.assignments || []).map(a => ({ 
            class: a.class_name, 
            subject: parseInt(a.subject_id, 10), 
            subjectName: a.subject_name 
        }));

        document.getElementById('selectClass').value = '';
        document.getElementById('selectSubject').value = '';
        
        renderCurrent();
        modal.show();
    }

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
        renderCurrent();

        if (duplicateCount > 0) alert(`Skipped ${duplicateCount} subject(s) that were already assigned to ${selectedClass}.`);
    }

    function removeAssignment(cls, name) {
        assignments = assignments.filter(a => !(a.class === cls && a.subjectName === name));
        renderCurrent();
    }

    function renderCurrent() {
        const box = document.getElementById('f_current');
        const jsonInput = document.getElementById('f_json');
        
        jsonInput.value = JSON.stringify(assignments);

        if (assignments.length === 0) {
            box.innerHTML = '<p class="sf-empty">No subjects assigned yet.</p>';
            return;
        }

        const byClass = {};
        assignments.forEach(a => {
            (byClass[a.class] = byClass[a.class] || new Set()).add(a.subjectName);
        });

        box.innerHTML = Object.keys(byClass).map(cls => {
            const chips = Array.from(byClass[cls]).map(name =>
                `<span class="sf-chip">${esc(name)}<button type="button" onclick="removeAssignment('${esc(cls)}', '${esc(name)}')"><i class="bi bi-x-lg"></i></button></span>`
            ).join('');
            return `<div class="sf-group"><span class="ui-badge is-gold">${esc(cls)}</span><div class="sf-chips">${chips}</div></div>`;
        }).join('');
    }

    // Search
    document.getElementById('staffSearch')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#staffTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });
</script>
</body>
</html>
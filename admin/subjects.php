<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$terms = ['Term 1', 'Term 2', 'Term 3'];

$reopen_add  = null;   // values to put back into the "Add subject" pop-up
$active_form = null;   // which form's tab to show after the page loads

// ---- Handle Adding New Subject ----
if (isset($_POST['add_subject']) || isset($_POST['add_subject_another'])) {
    $add_another  = isset($_POST['add_subject_another']);
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $form         = trim($_POST['form'] ?? '');
    $term         = trim($_POST['term'] ?? '');

    $active_form = $form;
    $keep_all = ['form' => $form, 'term' => $term, 'code' => $subject_code, 'name' => $subject_name];

    if ($subject_code === '' || $subject_name === '' || $form === '' || $term === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
        $reopen_add = $keep_all;
    } else {
        // Check for duplicate subject_code
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? LIMIT 1");
        $check->bind_param("s", $subject_code);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "Error: Subject code <strong>" . htmlspecialchars($subject_code) . "</strong> already exists!";
            $message_type = 'danger';
            $reopen_add = $keep_all;
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, form, term) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $subject_code, $subject_name, $form, $term);
            if ($stmt->execute()) {
                $message = "Subject <strong>" . htmlspecialchars($subject_name) . "</strong> added to "
                         . htmlspecialchars($form) . ", " . htmlspecialchars($term) . ".";
                $message_type = 'success';
                if ($add_another) {
                    // keep the same form and term, clear the code and name
                    $reopen_add = ['form' => $form, 'term' => $term, 'code' => '', 'name' => ''];
                }
            } else {
                $message = "Error: " . htmlspecialchars($stmt->error);
                $message_type = 'danger';
                $reopen_add = $keep_all;
            }
            $stmt->close();
        }
    }
}

// ---- Handle Update Subject ----
if (isset($_POST['update_subject'])) {
    $edit_id      = intval($_POST['edit_id'] ?? 0);
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $form         = trim($_POST['form'] ?? '');
    $term         = trim($_POST['term'] ?? '');

    $active_form = $form;

    if ($edit_id <= 0 || $subject_code === '' || $subject_name === '' || $form === '' || $term === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
    } else {
        // Check for duplicate subject_code (excluding self)
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ? LIMIT 1");
        $check->bind_param("si", $subject_code, $edit_id);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = "Error: Subject code <strong>" . htmlspecialchars($subject_code) . "</strong> is used by another subject.";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, form = ?, term = ? WHERE subject_id = ?");
            $stmt->bind_param("ssssi", $subject_code, $subject_name, $form, $term, $edit_id);
            if ($stmt->execute()) {
                $message = "Subject updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Update failed: " . htmlspecialchars($stmt->error);
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // remember which form it belonged to, so we come back to the same tab
    $f_stmt = $conn->prepare("SELECT form FROM subjects WHERE subject_id = ?");
    $f_stmt->bind_param("i", $id);
    $f_stmt->execute();
    $f_row = $f_stmt->get_result()->fetch_assoc();
    $f_stmt->close();

    $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $back = "subjects.php?deleted=1" . ($f_row ? "&form=" . urlencode($f_row['form']) : "");
    header("Location: " . $back);
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Subject removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all subjects ----
$subjects = [];
$result = mysqli_query($conn, "SELECT * FROM subjects ORDER BY form ASC, subject_name ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $subjects[] = $row;
}

// ---- Fetch classes (these become the tabs) ----
$classes = [];
$c_res = mysqli_query($conn, "SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) $classes[] = $row['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

// Forms that appear on subjects but are not in the classes table still get a tab
$form_names = $classes;
foreach ($subjects as $s) {
    if (!in_array($s['form'], $form_names, true)) $form_names[] = $s['form'];
}

// ---- Group subjects: form -> term -> list ----
$grouped = [];
$form_counts = [];
foreach ($subjects as $s) {
    $grouped[$s['form']][$s['term']][] = $s;
    $form_counts[$s['form']] = ($form_counts[$s['form']] ?? 0) + 1;
}

// ---- Which tab is open ----
if ($active_form === null) {
    $active_form = trim($_GET['form'] ?? '');
}
if ($active_form !== 'all' && !in_array($active_form, $form_names, true)) {
    $active_form = $form_names[0] ?? 'all';
}

$message_icons = [
    'success' => 'bi-check-circle-fill',
    'danger'  => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];

$active_page = 'subjects';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Form tabs ---------- */
        .fx-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 22px;
            padding: 6px;
            overflow-x: auto;
            background: var(--white);
            border-radius: 99px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
            scrollbar-width: thin;
        }
        .fx-tab {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border: none;
            border-radius: 99px;
            background: transparent;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .fx-tab:hover { background: var(--primary-tint); color: var(--primary); }
        .fx-tab.is-active { background: var(--primary); color: var(--white); }
        .fx-tab .n {
            min-width: 22px;
            padding: 2px 8px;
            border-radius: 99px;
            background: var(--bg-light);
            color: var(--primary);
            font-size: 0.75rem;
            font-weight: 800;
            text-align: center;
        }
        .fx-tab.is-active .n { background: var(--accent); color: var(--primary); }

        .fx-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        /* ---------- A form's section ---------- */
        .fx-section { display: none; margin-bottom: 36px; }
        .fx-section.is-visible { display: block; }
        .fx-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .fx-section-head h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }

        /* three term cards side by side */
        .fx-terms {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }
        .fx-term {
            background: var(--white);
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
        }
        .fx-term-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 14px;
            margin-bottom: 6px;
            border-bottom: 2px solid var(--primary-tint);
        }
        .fx-term-head strong {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 800;
            color: var(--primary);
        }
        .fx-term-head .n {
            padding: 2px 10px;
            border-radius: 99px;
            background: var(--primary-tint);
            color: var(--primary);
            font-size: 0.78rem;
            font-weight: 800;
        }
        .fx-subjects { list-style: none; margin: 0; padding: 0; }
        .fx-subject {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }
        .fx-subject:last-child { border-bottom: none; }
        .fx-subject .ui-row-actions { gap: 6px; }
        .fx-subject .ui-icon { width: 34px; height: 34px; font-size: 0.9rem; }
        a.ui-icon { text-decoration: none; }

        .fx-none {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-top: 12px;
            padding: 18px;
            border: 2px dashed var(--line);
            border-radius: 16px;
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .fx-nomatch { display: none; text-align: center; padding: 40px 20px; color: var(--text-muted); font-weight: 600; }

        @media (max-width: 992px) {
            .fx-terms { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/navbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Subjects</h1>
            <p>Each form has its own subjects, set out term by term</p>
        </div>
        <div class="ui-actions">
            <a href="forms.php" class="ui-btn is-ghost"><i class="bi bi-mortarboard"></i> Forms &amp; grades</a>
            <button type="button" class="ui-btn is-primary" onclick="openAddModal()">
                <i class="bi bi-plus-lg"></i> Add subject
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi <?php echo $message_icons[$message_type] ?? 'bi-info-circle-fill'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- One tab per form -->
    <div class="fx-tabs" role="tablist" aria-label="Forms">
        <button type="button" class="fx-tab" data-tab="all" role="tab">
            All forms <span class="n"><?php echo count($subjects); ?></span>
        </button>
        <?php foreach ($form_names as $fname): ?>
            <button type="button" class="fx-tab" data-tab="<?php echo htmlspecialchars($fname); ?>" role="tab">
                <?php echo htmlspecialchars($fname); ?>
                <span class="n"><?php echo intval($form_counts[$fname] ?? 0); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="fx-toolbar">
        <label class="ui-search">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Search subject name or code..." aria-label="Search subjects">
        </label>
    </div>

    <!-- A section for every form -->
    <?php foreach ($form_names as $fname):
        $count = intval($form_counts[$fname] ?? 0);
        $visible = ($active_form === 'all' || $active_form === $fname);
    ?>
        <section class="fx-section<?php echo $visible ? ' is-visible' : ''; ?>" data-form="<?php echo htmlspecialchars($fname); ?>">
            <div class="fx-section-head">
                <h2>
                    <?php echo htmlspecialchars($fname); ?>
                    <span class="ui-count"><?php echo $count; ?> subject<?php echo $count === 1 ? '' : 's'; ?></span>
                </h2>
                <button type="button" class="ui-btn is-ghost is-sm"
                        onclick="openAddModal(<?php echo htmlspecialchars(json_encode($fname), ENT_QUOTES); ?>)">
                    <i class="bi bi-plus-lg"></i> Add to <?php echo htmlspecialchars($fname); ?>
                </button>
            </div>

            <div class="fx-terms">
                <?php foreach ($terms as $tname):
                    $list = $grouped[$fname][$tname] ?? [];
                ?>
                    <div class="fx-term">
                        <div class="fx-term-head">
                            <strong><?php echo htmlspecialchars($tname); ?> <span class="n"><?php echo count($list); ?></span></strong>
                            <button type="button" class="ui-icon" title="Add a subject to <?php echo htmlspecialchars($fname . ', ' . $tname); ?>"
                                    aria-label="Add a subject to <?php echo htmlspecialchars($fname . ', ' . $tname); ?>"
                                    onclick="openAddModal(<?php echo htmlspecialchars(json_encode($fname), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($tname), ENT_QUOTES); ?>)">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>

                        <?php if (empty($list)): ?>
                            <div class="fx-none">
                                No subjects yet
                                <button type="button" class="ui-btn is-primary is-sm"
                                        onclick="openAddModal(<?php echo htmlspecialchars(json_encode($fname), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($tname), ENT_QUOTES); ?>)">
                                    <i class="bi bi-plus-lg"></i> Add subject
                                </button>
                            </div>
                        <?php else: ?>
                            <ul class="fx-subjects">
                                <?php foreach ($list as $row): ?>
                                    <li class="fx-subject subject-row">
                                        <div>
                                            <span class="ui-name subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></span>
                                            <span class="ui-sub subject-id"><?php echo htmlspecialchars($row['subject_code'] ?? $row['subject_id']); ?></span>
                                        </div>
                                        <div class="ui-row-actions">
                                            <button type="button" class="ui-icon" title="Edit subject" aria-label="Edit subject"
                                                    data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                                    data-edit-id="<?php echo intval($row['subject_id']); ?>"
                                                    data-code="<?php echo htmlspecialchars($row['subject_code'] ?? ''); ?>"
                                                    data-name="<?php echo htmlspecialchars($row['subject_name']); ?>"
                                                    data-form="<?php echo htmlspecialchars($row['form']); ?>"
                                                    data-term="<?php echo htmlspecialchars($row['term']); ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="?delete=<?php echo intval($row['subject_id']); ?>"
                                               class="ui-icon is-danger" title="Delete subject" aria-label="Delete subject"
                                               onclick="return confirm('Delete this subject?');">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <div class="fx-nomatch" id="noMatch"><i class="bi bi-search"></i> No subjects match your search.</div>

</main>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubjectTitle">New subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="add_form">Form / level</label>
                            <select name="form" id="add_form" class="form-select ui-input" required>
                                <option value="" disabled selected>Choose a form</option>
                                <?php foreach ($form_names as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="add_term">Term</label>
                            <select name="term" id="add_term" class="form-select ui-input" required>
                                <?php foreach ($terms as $tname): ?>
                                    <option><?php echo htmlspecialchars($tname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="add_code">Subject code</label>
                            <input type="text" name="subject_code" id="add_code" class="form-control ui-input" placeholder="e.g. ENG101" required>
                        </div>
                        <div class="ui-field">
                            <label for="add_name">Subject name</label>
                            <input type="text" name="subject_name" id="add_name" class="form-control ui-input" placeholder="e.g. English Literature" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_subject_another" class="ui-btn is-ghost">
                        <i class="bi bi-plus-circle"></i> Save &amp; add another
                    </button>
                    <button type="submit" name="add_subject" class="ui-btn is-primary">
                        <i class="bi bi-check-lg"></i> Save subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubjectTitle">Edit subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body">
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="edit_form">Form / level</label>
                            <select name="form" id="edit_form" class="form-select ui-input" required>
                                <?php foreach ($form_names as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="edit_term">Term</label>
                            <select name="term" id="edit_term" class="form-select ui-input" required>
                                <?php foreach ($terms as $tname): ?>
                                    <option><?php echo htmlspecialchars($tname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="edit_code">Subject code</label>
                            <input type="text" name="subject_code" id="edit_code" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="edit_name">Subject name</label>
                            <input type="text" name="subject_name" id="edit_name" class="form-control ui-input" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_subject" class="ui-btn is-primary">
                        <i class="bi bi-check-lg"></i> Update subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    let currentTab = <?php echo json_encode($active_form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // ---- Tabs: one form at a time (or all) ----
    function setTab(name) {
        currentTab = name;
        document.querySelectorAll('.fx-tab').forEach(t => {
            t.classList.toggle('is-active', t.dataset.tab === name);
        });
        document.querySelectorAll('.fx-section').forEach(s => {
            s.classList.toggle('is-visible', name === 'all' || s.dataset.form === name);
        });
        filterRows();
    }
    document.querySelectorAll('.fx-tab').forEach(t => {
        t.addEventListener('click', () => setTab(t.dataset.tab));
    });

    // ---- Search ----
    function filterRows() {
        const q = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
        let shown = 0;
        document.querySelectorAll('.fx-section.is-visible .subject-row').forEach(row => {
            const match = row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        document.querySelectorAll('.fx-section:not(.is-visible) .subject-row').forEach(row => { row.style.display = ''; });
        document.getElementById('noMatch').style.display = (q && shown === 0) ? 'block' : 'none';
    }
    document.getElementById('searchInput')?.addEventListener('input', filterRows);

    // ---- Add pop-up: opens already set to the form / term you were looking at ----
    const addModalEl = document.getElementById('addSubjectModal');
    const addModal   = new bootstrap.Modal(addModalEl);

    function openAddModal(form, term, code, name) {
        const f = form || (currentTab !== 'all' ? currentTab : '');
        document.getElementById('add_form').value = f;
        document.getElementById('add_term').value = term || 'Term 1';
        document.getElementById('add_code').value = code || '';
        document.getElementById('add_name').value = name || '';
        addModal.show();
    }
    addModalEl.addEventListener('shown.bs.modal', () => {
        const code = document.getElementById('add_code');
        const name = document.getElementById('add_name');
        (code.value === '' ? code : name).focus();
    });

    // ---- Edit pop-up: fill the fields from the clicked row ----
    const editModal = document.getElementById('editSubjectModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('edit_id').value   = btn.getAttribute('data-edit-id');
        document.getElementById('edit_code').value = btn.getAttribute('data-code');
        document.getElementById('edit_name').value = btn.getAttribute('data-name');
        document.getElementById('edit_form').value = btn.getAttribute('data-form');
        document.getElementById('edit_term').value = btn.getAttribute('data-term');
    });

    setTab(currentTab);

    <?php if ($reopen_add): ?>
    // Something to fix, or "Save & add another": bring the pop-up straight back
    openAddModal(
        <?php echo json_encode($reopen_add['form'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode($reopen_add['term'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode($reopen_add['code'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode($reopen_add['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    );
    <?php endif; ?>
</script>
</body>
</html>
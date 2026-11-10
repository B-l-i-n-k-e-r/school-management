<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$terms = ['Term 1', 'Term 2', 'Term 3'];

$reopen_add  = null;   // values to put back into the "Schedule exam" pop-up
$active_form = null;   // which form's tab to show after the page loads

// ---- Handle Adding New Exam ----
if (isset($_POST['add_exam']) || isset($_POST['add_exam_another'])) {
    $add_another = isset($_POST['add_exam_another']);
    $exam_code   = trim($_POST['exam_code'] ?? '');
    $subject_id  = intval($_POST['subject_id'] ?? 0);
    $form        = trim($_POST['form'] ?? '');
    $term        = trim($_POST['term'] ?? '');
    $exam_date   = trim($_POST['exam_date'] ?? '');
    $exam_time   = trim($_POST['exam_time'] ?? '');
    $room        = trim($_POST['room'] ?? '');

    $active_form = $form;
    $keep_all = ['code' => $exam_code, 'form' => $form, 'term' => $term, 'subject' => $subject_id,
                 'date' => $exam_date, 'time' => $exam_time, 'room' => $room];

    if ($exam_code === '' || $subject_id <= 0 || $form === '' || $term === '' || $exam_date === '' || $exam_time === '' || $room === '') {
        $message = "All fields are required.";
        $message_type = 'warning';
        $reopen_add = $keep_all;
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (exam_code, subject_id, form, term, exam_date, exam_time, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisssss", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room);
        if ($stmt->execute()) {
            $message = "Exam <strong>" . htmlspecialchars($exam_code) . "</strong> scheduled for "
                     . htmlspecialchars($form) . ", " . htmlspecialchars($term) . ".";
            $message_type = 'success';
            if ($add_another) {
                // keep the form, term, code, date and room; clear the subject and time
                $reopen_add = ['code' => $exam_code, 'form' => $form, 'term' => $term, 'subject' => 0,
                               'date' => $exam_date, 'time' => '', 'room' => $room];
            }
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
            $reopen_add = $keep_all;
        }
        $stmt->close();
    }
}

// ---- Handle Update Exam ----
if (isset($_POST['update_exam'])) {
    $exam_id    = intval($_POST['exam_id'] ?? 0);
    $exam_code  = trim($_POST['exam_code'] ?? '');
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $form       = trim($_POST['form'] ?? '');
    $term       = trim($_POST['term'] ?? '');
    $exam_date  = trim($_POST['exam_date'] ?? '');
    $exam_time  = trim($_POST['exam_time'] ?? '');
    $room       = trim($_POST['room'] ?? '');

    $active_form = $form;

    if ($exam_id <= 0) {
        $message = "Invalid exam ID.";
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE exams SET exam_code=?, subject_id=?, form=?, term=?, exam_date=?, exam_time=?, room=? WHERE exam_id=?");
        $stmt->bind_param("sisssssi", $exam_code, $subject_id, $form, $term, $exam_date, $exam_time, $room, $exam_id);
        if ($stmt->execute()) {
            $message = "Exam updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Update failed: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // remember which form it belonged to, so we come back to the same tab
    $f_stmt = $conn->prepare("SELECT form FROM exams WHERE exam_id = ?");
    $f_stmt->bind_param("i", $id);
    $f_stmt->execute();
    $f_row = $f_stmt->get_result()->fetch_assoc();
    $f_stmt->close();

    $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: exams.php?deleted=1" . ($f_row ? "&form=" . urlencode($f_row['form']) : ""));
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Exam schedule removed.";
    $message_type = 'info';
}

// ---- Fetch exams with subject names ----
$exams = [];
$query = "
    SELECT e.*, s.subject_name
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.subject_id
    ORDER BY e.exam_date ASC, e.exam_time ASC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $exams[] = $row;
}

// ---- Fetch subjects for JS filtering ----
$subjects_data = [];
$sub_list = $conn->query("SELECT subject_id, subject_name, form, term FROM subjects ORDER BY subject_name ASC");
if ($sub_list) {
    while ($s = $sub_list->fetch_assoc()) $subjects_data[] = $s;
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

// Forms that appear on exams but are not in the classes table still get a tab
$form_names = $classes;
foreach ($exams as $e) {
    if (!in_array($e['form'], $form_names, true)) $form_names[] = $e['form'];
}

// ---- Group exams: form -> term -> list (already in date/time order) ----
$grouped = [];
$form_counts = [];
foreach ($exams as $e) {
    $grouped[$e['form']][$e['term']][] = $e;
    $form_counts[$e['form']] = ($form_counts[$e['form']] ?? 0) + 1;
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

$active_page = 'exams';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedules | Admin Portal</title>

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
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }
        .fx-select {
            padding: 11px 40px 11px 16px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background-color: var(--white);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--ink);
        }

        /* ---------- A form's timetable ---------- */
        .fx-section { display: none; margin-bottom: 40px; }
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
            flex-wrap: wrap;
            gap: 12px;
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .fx-section-head h2 small { font-size: 0.95rem; font-weight: 600; color: var(--text-muted); }

        .fx-term-block { margin-bottom: 18px; }
        .fx-term-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 10px 4px;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--primary);
        }
        .fx-term-title .n {
            padding: 2px 10px;
            border-radius: 99px;
            background: var(--primary-tint);
            font-size: 0.78rem;
        }
        .fx-date { display: block; color: var(--ink); font-weight: 700; }
        .fx-time { display: block; font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        a.ui-icon { text-decoration: none; }

        .fx-none {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 36px 20px;
            border: 2px dashed var(--line);
            border-radius: 22px;
            background: var(--white);
            color: var(--text-muted);
            font-weight: 600;
            text-align: center;
        }
        .fx-nomatch { display: none; text-align: center; padding: 40px 20px; color: var(--text-muted); font-weight: 600; }

        @media print {
            .fx-section:not(.is-visible) { display: none !important; }
            .fx-tabs, .fx-toolbar { display: none !important; }
            .fx-section-head h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/navbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Exam schedules</h1>
            <p>Each form has its own exam timetable. Pick a form to see and manage it</p>
        </div>
        <div class="ui-actions no-print">
            <button type="button" class="ui-btn is-ghost" onclick="window.print()">
                <i class="bi bi-printer"></i> Print timetable
            </button>
            <button type="button" class="ui-btn is-primary" onclick="openAddExam()">
                <i class="bi bi-calendar-plus"></i> Schedule exam
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
            All forms <span class="n"><?php echo count($exams); ?></span>
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
            <input type="text" id="searchInput" placeholder="Search subject, code or room..." aria-label="Search exams">
        </label>
        <select id="termFilter" class="fx-select" aria-label="Filter by term">
            <option value="">All terms</option>
            <?php foreach ($terms as $tname): ?>
                <option value="<?php echo htmlspecialchars($tname); ?>"><?php echo htmlspecialchars($tname); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- A timetable for every form -->
    <?php foreach ($form_names as $fname):
        $count   = intval($form_counts[$fname] ?? 0);
        $visible = ($active_form === 'all' || $active_form === $fname);
        $jsForm  = htmlspecialchars(json_encode($fname), ENT_QUOTES);

        // terms to show for this form: the usual three, plus any unusual ones that exist
        $form_terms = array_keys($grouped[$fname] ?? []);
        $show_terms = array_values(array_unique(array_merge(
            array_filter($terms, fn($t) => isset($grouped[$fname][$t])),
            $form_terms
        )));
    ?>
        <section class="fx-section<?php echo $visible ? ' is-visible' : ''; ?>" data-form="<?php echo htmlspecialchars($fname); ?>">
            <div class="fx-section-head">
                <h2>
                    <?php echo htmlspecialchars($fname); ?> <small>exam timetable</small>
                    <span class="ui-count"><?php echo $count; ?> exam<?php echo $count === 1 ? '' : 's'; ?></span>
                </h2>
                <button type="button" class="ui-btn is-ghost is-sm no-print" onclick="openAddExam(<?php echo $jsForm; ?>)">
                    <i class="bi bi-calendar-plus"></i> Schedule for <?php echo htmlspecialchars($fname); ?>
                </button>
            </div>

            <?php if (empty($show_terms)): ?>
                <div class="fx-none">
                    <i class="bi bi-calendar-x" style="font-size:2rem; opacity:.5;"></i>
                    No exams scheduled for <?php echo htmlspecialchars($fname); ?> yet.
                    <button type="button" class="ui-btn is-primary is-sm no-print" onclick="openAddExam(<?php echo $jsForm; ?>)">
                        <i class="bi bi-calendar-plus"></i> Schedule the first exam
                    </button>
                </div>
            <?php else: foreach ($show_terms as $tname):
                $list = $grouped[$fname][$tname] ?? [];
            ?>
                <div class="fx-term-block" data-term="<?php echo htmlspecialchars($tname); ?>">
                    <h3 class="fx-term-title">
                        <?php echo htmlspecialchars($tname); ?>
                        <span class="n"><?php echo count($list); ?></span>
                    </h3>
                    <div class="panel is-flush">
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Date &amp; time</th>
                                        <th>Subject</th>
                                        <th>Room</th>
                                        <th>Code</th>
                                        <th class="is-center no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($list as $row): ?>
                                        <tr class="exam-row">
                                            <td class="nowrap">
                                                <span class="fx-date">
                                                    <?php echo !empty($row['exam_date']) ? htmlspecialchars(date("D, d M Y", strtotime($row['exam_date']))) : '—'; ?>
                                                </span>
                                                <span class="fx-time">
                                                    <?php echo !empty($row['exam_time']) ? htmlspecialchars(date("h:i A", strtotime($row['exam_time']))) : '—'; ?>
                                                </span>
                                            </td>
                                            <td><span class="ui-name"><?php echo htmlspecialchars($row['subject_name'] ?? '—'); ?></span></td>
                                            <td><span class="ui-badge is-soft"><?php echo htmlspecialchars($row['room'] ?? '—'); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['exam_code'] ?? ''); ?></td>
                                            <td class="is-center no-print">
                                                <div class="ui-row-actions" style="justify-content:center;">
                                                    <button type="button" class="ui-icon" title="Edit exam" aria-label="Edit exam"
                                                            data-bs-toggle="modal" data-bs-target="#editExamModal"
                                                            data-id="<?php echo intval($row['exam_id']); ?>"
                                                            data-code="<?php echo htmlspecialchars($row['exam_code'] ?? ''); ?>"
                                                            data-subject="<?php echo intval($row['subject_id'] ?? 0); ?>"
                                                            data-form="<?php echo htmlspecialchars($row['form'] ?? ''); ?>"
                                                            data-term="<?php echo htmlspecialchars($row['term'] ?? ''); ?>"
                                                            data-date="<?php echo htmlspecialchars($row['exam_date'] ?? ''); ?>"
                                                            data-time="<?php echo htmlspecialchars($row['exam_time'] ?? ''); ?>"
                                                            data-room="<?php echo htmlspecialchars($row['room'] ?? ''); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="?delete=<?php echo intval($row['exam_id']); ?>"
                                                       class="ui-icon is-danger" title="Cancel exam" aria-label="Cancel exam"
                                                       onclick="return confirm('Cancel this exam?');">
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </section>
    <?php endforeach; ?>

    <div class="fx-nomatch" id="noMatch"><i class="bi bi-search"></i> No exams match your search.</div>

</main>

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1" aria-labelledby="addExamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="addExamTitle">Schedule new exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="add_form_sel">Form</label>
                            <select name="form" id="add_form_sel" class="form-select ui-input" required>
                                <option value="" disabled selected>Select form</option>
                                <?php foreach ($form_names as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="add_term_sel">Term</label>
                            <select name="term" id="add_term_sel" class="form-select ui-input" required>
                                <?php foreach ($terms as $tname): ?>
                                    <option value="<?php echo htmlspecialchars($tname); ?>"><?php echo htmlspecialchars($tname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field is-full">
                            <label for="add_sub_sel">Subject</label>
                            <select name="subject_id" id="add_sub_sel" class="form-select ui-input" disabled required>
                                <option value="">Select form &amp; term first</option>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="add_date">Date</label>
                            <input type="date" name="exam_date" id="add_date" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="add_time">Time</label>
                            <input type="time" name="exam_time" id="add_time" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="add_room">Room</label>
                            <input type="text" name="room" id="add_room" class="form-control ui-input" placeholder="e.g. Hall A" required>
                        </div>
                        <div class="ui-field">
                            <label for="add_code">Exam code</label>
                            <input type="text" name="exam_code" id="add_code" class="form-control ui-input" placeholder="e.g. END-TERM-2026" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_exam_another" class="ui-btn is-ghost">
                        <i class="bi bi-plus-circle"></i> Save &amp; add another
                    </button>
                    <button type="submit" name="add_exam" class="ui-btn is-primary">
                        <i class="bi bi-calendar-plus"></i> Post schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Exam Modal -->
<div class="modal fade" id="editExamModal" tabindex="-1" aria-labelledby="editExamTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="editExamTitle">Edit exam schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="modal-body">
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="edit_form_sel">Form</label>
                            <select name="form" id="edit_form_sel" class="form-select ui-input" required>
                                <?php foreach ($form_names as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="edit_term_sel">Term</label>
                            <select name="term" id="edit_term_sel" class="form-select ui-input" required>
                                <?php foreach ($terms as $tname): ?>
                                    <option value="<?php echo htmlspecialchars($tname); ?>"><?php echo htmlspecialchars($tname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field is-full">
                            <label for="edit_sub_sel">Subject</label>
                            <select name="subject_id" id="edit_sub_sel" class="form-select ui-input" required></select>
                        </div>
                        <div class="ui-field">
                            <label for="edit_exam_date">Date</label>
                            <input type="date" name="exam_date" id="edit_exam_date" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="edit_exam_time">Time</label>
                            <input type="time" name="exam_time" id="edit_exam_time" class="form-control ui-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="edit_room">Room</label>
                            <input type="text" name="room" id="edit_room" class="form-control ui-input" placeholder="e.g. Hall A" required>
                        </div>
                        <div class="ui-field">
                            <label for="edit_exam_code">Exam code</label>
                            <input type="text" name="exam_code" id="edit_exam_code" class="form-control ui-input" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_exam" class="ui-btn is-primary">
                        <i class="bi bi-check-lg"></i> Update schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    const subjects = <?php echo json_encode($subjects_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let currentTab = <?php echo json_encode($active_form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // ---- Tabs: one form's timetable at a time (or all) ----
    function setTab(name) {
        currentTab = name;
        document.querySelectorAll('.fx-tab').forEach(t => {
            t.classList.toggle('is-active', t.dataset.tab === name);
        });
        document.querySelectorAll('.fx-section').forEach(s => {
            s.classList.toggle('is-visible', name === 'all' || s.dataset.form === name);
        });
        applyFilters();
    }
    document.querySelectorAll('.fx-tab').forEach(t => {
        t.addEventListener('click', () => setTab(t.dataset.tab));
    });

    // ---- Search + term filter ----
    function applyFilters() {
        const q    = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
        const term = document.getElementById('termFilter')?.value || '';
        let shown = 0;

        document.querySelectorAll('.fx-term-block').forEach(block => {
            const termOk = term === '' || block.dataset.term === term;
            let rowsShown = 0;
            block.querySelectorAll('.exam-row').forEach(row => {
                const ok = termOk && row.textContent.toLowerCase().includes(q);
                row.style.display = ok ? '' : 'none';
                if (ok) rowsShown++;
            });
            block.style.display = (termOk && (q === '' || rowsShown > 0)) ? '' : 'none';
            if (block.closest('.fx-section')?.classList.contains('is-visible')) shown += rowsShown;
        });

        document.getElementById('noMatch').style.display = ((q || term) && shown === 0) ? 'block' : 'none';
    }
    document.getElementById('searchInput')?.addEventListener('input', applyFilters);
    document.getElementById('termFilter')?.addEventListener('change', applyFilters);

    // ---- Subject dropdown follows the chosen form and term ----
    function updateSubjectOptions(formId, termId, subSelectId, currentVal = null) {
        const f = document.getElementById(formId).value;
        const t = document.getElementById(termId).value;
        const sel = document.getElementById(subSelectId);

        sel.innerHTML = '<option value="">Select subject...</option>';
        if (f && t) {
            sel.disabled = false;
            const filtered = subjects.filter(s => s.form == f && s.term == t);
            if (filtered.length === 0) {
                sel.innerHTML = '<option value="">No subjects for ' + f + ', ' + t + ' yet</option>';
            }
            filtered.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.subject_id;
                opt.textContent = s.subject_name;
                if (currentVal && s.subject_id == currentVal) opt.selected = true;
                sel.appendChild(opt);
            });
        } else {
            sel.disabled = true;
            sel.innerHTML = '<option value="">Select form & term first</option>';
        }
    }

    ['add', 'edit'].forEach(p => {
        const run = () => updateSubjectOptions(p + '_form_sel', p + '_term_sel', p + '_sub_sel');
        document.getElementById(p + '_form_sel')?.addEventListener('change', run);
        document.getElementById(p + '_term_sel')?.addEventListener('change', run);
    });

    // ---- Schedule pop-up: opens already set to the form you were looking at ----
    const addExamEl    = document.getElementById('addExamModal');
    const addExamModal = new bootstrap.Modal(addExamEl);

    function openAddExam(form, term, vals) {
        vals = vals || {};
        document.getElementById('add_form_sel').value = form || (currentTab !== 'all' ? currentTab : '');
        document.getElementById('add_term_sel').value = term || 'Term 1';
        document.getElementById('add_date').value     = vals.date || '';
        document.getElementById('add_time').value     = vals.time || '';
        document.getElementById('add_room').value     = vals.room || '';
        document.getElementById('add_code').value     = vals.code || '';
        updateSubjectOptions('add_form_sel', 'add_term_sel', 'add_sub_sel', vals.subject || null);
        addExamModal.show();
    }

    // ---- Edit pop-up: fill the fields from the clicked row ----
    document.getElementById('editExamModal').addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        if (!b) return;
        document.getElementById('edit_exam_id').value   = b.getAttribute('data-id');
        document.getElementById('edit_exam_code').value = b.getAttribute('data-code');
        document.getElementById('edit_form_sel').value  = b.getAttribute('data-form');
        document.getElementById('edit_term_sel').value  = b.getAttribute('data-term');
        document.getElementById('edit_exam_date').value = b.getAttribute('data-date');
        document.getElementById('edit_exam_time').value = b.getAttribute('data-time');
        document.getElementById('edit_room').value      = b.getAttribute('data-room');
        updateSubjectOptions('edit_form_sel', 'edit_term_sel', 'edit_sub_sel', b.getAttribute('data-subject'));
    });

    setTab(currentTab);

    <?php if ($reopen_add): ?>
    // Something to fix, or "Save & add another": bring the pop-up straight back
    openAddExam(
        <?php echo json_encode($reopen_add['form'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode($reopen_add['term'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        <?php echo json_encode([
            'date' => $reopen_add['date'], 'time' => $reopen_add['time'], 'room' => $reopen_add['room'],
            'code' => $reopen_add['code'], 'subject' => $reopen_add['subject'],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    );
    <?php endif; ?>
</script>
</body>
</html>
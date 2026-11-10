<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- Handle Adding New Class ----
if (isset($_POST['add_form'])) {
    $form_name = trim($_POST['form_name'] ?? '');

    if ($form_name === '') {
        $message = "Please enter a level name.";
        $message_type = 'warning';
    } else {
        // Detect level & curriculum based on name pattern
        if (stripos($form_name, 'form') !== false) {
            $level = 'Secondary';
            $curr  = '8-4-4';
            $order = 10 + (intval(preg_replace('/\D/', '', $form_name)) ?: 0);
        } else {
            $level = 'Primary';
            $curr  = 'CBC';
            $order = intval(preg_replace('/\D/', '', $form_name)) ?: 99;
        }

        $stmt = $conn->prepare("INSERT INTO classes (name, level, curriculum, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $form_name, $level, $curr, $order);
        if ($stmt->execute()) {
            $message = "New academic level <strong>" . htmlspecialchars($form_name) . "</strong> added successfully! "
                     . "Next, add its subjects, then build its timetable.";
            $message_type = 'success';
        } else {
            $message = "Error: " . htmlspecialchars($stmt->error);
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: forms.php?deleted=1");
    exit();
}

// ---- Flash for delete ----
if (isset($_GET['deleted'])) {
    $message = "Academic level removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all classes + student and subject counts ----
$classes = [];
$query = "
    SELECT c.*,
        (SELECT COUNT(*) FROM students s WHERE TRIM(s.form) = TRIM(c.name)) AS student_count,
        (SELECT COUNT(*) FROM subjects sb WHERE TRIM(sb.form) = TRIM(c.name)) AS subject_count
    FROM classes c
    ORDER BY c.sort_order ASC, c.name ASC
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $classes[] = $row;
}

// ---- Group the levels: Primary grades, then Secondary forms ----
$groups = [
    'Primary'   => ['title' => 'Primary',   'sub' => 'Grades (CBC)',  'icon' => 'bi-backpack2-fill', 'items' => []],
    'Secondary' => ['title' => 'Secondary', 'sub' => 'Forms (8-4-4)', 'icon' => 'bi-mortarboard-fill', 'items' => []],
];
foreach ($classes as $c) {
    $lvl = $c['level'] ?? '';
    if (!isset($groups[$lvl])) {
        $groups[$lvl] = ['title' => ($lvl ?: 'Other'), 'sub' => '', 'icon' => 'bi-journal-fill', 'items' => []];
    }
    $groups[$lvl]['items'][] = $c;
}

// Names that already exist, so the quick-add chips can grey them out
$existing = array_map(fn($c) => strtolower(trim($c['name'])), $classes);
$suggested = [
    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9',
    'Form 1', 'Form 2', 'Form 3', 'Form 4',
];

$message_icons = [
    'success' => 'bi-check-circle-fill',
    'danger'  => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];

$active_page = 'forms';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Set-up steps ---------- */
        .fx-steps {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        .fx-step {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px 8px 8px;
            border-radius: 99px;
            background: var(--white);
            box-shadow: 0 16px 32px -24px rgba(0, 10, 102, 0.3);
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink);
        }
        .fx-step b {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--primary);
            color: var(--accent);
            font-size: 0.8rem;
        }

        /* ---------- Level groups ---------- */
        .fx-group { margin-bottom: 40px; }
        .fx-group-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .fx-group-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--primary);
            color: var(--accent);
            font-size: 1.2rem;
        }
        .fx-group-head h2 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--primary);
        }
        .fx-group-head span.sub { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

        .fx-levels {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 20px;
        }
        .fx-level {
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22);
        }
        .fx-level-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        .fx-level h3 {
            margin: 0 0 6px;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--ink);
        }
        .fx-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }
        .fx-stat {
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--bg-light);
        }
        .fx-stat strong { display: block; font-size: 1.3rem; font-weight: 800; color: var(--primary); line-height: 1.1; }
        .fx-stat span { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); }

        .fx-hint {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin: 0 0 14px;
            padding: 10px 12px;
            border-radius: 12px;
            background: var(--accent-tint);
            color: var(--accent-deep);
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.45;
        }
        .fx-hint a { color: inherit; font-weight: 800; }

        .fx-level-actions { margin-top: auto; display: grid; gap: 8px; }
        .fx-level-actions .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .fx-level-actions .ui-btn { width: 100%; }
        a.ui-icon { text-decoration: none; }

        /* ---------- Quick-add chips ---------- */
        .fx-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .fx-chip {
            padding: 7px 14px;
            border: 1.5px solid var(--line);
            border-radius: 99px;
            background: var(--white);
            color: var(--primary);
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
        }
        .fx-chip:hover { background: var(--primary-tint); border-color: var(--primary); }
        .fx-chip:disabled { opacity: 0.45; cursor: not-allowed; background: var(--bg-light); border-style: dashed; }
        .fx-chip-label { margin: 18px 0 0; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); }
        .fx-note { margin: 14px 0 0; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Forms &amp; grades</h1>
            <p>Set up each level, then add its subjects and build its timetable</p>
        </div>
        <div class="ui-actions">
            <button type="button" class="ui-btn is-primary" data-bs-toggle="modal" data-bs-target="#addFormModal">
                <i class="bi bi-plus-lg"></i> Create level
            </button>
        </div>
    </div>

    <div class="fx-steps">
        <span class="fx-step"><b>1</b> Create the level</span>
        <span class="fx-step"><b>2</b> Add its subjects</span>
        <span class="fx-step"><b>3</b> Build its timetable</span>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi <?php echo $message_icons[$message_type] ?? 'bi-info-circle-fill'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($classes)): ?>
        <section class="panel">
            <div class="ui-empty">
                <i class="bi bi-mortarboard"></i>
                <p>No academic levels yet</p>
                <small>Create your first level, such as Form 1 or Grade 7.</small>
                <button type="button" class="ui-btn is-primary" style="margin-top:18px;"
                        data-bs-toggle="modal" data-bs-target="#addFormModal">
                    <i class="bi bi-plus-lg"></i> Create level
                </button>
            </div>
        </section>
    <?php else: ?>

        <?php foreach ($groups as $g):
            if (empty($g['items'])) continue;
        ?>
            <section class="fx-group">
                <div class="fx-group-head">
                    <div class="fx-group-icon"><i class="bi <?php echo $g['icon']; ?>"></i></div>
                    <h2>
                        <?php echo htmlspecialchars($g['title']); ?>
                        <?php if ($g['sub']): ?><span class="sub"><?php echo htmlspecialchars($g['sub']); ?></span><?php endif; ?>
                    </h2>
                </div>

                <div class="fx-levels">
                    <?php foreach ($g['items'] as $row):
                        $students = intval($row['student_count']);
                        $subs     = intval($row['subject_count']);
                        $confirm  = "Delete " . $row['name'] . "?"
                                  . ($students > 0 ? " It currently has $students student" . ($students === 1 ? '' : 's') . "." : "");
                        $nameQ    = urlencode($row['name']);
                    ?>
                        <article class="fx-level">
                            <div class="fx-level-top">
                                <div>
                                    <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                                    <span class="ui-badge is-soft"><?php echo htmlspecialchars($row['curriculum']); ?></span>
                                </div>
                                <a href="?delete=<?php echo intval($row['class_id']); ?>"
                                   class="ui-icon is-danger" title="Delete level" aria-label="Delete <?php echo htmlspecialchars($row['name']); ?>"
                                   onclick="return confirm(<?php echo htmlspecialchars(json_encode($confirm), ENT_QUOTES); ?>);">
                                    <i class="bi bi-trash3-fill"></i>
                                </a>
                            </div>

                            <div class="fx-stats">
                                <div class="fx-stat"><strong><?php echo $students; ?></strong><span>Student<?php echo $students === 1 ? '' : 's'; ?></span></div>
                                <div class="fx-stat"><strong><?php echo $subs; ?></strong><span>Subject<?php echo $subs === 1 ? '' : 's'; ?></span></div>
                            </div>

                            <?php if ($subs === 0): ?>
                                <p class="fx-hint">
                                    <i class="bi bi-lightbulb-fill"></i>
                                    <span>No subjects yet. <a href="subjects.php?form=<?php echo $nameQ; ?>">Add subjects</a> first, then build the timetable.</span>
                                </p>
                            <?php endif; ?>

                            <div class="fx-level-actions">
                                <a class="ui-btn is-primary" href="manage_timetable.php?form=<?php echo $nameQ; ?>">
                                    <i class="bi bi-calendar3"></i> Build timetable
                                </a>
                                <div class="row2">
                                    <a class="ui-btn is-ghost is-sm" href="subjects.php?form=<?php echo $nameQ; ?>">
                                        <i class="bi bi-journal-bookmark"></i> Subjects
                                    </a>
                                    <a class="ui-btn is-ghost is-sm" href="exams.php?form=<?php echo $nameQ; ?>">
                                        <i class="bi bi-calendar-check"></i> Exams
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<!-- Add Level Modal -->
<div class="modal fade" id="addFormModal" tabindex="-1" aria-labelledby="addFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="addFormTitle">New academic level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="ui-field">
                        <label for="form_name">Level name</label>
                        <input type="text" name="form_name" id="form_name" class="form-control ui-input"
                               placeholder="e.g. Form 1 or Grade 7" autocomplete="off" required>
                    </div>

                    <p class="fx-chip-label">Or pick one:</p>
                    <div class="fx-chips">
                        <?php foreach ($suggested as $sname):
                            $taken = in_array(strtolower($sname), $existing, true);
                        ?>
                            <button type="button" class="fx-chip" data-name="<?php echo htmlspecialchars($sname); ?>"
                                    <?php echo $taken ? 'disabled title="Already created"' : ''; ?>>
                                <?php echo htmlspecialchars($sname); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <p class="fx-note">
                        Names containing "Form" are set up as <strong>Secondary (8-4-4)</strong>.
                        Anything else, such as "Grade 7", is set up as <strong>Primary (CBC)</strong>.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_form" class="ui-btn is-primary">
                        <i class="bi bi-plus-circle"></i> Create level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    // Quick-add chips fill the name box
    const nameInput = document.getElementById('form_name');
    document.querySelectorAll('.fx-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            nameInput.value = chip.dataset.name;
            nameInput.focus();
        });
    });
    document.getElementById('addFormModal').addEventListener('shown.bs.modal', () => nameInput.focus());
</script>
</body>
</html>
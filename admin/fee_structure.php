<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$terms = ['Term 1', 'Term 2', 'Term 3'];
$active_form = null;   // which form's tab to show after the page loads

// ---- Handle Adding/Updating Fee Structure ----
if (isset($_POST['save_fee'])) {
    $form     = trim($_POST['form'] ?? '');
    $term     = trim($_POST['term'] ?? '');
    $tuition  = (float) ($_POST['tuition'] ?? 0);
    $boarding = (float) ($_POST['boarding'] ?? 0);
    $activity = (float) ($_POST['activity'] ?? 0);
    $others   = (float) ($_POST['others'] ?? 0);
    $total    = $tuition + $boarding + $activity + $others;

    $active_form = $form;

    if ($form === '' || $term === '') {
        $message = "Form and Term are required.";
        $message_type = 'warning';
    } else {
        $check = $conn->prepare("SELECT id FROM fee_structure WHERE form = ? AND term = ? LIMIT 1");
        $check->bind_param("ss", $form, $term);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE fee_structure SET amount=?, tuition=?, boarding=?, activity=?, others=?, total_amount=? WHERE form=? AND term=?");
            $stmt->bind_param("ddddddss", $total, $tuition, $boarding, $activity, $others, $total, $form, $term);
        } else {
            $stmt = $conn->prepare("INSERT INTO fee_structure (form, term, amount, tuition, boarding, activity, others, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdddddd", $form, $term, $total, $tuition, $boarding, $activity, $others, $total);
        }

        if ($stmt->execute()) {
            $message = "Fee structure for <strong>" . htmlspecialchars($form) . " — " . htmlspecialchars($term) . "</strong> saved successfully!";
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

    // remember which form it belonged to, so we come back to the same tab
    $f_stmt = $conn->prepare("SELECT form FROM fee_structure WHERE id = ?");
    $f_stmt->bind_param("i", $id);
    $f_stmt->execute();
    $f_row = $f_stmt->get_result()->fetch_assoc();
    $f_stmt->close();

    $stmt = $conn->prepare("DELETE FROM fee_structure WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: fee_structure.php?deleted=1" . ($f_row ? "&form=" . urlencode($f_row['form']) : ""));
    exit();
}

if (isset($_GET['deleted'])) {
    $message = "Fee structure removed successfully.";
    $message_type = 'info';
}

// ---- Fetch all fee structures ----
$structures = [];
$res = $conn->query("SELECT * FROM fee_structure ORDER BY form ASC, term ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $structures[] = $row;
}

// ---- Fetch classes (these become the tabs) ----
$classes = [];
$c_res = $conn->query("SELECT name FROM classes ORDER BY sort_order ASC");
if ($c_res) {
    while ($row = $c_res->fetch_assoc()) $classes[] = $row['name'];
}
if (empty($classes)) {
    $classes = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
}

// Forms that appear in fee structures but not in the classes table still get a tab
$form_names = $classes;
foreach ($structures as $s) {
    if (!in_array($s['form'], $form_names, true)) $form_names[] = $s['form'];
}

// ---- Arrange: form -> term -> fee row ----
$by_form = [];
$term_counts = [];
foreach ($structures as $s) {
    $by_form[$s['form']][$s['term']] = $s;
    $term_counts[$s['form']] = ($term_counts[$s['form']] ?? 0) + 1;
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

$active_page = 'fee_structure';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ---------- Form tabs ---------- */
        .fx-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 26px;
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

        /* ---------- A form's fees ---------- */
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
        .fx-year {
            padding: 5px 14px;
            border-radius: 99px;
            background: var(--accent-tint);
            color: var(--accent-deep);
            font-size: 0.8rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .fx-terms {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }
        .fx-term {
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-radius: 22px;
            padding: 22px;
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
        .fx-term-head strong { font-size: 1rem; font-weight: 800; color: var(--primary); }
        .fx-term-head .ui-row-actions { gap: 6px; }
        .fx-term-head .ui-icon { width: 34px; height: 34px; font-size: 0.9rem; }
        a.ui-icon { text-decoration: none; }

        .fx-lines { list-style: none; margin: 0 0 14px; padding: 0; }
        .fx-lines li {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--line);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .fx-lines li:last-child { border-bottom: none; }
        .fx-lines li span:last-child { color: var(--ink); font-weight: 700; white-space: nowrap; }
        .fx-total {
            margin-top: auto;
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--primary);
            color: var(--white);
        }
        .fx-total span { display: block; font-size: 0.78rem; font-weight: 700; color: rgba(255, 255, 255, 0.75); }
        .fx-total strong { display: block; font-size: 1.3rem; font-weight: 800; letter-spacing: -0.01em; color: var(--accent); }

        .fx-none {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            margin-top: 8px;
            padding: 22px 18px;
            border: 2px dashed var(--line);
            border-radius: 16px;
            color: var(--text-muted);
            font-size: 0.88rem;
            font-weight: 600;
        }

        /* live total in the pop-up */
        .fx-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 18px;
            padding: 14px 18px;
            border-radius: 16px;
            background: var(--primary-tint);
            font-weight: 700;
            color: var(--primary);
        }
        .fx-preview strong { font-size: 1.2rem; font-weight: 800; }

        @media (max-width: 992px) {
            .fx-terms { grid-template-columns: 1fr; }
        }
        @media print {
            .fx-section:not(.is-visible) { display: none !important; }
            .fx-tabs { display: none !important; }
            .fx-total { background: #fff !important; color: #000 !important; border: 1px solid #ccc; }
            .fx-total span, .fx-total strong { color: #000 !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Fee structure</h1>
            <p>Set the fees for each form, term by term</p>
        </div>
        <div class="ui-actions no-print">
            <button type="button" class="ui-btn is-ghost" onclick="window.print()">
                <i class="bi bi-printer"></i> Print
            </button>
            <button type="button" class="ui-btn is-primary" onclick="openFeeModal()">
                <i class="bi bi-plus-lg"></i> New structure
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
            All forms <span class="n"><?php echo count($structures); ?></span>
        </button>
        <?php foreach ($form_names as $fname): ?>
            <button type="button" class="fx-tab" data-tab="<?php echo htmlspecialchars($fname); ?>" role="tab">
                <?php echo htmlspecialchars($fname); ?>
                <span class="n"><?php echo intval($term_counts[$fname] ?? 0); ?>/<?php echo count($terms); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- A section for every form -->
    <?php foreach ($form_names as $fname):
        $visible = ($active_form === 'all' || $active_form === $fname);
        $jsForm  = htmlspecialchars(json_encode($fname), ENT_QUOTES);

        // terms to show: the usual three, plus any unusual ones that exist
        $show_terms = array_values(array_unique(array_merge($terms, array_keys($by_form[$fname] ?? []))));

        // what a family would pay across the terms that are set
        $year_total = 0.0;
        foreach (($by_form[$fname] ?? []) as $fee) $year_total += (float) ($fee['total_amount'] ?? 0);
    ?>
        <section class="fx-section<?php echo $visible ? ' is-visible' : ''; ?>" data-form="<?php echo htmlspecialchars($fname); ?>">
            <div class="fx-section-head">
                <h2>
                    <?php echo htmlspecialchars($fname); ?>
                    <?php if ($year_total > 0): ?>
                        <span class="fx-year">All terms: KES <?php echo number_format($year_total, 2); ?></span>
                    <?php endif; ?>
                </h2>
            </div>

            <div class="fx-terms">
                <?php foreach ($show_terms as $tname):
                    $fee = $by_form[$fname][$tname] ?? null;
                    $jsTerm = htmlspecialchars(json_encode($tname), ENT_QUOTES);
                ?>
                    <div class="fx-term">
                        <div class="fx-term-head">
                            <strong><?php echo htmlspecialchars($tname); ?></strong>
                            <?php if ($fee): ?>
                                <div class="ui-row-actions no-print">
                                    <button type="button" class="ui-icon" title="Edit fees" aria-label="Edit fees for <?php echo htmlspecialchars($fname . ', ' . $tname); ?>"
                                            onclick="openFeeModal(<?php echo $jsForm; ?>, <?php echo $jsTerm; ?>, {
                                                tuition: <?php echo (float)($fee['tuition'] ?? 0); ?>,
                                                boarding: <?php echo (float)($fee['boarding'] ?? 0); ?>,
                                                activity: <?php echo (float)($fee['activity'] ?? 0); ?>,
                                                others: <?php echo (float)($fee['others'] ?? 0); ?>
                                            })">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <a href="?delete=<?php echo intval($fee['id']); ?>" class="ui-icon is-danger"
                                       title="Delete" aria-label="Delete fees for <?php echo htmlspecialchars($fname . ', ' . $tname); ?>"
                                       onclick="return confirm('Delete this record?');">
                                        <i class="bi bi-trash3-fill"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($fee): ?>
                            <ul class="fx-lines">
                                <li><span>Tuition</span><span><?php echo number_format((float)($fee['tuition'] ?? 0), 2); ?></span></li>
                                <li><span>Boarding</span><span><?php echo number_format((float)($fee['boarding'] ?? 0), 2); ?></span></li>
                                <li><span>Activity</span><span><?php echo number_format((float)($fee['activity'] ?? 0), 2); ?></span></li>
                                <li><span>Others</span><span><?php echo number_format((float)($fee['others'] ?? 0), 2); ?></span></li>
                            </ul>
                            <div class="fx-total">
                                <span>Total for the term (KES)</span>
                                <strong><?php echo number_format((float)($fee['total_amount'] ?? 0), 2); ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="fx-none">
                                Fees not set yet
                                <button type="button" class="ui-btn is-primary is-sm no-print"
                                        onclick="openFeeModal(<?php echo $jsForm; ?>, <?php echo $jsTerm; ?>)">
                                    <i class="bi bi-plus-lg"></i> Set fees
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

</main>

<!-- Set / edit fees -->
<div class="modal fade" id="feeModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ui-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Set fees</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="ui-form-grid">
                        <div class="ui-field">
                            <label for="m_form">Form</label>
                            <select name="form" id="m_form" class="form-select ui-input" required>
                                <?php foreach ($form_names as $cname): ?>
                                    <option value="<?php echo htmlspecialchars($cname); ?>"><?php echo htmlspecialchars($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="m_term">Term</label>
                            <select name="term" id="m_term" class="form-select ui-input" required>
                                <?php foreach ($terms as $tname): ?>
                                    <option value="<?php echo htmlspecialchars($tname); ?>"><?php echo htmlspecialchars($tname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ui-field">
                            <label for="m_tuition">Tuition fee</label>
                            <input type="number" step="0.01" min="0" name="tuition" id="m_tuition" class="form-control ui-input fee-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="m_boarding">Boarding fee</label>
                            <input type="number" step="0.01" min="0" name="boarding" id="m_boarding" class="form-control ui-input fee-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="m_activity">Activity fee</label>
                            <input type="number" step="0.01" min="0" name="activity" id="m_activity" class="form-control ui-input fee-input" required>
                        </div>
                        <div class="ui-field">
                            <label for="m_others">Other charges</label>
                            <input type="number" step="0.01" min="0" name="others" id="m_others" class="form-control ui-input fee-input" required>
                        </div>
                    </div>
                    <div class="fx-preview">
                        <span>Total for the term</span>
                        <strong id="m_total">KES 0.00</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ui-btn is-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_fee" class="ui-btn is-primary">
                        <i class="bi bi-check-lg"></i> Save fees
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
    }
    document.querySelectorAll('.fx-tab').forEach(t => {
        t.addEventListener('click', () => setTab(t.dataset.tab));
    });
    setTab(currentTab);

    // ---- Live total in the pop-up ----
    function updateTotal() {
        let sum = 0;
        document.querySelectorAll('.fee-input').forEach(i => { sum += parseFloat(i.value) || 0; });
        document.getElementById('m_total').textContent =
            'KES ' + sum.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    document.querySelectorAll('.fee-input').forEach(i => i.addEventListener('input', updateTotal));

    // ---- Set / edit pop-up: opens already set to the form and term you clicked ----
    const feeModalEl = document.getElementById('feeModal');
    const feeModal   = new bootstrap.Modal(feeModalEl);

    function openFeeModal(form, term, vals) {
        const editing = !!vals;
        vals = vals || {};
        const f = form || (currentTab !== 'all' ? currentTab : '');
        document.getElementById('m_form').value     = f;
        document.getElementById('m_term').value     = term || 'Term 1';
        document.getElementById('m_tuition').value  = editing ? vals.tuition  : '';
        document.getElementById('m_boarding').value = editing ? vals.boarding : '0';
        document.getElementById('m_activity').value = editing ? vals.activity : '0';
        document.getElementById('m_others').value   = editing ? vals.others   : '0';
        document.getElementById('modalTitle').textContent = editing ? 'Edit fees' : 'Set fees';
        updateTotal();
        feeModal.show();
    }
    feeModalEl.addEventListener('shown.bs.modal', () => document.getElementById('m_tuition').focus());
</script>
</body>
</html>
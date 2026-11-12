<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

// Security
if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    die("Unauthorized");
}

$adm_no = $_SESSION['adm_no'];
$type = $_GET['type'] ?? '';

// Fetch student
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student not found.");

$student_id = (int) $student['student_id'];
$student_form = $student['form'] ?? '';

// Get school name
$sys = $conn->query("SELECT school_name FROM system_settings LIMIT 1")->fetch_assoc();
$school_name = $sys['school_name'] ?? 'The Michaelson School';

// --- FETCH FEES LOGIC ---
$total_fees = 0;
$amount_paid = 0;

// 1. Get Expected Fees from Structure
$s_stmt = $conn->prepare("SELECT * FROM fee_structure WHERE form = ? LIMIT 1");
if ($s_stmt) {
    $s_stmt->bind_param("s", $student_form);
    $s_stmt->execute();
    $structure = $s_stmt->get_result()->fetch_assoc();
    $total_fees = $structure['total_amount'] ?? 0;
    $s_stmt->close();
}

// 2. Get Amount Paid from Fees
$f_stmt = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM fees WHERE student_id = ?");
if ($f_stmt) {
    $f_stmt->bind_param("i", $student_id);
    $f_stmt->execute();
    $fee_data = $f_stmt->get_result()->fetch_assoc();
    $amount_paid = $fee_data['total_paid'] ?? 0;
    $f_stmt->close();
}

$balance = $total_fees - $amount_paid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Download Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-light); padding: 40px 20px; color: var(--ink); }
        
        .document-box { 
            background: var(--white); max-width: 850px; margin: 0 auto; padding: 50px; 
            border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1.5px solid var(--line);
        }

        /* Modern Header */
        .doc-header { text-align: center; border-bottom: 3px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        .doc-header h2 { font-weight: 800; color: var(--primary); margin: 0; font-size: 1.8rem; letter-spacing: -0.5px; }
        .doc-header p { margin: 5px 0 0 0; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; background: var(--bg-light); padding: 20px; border-radius: 16px; border: 1px solid var(--line); }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { font-size: 1.05rem; font-weight: 700; color: var(--ink); }

        /* Tables */
        .table-custom { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table-custom th { background: var(--bg-light); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 800; padding: 15px; border-bottom: 2px solid var(--line); text-align: left; }
        .table-custom td { padding: 15px; border-bottom: 1px solid var(--line); font-weight: 600; color: var(--ink); }
        
        .amount-highlight { color: var(--primary); font-weight: 800; font-size: 1.1rem; }
        .text-danger { color: #dc2626 !important; }
        .text-success { color: #059669 !important; }

        /* Signatures */
        .signature-area { display: flex; justify-content: space-between; margin-top: 60px; padding-top: 20px; }
        .sig-box { text-align: center; }
        .sig-line { width: 200px; border-top: 2px dashed #cbd5e1; margin-bottom: 10px; }
        .sig-text { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

        /* Floating Action Button for Print */
        .print-action { position: fixed; bottom: 30px; right: 30px; z-index: 100; }
        .print-btn { background: var(--primary); color: white; border: none; padding: 16px 30px; border-radius: 50px; font-weight: 800; font-size: 1.05rem; box-shadow: 0 10px 25px rgba(0,10,102,0.3); display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .print-btn:hover { transform: translateY(-3px); background: var(--primary-soft); box-shadow: 0 15px 30px rgba(0,10,102,0.4); }

        /* =========================================
           EXAM CARD SPECIFIC STYLES 
           ========================================= */
        .modern-exam-card {
            background: var(--white); border-radius: 24px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.1);
            overflow: hidden; max-width: 900px; margin: 0 auto; position: relative; border: 1.5px solid var(--line);
        }
        
        .card-header-custom { background: linear-gradient(135deg, var(--primary) 0%, #2c3a4d 100%); padding: 30px 40px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .card-header-custom h4 { font-weight: 800; margin: 0; letter-spacing: -0.5px; }
        .card-header-custom .exam-no { font-size: 0.85rem; opacity: 0.8; font-family: monospace; letter-spacing: 1px; }

        .student-strip { background: #f8fafc; padding: 25px 40px; display: flex; gap: 40px; border-bottom: 1px solid var(--line); }
        .info-group { display: flex; flex-direction: column; }
        .info-group label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; margin-bottom: 4px; }
        .info-group span { font-size: 1.1rem; font-weight: 700; color: var(--ink); }

        .schedule-container { padding: 30px 40px; }
        .schedule-title { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 1px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .table-modern th { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 0 15px 10px; text-align: left; border: none; }
        .table-modern td { background: var(--bg-light); padding: 15px; vertical-align: middle; border: 1px solid var(--line); }
        
        .table-modern tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; border-right: none; }
        .table-modern tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; border-left: none; }
        
        .subject-pill { background: var(--white); border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 50px; font-weight: 700; color: var(--primary); font-size: 0.85rem; display: inline-block; }
        .time-badge { background: var(--primary-tint); color: var(--primary); padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.8rem; display: inline-block; }

        .card-footer-custom { padding: 20px 40px 40px; display: flex; justify-content: space-between; align-items: flex-end; }
        .signature-area-exam { text-align: center; }
        .signature-line-exam { width: 180px; border-bottom: 2px solid #cbd5e1; margin-bottom: 8px; }
        .signature-area-exam span { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }

        @media print {
            body { background: white; padding: 0; }
            .document-box, .modern-exam-card { box-shadow: none; padding: 0; max-width: 100%; border: none; }
            .print-action { display: none; }
            .info-grid { background: white; }
            .card-header-custom { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .table-modern td { background: #fff !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 500);">

    <?php if ($type === 'exam_card'): ?>
        
        <!-- EXAM CARD MATCHING THE MAIN UI -->
        <div class="modern-exam-card">
            <div class="card-header-custom">
                <div>
                    <h4><?php echo htmlspecialchars($school_name); ?></h4>
                    <div class="exam-no mt-1">OFFICIAL EXAMINATION PASS</div>
                </div>
                <div class="text-end">
                    <div class="exam-no">ID: EX-<?php echo date('Y') ?>-<?php echo str_pad($student_id, 4, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <div class="student-strip">
                <div class="info-group"><label>Student Name</label><span><?php echo htmlspecialchars($student['full_name'] ?? ''); ?></span></div>
                <div class="info-group"><label>Admission No</label><span><?php echo htmlspecialchars($student['adm_no'] ?? ''); ?></span></div>
                <div class="info-group"><label>Class / Form</label><span><?php echo htmlspecialchars($student_form ?: '—'); ?></span></div>
            </div>

            <div class="schedule-container">
                <div class="schedule-title"><i class="bi bi-calendar-event-fill"></i> Scheduled Examinations</div>

                <?php 
                $exam_schedule = [];
                $columns_res = $conn->query("SHOW COLUMNS FROM exams");
                $exam_columns = [];
                while ($col = $columns_res->fetch_assoc()) $exam_columns[] = $col['Field'];

                $time_col = 'start_time'; 
                if (in_array('start', $exam_columns)) $time_col = 'start';
                elseif (in_array('time', $exam_columns)) $time_col = 'time';
                elseif (in_array('exam_time', $exam_columns)) $time_col = 'exam_time';

                $end_col = 'end_time';
                if (in_array('end', $exam_columns)) $end_col = 'end';
                elseif (in_array('duration', $exam_columns)) $end_col = 'duration';

                $date_col = 'exam_date';
                if (in_array('date', $exam_columns)) $date_col = 'date';
                elseif (in_array('exam_day', $exam_columns)) $date_col = 'exam_day';

                $class_col = 'form';
                if (in_array('class_name', $exam_columns)) $class_col = 'class_name';
                elseif (in_array('class', $exam_columns)) $class_col = 'class';

                $exam_sql = "SELECT e.*, s.subject_name FROM exams e LEFT JOIN subjects s ON e.subject_id = s.subject_id WHERE e.`$class_col` = ? ORDER BY e.`$date_col` ASC";
                $exam_stmt = $conn->prepare($exam_sql);
                
                if ($exam_stmt) {
                    $exam_stmt->bind_param("s", $student_form);
                    $exam_stmt->execute();
                    $exam_res = $exam_stmt->get_result();
                    while ($row = $exam_res->fetch_assoc()) $exam_schedule[] = $row;
                    $exam_stmt->close();
                }
                ?>

                <?php if (empty($exam_schedule)): ?>
                    <div class="alert alert-warning border-0 rounded-3 mb-0"><i class="bi bi-info-circle-fill me-2"></i> The exam schedule has not been published yet.</div>
                <?php else: ?>
                    <table class="table-modern">
                        <thead><tr><th>Date</th><th>Subject</th><th>Time</th><th>Venue</th></tr></thead>
                        <tbody>
                            <?php foreach ($exam_schedule as $exam): 
                                $date_val = $exam[$date_col] ?? 'TBA';
                                $start_val = $exam[$time_col] ?? 'TBA';
                                $end_val = $exam[$end_col] ?? '';
                                $venue_val = $exam['venue'] ?? $exam['room'] ?? 'Main Hall';
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo ($date_val !== 'TBA') ? date('d M Y', strtotime($date_val)) : 'TBA'; ?></td>
                                    <td><span class="subject-pill"><?php echo htmlspecialchars($exam['subject_name'] ?? 'General'); ?></span></td>
                                    <td><span class="time-badge"><?php echo ($start_val !== 'TBA') ? date('H:i', strtotime($start_val)) : 'TBA'; ?> <?php if (!empty($end_val)): ?> - <?php echo date('H:i', strtotime($end_val)); ?><?php endif; ?></span></td>
                                    <td class="text-muted fw-semibold"><?php echo htmlspecialchars($venue_val); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card-footer-custom">
                <div class="signature-area-exam"><div class="signature-line-exam"></div><span>Student Signature</span></div>
                <div class="signature-area-exam"><div class="signature-line-exam"></div><span>Registrar's Stamp</span></div>
            </div>
        </div>

    <?php else: ?>
        
        <!-- ALL OTHER DOCUMENTS -->
        <div class="document-box">
            <div class="doc-header">
                <h2><?php echo htmlspecialchars($school_name); ?></h2>
                <p>
                    <?php 
                        if($type === 'timetable') echo 'Master Class Timetable';
                        elseif($type === 'fee_structure') echo 'Official Fee Structure';
                        elseif($type === 'fees') echo 'Student Fee Statement';
                    ?>
                </p>
            </div>

            <div class="info-grid">
                <div class="info-item"><span class="info-label">Student Name</span><span class="info-value text-uppercase"><?php echo htmlspecialchars($student['full_name']); ?></span></div>
                <div class="info-item"><span class="info-label">Admission No</span><span class="info-value"><?php echo htmlspecialchars($student['adm_no']); ?></span></div>
                <div class="info-item"><span class="info-label">Class / Form</span><span class="info-value"><?php echo htmlspecialchars($student_form); ?></span></div>
                <div class="info-item"><span class="info-label">Date Generated</span><span class="info-value"><?php echo date('d M Y'); ?></span></div>
            </div>

            <?php if ($type === 'timetable'): ?>
                <h5 class="fw-bold mb-3" style="color: var(--primary);">Weekly Timetable (<?php echo htmlspecialchars($student_form); ?>)</h5>
                <table class="table-custom">
                    <thead><tr><th>Time</th><th>Subject</th><th>Venue</th></tr></thead>
                    <tbody>
                        <?php 
                        $t_stmt = $conn->prepare("SELECT t.start_time, t.end_time, t.room_no, s.subject_name FROM timetable t LEFT JOIN subjects s ON t.subject_id = s.subject_id WHERE t.form = ? ORDER BY t.start_time");
                        $t_stmt->bind_param("s", $student_form);
                        $t_stmt->execute();
                        $t_res = $t_stmt->get_result();
                        $count = 0;
                        while ($r = $t_res->fetch_assoc()) {
                            $time = date('H:i', strtotime($r['start_time'])) . ' - ' . date('H:i', strtotime($r['end_time']));
                            echo "<tr><td>{$time}</td><td>" . htmlspecialchars($r['subject_name']) . "</td><td>" . htmlspecialchars($r['room_no']) . "</td></tr>";
                            $count++;
                        }
                        if ($count === 0) echo "<tr><td colspan='3' class='text-center text-muted'>No timetable published yet.</td></tr>";
                        ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'fee_structure'): ?>
                <h5 class="fw-bold mb-3" style="color: var(--primary);">Fee Breakdown for <?php echo htmlspecialchars($student_form); ?></h5>
                <table class="table-custom">
                    <thead><tr><th>Fee Item</th><th class="text-end">Amount (KES)</th></tr></thead>
                    <tbody>
                        <?php if ($structure): ?>
                            <tr><td>Tuition Fees</td><td class="text-end"><?php echo number_format($structure['tuition'] ?? 0, 2); ?></td></tr>
                            <tr><td>Boarding & Accommodation</td><td class="text-end"><?php echo number_format($structure['boarding'] ?? 0, 2); ?></td></tr>
                            <tr><td>Co-Curricular Activities</td><td class="text-end"><?php echo number_format($structure['activity'] ?? 0, 2); ?></td></tr>
                            <tr><td>Other Charges</td><td class="text-end"><?php echo number_format($structure['others'] ?? 0, 2); ?></td></tr>
                            <tr style="background: var(--bg-light);">
                                <td class="fw-bold text-uppercase">Total Expected Fees</td>
                                <td class="text-end amount-highlight">KES <?php echo number_format($total_fees, 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center text-muted">No fee structure published for your class.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php elseif ($type === 'fees'): ?>
                <h5 class="fw-bold mb-3" style="color: var(--primary);">Fee Statement</h5>
                <table class="table-custom">
                    <tbody>
                        <tr><td class="fw-bold text-muted text-uppercase" style="font-size: 0.8rem;">Total Expected Fees (<?php echo htmlspecialchars($student_form); ?>)</td><td class="text-end fw-bold">KES <?php echo number_format($total_fees, 2); ?></td></tr>
                        <tr><td class="fw-bold text-muted text-uppercase" style="font-size: 0.8rem;">Total Amount Paid</td><td class="text-end fw-bold text-success">KES <?php echo number_format($amount_paid, 2); ?></td></tr>
                        <tr style="background: var(--bg-light);">
                            <td class="fw-bold text-uppercase" style="font-size: 0.9rem;">Outstanding Balance</td>
                            <td class="text-end amount-highlight <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">KES <?php echo number_format(abs($balance), 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if ($balance <= 0): ?>
                    <div class="alert alert-success fw-bold mt-4 text-center" style="background: #ecfdf5; border: 1px dashed #10b981; color: #065f46;">Account Fully Settled. Thank you!</div>
                <?php endif; ?>

            <?php endif; ?>

            <div class="signature-area">
                <div class="sig-box"><div class="sig-line"></div><div class="sig-text">Student Signature</div></div>
                <div class="sig-box"><div class="sig-line"></div><div class="sig-text">Official Stamp</div></div>
            </div>
        </div>

    <?php endif; ?>

    <!-- Manual Print Button -->
    <div class="print-action">
        <button onclick="window.print()" class="print-btn">
            <i class="bi bi-printer-fill"></i> Print / Save PDF
        </button>
    </div>

</body>
</html>
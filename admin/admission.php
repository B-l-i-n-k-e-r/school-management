<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// --- 1. APPROVAL LOGIC ---
if (isset($_POST['approve_app_id'])) {
    $app_id = intval($_POST['approve_app_id']);
    $app_query = $conn->prepare("SELECT * FROM admission_applications WHERE id = ?");
    $app_query->bind_param("i", $app_id);
    $app_query->execute();
    $app = $app_query->get_result()->fetch_assoc();
    $app_query->close();

    if ($app) {
        $user_email = trim($app['email'] ?? '');
        if (!empty($user_email)) {
            $conn->begin_transaction();
            try {
                $adm_no      = "ADM-" . rand(1000, 9999);
                $hashed_pass = password_hash('123456', PASSWORD_DEFAULT);

                // 1. Create user
                $stmt1 = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt1->bind_param("sss", $adm_no, $user_email, $hashed_pass);
                $stmt1->execute();
                $new_user_id = $conn->insert_id;
                $stmt1->close();

                // 2. Create student
                $stmt2 = $conn->prepare("
                    INSERT INTO students
                    (user_id, full_name, adm_no, gender, form, parent_name, parent_contact, email, dob, address, password)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param("issssssssss",
                    $new_user_id,
                    $app['student_name'],
                    $adm_no,
                    $app['gender'],
                    $app['form_requested'],
                    $app['parent_name'],
                    $app['phone'],
                    $user_email,
                    $app['dob'],
                    $app['address'],
                    $hashed_pass
                );
                $stmt2->execute();
                $stmt2->close();

                // 3. Mark application approved
                $stmt3 = $conn->prepare("UPDATE admission_applications SET status = 'Approved' WHERE id = ?");
                $stmt3->bind_param("i", $app_id);
                $stmt3->execute();
                $stmt3->close();

                $conn->commit();
                $message = "Approved! Student account created with ADM: <strong>" . htmlspecialchars($adm_no) . "</strong>";
                $message_type = 'success';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// --- 2. REJECTION MESSAGE ---
if (isset($_GET['status']) && $_GET['status'] === 'rejected') {
    $message = "Application has been marked as rejected.";
    $message_type = 'warning';
}

// --- 3. DELETE LOGIC ---
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $get_info = $conn->prepare("SELECT user_id FROM students WHERE student_id = ?");
    $get_info->bind_param("i", $delete_id);
    $get_info->execute();
    $student_data = $get_info->get_result()->fetch_assoc();
    $get_info->close();

    if ($student_data) {
        $conn->begin_transaction();
        try {
            $u_id = (int) $student_data['user_id'];
            $conn->query("DELETE FROM students WHERE student_id = $delete_id");
            $conn->query("DELETE FROM users WHERE user_id = $u_id");
            $conn->commit();
            $message = "Record deleted successfully.";
            $message_type = 'info';
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// --- 4. FETCH DATA ---
$pending = [];
$p_res = mysqli_query($conn, "SELECT * FROM admission_applications WHERE status = 'pending' ORDER BY id DESC");
if ($p_res) {
    while ($row = mysqli_fetch_assoc($p_res)) $pending[] = $row;
}

$recent = [];
$r_res = mysqli_query($conn, "SELECT * FROM students ORDER BY student_id DESC LIMIT 10");
if ($r_res) {
    while ($row = mysqli_fetch_assoc($r_res)) $recent[] = $row;
}

$active_page = 'admission';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';

// Icon for the message box
$message_icons = [
    'success' => 'bi-check-circle-fill',
    'danger'  => 'bi-x-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'info'    => 'bi-info-circle-fill',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Management | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Sidebar, top bar and shared components (buttons, tables, alerts) come from includes/sidebar.php and includes/navbar.php -->
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Admission management</h1>
            <p>Process applications and manage the student registry</p>
        </div>
        <div class="ui-actions">
            <a href="student_list.php" class="ui-btn is-ghost">
                <i class="bi bi-person-gear"></i> Manage students
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi <?php echo $message_icons[$message_type] ?? 'bi-info-circle-fill'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Pending Applications -->
    <section class="panel is-flush" style="margin-bottom: 28px;">
        <div class="ui-panel-head">
            <div class="ui-panel-title">
                <h2>Pending admission requests</h2>
                <span class="ui-count"><?php echo count($pending); ?> pending</span>
            </div>
        </div>

        <?php if (empty($pending)): ?>
            <div class="ui-empty">
                <i class="bi bi-inbox"></i>
                <p>No pending applications right now.</p>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Gender</th>
                            <th>Requested level</th>
                            <th>Parent info</th>
                            <th>Address</th>
                            <th class="is-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $p): ?>
                            <tr>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($p['student_name'] ?? ''); ?></span>
                                    <span class="ui-sub"><?php echo htmlspecialchars($p['email'] ?? ''); ?></span>
                                </td>
                                <td><span class="ui-badge is-soft"><?php echo htmlspecialchars($p['gender'] ?? ''); ?></span></td>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($p['form_requested'] ?? ''); ?></span></td>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($p['parent_name'] ?? ''); ?></span>
                                    <span class="ui-sub"><?php echo htmlspecialchars($p['phone'] ?? ''); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($p['address'] ?? ''); ?></td>
                                <td class="is-right">
                                    <div class="ui-row-actions">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="approve_app_id" value="<?php echo intval($p['id']); ?>">
                                            <button type="submit" class="ui-btn is-success is-sm">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                        </form>
                                        <a href="reject_app.php?id=<?php echo intval($p['id']); ?>"
                                           class="ui-btn is-danger is-sm"
                                           onclick="return confirm('Reject this application?');">
                                            <i class="bi bi-x-lg"></i> Deny
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recently Enrolled -->
    <section class="panel is-flush">
        <div class="ui-panel-head">
            <h2>Recently enrolled students</h2>
        </div>

        <?php if (empty($recent)): ?>
            <div class="ui-empty">
                <i class="bi bi-people"></i>
                <p>No students enrolled yet.</p>
            </div>
        <?php else: ?>
            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>ADM no</th>
                            <th>Student details</th>
                            <th>Gender</th>
                            <th>Form</th>
                            <th>Parent / guardian</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><span class="ui-badge is-navy"><?php echo htmlspecialchars($row['adm_no'] ?? ''); ?></span></td>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($row['full_name'] ?? ''); ?></span>
                                    <span class="ui-sub"><?php echo htmlspecialchars($row['email'] ?? ''); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['gender'] ?? ''); ?></td>
                                <td><span class="ui-badge is-gold"><?php echo htmlspecialchars($row['form'] ?? ''); ?></span></td>
                                <td>
                                    <span class="ui-name"><?php echo htmlspecialchars($row['parent_name'] ?? ''); ?></span>
                                    <span class="ui-sub"><?php echo htmlspecialchars($row['parent_contact'] ?? ''); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include("footer.php"); ?>
</body>
</html>
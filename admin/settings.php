<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily on so we can see issues
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// ---- 1. Ensure the table and columns exist (Auto-fix) ----
$conn->query("
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) DEFAULT 'My School',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Check for missing columns and add them dynamically
$required_columns = [
    'school_email'  => "VARCHAR(255) DEFAULT NULL",
    'logo_path'     => "VARCHAR(255) DEFAULT NULL",
    'motto'         => "VARCHAR(255) DEFAULT NULL",
    'address'       => "VARCHAR(255) DEFAULT NULL",
    'phone'         => "VARCHAR(50) DEFAULT NULL",
    'website'       => "VARCHAR(255) DEFAULT NULL",
    'academic_year' => "VARCHAR(20) DEFAULT NULL",
    'current_term'  => "VARCHAR(20) DEFAULT 'Term 1'",
    'currency'      => "VARCHAR(10) DEFAULT 'KES'"
];

$check_cols = $conn->query("SHOW COLUMNS FROM system_settings");
$existing_cols = [];
while ($col = $check_cols->fetch_assoc()) {
    $existing_cols[] = $col['Field'];
}

foreach ($required_columns as $col_name => $col_def) {
    if (!in_array($col_name, $existing_cols)) {
        $conn->query("ALTER TABLE system_settings ADD COLUMN `$col_name` $col_def");
    }
}

// ---- 2. Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $school_name   = trim($_POST['school_name']   ?? '');
    $school_email  = trim($_POST['school_email']  ?? '');
    $logo_path     = trim($_POST['logo_path']     ?? '');
    $motto         = trim($_POST['motto']         ?? '');
    $address       = trim($_POST['address']       ?? '');
    $phone         = trim($_POST['phone']         ?? '');
    $website       = trim($_POST['website']       ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $current_term  = trim($_POST['current_term']  ?? 'Term 1');
    $currency      = trim($_POST['currency']      ?? 'KES');

    if ($school_name === '' || $school_email === '') {
        $message = "School name and email are required.";
        $message_type = 'warning';
    } elseif (!filter_var($school_email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid school email address.";
        $message_type = 'warning';
    } else {
        // Check if row 1 exists
        $check_row = $conn->query("SELECT id FROM system_settings WHERE id = 1");
        
        if ($check_row && $check_row->num_rows > 0) {
            // Update
            $upd = $conn->prepare("
                UPDATE system_settings
                SET school_name = ?, school_email = ?, logo_path = ?, motto = ?, address = ?, phone = ?, website = ?, academic_year = ?, current_term = ?, currency = ?
                WHERE id = 1
            ");
            $upd->bind_param(
                "ssssssssss",
                $school_name, $school_email, $logo_path, $motto, $address, $phone, $website, $academic_year, $current_term, $currency
            );
        } else {
            // Insert
            $upd = $conn->prepare("
                INSERT INTO system_settings
                (id, school_name, school_email, logo_path, motto, address, phone, website, academic_year, current_term, currency)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $upd->bind_param(
                "ssssssssss",
                $school_name, $school_email, $logo_path, $motto, $address, $phone, $website, $academic_year, $current_term, $currency
            );
        }

        if ($upd->execute()) {
            $message = "Settings saved successfully!";
            $message_type = 'success';
        } else {
            $message = "Failed to save: " . htmlspecialchars($upd->error);
            $message_type = 'danger';
        }
        $upd->close();
    }
}

// ---- 3. Fetch current settings ----
$settings = [];
$res = $conn->query("SELECT * FROM system_settings WHERE id = 1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $settings = $res->fetch_assoc();
}

$active_page = 'settings';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Admin Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* Logo preview */
        .logo-preview {
            width: 96px; 
            height: 96px;
            border-radius: 16px;
            background: var(--bg-light);
            border: 2px dashed var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>System Settings</h1>
            <p>Configure school-wide information used across the entire portal</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo $message_type; ?>">
            <i class="bi bi-info-circle-fill"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" class="panel p-4 p-md-5">

        <!-- Section: School Identity -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                    <h5 class="fw-bold mb-0" style="color: var(--primary);">School Identity</h5>
                </div>
            </div>

            <div class="col-md-8 ui-field">
                <label>School Name</label>
                <input type="text" name="school_name" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>"
                       placeholder="e.g. The Michaelson School" required>
            </div>

            <div class="col-md-4 ui-field">
                <label>Currency</label>
                <select name="currency" class="ui-input form-select">
                    <?php $cur = $settings['currency'] ?? 'KES'; ?>
                    <option value="KES" <?php echo $cur === 'KES' ? 'selected' : ''; ?>>KES (Kenyan Shilling)</option>
                    <option value="USD" <?php echo $cur === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                    <option value="EUR" <?php echo $cur === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                    <option value="GBP" <?php echo $cur === 'GBP' ? 'selected' : ''; ?>>GBP (Pound)</option>
                    <option value="UGX" <?php echo $cur === 'UGX' ? 'selected' : ''; ?>>UGX (Ugandan Shilling)</option>
                    <option value="TZS" <?php echo $cur === 'TZS' ? 'selected' : ''; ?>>TZS (Tanzanian Shilling)</option>
                </select>
            </div>

            <div class="col-12 ui-field">
                <label>Motto / Tagline</label>
                <input type="text" name="motto" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['motto'] ?? ''); ?>"
                       placeholder="e.g. Higher Is The Goal">
            </div>

            <div class="col-md-4 ui-field">
                <label>Logo</label>
                <div class="logo-preview mb-2">
                    <img src="../<?php echo htmlspecialchars($settings['logo_path'] ?? 'images/logo.jpg'); ?>"
                         alt="School Logo" onerror="this.style.display='none'">
                </div>
                <input type="text" name="logo_path" class="ui-input form-control-sm"
                       value="<?php echo htmlspecialchars($settings['logo_path'] ?? 'images/logo.jpg'); ?>"
                       placeholder="images/logo.jpg">
                <small class="text-muted mt-1 d-block">Path relative to the project root.</small>
            </div>
        </div>

        <!-- Section: Contact Information -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                    <h5 class="fw-bold mb-0" style="color: var(--primary);">Contact Information</h5>
                </div>
            </div>

            <div class="col-md-6 ui-field">
                <label>Contact Email</label>
                <input type="email" name="school_email" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"
                       placeholder="info@school.ac.ke" required>
            </div>

            <div class="col-md-6 ui-field">
                <label>Phone Number</label>
                <input type="text" name="phone" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>"
                       placeholder="+254 700 000 000">
            </div>

            <div class="col-12 ui-field">
                <label>Physical Address</label>
                <input type="text" name="address" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>"
                       placeholder="e.g. P.O. Box 1234, Nairobi, Kenya">
            </div>

            <div class="col-12 ui-field">
                <label>Website (optional)</label>
                <input type="text" name="website" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>"
                       placeholder="https://school.ac.ke">
            </div>
        </div>

        <!-- Section: Academic Settings -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="d-flex align-items-center gap-2" style="border-left: 4px solid var(--accent); padding-left: 15px;">
                    <h5 class="fw-bold mb-0" style="color: var(--primary);">Academic Settings</h5>
                </div>
            </div>

            <div class="col-md-6 ui-field">
                <label>Academic Year</label>
                <input type="text" name="academic_year" class="ui-input"
                       value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>"
                       placeholder="e.g. 2025/2026">
            </div>

            <div class="col-md-6 ui-field">
                <label>Current Term</label>
                <select name="current_term" class="ui-input form-select">
                    <?php $term = $settings['current_term'] ?? 'Term 1'; ?>
                    <option value="Term 1" <?php echo $term === 'Term 1' ? 'selected' : ''; ?>>Term 1</option>
                    <option value="Term 2" <?php echo $term === 'Term 2' ? 'selected' : ''; ?>>Term 2</option>
                    <option value="Term 3" <?php echo $term === 'Term 3' ? 'selected' : ''; ?>>Term 3</option>
                </select>
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-between align-items-center pt-4 border-top flex-wrap gap-2">
            <small class="text-muted fw-medium">
                <i class="bi bi-info-circle-fill me-1 text-primary"></i>
                Changes apply instantly across the whole portal.
            </small>
            <div class="d-flex gap-2">
                <button type="reset" class="ui-btn is-ghost">Discard</button>
                <button type="submit" name="save_settings" class="ui-btn is-primary">
                    <i class="bi bi-save"></i> Save Configuration
                </button>
            </div>
        </div>

    </form>

</main>

<?php include("footer.php"); ?>
</body>
</html>
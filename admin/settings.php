<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// Fetch current settings (single-row table, id = 1)
$settings = [];
$res = $conn->query("SELECT * FROM system_settings WHERE id = 1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $settings = $res->fetch_assoc();
}

// Handle form submission
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
        if (!empty($settings)) {
            // Update existing row
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
            // Insert first row
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
            // Refresh the form
            $res = $conn->query("SELECT * FROM system_settings WHERE id = 1 LIMIT 1");
            if ($res && $res->num_rows > 0) $settings = $res->fetch_assoc();
        } else {
            $message = "Failed to save: " . htmlspecialchars($upd->error);
            $message_type = 'danger';
        }
        $upd->close();
    }
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
        :root {
            --sidebar-bg: #34445c;
            --sidebar-header: #dee2e6;
            --primary-blue: #1a3066;
            --sidebar-width: 260px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; letter-spacing: 0.5px; }

        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; transition: 0.2s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }

        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        #hamburger { color: #34445c; cursor: pointer; font-size: 1.8rem; }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .adm-card { background: white; border-radius: 20px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select {
            border-radius: 12px; padding: 12px 15px;
            border: 1px solid #e2e8f0; background-color: #f8fafc;
            font-size: 0.9rem; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12);
            border-color: var(--primary-blue);
            background: #fff;
        }
        .btn-primary {
            background: var(--primary-blue); border: none;
            padding: 12px 25px; border-radius: 12px;
            font-weight: 700; transition: 0.3s;
        }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .section-heading {
            border-left: 4px solid #f6b612;
            padding-left: 15px;
            margin-bottom: 20px;
        }
        .section-heading h6 {
            font-weight: 800;
            color: #1e293b;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Logo preview */
        .logo-preview {
            width: 96px; height: 96px;
            border-radius: 16px;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
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

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">System Settings</h2>
        <p class="text-muted small mb-0">Configure school-wide information used across the entire portal</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card adm-card p-4 p-md-5">

        <!-- Section: School Identity -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="section-heading">
                    <h6>School Identity</h6>
                </div>
            </div>

            <div class="col-md-8">
                <label class="form-label">School Name</label>
                <input type="text" name="school_name" class="form-control"
                       value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>"
                       placeholder="e.g. The Michaelson School" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-select">
                    <?php $cur = $settings['currency'] ?? 'KES'; ?>
                    <option value="KES" <?php echo $cur === 'KES' ? 'selected' : ''; ?>>KES (Kenyan Shilling)</option>
                    <option value="USD" <?php echo $cur === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                    <option value="EUR" <?php echo $cur === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                    <option value="GBP" <?php echo $cur === 'GBP' ? 'selected' : ''; ?>>GBP (Pound)</option>
                    <option value="UGX" <?php echo $cur === 'UGX' ? 'selected' : ''; ?>>UGX (Ugandan Shilling)</option>
                    <option value="TZS" <?php echo $cur === 'TZS' ? 'selected' : ''; ?>>TZS (Tanzanian Shilling)</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Motto / Tagline</label>
                <input type="text" name="motto" class="form-control"
                       value="<?php echo htmlspecialchars($settings['motto'] ?? ''); ?>"
                       placeholder="e.g. Higher Is The Goal">
            </div>

            <div class="col-md-4">
                <label class="form-label">Logo</label>
                <div class="logo-preview mb-2">
                    <img src="../<?php echo htmlspecialchars($settings['logo_path'] ?? 'images/logo.jpg'); ?>"
                         alt="School Logo" onerror="this.style.display='none'">
                </div>
                <input type="text" name="logo_path" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($settings['logo_path'] ?? 'images/logo.jpg'); ?>"
                       placeholder="images/logo.jpg">
                <small class="text-muted">Path relative to the project root.</small>
            </div>
        </div>

        <!-- Section: Contact Information -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="section-heading">
                    <h6>Contact Information</h6>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Email</label>
                <input type="email" name="school_email" class="form-control"
                       value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>"
                       placeholder="info@school.ac.ke" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>"
                       placeholder="+254 700 000 000">
            </div>

            <div class="col-12">
                <label class="form-label">Physical Address</label>
                <input type="text" name="address" class="form-control"
                       value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>"
                       placeholder="e.g. P.O. Box 1234, Nairobi, Kenya">
            </div>

            <div class="col-12">
                <label class="form-label">Website (optional)</label>
                <input type="text" name="website" class="form-control"
                       value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>"
                       placeholder="https://school.ac.ke">
            </div>
        </div>

        <!-- Section: Academic Settings -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="section-heading">
                    <h6>Academic Settings</h6>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Academic Year</label>
                <input type="text" name="academic_year" class="form-control"
                       value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>"
                       placeholder="e.g. 2025/2026">
            </div>

            <div class="col-md-6">
                <label class="form-label">Current Term</label>
                <select name="current_term" class="form-select">
                    <?php $term = $settings['current_term'] ?? 'Term 1'; ?>
                    <option value="Term 1" <?php echo $term === 'Term 1' ? 'selected' : ''; ?>>Term 1</option>
                    <option value="Term 2" <?php echo $term === 'Term 2' ? 'selected' : ''; ?>>Term 2</option>
                    <option value="Term 3" <?php echo $term === 'Term 3' ? 'selected' : ''; ?>>Term 3</option>
                </select>
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-between align-items-center pt-4 border-top flex-wrap gap-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Changes apply instantly across the whole portal.
            </small>
            <div class="d-flex gap-2">
                <button type="reset" class="btn btn-light border rounded-pill px-4">Discard</button>
                <button type="submit" name="save_settings" class="btn btn-primary rounded-pill px-5">
                    <i class="bi bi-save me-1"></i>Save Configuration
                </button>
            </div>
        </div>

    </form>

</main>

<?php include("footer.php"); ?>
</body>
</html>
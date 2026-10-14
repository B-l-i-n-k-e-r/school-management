<?php
/**
 * Student Management System - System Settings
 * -------------------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

include('../database/db_connect.php');

// 1. Authentication Check
if (!isset($_SESSION['adminUser'])) {
    header("Location: adminLogin.php");
    exit();
}

// 2. Fetch Existing Settings
$query = $conn->query("SELECT * FROM system_settings WHERE id = 1");
$settings = $query->fetch_assoc();

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = $_POST['school_name'];
    $school_email = $_POST['school_email'];
    $academic_year = $_POST['academic_year'];
    $current_term = $_POST['current_term']; // Changed from semester

    if ($settings) {
        $update = $conn->prepare("UPDATE system_settings SET school_name = ?, school_email = ?, academic_year = ?, current_term = ? WHERE id = 1");
        $update->bind_param("ssss", $school_name, $school_email, $academic_year, $current_term);
    } else {
        $update = $conn->prepare("INSERT INTO system_settings (school_name, school_email, academic_year, current_term, id) VALUES (?, ?, ?, ?, 1)");
        $update->bind_param("ssss", $school_name, $school_email, $academic_year, $current_term);
    }
    
    if ($update->execute()) {
        header("Location: settings.php?msg=success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | SCEM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f4f7f6; padding-top: 30px; }
        .settings-card { max-width: 800px; margin: auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container">
    <div class="card settings-card">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary"><i class="bi bi-sliders"></i> Global System Settings</h5>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">Back to Dashboard</a>
        </div>
        
        <div class="card-body p-4">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">Settings updated successfully!</div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-12 mb-2">
                        <label class="form-label fw-bold">School Name</label>
                        <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Contact Email</label>
                        <input type="email" name="school_email" class="form-control" value="<?php echo htmlspecialchars($settings['school_email'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Academic Year</label>
                        <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2025/2026" value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Current Term</label>
                        <select name="current_term" class="form-select">
                            <?php 
                                // Safe way to check value even if $settings is null
                                $active_term = $settings['current_term'] ?? ''; 
                            ?>
                            <option value="Term 1" <?php echo ($active_term == 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                            <option value="Term 2" <?php echo ($active_term == 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                            <option value="Term 3" <?php echo ($active_term == 'Term 3') ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                        <button type="reset" class="btn btn-light me-2">Discard Changes</button>
                        <button type="submit" class="btn btn-primary px-4">Save Configuration</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
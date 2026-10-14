<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

$adm_no = $_SESSION['adm_no'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $email = $_POST['email'];

    $update = $conn->prepare("UPDATE students SET parent_contact = ?, address = ?, email = ? WHERE adm_no = ?");
    $update->bind_param("ssss", $phone, $address, $email, $adm_no);
    
    if ($update->execute()) {
        header("Location: profile.php?msg=updated");
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ?");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile | SCEM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; padding-top: 50px; }
        .edit-container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="edit-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Edit Your Information</h4>
        <a href="dashboard_view.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-house"></i> Back to Home
        </a>
    </div>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-muted small">Full Name (Read Only)</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['full_name']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_contact']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($student['address']); ?></textarea>
        </div>
        <div class="d-flex justify-content-between mt-4">
            <a href="profile.php" class="btn btn-light border">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
        </div>
    </form>
</div>

</body>
</html>
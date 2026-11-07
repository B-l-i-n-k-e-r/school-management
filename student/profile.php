<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include('../database/db_connect.php');

// Session protection
if (!isset($_SESSION['adm_no']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: ../login.php");
    exit();
}

$adm_no = $_SESSION['adm_no'];

$success_msg = '';
$error_msg   = '';

// Handle profile update & photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $email   = trim($_POST['email']   ?? '');
    $dob     = trim($_POST['dob']     ?? '');
    
    // Initialize avatar variable (keeps existing if no new upload)
    $new_avatar_name = null;

    // --- HANDLE IMAGE UPLOAD ---
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        $fileType = $_FILES['avatar']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Allowed file types
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize < 2000000) { // Max 2MB
                // Generate unique name to prevent overwriting
                $new_avatar_name = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = '../images/avatars/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                
                $dest_path = $uploadFileDir . $new_avatar_name;
                
                if(!move_uploaded_file($fileTmpPath, $dest_path)) {
                    $error_msg = 'There was an error moving the uploaded file.';
                    $new_avatar_name = null; // reset so we don't save a broken link
                }
            } else {
                $error_msg = 'File is too large. Max size is 2MB.';
            }
        } else {
            $error_msg = 'Upload failed. Allowed formats: ' . implode(',', $allowedfileExtensions);
        }
    }

    // Only update DB if there are no upload errors
    if (empty($error_msg)) {
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $error_msg = 'Please enter a valid date of birth.';
        } else {
            // Build query dynamically depending on what changed
            $query = "UPDATE students SET parent_contact = ?, address = ?, email = ?";
            $params = [$phone, $address, $email];
            $types = "sss";

            if ($dob !== '') {
                $query .= ", dob = ?";
                $params[] = $dob;
                $types .= "s";
            }

            if ($new_avatar_name !== null) {
                $query .= ", avatar = ?";
                $params[] = $new_avatar_name;
                $types .= "s";
            }

            $query .= " WHERE adm_no = ?";
            $params[] = $adm_no;
            $types .= "s";

            $update = $conn->prepare($query);
            $update->bind_param($types, ...$params);

            if ($update->execute()) {
                header("Location: profile.php?msg=updated");
                exit();
            } else {
                $error_msg = 'Failed to update profile. Please try again.';
            }
            $update->close();
        }
    }
}

// Success flash from redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $success_msg = 'Your profile has been updated successfully.';
}

// Fetch student record
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student record not found. Please contact administration.");
}

// Format DOB for the date input (must be YYYY-MM-DD)
$dob_value = '';
if (!empty($student['dob'])) {
    $dob_value = date('Y-m-d', strtotime($student['dob']));
}

$avatar_path = "../images/avatars/" . ($student['avatar'] ?? '');
$has_avatar = !empty($student['avatar']) && file_exists($avatar_path);

$student_display_name = $student['full_name'] ?? 'Student';
$active_page = 'profile';
$extra_label = 'Edit Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | <?php echo htmlspecialchars($student_display_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #34445c;
            --submenu-bg: #2c3a4d;
            --primary-blue: #1a3066;
            --sidebar-width: 240px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin:0; overflow-x: hidden; transition: all 0.3s; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 1200; transition: all 0.3s ease; scrollbar-width: thin; scrollbar-color: #4b5a71 var(--sidebar-bg); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4b5a71; border-radius: 10px; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }

        .sidebar-header { background: #dee2e6; padding: 10px 15px; display: flex; align-items: center; gap: 10px; height: 70px; }
        .sidebar-header img { height: 40px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 0.85rem; }

        .nav-item { border-bottom: 1px solid #4a5568; }
        .nav-link { color: #cbd5e0; padding: 12px 20px; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; text-decoration: none; transition: 0.3s; cursor: pointer; }
        .nav-link:hover, .nav-link.active { background: #4a5568; color: white; }
        .submenu { background: var(--submenu-bg); display: none; list-style: none; padding: 0; margin: 0; }
        .submenu li a { color: #fff; padding: 10px 20px 10px 45px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { background: #3e4d63; color: #f6e05e; }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 25px; border-bottom: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .user-dropdown { background: var(--primary-blue); color: white; padding: 6px 15px; font-size: 0.8rem; cursor: pointer; border: none; border-radius: 6px; font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: all 0.3s ease; }

        .edit-card { background: white; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); padding: 40px; max-width: 700px; margin: 0 auto; }
        
        .form-label { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .form-control { border-radius: 12px; padding: 12px 16px; border: 1px solid #cbd5e1; font-size: 0.95rem; background: #f8fafc; transition: all 0.2s; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 4px rgba(26,48,102,0.1); background: #fff; }
        .form-control[readonly] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-color: #e2e8f0; }

        /* Modern Avatar Upload */
        .avatar-upload-container { display: flex; align-items: center; gap: 25px; margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e2e8f0; }
        
        .avatar-preview { 
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover; 
            border: 4px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            background: #f1f5f9; display: flex; align-items: center; justify-content: center;
        }
        .avatar-preview i { font-size: 3rem; color: #cbd5e1; }
        
        .avatar-info h5 { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; color: #1e293b; }
        .avatar-info p { font-size: 0.85rem; color: #64748b; margin-bottom: 12px; }
        
        .btn-upload { position: relative; overflow: hidden; display: inline-block; }
        .btn-upload input[type=file] { position: absolute; font-size: 100px; right: 0; top: 0; opacity: 0; cursor: pointer; }

        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 992px) {
            .sidebar { left: calc(-1 * var(--sidebar-width)); }
            .sidebar.active { left: 0 !important; }
            .topbar, .main-content { margin-left: 0 !important; }
            .main-content { padding: 20px; }
            .avatar-upload-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="mb-4 text-center">
        <h2 class="fw-bold mb-1 text-dark">Account Settings</h2>
        <p class="text-muted small mb-0">Update your personal information and profile photo</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 d-flex align-items-center mb-4 mx-auto" style="max-width: 700px;">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span class="fw-medium"><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 d-flex align-items-center mb-4 mx-auto" style="max-width: 700px;">
            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
            <span class="fw-medium"><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>

    <div class="edit-card">
        <form method="POST" action="profile.php" enctype="multipart/form-data">

            <!-- Avatar Upload Section -->
            <div class="avatar-upload-container">
                <?php if ($has_avatar): ?>
                    <img src="<?php echo $avatar_path; ?>" alt="Profile Photo" class="avatar-preview" id="avatarPreview">
                <?php else: ?>
                    <div class="avatar-preview" id="avatarPreview">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>
                
                <div class="avatar-info">
                    <h5>Profile Photo</h5>
                    <p>Upload a new avatar. Max size: 2MB (JPG, PNG).</p>
                    <div class="btn btn-outline-primary btn-upload rounded-pill px-4 fw-bold">
                        <i class="bi bi-camera-fill me-2"></i> Choose Photo
                        <input type="file" name="avatar" id="avatarInput" accept="image/png, image/jpeg, image/jpg">
                    </div>
                </div>
            </div>

            <!-- Personal Info Section -->
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" readonly>
                <small class="text-muted mt-1 d-block">Contact admin to change your legal name.</small>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Admission No</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['adm_no'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($dob_value); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($student['parent_contact'] ?? ''); ?>" placeholder="07..." required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="you@example.com" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Physical Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="e.g. Westlands, Nairobi"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-between mt-4 pt-4 border-top">
                <a href="dashboard_view.php" class="btn btn-light border rounded-pill px-4 fw-bold">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggles
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            } else {
                document.body.classList.toggle('sidebar-toggled');
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    document.querySelectorAll('.dropdown-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const sub = this.nextElementSibling;
            const icon = this.querySelector('.bi-plus, .bi-dash');
            const isClosed = sub.style.display === "none" || sub.style.display === "";
            sub.style.display = isClosed ? "block" : "none";
            if (icon) icon.classList.replace(isClosed ? 'bi-plus' : 'bi-dash', isClosed ? 'bi-dash' : 'bi-plus');
        });
    });

    // LIVE IMAGE PREVIEW
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');

    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // If it's currently a div with an icon, replace it with an img tag
                    if (avatarPreview.tagName === 'DIV') {
                        const newImg = document.createElement('img');
                        newImg.id = 'avatarPreview';
                        newImg.className = 'avatar-preview';
                        newImg.src = e.target.result;
                        avatarPreview.replaceWith(newImg);
                        // Update global reference
                        window.avatarPreview = newImg; 
                    } else {
                        avatarPreview.src = e.target.result;
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    }
</script>
</body>
</html>
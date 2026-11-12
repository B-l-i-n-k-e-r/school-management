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
    
    $new_avatar_name = null;

    // Handle Image Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize < 2000000) { // Max 2MB
                $new_avatar_name = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = '../images/avatars/';
                
                if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
                
                $dest_path = $uploadFileDir . $new_avatar_name;
                if(!move_uploaded_file($fileTmpPath, $dest_path)) {
                    $error_msg = 'There was an error moving the uploaded file.';
                    $new_avatar_name = null;
                }
            } else {
                $error_msg = 'File is too large. Max size is 2MB.';
            }
        } else {
            $error_msg = 'Upload failed. Allowed formats: ' . implode(',', $allowedfileExtensions);
        }
    }

    if (empty($error_msg)) {
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $error_msg = 'Please enter a valid date of birth.';
        } else {
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

if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $success_msg = 'Your profile has been updated successfully.';
}

// Fetch student record
$stmt = $conn->prepare("SELECT * FROM students WHERE adm_no = ? LIMIT 1");
$stmt->bind_param("s", $adm_no);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found. Please contact administration.");

$dob_value = '';
if (!empty($student['dob'])) $dob_value = date('Y-m-d', strtotime($student['dob']));

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
        :root { --primary: #000a66; --primary-soft: #1a2590; --primary-tint: #eef0ff; --accent: #facc15; --accent-tint: #fff6cc; --accent-deep: #8a6a00; --bg-light: #f6f7fb; --white: #ffffff; --ink: #14183a; --text-muted: #5b6280; --line: #e3e6f2; --radius-lg: 24px; }
        
        .panel { background: var(--white); border-radius: var(--radius-lg); padding: 28px; box-shadow: 0 24px 48px -28px rgba(0, 10, 102, 0.22); }
        .ui-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
        .ui-head h1 { margin: 0 0 4px; font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; color: var(--primary); }
        .ui-head p { margin: 0; color: var(--text-muted); font-size: 0.95rem; }
        
        .ui-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border: 1.5px solid transparent; border-radius: 99px; font-family: inherit; font-size: 0.9rem; font-weight: 800; line-height: 1.2; text-decoration: none; cursor: pointer; transition: all 0.2s; }
        .ui-btn:hover { transform: translateY(-1px); }
        .ui-btn.is-primary { background: var(--accent); color: var(--primary); }
        .ui-btn.is-primary:hover { background: #ffd93d; color: var(--primary); box-shadow: 0 12px 20px -10px rgba(250, 204, 21, 0.9); }
        .ui-btn.is-ghost { background: var(--white); border-color: var(--line); color: var(--primary); }
        .ui-btn.is-ghost:hover { background: var(--primary-tint); }

        .ui-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: 16px; margin-bottom: 24px; font-size: 0.95rem; font-weight: 600; line-height: 1.5; }
        .ui-alert i { font-size: 1.15rem; line-height: 1.4; }
        .ui-alert.is-success { background: #e7f7ee; color: #14633a; }
        .ui-alert.is-danger  { background: #fdeaea; color: #a42525; }

        .ui-field label { display: block; margin-bottom: 8px; font-size: 0.88rem; font-weight: 700; color: var(--ink); }
        .ui-input { width: 100%; padding: 13px 16px; border: 1.5px solid var(--line); border-radius: 14px; background-color: var(--bg-light); font-family: inherit; font-size: 0.95rem; font-weight: 500; color: var(--ink); transition: all 0.2s; }
        .ui-input:focus { outline: none; border-color: var(--primary); background-color: var(--white); box-shadow: 0 0 0 4px rgba(0, 10, 102, 0.1); }
        .ui-input[readonly] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-color: #e2e8f0; }

        .edit-card { max-width: 700px; margin: 0 auto; }
        
        .avatar-upload-container { display: flex; align-items: center; gap: 25px; margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid var(--line); }
        .avatar-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid var(--white); box-shadow: 0 4px 10px rgba(0,0,0,0.1); background: var(--primary-tint); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .avatar-preview i { font-size: 3rem; color: var(--primary); }
        .avatar-info h5 { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; color: var(--ink); }
        .avatar-info p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; }
        
        .btn-upload { position: relative; overflow: hidden; display: inline-block; }
        .btn-upload input[type=file] { position: absolute; font-size: 100px; right: 0; top: 0; opacity: 0; cursor: pointer; }

        @media (max-width: 992px) {
            .main-content { padding: 20px; }
            .avatar-upload-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php include('includes/sidebar.php'); ?>
<?php include('includes/topbar.php'); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Account Settings</h1>
            <p>Update your personal information and profile photo</p>
        </div>
    </div>

    <div class="edit-card">
        
        <?php if ($success_msg): ?>
            <div class="ui-alert is-success"><i class="bi bi-check-circle-fill"></i><div><?php echo htmlspecialchars($success_msg); ?></div></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="ui-alert is-danger"><i class="bi bi-exclamation-circle-fill"></i><div><?php echo htmlspecialchars($error_msg); ?></div></div>
        <?php endif; ?>

        <div class="panel">
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
                        <div class="ui-btn is-ghost border border-secondary btn-upload">
                            <i class="bi bi-camera-fill"></i> Choose Photo
                            <input type="file" name="avatar" id="avatarInput" accept="image/png, image/jpeg, image/jpg">
                        </div>
                    </div>
                </div>

                <!-- Personal Info Section -->
                <div class="ui-field mb-3">
                    <label>Full Name</label>
                    <input type="text" class="ui-input" value="<?php echo htmlspecialchars($student['full_name'] ?? ''); ?>" readonly>
                    <small class="text-muted mt-1 d-block">Contact admin to change your legal name.</small>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6 ui-field">
                        <label>Admission No</label>
                        <input type="text" class="ui-input" value="<?php echo htmlspecialchars($student['adm_no'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-6 ui-field">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="ui-input" value="<?php echo htmlspecialchars($dob_value); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="ui-field mb-3">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="ui-input" value="<?php echo htmlspecialchars($student['parent_contact'] ?? ''); ?>" placeholder="07..." required>
                </div>

                <div class="ui-field mb-3">
                    <label>Email Address</label>
                    <input type="email" name="email" class="ui-input" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" placeholder="you@example.com" required>
                </div>

                <div class="ui-field mb-4">
                    <label>Physical Address</label>
                    <textarea name="address" class="ui-input" rows="2" placeholder="e.g. Westlands, Nairobi"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4 pt-4 border-top">
                    <a href="dashboard_view.php" class="ui-btn is-ghost">Cancel</a>
                    <button type="submit" class="ui-btn is-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Submenu toggle logic
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
                    if (avatarPreview.tagName === 'DIV') {
                        const newImg = document.createElement('img');
                        newImg.id = 'avatarPreview';
                        newImg.className = 'avatar-preview';
                        newImg.src = e.target.result;
                        avatarPreview.replaceWith(newImg);
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
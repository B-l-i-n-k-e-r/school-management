<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    if (empty($title) || empty($category) || empty($_FILES['document']['name'])) {
        $message = "Please fill in all fields and select a file.";
        $message_type = 'warning';
    } else {
        $fileName = $_FILES['document']['name'];
        $fileSize = $_FILES['document']['size'];
        $fileTmp  = $_FILES['document']['tmp_name'];
        $fileType = $_FILES['document']['type'];
        
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Allowed file types
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $message = "Invalid file type. Allowed: " . implode(', ', $allowedExtensions);
            $message_type = 'danger';
        } elseif ($fileSize > 10000000) { // 10MB Limit
            $message = "File is too large. Maximum size is 10MB.";
            $message_type = 'danger';
        } else {
            // Create unique name
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadDir = '../uploads/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmp, $destPath)) {
                // Format size for DB
                $formattedSize = round($fileSize / 1024, 1) . ' KB';
                if ($fileSize > 1048576) $formattedSize = round($fileSize / 1048576, 1) . ' MB';
                
                // Insert into DB
                $stmt = $conn->prepare("INSERT INTO downloads (title, category, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $title, $category, $destPath, $fileExtension, $formattedSize);
                
                if ($stmt->execute()) {
                    $message = "Document uploaded successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Database error: " . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $message = "Failed to move the uploaded file.";
                $message_type = 'danger';
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get file path to delete from folder
    $get_file = $conn->prepare("SELECT file_path FROM downloads WHERE id = ?");
    $get_file->bind_param("i", $id);
    $get_file->execute();
    $file_data = $get_file->get_result()->fetch_assoc();
    $get_file->close();
    
    if ($file_data && file_exists($file_data['file_path'])) {
        unlink($file_data['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM downloads WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: downloads.php?msg=deleted");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Document deleted successfully.";
    $message_type = 'success';
}

// Fetch existing files
$files = [];
$res = $conn->query("SELECT * FROM downloads ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $files[] = $row;
}

$active_page = 'downloads';
$admin_display_name = $_SESSION['adminUser'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloads | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-bg: #34445c; --sidebar-header: #dee2e6; --primary-blue: #1a3066; --sidebar-width: 260px; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; margin: 0; overflow-x: hidden; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); color: white; position: fixed; left: 0; top: 0; z-index: 1200; transition: all 0.3s ease; overflow-y: auto; }
        body.sidebar-toggled .sidebar { left: calc(-1 * var(--sidebar-width)); }
        body.sidebar-toggled .topbar, body.sidebar-toggled .main-content { margin-left: 0; }
        .sidebar-header { background: var(--sidebar-header); padding: 12px 20px; display: flex; align-items: center; gap: 12px; height: 70px; }
        .sidebar-header img { height: 45px; width: 45px; object-fit: contain; }
        .sidebar-header span { color: var(--primary-blue); font-weight: 800; font-size: 1rem; }
        .nav-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link { color: #d1d5db; padding: 15px 20px; font-size: 0.95rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: #41516a; color: white; }
        .submenu { background: #2c3a4d; list-style: none; padding: 0; margin: 0; display: none; }
        .submenu li a { color: #cbd5e0; padding: 12px 20px 12px 50px; display: block; text-decoration: none; font-size: 0.85rem; }
        .submenu li a:hover { color: #f6e05e; background: rgba(255,255,255,0.05); }

        .topbar { margin-left: var(--sidebar-width); background: #fff; height: 70px; display: flex; justify-content: space-between; align-items: center; padding: 0 30px; border-bottom: 1px solid #e2e8f0; }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; }

        .card { border: none; border-radius: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px; }
        .form-control, .form-select { border-radius: 12px; padding: 12px 15px; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { box-shadow: 0 0 0 3px rgba(26, 48, 102, 0.12); border-color: var(--primary-blue); background: #fff; }
        .btn-primary { background: var(--primary-blue); border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #0f1f3d; transform: translateY(-2px); }

        .file-icon-box { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: #f1f5f9; }
        
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
    <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1">Document Center</h2>
            <p class="text-muted small mb-0">Upload files that students and staff can download</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> border-0 shadow-sm rounded-4 mb-4">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Upload Form -->
        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-cloud-arrow-up-fill me-2 text-primary"></i>Upload New File</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Document Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. End of Term 1 Exams" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="" disabled selected>Select category...</option>
                            <option value="Academic">Academic / Exams</option>
                            <option value="Forms">School Forms</option>
                            <option value="Fees">Financial Documents</option>
                            <option value="General">General Resources</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Select File</label>
                        <input type="file" name="document" class="form-control" required>
                        <small class="text-muted mt-1 d-block">Max size: 10MB. PDF, DOC, XLS, PPT, ZIP, Images.</small>
                    </div>
                    <button type="submit" name="upload_file" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-2"></i> Upload Document
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Files List -->
        <div class="col-lg-8">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-folder-fill me-2 text-warning"></i>Uploaded Files</h5>
                    <span class="badge rounded-pill bg-light text-dark border"><?php echo count($files); ?> files</span>
                </div>

                <?php if (empty($files)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder-x fs-1 text-muted d-block mb-3"></i>
                        <h6 class="fw-bold text-muted">No files uploaded yet</h6>
                        <p class="text-muted small mb-0">Use the form to upload documents for students.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr class="small text-muted text-uppercase">
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Size</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $f): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="file-icon-box">
                                                    <?php 
                                                        $ext = strtolower($f['file_type']);
                                                        $icon = 'file-earmark-text';
                                                        if($ext == 'pdf') $icon = 'file-earmark-pdf-fill text-danger';
                                                        elseif(in_array($ext, ['doc','docx'])) $icon = 'file-earmark-word-fill text-primary';
                                                        elseif(in_array($ext, ['xls','xlsx'])) $icon = 'file-earmark-excel-fill text-success';
                                                        elseif(in_array($ext, ['zip','rar'])) $icon = 'file-earmark-zip-fill text-warning';
                                                    ?>
                                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($f['title']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($f['file_type']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($f['category']); ?></span></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($f['file_size']); ?></td>
                                        <td class="small text-muted"><?php echo date('d M Y', strtotime($f['created_at'])); ?></td>
                                        <td class="text-end">
                                            <a href="?delete=<?php echo $f['id']; ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Delete this file permanently?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</script>
</body>
</html>
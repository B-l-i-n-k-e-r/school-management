<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once("adminOnly.php");
include('../database/db_connect.php');

$message = '';
$message_type = '';
$categories = [
    'Academic' => 'Academic / Exams',
    'Forms'    => 'School Forms',
    'Fees'     => 'Financial Documents',
    'General'  => 'General Resources',
];

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
                    $message = "Database error: " . htmlspecialchars($stmt->error);
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

// Count per category (for the tabs)
$cat_counts = [];
foreach ($files as $f) {
    $c = $f['category'] ?? 'General';
    $cat_counts[$c] = ($cat_counts[$c] ?? 0) + 1;
}
// categories that exist in the data but not in the list above still get a tab
$tab_cats = $categories;
foreach (array_keys($cat_counts) as $c) {
    if (!isset($tab_cats[$c])) $tab_cats[$c] = $c;
}

// Icon + colour for a file type
function file_style($ext) {
    $ext = strtolower($ext ?? '');
    if ($ext === 'pdf')                          return ['file-earmark-pdf-fill',   '#fdeaea', '#a42525'];
    if (in_array($ext, ['doc', 'docx']))         return ['file-earmark-word-fill',  '#e3f2fd', '#0b5394'];
    if (in_array($ext, ['xls', 'xlsx']))         return ['file-earmark-excel-fill', '#e7f7ee', '#14633a'];
    if (in_array($ext, ['ppt', 'pptx']))         return ['file-earmark-ppt-fill',   '#fff0e0', '#b45309'];
    if (in_array($ext, ['zip', 'rar']))          return ['file-earmark-zip-fill',   '#fff6cc', '#8a6a00'];
    if (in_array($ext, ['jpg', 'jpeg', 'png']))  return ['file-earmark-image-fill', '#f3e8ff', '#6b21a8'];
    return ['file-earmark-text-fill', '#eef0ff', '#000a66'];
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
        .dl-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 22px;
            align-items: start;
        }

        /* ---------- Upload panel ---------- */
        .dl-upload h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 22px;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary);
        }
        .dl-upload h2 i {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--primary);
            color: var(--accent);
        }
        .dl-upload .ui-field { margin-bottom: 18px; }

        .dl-drop {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 26px 18px;
            border: 2px dashed var(--line);
            border-radius: 18px;
            background: var(--bg-light);
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .dl-drop:hover, .dl-drop.is-over { border-color: var(--primary); background: var(--primary-tint); }
        .dl-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .dl-drop i { font-size: 1.8rem; color: var(--primary); }
        .dl-drop strong { font-size: 0.92rem; color: var(--ink); word-break: break-word; }
        .dl-drop small { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
        .dl-drop.has-file { border-style: solid; border-color: var(--primary); background: var(--primary-tint); }
        .dl-drop.is-big { border-color: #b42318; background: #fdeaea; }
        .dl-upload .ui-btn { width: 100%; margin-top: 6px; }

        /* ---------- Category tabs ---------- */
        .fx-tabs {
            display: flex;
            gap: 6px;
            padding: 6px;
            overflow-x: auto;
            background: var(--bg-light);
            border-radius: 99px;
            margin: 0 28px 8px;
            scrollbar-width: thin;
        }
        .fx-tab {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            border: none;
            border-radius: 99px;
            background: transparent;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.86rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .fx-tab:hover { background: var(--white); color: var(--primary); }
        .fx-tab.is-active { background: var(--primary); color: var(--white); }
        .fx-tab .n {
            min-width: 22px;
            padding: 2px 8px;
            border-radius: 99px;
            background: var(--white);
            color: var(--primary);
            font-size: 0.74rem;
            font-weight: 800;
            text-align: center;
        }
        .fx-tab.is-active .n { background: var(--accent); }

        /* ---------- File rows ---------- */
        .dl-file {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 28px;
            border-top: 1px solid var(--line);
            transition: background 0.2s;
        }
        .dl-file:hover { background: var(--primary-tint); }
        .dl-icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            font-size: 1.3rem;
        }
        .dl-info { flex: 1; min-width: 0; }
        .dl-info strong { display: block; font-size: 0.97rem; font-weight: 800; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dl-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 4px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }
        .dl-meta .dot { width: 3px; height: 3px; border-radius: 50%; background: #b6bcd6; }
        .dl-actions { display: flex; gap: 8px; }
        a.ui-icon { text-decoration: none; }
        .dl-none { display: none; text-align: center; padding: 40px 20px; color: var(--text-muted); font-weight: 600; }

        @media (max-width: 1100px) {
            .dl-layout { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .dl-file { padding: 14px 18px; flex-wrap: wrap; }
            .fx-tabs { margin: 0 18px 8px; }
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>
<?php include("includes/navbar.php"); ?>

<main class="main-content">

    <div class="ui-head">
        <div>
            <h1>Document center</h1>
            <p>Upload files that students and staff can download</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="ui-alert is-<?php echo htmlspecialchars($message_type); ?>" role="alert">
            <i class="bi <?php echo ['success' => 'bi-check-circle-fill', 'danger' => 'bi-x-circle-fill', 'warning' => 'bi-exclamation-triangle-fill'][$message_type] ?? 'bi-info-circle-fill'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <div class="dl-layout">

        <!-- Upload Form -->
        <section class="panel dl-upload">
            <h2><i class="bi bi-cloud-arrow-up-fill"></i> Upload new file</h2>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="ui-field">
                    <label for="dl_file">File</label>
                    <label class="dl-drop" id="dropZone">
                        <input type="file" name="document" id="dl_file" required>
                        <i class="bi bi-file-earmark-arrow-up"></i>
                        <strong id="dropName">Choose a file or drop it here</strong>
                        <small id="dropInfo">Max 10MB. PDF, Word, Excel, PowerPoint, ZIP, images.</small>
                    </label>
                </div>
                <div class="ui-field">
                    <label for="dl_title">Document title</label>
                    <input type="text" name="title" id="dl_title" class="form-control ui-input" placeholder="e.g. End of Term 1 Exams" required>
                </div>
                <div class="ui-field">
                    <label for="dl_cat">Category</label>
                    <select name="category" id="dl_cat" class="form-select ui-input" required>
                        <option value="" disabled selected>Select category...</option>
                        <?php foreach ($categories as $val => $label): ?>
                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="upload_file" class="ui-btn is-primary">
                    <i class="bi bi-upload"></i> Upload document
                </button>
            </form>
        </section>

        <!-- Existing Files List -->
        <section class="panel is-flush">
            <div class="ui-panel-head">
                <div class="ui-panel-title">
                    <h2>Uploaded files</h2>
                    <span class="ui-count"><?php echo count($files); ?> file<?php echo count($files) === 1 ? '' : 's'; ?></span>
                </div>
            </div>

            <?php if (empty($files)): ?>
                <div class="ui-empty">
                    <i class="bi bi-folder-x"></i>
                    <p>No files uploaded yet</p>
                    <small>Use the form to upload documents for students.</small>
                </div>
            <?php else: ?>
                <div class="fx-tabs" role="tablist" aria-label="Categories">
                    <button type="button" class="fx-tab is-active" data-cat="all" role="tab">
                        All <span class="n"><?php echo count($files); ?></span>
                    </button>
                    <?php foreach ($tab_cats as $val => $label): ?>
                        <button type="button" class="fx-tab" data-cat="<?php echo htmlspecialchars($val); ?>" role="tab">
                            <?php echo htmlspecialchars($label); ?> <span class="n"><?php echo intval($cat_counts[$val] ?? 0); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div id="fileList">
                    <?php foreach ($files as $f):
                        [$icon, $bg, $fg] = file_style($f['file_type'] ?? '');
                        $cat_label = $tab_cats[$f['category']] ?? $f['category'];
                    ?>
                        <div class="dl-file" data-cat="<?php echo htmlspecialchars($f['category'] ?? ''); ?>">
                            <div class="dl-icon" style="background: <?php echo $bg; ?>; color: <?php echo $fg; ?>;">
                                <i class="bi bi-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="dl-info">
                                <strong title="<?php echo htmlspecialchars($f['title']); ?>"><?php echo htmlspecialchars($f['title']); ?></strong>
                                <div class="dl-meta">
                                    <span class="ui-badge is-navy"><?php echo htmlspecialchars($cat_label); ?></span>
                                    <span><?php echo strtoupper(htmlspecialchars($f['file_type'])); ?></span><span class="dot"></span>
                                    <span><?php echo htmlspecialchars($f['file_size']); ?></span><span class="dot"></span>
                                    <span><?php echo !empty($f['created_at']) ? date('d M Y', strtotime($f['created_at'])) : '—'; ?></span>
                                </div>
                            </div>
                            <div class="dl-actions">
                                <a href="<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" rel="noopener"
                                   class="ui-icon" title="Open file" aria-label="Open <?php echo htmlspecialchars($f['title']); ?>">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                                <a href="?delete=<?php echo intval($f['id']); ?>" class="ui-icon is-danger"
                                   title="Delete file" aria-label="Delete <?php echo htmlspecialchars($f['title']); ?>"
                                   onclick="return confirm('Delete this file permanently?');">
                                    <i class="bi bi-trash3-fill"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="dl-none" id="dlNone">No files in this category yet.</div>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php include("footer.php"); ?>

<script>
    // ---- Category tabs ----
    document.querySelectorAll('.fx-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const cat = tab.dataset.cat;
            document.querySelectorAll('.fx-tab').forEach(t => t.classList.toggle('is-active', t === tab));
            let shown = 0;
            document.querySelectorAll('.dl-file').forEach(row => {
                const show = cat === 'all' || row.dataset.cat === cat;
                row.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            const none = document.getElementById('dlNone');
            if (none) none.style.display = shown === 0 ? 'block' : 'none';
        });
    });

    // ---- File picker: show the chosen file and suggest a title ----
    const fileInput = document.getElementById('dl_file');
    const dropZone  = document.getElementById('dropZone');
    const MAX_BYTES = 10000000;

    function showFile() {
        const f = fileInput.files[0];
        const name = document.getElementById('dropName');
        const info = document.getElementById('dropInfo');
        dropZone.classList.remove('has-file', 'is-big');
        if (!f) {
            name.textContent = 'Choose a file or drop it here';
            info.textContent = 'Max 10MB. PDF, Word, Excel, PowerPoint, ZIP, images.';
            return;
        }
        name.textContent = f.name;
        const mb = (f.size / 1048576).toFixed(1);
        if (f.size > MAX_BYTES) {
            dropZone.classList.add('is-big');
            info.textContent = 'This file is ' + mb + ' MB. The limit is 10 MB.';
        } else {
            dropZone.classList.add('has-file');
            info.textContent = mb + ' MB. Ready to upload.';
        }
        const title = document.getElementById('dl_title');
        if (title.value.trim() === '') {
            title.value = f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
        }
    }
    fileInput.addEventListener('change', showFile);
    ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.add('is-over')));
    ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, () => dropZone.classList.remove('is-over')));

    document.getElementById('uploadForm').addEventListener('submit', e => {
        const f = fileInput.files[0];
        if (f && f.size > MAX_BYTES) {
            e.preventDefault();
            alert('This file is larger than 10 MB. Please choose a smaller one.');
        }
    });
</script>
</body>
</html>
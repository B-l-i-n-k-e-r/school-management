<?php 
include("../includes/header.php");
// 1. Database Connection with Error Handling
$db_path = '../database/db_connect.php';
if (file_exists($db_path)) {
    include($db_path); 
} else {
    die("Database configuration file missing. Please check the 'database' folder.");
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_application'])) {
    
    // Sanitize inputs to prevent SQL Injection
    $student_name = mysqli_real_escape_string($conn, $_POST['student_name']);
    $parent_name = mysqli_real_escape_string($conn, $_POST['parent_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']); // Added Gender
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $form_requested = mysqli_real_escape_string($conn, $_POST['form_requested']);
    $previous_school = isset($_POST['previous_school']) ? mysqli_real_escape_string($conn, $_POST['previous_school']) : "N/A";

    // Check for existing applications or users
    $check_app = mysqli_query($conn, "SELECT id FROM admission_applications WHERE email = '$email' LIMIT 1");
    $check_user = mysqli_query($conn, "SELECT student_id FROM students WHERE email = '$email' LIMIT 1");

    if (mysqli_num_rows($check_app) > 0) {
        $message = "<div class='alert alert-warning border-0 shadow-sm rounded-4 mb-4'>
                        <i class='bi bi-exclamation-triangle-fill me-2'></i> 
                        An application with this email is already being processed.
                    </div>";
    } elseif (mysqli_num_rows($check_user) > 0) {
        $message = "<div class='alert alert-info border-0 shadow-sm rounded-4 mb-4'>
                        <i class='bi bi-info-circle-fill me-2'></i>
                        You are already enrolled. Please <a href='../login.php' class='alert-link'>Login here</a>.
                    </div>";
    } else {
        // Updated SQL to include gender
        $sql = "INSERT INTO admission_applications (student_name, parent_name, email, phone, dob, gender, address, form_requested, previous_school, status) 
                VALUES ('$student_name', '$parent_name', '$email', '$phone', '$dob', '$gender', '$address', '$form_requested', '$previous_school', 'pending')";
        
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success border-0 shadow-sm rounded-4 mb-4'>
                            <i class='bi bi-check-circle-fill me-2'></i> Application submitted successfully!
                        </div>";
        } else {
            $message = "<div class='alert alert-danger border-0 shadow-sm rounded-4 mb-4'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Request | The Michaelson School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
       :root {
             --primary: #000a66;
            --accent: #FFC107;
            --bg: #f8fafc94;
            --white: #ffffff;
            --text: #334155;
            --text-muted: #64748b; 
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            line-height: 1.6;
        }

        
        .card { 
            border: none; 
            border-radius: 24px; 
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-left: 6px solid var(--primary);
            transition: transform 0.3s;
        }

        .form-label {
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select { 
            border-radius: 12px; 
            padding: 12px 16px; 
            border: 1px solid #e2e8f0; 
            transition: all 0.2s;
        }

        .btn-admission { 
            background: var(--primary); 
            color: white;
            border: none; 
            border-radius: 12px; 
            padding: 14px; 
            font-weight: 700; 
            transition: all 0.3s;
        }

        .btn-admission:hover {
            background: #2d99f1;
            transform: translateY(-2px);
            color: white;
        }

        .back-link {
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            margin-bottom: 2rem;
        }

         footer {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }
        
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            
            <div class="card p-4 p-md-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold mb-1">Student Admission Form</h2>
                    <p class="text-muted">Join the The Michaelson School community</p>
                </div>
                
                <?php echo $message; ?>

                <form method="POST" action="">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Student Full Name</label>
                            <input type="text" name="student_name" class="form-control" placeholder="Full Legal Name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>

                        <!-- Added Gender Field -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="" selected disabled>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="+254" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Parent/Guardian Name</label>
                            <input type="text" name="parent_name" class="form-control" placeholder="Guardian Full Name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="example@mail.com" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Physical Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Estate/Town/Street" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Desired Standard</label>
                            <select name="form_requested" class="form-select" required>
                                <option value="" selected disabled>Choose Form...</option>
                                <option value="Form 1">Form 1</option>
                                <option value="Form 2">Form 2</option>
                                <option value="Form 3">Form 3</option>
                                <option value="Form 4">Form 4</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Previous School</label>
                            <input type="text" name="previous_school" class="form-control" placeholder="Last School Attended">
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" name="submit_application" class="btn btn-admission w-100 shadow-sm">
                                <i class="bi bi-send-check-fill me-2"></i> Submit My Application
                            </button>
                            <p class="text-center small text-muted mt-3">
                              <i> By submitting, you agree to <strong> The Michaelson School</strong> admission policies.</i>
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<footer>
    <p>&copy; 2026 The Michaelson School, Kenya. All Rights Reserved.</p>
</footer>
</body>
</html>
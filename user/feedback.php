<?php
include("../includes/header.php");
/**
 * Integrated with PHP Logic to prevent 'Undefined Index' errors
 */

// 1. Handle Form Logic at the top of the file
$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    // Database Connection
    $conn = mysqli_connect("localhost", "root", "", "school_admin_db");

    if (!$conn) {
        $message = "Connection Error: " . mysqli_connect_error();
        $messageClass = "error-msg";
    } else {
        // Sanitize inputs
        $fname = mysqli_real_escape_string($conn, $_POST['fname']);
        $lname = mysqli_real_escape_string($conn, $_POST['lname']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $age = mysqli_real_escape_string($conn, $_POST['age']);
        $city = mysqli_real_escape_string($conn, $_POST['city']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $comments = mysqli_real_escape_string($conn, $_POST['comments']);

        $sql = "INSERT INTO feedback (fname, lname, gender, age, city, phone, email, comments) 
                VALUES ('$fname', '$lname', '$gender', '$age', '$city', '$phone', '$email', '$comments')";

        if (mysqli_query($conn, $sql)) {
            $message = "Thank you! Your feedback has been submitted successfully.";
            $messageClass = "success-msg";
        } else {
            $message = "Error: " . mysqli_error($conn);
            $messageClass = "error-msg";
        }
        mysqli_close($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback | The Michaelson School</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        /* Blue Hero Banner */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../images/school_bg.jpg');
            color: white;
            padding: 60px 20px 100px;
            text-align: center;
        }

        .hero h1 { margin: 0; font-size: 2.5rem; }

        /* Form Card */
        .container {
            max-width: 800px;
            margin: -60px auto 60px;
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        /* Grid Layout for Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width { grid-column: span 2; }

        .form-group { display: flex; flex-direction: column; }

        label {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #4B5563;
            text-transform: uppercase;
        }

        input[type="text"], input[type="email"], textarea {
            padding: 12px 15px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 51, 102, 0.1);
        }

        /* Custom Radio Buttons */
        .radio-options { display: flex; gap: 20px; align-items: center; padding: 10px 0; }

        /* Modern Buttons */
        .btn-group { display: flex; gap: 15px; margin-top: 30px; }

        .btn {
            flex: 1;
            padding: 15px;
            border-radius: 50px; /* Pill Shape */
            border: none;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #F3F4F6; color: #4B5563; }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            opacity: 0.9; 
            background: #2d99f1;
            transition: all 0.3s;
        }

        .btn-secondary:hover { 
            transform: translateY(-2px); 
            opacity: 0.9; 
            background: #e60303;
            color: white;
            transition: all 0.3s;
        }

        /* Message Styling */
        .status-msg { padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 600; }
        .success-msg { background: #D1FAE5; color: #065F46; }
        .error-msg { background: #FEE2E2; color: #991B1B; }

        footer {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
            margin-top: auto;
        }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>
<section class="hero">
    <h1>Feedback</h1>
    <p>We value your experience and suggestions</p>
</section>

<div class="container">
    <?php if($message): ?>
        <div class="status-msg <?php echo $messageClass; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
        <div class="form-grid">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="fname" required placeholder="Enter first name">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lname" placeholder="Enter last name">
            </div>

            <div class="form-group">
                <label>Gender</label>
                <div class="radio-options">
                    <label><input type="radio" name="gender" value="male" checked> Male</label>
                    <label><input type="radio" name="gender" value="female"> Female</label>
                </div>
            </div>
            <div class="form-group">
                <label>Age</label>
                <input type="text" name="age" placeholder="Years">
            </div>

            <div class="form-group">
                <label>City</label>
                <input type="text" name="city">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone">
            </div>

            <div class="form-group full-width">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@mail.com">
            </div>

            <div class="form-group full-width">
                <label>Your Comments</label>
                <textarea name="comments" rows="5" placeholder="Share your thoughts with us..."></textarea>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
            <button type="reset" class="btn btn-secondary">Clear Form</button>
        </div>
    </form>
</div>

<script>
function toggleDropdown() {
    const dropdown = document.getElementById("loginDropdown");
    const btn = document.getElementById("loginBtn");
    dropdown.classList.toggle("show");
    btn.classList.toggle("active");
}

window.onclick = function(event) {
    if (!event.target.closest('.dropdown')) {
        const dropdowns = document.getElementsByClassName("dropdown-content");
        const buttons = document.getElementsByClassName("dropbtn");
        
        for (let i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
        for (let j = 0; j < buttons.length; j++) {
            buttons[j].classList.remove('active');
        }
    }
}
</script>
<footer>
    <p>&copy; 2026 The Michaelson School, Kenya. All Rights Reserved.</p>
</footer>

</body>
</html>
<?php
include('../database/db_connect.php');

if (isset($_POST['save_marks'])) {
    $subject = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $form = mysqli_real_escape_string($conn, $_POST['form']);
    $year = date("Y");
    $term = "Term 1"; 

    foreach ($_POST['student_id'] as $key => $student_id) {
        $s_id = mysqli_real_escape_string($conn, $student_id);
        $cat1 = (int)$_POST['cat1'][$key];
        $cat2 = (int)$_POST['cat2'][$key];
        $exam = (int)$_POST['exam'][$key];
        $total = $cat1 + $cat2 + $exam;

        $grade = 'F';
        if ($total >= 80) $grade = 'A';
        elseif ($total >= 70) $grade = 'B';
        elseif ($total >= 60) $grade = 'C';
        elseif ($total >= 50) $grade = 'D';

        $check = mysqli_query($conn, "SELECT grade_id FROM grades WHERE student_id = '$s_id' AND subject_name = '$subject'");

        if (mysqli_num_rows($check) > 0) {
            $sql = "UPDATE grades SET cat1=$cat1, cat2=$cat2, exam=$exam, total=$total, grade='$grade' 
                    WHERE student_id = '$s_id' AND subject_name = '$subject'";
        } else {
            $sql = "INSERT INTO grades (student_id, subject_name, form, cat1, cat2, exam, total, grade, term, year) 
                    VALUES ('$s_id', '$subject', '$form', $cat1, $cat2, $exam, $total, '$grade', '$term', $year)";
        }
        mysqli_query($conn, $sql);
    }

    header("Location: grade_sheets.php?form=$form&subject=$subject&msg=success");
    exit();
}
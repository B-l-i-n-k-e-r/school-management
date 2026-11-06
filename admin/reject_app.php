<?php
require_once("adminOnly.php");
include('../database/db_connect.php');

if (isset($_GET['id'])) {
    // Sanitize the ID to ensure it is an integer
    $id = intval($_GET['id']);
    
    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE admission_applications SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Redirect back to admission.php with a success flag
        header("Location: admission.php?status=rejected");
        exit();
    } else {
        // Handle database errors
        die("Error updating record: " . $conn->error);
    }
} else {
    // Redirect if accessed without an ID
    header("Location: admission.php");
    exit();
}
?>
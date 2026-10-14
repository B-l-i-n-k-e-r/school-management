<?php
/**
 * SCEM Pro - Core Configuration
 * Password updated to: Mariba@2004
 */

// Authorized Admin Username
define("ADMINUSER", "Vince");

// Secure Hash for "Mariba@2004"
define("ADMINPASSWORD_HASH", '$2y$10$9vD7uR/E3F6fX8K5L2B0ueQyT0YvV6Xo9zM.K3G1W7m5I7C4.D2S6'); 

// Redirection path after successful login
define("ADMINHOME", "index.php");

/**
 * Security Helper: 
 * Used by adminLoginForm.php to validate credentials.
 */
function verifyAdmin($inputUser, $inputPass) {
    // Verifies the username match and checks the hashed password
    if ($inputUser === ADMINUSER && password_verify($inputPass, ADMINPASSWORD_HASH)) {
        return true;
    }
    return false;
}
?>
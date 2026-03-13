<?php
/**
 * FinTrack - Logout
 * Standalone file - tidak load bootstrap atau class apapun
 */

// Mulai session dengan nama yang sama seperti di config
session_name('fintrack_session');
session_start();

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session dari browser
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        '',
        time() - 86400,
        '/',
        '',
        false,
        true
    );
}

// Hancurkan session di server
session_destroy();

// Cegah cache browser
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Redirect ke login
header('Location: index.php');
exit;
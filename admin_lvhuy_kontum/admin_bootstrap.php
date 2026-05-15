<?php
// Central admin authentication guard.
// require_once this as the FIRST line of every admin file that processes
// state-changing actions BEFORE HTML output (so redirects still work).
//
// Guarantees: DB connection, CSRF helpers, session started, admin authenticated.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    if (!headers_sent()) header('Location: /auth/login_admin.php');
    exit;
}

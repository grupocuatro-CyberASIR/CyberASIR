<?php
// auth.php - Protección por sesión
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
// Si vais por HTTPS en producción (duckdns), activa secure:
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  ini_set('session.cookie_secure', '1');
}
ini_set('session.use_strict_mode', '1');

session_name('SIEMSESS');
session_start();

function require_login(): void {
  if (empty($_SESSION['user'])) {
    $next = $_SERVER['REQUEST_URI'] ?? '/siem/';
    header('Location: /siem/login.php?next=' . urlencode($next));
    exit;
  }
}

// CSRF token helper (por si luego lo quieres en forms)
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/config.php';

// Si ya hay sesión, fuera
if (!empty($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

$next = $_GET['next'] ?? 'index.php';
$error = null;

// Rate limit simple por sesión: 8 intentos/5 min
$now = time();
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
// Limpia intentos antiguos (>5 min)
$_SESSION['login_attempts'] = array_values(array_filter($_SESSION['login_attempts'], fn($t) => ($now - $t) < 300));
$blocked = count($_SESSION['login_attempts']) >= 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($blocked) {
    $error = "Demasiados intentos. Espera 5 minutos.";
  } else {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
      $error = "Rellena usuario y contraseña.";
    } else {
      $_SESSION['login_attempts'][] = $now;

      $stmt = $pdo->prepare("SELECT id, username, pass_hash, role FROM siem_users WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      $u = $stmt->fetch();

      if ($u && password_verify($password, $u['pass_hash'])) {
        // Sesión OK
        session_regenerate_id(true);
        $_SESSION['user'] = [
          'id' => $u['id'],
          'username' => $u['username'],
          'role' => $u['role'],
        ];
        // reset intentos
        $_SESSION['login_attempts'] = [];
        header('Location: ' . $next);
        exit;
      } else {
        $error = "Credenciales incorrectas.";
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso · SIEM CYBERASIR</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Exo+2:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --bg-void:      #050a0f;
      --bg-card:      #0c1420;
      --border:       #1a2d45;
      --border-glow:  #1e4a7a;
      --accent-cyan:  #00d4ff;
      --accent-blue:  #1a6fff;
      --text-primary: #e8f4ff;
      --text-dim:     #8ab4d4;
      --bad:          #ff3b5c;
      --font-display: 'Orbitron', monospace;
      --font-body:    'Exo 2', sans-serif;
    }
    
    * { box-sizing: border-box; }
    
    body {
      margin: 0; 
      min-height: 100vh;
      font-family: var(--font-body);
      color: var(--text-primary);
      background: var(--bg-void);
      display: flex; 
      align-items: center; 
      justify-content: center;
      padding: 22px;
      position: relative;
      overflow: hidden;
    }

    /* Efecto Scanline sutil igual que en el index */
    body::before {
        content: '';
        position: fixed; inset: 0;
        background: repeating-linear-gradient(
            0deg,
            transparent,
            transparent 2px,
            rgba(0, 212, 255, 0.015) 2px,
            rgba(0, 212, 255, 0.015) 4px
        );
        pointer-events: none;
        z-index: 1;
    }

    /* Brillo de fondo */
    .bg-glow {
        position: absolute;
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(26,111,255,0.1) 0%, rgba(5,10,15,0) 70%);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 0;
        pointer-events: none;
    }

    .card {
      position: relative;
      z-index: 2;
      width: min(420px, 100%);
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--bg-card);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 212, 255, 0.05);
      padding: 40px 30px;
    }
    
    .card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan));
        border-radius: 8px 8px 0 0;
    }

    .brand {
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        margin-bottom: 30px;
        text-align: center;
    }

    .logo {
      width: 54px; 
      height: 54px; 
      margin-bottom: 15px;
      background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
      clip-path: polygon(50% 0%, 100% 20%, 100% 70%, 50% 100%, 0% 70%, 0% 20%);
      display: flex; 
      align-items: center; 
      justify-content: center;
      font-family: var(--font-display);
      font-size: 24px;
      font-weight: 800;
      color: white;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    h1 {
        font-family: var(--font-display);
        font-size: 1.2rem; 
        letter-spacing: 0.15em;
        margin: 0;
        color: var(--accent-cyan);
        text-transform: uppercase;
    }

    .sub {
        font-size: 0.85rem; 
        color: var(--text-dim); 
        margin-top: 8px;
    }

    label {
        display: block; 
        font-family: var(--font-display);
        font-size: 0.7rem; 
        letter-spacing: 0.1em;
        color: var(--text-dim); 
        margin: 16px 0 8px;
        text-transform: uppercase;
    }

    input {
      width: 100%;
      padding: 12px 16px;
      border-radius: 4px;
      border: 1px solid var(--border);
      background: rgba(8, 14, 22, 0.8);
      color: var(--text-primary);
      font-family: var(--font-body);
      font-size: 0.95rem;
      outline: none;
      transition: all 0.3s;
    }

    input:focus {
        border-color: var(--accent-cyan);
        box-shadow: 0 0 10px rgba(0, 212, 255, 0.15);
    }

    .btn {
      width: 100%;
      margin-top: 24px;
      padding: 14px;
      border-radius: 4px;
      border: none;
      background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
      color: white;
      font-family: var(--font-display);
      font-size: 0.85rem;
      letter-spacing: 0.1em;
      font-weight: 600;
      cursor: pointer;
      text-transform: uppercase;
      transition: all 0.3s ease;
    }

    .btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 212, 255, 0.4);
    }
    
    .btn:disabled {
        background: #2a3d54;
        color: #6a8ba8;
        cursor: not-allowed;
    }

    .err {
      margin-bottom: 20px;
      padding: 12px;
      border-radius: 4px;
      border-left: 4px solid var(--bad);
      background: rgba(255, 59, 92, 0.1);
      color: #ffb3c1;
      font-size: 0.85rem;
      text-align: center;
    }

    .tiny {
        margin-top: 30px; 
        color: #4a6a8a; 
        font-family: var(--font-display);
        font-size: 0.6rem; 
        letter-spacing: 0.1em;
        text-align: center;
        text-transform: uppercase;
    }
  </style>
</head>
<body>
  <div class="bg-glow"></div>
  
  <div class="card">
    <div class="brand">
      <div class="logo">⬡</div>
      <h1>SIEM CYBERASIR</h1>
      <div class="sub">Autenticación requerida para acceso al SOC</div>
    </div>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($blocked): ?>
      <div class="err">Acceso bloqueado por protocolo de seguridad. Reintente en 5 min.</div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      
      <label>Operador / Usuario</label>
      <input name="username" placeholder="Ingrese su credencial" required>

      <label>Clave de Acceso</label>
      <input name="password" type="password" placeholder="••••••••" required>

      <button class="btn" type="submit" <?= $blocked ? 'disabled' : '' ?>>
        <i class="bi bi-box-arrow-in-right"></i> Autorizar Acceso
      </button>
    </form>

    <div class="tiny">SIEM v1.0 &middot; Proyecto de Grado &middot; <?= date('Y') ?></div>
  </div>
</body>
</html>

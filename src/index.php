<?php
// index.php
require_once __DIR__ . '/auth.php'; // Esto ya hace el session_start() con el nombre correcto
require_login();

// ============================================================
//   SIEM Dashboard - Conexión y Consultas SQL REALES
// ============================================================
$host   = 'localhost';
$dbname = 'siem';
$user   = 'siemuser';
$pass   = 'TU_PASS'; // <-- PON AQUÍ TU CONTRASEÑA REAL DE LA BASE DE DATOS

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la Base de Datos: " . $e->getMessage());
}

// --- Consultas Reales ---
$totalAtaques = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$ataquesRoot = $pdo->query("SELECT COUNT(*) FROM logs WHERE username = 'root'")->fetchColumn();
$uniqueIPs = $pdo->query("SELECT COUNT(DISTINCT ip) FROM logs")->fetchColumn();

$topIPs = $pdo->query("SELECT ip, COUNT(*) AS total FROM logs GROUP BY ip ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$topUsers = $pdo->query("SELECT username, COUNT(*) AS total FROM logs GROUP BY username ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$ultimosLogs = $pdo->query("SELECT id, DATE_ADD(event_time, INTERVAL 1 HOUR) as event_time, ip, username, action, message FROM logs ORDER BY event_time DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// --- Arrays para Chart.js ---
$ipLabels  = json_encode(array_column($topIPs, 'ip'));
$ipData    = json_encode(array_column($topIPs, 'total'));
$userLabels = json_encode(array_column($topUsers, 'username'));
$userData   = json_encode(array_column($topUsers, 'total'));

// ============================================================
//   INTEGRACIÓN WAZUH API (MODO SEGURO - ANTI FALLOS)
// ============================================================
$w_user = 'wazuh-wui';
$w_pass = 'gkPWJpBy*P?*aKaRN9?8Ejrxjr7pGHtL'; 
$w_ip = '127.0.0.1';
$w_port = '55000';

$wazuh_status = '<span style="color:var(--accent-red)">Offline</span>';
$wazuh_agents = '-';

if (function_exists('curl_init')) {
    try {
        // 1. Obtener Token (Timeout de 1 segundo para no congelar la web)
        $ch = curl_init("https://$w_ip:$w_port/security/user/authenticate?raw=true");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$w_user:$w_pass");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); 
        $token = @curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status === 200 && !empty($token)) {
            $wazuh_status = '<span style="color:var(--accent-green); text-shadow:0 0 10px rgba(0,255,136,.4);">Online & Activo</span>';

            // 2. Obtener Agentes Activos
            $ch2 = curl_init("https://$w_ip:$w_port/agents?status=active");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 1);
            $res2 = @curl_exec($ch2);
            curl_close($ch2);

            if ($res2) {
                $data2 = json_decode($res2, true);
                $wazuh_agents = $data2['data']['totalItems'] ?? 0;
            }
        }
    } catch (Exception $e) {
        // Silencioso: Si falla, la web no se rompe.
    }
} else {
    $wazuh_status = '<span style="color:var(--accent-orange); font-size:1rem;">Falta php-curl</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="60">

<title>SIEM · Security Operations Center</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛡️</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Share+Tech+Mono&family=Exo+2:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ========================================================
   VARIABLES & BASE
======================================================== */
:root {
    --bg-void:      #050a0f;
    --bg-panel:     #080e16;
    --bg-card:      #0c1420;
    --bg-card-2:    #0f1928;
    --border:       #1a2d45;
    --border-glow:  #1e4a7a;
    --accent-cyan:  #00d4ff;
    --accent-blue:  #1a6fff;
    --accent-green: #00ff88;
    --accent-red:   #ff3b5c;
    --accent-orange:#ff8c00;
    --accent-yellow:#ffd700;
    --accent-purple:#a855f7; 
    --accent-pink:  #ec4899;
    --text-primary: #e8f4ff;
    --text-dim:     #8ab4d4;
    --text-muted:   #4a6a8a;
    --font-display: 'Orbitron', monospace;
    --font-mono:    'Share Tech Mono', monospace;
    --font-body:    'Exo 2', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    background: var(--bg-void);
    color: var(--text-primary);
    font-family: var(--font-body);
    font-size: 14px;
    min-height: 100vh;
    overflow-x: hidden;
}
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
    z-index: 9999;
}
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg-void); }
::-webkit-scrollbar-thumb { background: var(--border-glow); border-radius: 3px; }

/* ========================================================
   HEADER & BOTONES
======================================================== */
.soc-header {
    background: var(--bg-panel);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 0 40px rgba(0, 212, 255, 0.04);
}
.soc-logo {
    font-family: var(--font-display);
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: 0.15em;
    color: var(--accent-cyan);
    display: flex; align-items: center; gap: 10px;
}
.soc-logo .shield-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan));
    clip-path: polygon(50% 0%, 100% 20%, 100% 70%, 50% 100%, 0% 70%, 0% 20%);
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; color: white;
}
.header-meta { font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-dim); text-align: right; }
.live-dot {
    display: inline-block; width: 7px; height: 7px; background: var(--accent-green);
    border-radius: 50%; box-shadow: 0 0 8px var(--accent-green);
    animation: pulse-dot 1.5s infinite; margin-right: 6px; vertical-align: middle;
}
@keyframes pulse-dot { 0%, 100% { opacity: 1; box-shadow: 0 0 8px var(--accent-green); } 50% { opacity: 0.4; box-shadow: 0 0 4px var(--accent-green); } }

.btn-wazuh {
    display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #ffffff;
    text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 0.8rem; margin-left: 20px;
}
.btn-wazuh:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(2, 132, 199, 0.6); color: white; }
.btn-logout { font-family: var(--font-display); font-size: 0.75rem; letter-spacing: 0.1em; padding: 6px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }

/* ========================================================
   MAIN & CARDS
======================================================== */
.soc-main { padding: 2rem; max-width: 1600px; margin: 0 auto; }
.section-label { font-family: var(--font-display); font-size: 0.6rem; letter-spacing: 0.3em; color: #6a9abf; text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

.stat-card {
    background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 1.5rem;
    position: relative; overflow: hidden; transition: border-color 0.3s, transform 0.2s;
}
.stat-card:hover { border-color: var(--border-glow); transform: translateY(-2px); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; }
.stat-card.card-total::before { background: linear-gradient(90deg, var(--accent-blue), var(--accent-cyan)); }
.stat-card.card-root::before { background: linear-gradient(90deg, var(--accent-red), var(--accent-orange)); }
.stat-card.card-wazuh::before { background: linear-gradient(90deg, var(--accent-purple), var(--accent-pink)); }

.stat-card .card-bg-icon { position: absolute; right: -10px; bottom: -10px; font-size: 6rem; opacity: 0.04; pointer-events: none; }
.stat-label { font-family: var(--font-display); font-size: 0.55rem; letter-spacing: 0.25em; color: #8ab4d4; text-transform: uppercase; margin-bottom: 0.75rem; }
.stat-number { font-family: var(--font-display); font-size: 2.8rem; font-weight: 800; line-height: 1; margin-bottom: 0.5rem; }
.stat-card.card-total .stat-number { color: var(--accent-cyan); text-shadow: 0 0 30px rgba(0,212,255,0.4); }
.stat-card.card-root .stat-number { color: var(--accent-red); text-shadow: 0 0 30px rgba(255,59,92,0.4); }
.stat-sub { font-family: var(--font-mono); font-size: 0.7rem; color: #7aaccc; }

.chart-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 1.5rem; }
.chart-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
.chart-title { font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.2em; color: #d0e8ff; text-transform: uppercase; }
.chart-badge { font-family: var(--font-mono); font-size: 0.65rem; padding: 2px 8px; border-radius: 2px; background: rgba(0,212,255,0.08); border: 1px solid rgba(0,212,255,0.2); color: var(--accent-cyan); }
canvas { max-height: 260px; }

/* ========================================================
   LOGS TABLE
======================================================== */
.logs-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: 1.5rem; }
.logs-card .table { --bs-table-bg: transparent; color: var(--text-primary); font-family: var(--font-mono); font-size: 0.85rem; margin: 0; }
.logs-card .table > :not(caption) > * > * { background-color: transparent !important; color: var(--text-primary) !important; border-bottom: 1px solid rgba(26, 45, 69, 0.5); }
.logs-card .table thead th { font-family: var(--font-display); font-size: 0.55rem; letter-spacing: 0.2em; color: #8ab4d4 !important; text-transform: uppercase; border-bottom: 2px solid var(--border) !important; padding: 0.8rem 0.75rem; }
.logs-card .table tbody tr { transition: background 0.2s; }
.logs-card .table tbody tr:hover { background-color: rgba(0, 212, 255, 0.04) !important; }
.logs-card .table td { padding: 0.8rem 0.75rem; vertical-align: middle; border: none; }

.row-root { border-left: 3px solid var(--accent-red) !important; background-color: rgba(255, 59, 92, 0.08) !important; }
.row-root:hover { background-color: rgba(255, 59, 92, 0.12) !important; }
.row-login { border-left: 3px solid var(--accent-orange) !important; background-color: rgba(255, 140, 0, 0.08) !important; }
.row-login:hover { background-color: rgba(255, 140, 0, 0.12) !important; }

.action-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 2px; font-size: 0.65rem; font-family: var(--font-display); letter-spacing: 0.1em; font-weight: 600; white-space: nowrap; }
.action-badge.badge-root { background: rgba(255, 59, 92, 0.15); border: 1px solid rgba(255, 59, 92, 0.5); color: var(--accent-red); text-shadow: 0 0 10px rgba(255, 59, 92, 0.5); }
.action-badge.badge-login { background: rgba(255, 140, 0, 0.12); border: 1px solid rgba(255, 140, 0, 0.4); color: var(--accent-orange); text-shadow: 0 0 10px rgba(255, 140, 0, 0.4); }

.ip-text { color: var(--accent-cyan) !important; font-family: var(--font-mono); font-weight: bold; }
.time-text { color: #8da6c0 !important; font-size: 0.75rem; }
.msg-text { color: #c8dff5 !important; font-weight: 500; font-size: 0.8rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.soc-footer { text-align: center; padding: 1.5rem 2rem; font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-muted); border-top: 1px solid var(--border); margin-top: 1rem; }

/* ========================================================
   ADAPTACIÓN PARA MÓVILES (Responsive)
======================================================== */
@media (max-width: 992px) {
    /* Ajustar el contenedor y los márgenes */
    .soc-main { padding: 1rem; }
    .stat-card, .chart-card, .logs-card { padding: 1rem; margin-bottom: 1rem; }
    
    /* Reducir el tamaño de la fuente para que quepa todo */
    .stat-number { font-size: 2rem; }
    .soc-logo { font-size: 0.9rem; }
    
    /* Reorganizar la cabecera para que los botones no empujen la pantalla */
    .soc-header { padding: 0 1rem; flex-wrap: wrap; height: auto; padding-top: 10px; padding-bottom: 10px; }
    .header-meta { display: none; } /* Ocultamos el reloj en móvil para ahorrar espacio */
    .btn-wazuh, .btn-logout { margin-left: 5px; padding: 4px 8px; font-size: 0.7rem; }
    
    /* Asegurar que la tabla hace scroll horizontal interno sin romper la página */
    .msg-text { max-width: 150px; }
    
    /* Forzar que las gráficas ocupen el ancho correcto */
    canvas { max-width: 100% !important; height: auto !important; }
}

</style>
</head>
<body>
<header class="soc-header">
    <div class="soc-logo">
        <div class="shield-icon">⬡</div>
        <span class="logo-text">SIEM &middot; CYBERASIR</span>
    </div>
    <div style="display: flex; align-items: center;">
        <div class="header-meta">
            <div><span class="live-dot"></span> SISTEMA EN VIVO</div>
            <div id="clock" style="margin-top:2px;"></div>
        </div>
        <a href="https://cyberasir.duckdns.org:5601/app/threat-hunting#/overview" target="_blank" class="btn-wazuh">
         <i class="bi bi-shield-check"></i> Abrir Wazuh
        </a>
        <a href="logout.php" class="btn btn-outline-danger btn-logout">
            <i class="bi bi-box-arrow-right"></i> Salir
        </a>
    </div>
</header>

<main class="soc-main">
    <div class="section-label"><i class="bi bi-activity"></i> HONEYPOT · MÉTRICAS GLOBALES</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card card-total">
                <i class="bi bi-shield-exclamation card-bg-icon"></i>
                <div class="stat-label"><i class="bi bi-database-exclamation"></i> &nbsp;Total de Eventos</div>
                <div class="stat-number"><?= number_format($totalAtaques) ?></div>
                <div class="stat-sub">ataques registrados en base de datos</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card card-root">
                <i class="bi bi-person-fill-lock card-bg-icon"></i>
                <div class="stat-label"><i class="bi bi-person-badge"></i> &nbsp;Ataques a ROOT</div>
                <div class="stat-number"><?= number_format($ataquesRoot) ?></div>
                <div class="stat-sub">intentos al usuario privilegiado</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card" style="--c: var(--accent-yellow);">
                <style>.stat-card.card-ratio::before{background:linear-gradient(90deg,var(--accent-yellow),var(--accent-orange));}</style>
                <i class="bi bi-percent card-bg-icon"></i>
                <div class="stat-label"><i class="bi bi-pie-chart"></i> &nbsp;Ratio Root / Total</div>
                <div class="stat-number" style="color:var(--accent-yellow);text-shadow:0 0 30px rgba(255,215,0,.4);">
                    <?= $totalAtaques > 0 ? number_format(($ataquesRoot / $totalAtaques) * 100, 1) : '0.0' ?>%
                </div>
                <div class="stat-sub">porcentaje orientado a root</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="stat-card" style="">
                <style>.stat-card.card-ip::before{background:linear-gradient(90deg,#00ff88,#00d4ff);}</style>
                <i class="bi bi-globe2 card-bg-icon"></i>
                <div class="stat-label"><i class="bi bi-hdd-network"></i> &nbsp;IPs únicas detectadas</div>
                <div class="stat-number" style="color:var(--accent-green);text-shadow:0 0 30px rgba(0,255,136,.4);"><?= number_format($uniqueIPs) ?></div>
                <div class="stat-sub">orígenes distintos identificados</div>
            </div>
        </div>
    </div>

    <div class="section-label" style="color: var(--accent-purple);"><i class="bi bi-radar"></i> WAZUH SIEM · ESTADO DEL SISTEMA</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card card-wazuh">
                <i class="bi bi-cpu card-bg-icon"></i>
                <div class="stat-label" style="color:#d0e8ff;"><i class="bi bi-hdd-rack"></i> &nbsp;Estado Motor Wazuh</div>
                <div class="stat-number" style="font-size: 2.2rem; margin-top: 10px;"><?= $wazuh_status ?></div>
                <div class="stat-sub" style="color: var(--accent-purple);">Conexión API puerto 55000</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card card-wazuh">
                <i class="bi bi-pc-display card-bg-icon"></i>
                <div class="stat-label" style="color:#d0e8ff;"><i class="bi bi-shield-check"></i> &nbsp;Agentes Activos</div>
                <div class="stat-number" style="color:var(--accent-purple); text-shadow:0 0 30px rgba(168,85,247,.4);"><?= $wazuh_agents ?></div>
                <div class="stat-sub" style="color: var(--accent-purple);">Servidores bajo vigilancia</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card" style="border-color: var(--accent-purple); background: rgba(168,85,247,0.05); cursor: pointer;" onclick="window.open('https://cyberasir.duckdns.org:5601/app/threat-hunting', '_blank')">
                <i class="bi bi-box-arrow-up-right card-bg-icon" style="opacity:0.2; color:var(--accent-purple);"></i>
                <div class="stat-label" style="color:var(--accent-purple);"><i class="bi bi-search"></i> &nbsp;Threat Hunting</div>
                <div class="stat-number" style="font-size: 1.5rem; color:#e8f4ff; margin-top: 15px;">Explorar Alertas</div>
                <div class="stat-sub" style="color:var(--accent-purple);">Abrir dashboard completo de Wazuh</div>
            </div>
        </div>
    </div>

    <div class="section-label"><i class="bi bi-bar-chart-line"></i> ANÁLISIS VISUAL</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-title"><i class="bi bi-hdd-network me-2"></i>Top 5 IPs más activas</span>
                    <span class="chart-badge">BARRA</span>
                </div>
                <canvas id="chartIPs"></canvas>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-title"><i class="bi bi-people me-2"></i>Top 5 Usuarios probados</span>
                    <span class="chart-badge">PIE</span>
                </div>
                <canvas id="chartUsers"></canvas>
            </div>
        </div>
    </div>

    <div class="section-label"><i class="bi bi-terminal"></i> ÚLTIMOS 10 EVENTOS EN TIEMPO REAL</div>
    <div class="logs-card mb-4">
        <div class="table-responsive">
            <table class="table table-borderless">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th><i class="bi bi-clock me-1"></i>Timestamp</th>
                        <th><i class="bi bi-hdd-network me-1"></i>IP Origen</th>
                        <th><i class="bi bi-person me-1"></i>Usuario</th>
                        <th><i class="bi bi-exclamation-triangle me-1"></i>Acción</th>
                        <th><i class="bi bi-chat-text me-1"></i>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimosLogs as $log): ?>
                        <?php
                            $rowClass = '';
                            $badgeClass = 'badge-other';
                            $icon = 'bi-info-circle';

                            if ($log['action'] === 'failed_login_root') {
                                $rowClass  = 'row-root';
                                $badgeClass = 'badge-root';
                                $icon = 'bi-shield-x';
                            } elseif ($log['action'] === 'failed_login') {
                                $rowClass  = 'row-login';
                                $badgeClass = 'badge-login';
                                $icon = 'bi-lock';
                            }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td style="color:#6a9abf;"><?= htmlspecialchars($log['id']) ?></td>
                            <td class="time-text"><?= htmlspecialchars($log['event_time']) ?></td>
                            <td class="ip-text"><?= htmlspecialchars($log['ip']) ?></td>
                            <td><?= htmlspecialchars($log['username']) ?></td>
                            <td>
                                <span class="action-badge <?= $badgeClass ?>">
                                    <i class="bi <?= $icon ?>"></i>
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td class="msg-text" title="<?= htmlspecialchars($log['message']) ?>">
                                <?= htmlspecialchars($log['message']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ultimosLogs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:var(--text-muted);">
                                <i class="bi bi-database-x" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                No hay registros de ataques en la base de datos
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<footer class="soc-footer">
    SIEM v1.0 &mdash; Security Information and Event Management &mdash; Proyecto de Grado &mdash; <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleDateString('es-CO') + '  ' + now.toLocaleTimeString('es-CO');
}
updateClock();
setInterval(updateClock, 1000);

const CYAN   = '#00d4ff', BLUE   = '#1a6fff', GREEN  = '#00ff88', RED    = '#ff3b5c', ORANGE = '#ff8c00';
const PALETTE_PIE = [CYAN, BLUE, GREEN, ORANGE, RED];

const baseOptions = {
    plugins: {
        legend: { labels: { color: '#8ab4d4', font: { family: 'Share Tech Mono', size: 11 }, boxWidth: 12 } },
        tooltip: { backgroundColor: '#0c1420', borderColor: '#1a2d45', borderWidth: 1, titleColor: '#00d4ff', bodyColor: '#ddeeff', titleFont: { family: 'Orbitron', size: 10 }, bodyFont: { family: 'Share Tech Mono', size: 11 } }
    }
};

const ctxIPs = document.getElementById('chartIPs').getContext('2d');
const ipGradient = ctxIPs.createLinearGradient(0, 0, 0, 260);
ipGradient.addColorStop(0,   'rgba(0,212,255,0.8)');
ipGradient.addColorStop(1,   'rgba(26,111,255,0.2)');
new Chart(ctxIPs, {
    type: 'bar',
    data: {
        labels: <?= $ipLabels ?>,
        datasets: [{
            label: 'Eventos',
            data: <?= $ipData ?>,
            backgroundColor: ipGradient,
            borderColor: CYAN,
            borderWidth: 1,
            borderRadius: 3,
            hoverBackgroundColor: 'rgba(0,212,255,0.9)',
        }]
    },
    options: { ...baseOptions, responsive: true, scales: { x: { ticks: { color: '#8ab4d4', font: { family: 'Share Tech Mono', size: 11 } }, grid: { color: 'rgba(26,45,69,0.6)' } }, y: { ticks: { color: '#8ab4d4', font: { family: 'Share Tech Mono', size: 11 } }, grid: { color: 'rgba(26,45,69,0.6)' }, beginAtZero: true } }, plugins: { ...baseOptions.plugins, legend: { display: false } } }
});

const ctxUsers = document.getElementById('chartUsers').getContext('2d');
new Chart(ctxUsers, {
    type: 'pie',
    data: {
        labels: <?= $userLabels ?>,
        datasets: [{
            data: <?= $userData ?>,
            backgroundColor: PALETTE_PIE.map(c => c + 'cc'),
            borderColor: PALETTE_PIE,
            borderWidth: 2,
            hoverBorderWidth: 3,
        }]
    },
    options: { ...baseOptions, responsive: true, plugins: { ...baseOptions.plugins, legend: { ...baseOptions.plugins.legend, position: 'right' } } }
});
</script>
<script>
async function geo() {
  let ips = document.querySelectorAll('.ip-text');
  // 1. SALVAVIDAS: Las IPs que ya conocemos (Cero fallos)
  let dic = {
    '103.217.146.41': 'id,Indonesia',
    '139.59.151.237': 'sg,Singapore',
    '170.64.191.156': 'au,Australia',
    '103.253.147.252': 'vn,Vietnam'
  };

  for (let el of ips) {
    let ip = el.innerText.trim();
    if (!ip || ip.includes('127.')) continue;

    let c='', n='';
    
    // 2. Busca en diccionario manual O en la memoria del navegador
    if (dic[ip]) {
      let p = dic[ip].split(','); c = p[0]; n = p[1];
    } else if (localStorage.getItem('g_'+ip)) {
      let p = localStorage.getItem('g_'+ip).split(','); c = p[0]; n = p[1];
    } else {
      // 3. Solo si es 100% nueva, pregunta a la API
      try {
        let r = await fetch('https://ipwho.is/' + ip);
        let d = await r.json();
        if (d && d.success) {
          c = d.country_code.toLowerCase(); n = d.country;
          localStorage.setItem('g_'+ip, c+','+n);
        }
      } catch(e){}
    }

    // 4. Pinta la bandera redonda
    if (c && n) {
      let img = '<img src="https://hatscripts.github.io/circle-flags/flags/'+c+'.svg" width="16" style="vertical-align:middle;margin-right:5px">';
      el.innerHTML = ip + '<br><span style="font-size:0.75rem;color:#00d4ff">' + img + n + '</span>';
    }
  }
}
setTimeout(geo, 500);
</script>
</body>
</html>

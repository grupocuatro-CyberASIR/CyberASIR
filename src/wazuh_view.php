<?php
// Protegemos la página igual que el index
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wazuh SIEM · Integración</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Share+Tech+Mono&family=Exo+2:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* === MISMO DISEÑO EXACTO QUE TU INDEX === */
:root {
    --bg-void:      #050a0f;
    --bg-panel:     #080e16;
    --border:       #1a2d45;
    --accent-cyan:  #00d4ff;
    --accent-blue:  #1a6fff;
    --accent-green: #00ff88;
    --accent-red:   #ff3b5c;
    --accent-orange:#ff8c00;
    --text-primary: #e8f4ff;
    --text-dim:     #8ab4d4;
    --font-display: 'Orbitron', monospace;
    --font-mono:    'Share Tech Mono', monospace;
    --font-body:    'Exo 2', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--bg-void);
    color: var(--text-primary);
    font-family: var(--font-body);
    font-size: 14px;
    height: 100vh;
    overflow: hidden; /* Quitamos el scroll de la página principal para dárselo al Iframe */
}
body::before {
    content: ''; position: fixed; inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0, 212, 255, 0.015) 2px, rgba(0, 212, 255, 0.015) 4px);
    pointer-events: none; z-index: 9999;
}

/* HEADER EXACTAMENTE IGUAL */
.soc-header { background: var(--bg-panel); border-bottom: 1px solid var(--border); padding: 0 2rem; height: 64px; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 100; }
.soc-logo { font-family: var(--font-display); font-size: 1.1rem; font-weight: 800; letter-spacing: 0.15em; color: var(--accent-cyan); display: flex; align-items: center; gap: 10px; }
.soc-logo .shield-icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--accent-blue), var(--accent-cyan)); clip-path: polygon(50% 0%, 100% 20%, 100% 70%, 50% 100%, 0% 70%, 0% 20%); display: flex; align-items: center; justify-content: center; font-size: 14px; color: white; }
.header-meta { font-family: var(--font-mono); font-size: 0.72rem; color: var(--text-dim); text-align: right; }
.live-dot { display: inline-block; width: 7px; height: 7px; background: var(--accent-green); border-radius: 50%; box-shadow: 0 0 8px var(--accent-green); animation: pulse-dot 1.5s infinite; margin-right: 6px; vertical-align: middle; }

.btn-wazuh { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: linear-gradient(135deg, var(--accent-orange), var(--accent-red)); color: #ffffff; text-decoration: none; font-weight: bold; border-radius: 8px; font-size: 0.8rem; margin-left: 20px; }
.btn-wazuh:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255, 59, 92, 0.4); color: white; }
.btn-logout { font-family: var(--font-display); font-size: 0.75rem; letter-spacing: 0.1em; padding: 6px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; }

/* CSS DEL IFRAME */
.iframe-container { width: 100%; height: calc(100vh - 64px); border: none; display: block; position: relative; z-index: 10; }
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
            <div><span class="live-dot"></span> INTEGRACIÓN WAZUH (5601)</div>
            <div id="clock" style="margin-top:2px;"></div>
        </div>
        <a href="index.php" class="btn-wazuh">
            <i class="bi bi-arrow-left-circle"></i> Volver a Métricas
        </a>
        <a href="logout.php" class="btn btn-outline-danger btn-logout">
            <i class="bi bi-box-arrow-right"></i> Salir
        </a>
    </div>
</header>

<iframe class="iframe-container" src="https://cyberasir.duckdns.org:5601/app/threat-hunting#/overview" title="Panel de Wazuh"></iframe>

<script>
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleDateString('es-CO') + '  ' + now.toLocaleTimeString('es-CO');
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>

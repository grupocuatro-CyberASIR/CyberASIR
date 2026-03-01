import json
import MySQLdb
import os
import hashlib
from datetime import datetime

DB_HOST = "127.0.0.1"
DB_USER = "siemuser"
DB_PASS = "TU_PASS" 
DB_NAME = "siem"

LOG_FILE = "/opt/cowrie/var/log/cowrie/cowrie.json"

def procesar_logs():
    try:
        db = MySQLdb.connect(host=DB_HOST, user=DB_USER, passwd=DB_PASS, db=DB_NAME)
        cursor = db.cursor()
    except Exception as e:
        print(f"❌ Error DB al conectar: {e}")
        return

    if not os.path.exists(LOG_FILE):
        print(f"❌ No se encontró el archivo {LOG_FILE}")
        return

    print(f"📂 Procesando archivo: {LOG_FILE}")
    nuevos = 0

    with open(LOG_FILE, 'r') as f:
        for linea in f:
            linea_clean = linea.strip()
            if not linea_clean:
                continue
                
            try:
                datos = json.loads(linea_clean)
                event_id = datos.get("eventid")

                if event_id in ["cowrie.login.failed", "cowrie.login.success"]:
                    ip = datos.get("src_ip", "0.0.0.0")
                    user = datos.get("username", "unknown")
                    pwd = datos.get("password", "unknown")
                    ts = datos.get("timestamp", "")[:19]
                    
                    try:
                        event_time = datetime.strptime(ts, "%Y-%m-%dT%H:%M:%S").strftime("%Y-%m-%d %H:%M:%S")
                    except:
                        event_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

                    if event_id == "cowrie.login.success" and user == "root":
                        action = "success_login_root"
                    elif event_id == "cowrie.login.success":
                        action = "success_login"
                    elif user == "root":
                        action = "failed_login_root"
                    else:
                        action = "failed_login"
                        
                    msg = f"Ataque SSH: usuario '{user}', password '{pwd}'"

                    # Generar el raw_hash (Requisito estricto de tu base de datos)
                    m = hashlib.md5()
                    m.update(linea_clean.encode('utf-8'))
                    raw_hash = m.digest()

                    # INSERT IGNORE salta los duplicados silenciosamente sin fallar
                    sql = """INSERT IGNORE INTO logs (source, event_time, username, ip, action, message, raw_line, raw_hash)
                             VALUES (%s, %s, %s, %s, %s, %s, %s, %s)"""
                    cursor.execute(sql, ("honeypot", event_time, user, ip, action, msg, linea_clean, raw_hash))
                    
                    if cursor.rowcount > 0:
                        nuevos += 1
            except Exception as e:
                print(f"⚠️ Error procesando línea: {e}")
                continue

    db.commit()
    db.close()
    print(f"✅ ¡ÉXITO! {nuevos} ataques cargados en la web.")

if __name__ == "__main__":
    procesar_logs()

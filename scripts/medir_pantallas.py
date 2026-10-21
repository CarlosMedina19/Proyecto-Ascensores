"""Mide el ajuste a la pantalla del panel web y guarda capturas (Fase 5).

Uso:
    pip install websocket-client
    set SIGMA_CORREO=... y SIGMA_CLAVE=...   (cuenta con permiso resumen.ver)
    python scripts/medir_pantallas.py <carpeta_salida> [claro|oscuro]

Abre Edge sin interfaz por el protocolo DevTools, fotografía el inicio de sesión y el panel en cada tamaño e
informa el desplazamiento vertical/horizontal y dónde termina la última tarjeta de indicadores.
"""
import base64
import json
import os
import subprocess
import sys
import tempfile
import time
import urllib.request

import websocket

EDGE = os.environ.get("EDGE", r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")
PUERTO = 9333
WEB = os.environ.get("SIGMA_WEB", "http://localhost:5173")
API = os.environ.get("SIGMA_API", "http://localhost:8000/api/v1")
TAMANOS = [(1280, 720), (1366, 768), (1440, 900), (1920, 1080), (1280, 650), (390, 844)]
MEDIR = """JSON.stringify({
  sv: document.documentElement.scrollHeight - innerHeight,
  sh: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
  kpi: Math.round(Math.max(0, ...[...document.querySelectorAll('.indicador')].map(e => e.getBoundingClientRect().bottom))),
  enc: Math.round(document.querySelector('.encabezado')?.getBoundingClientRect().height || 0),
})"""


class Pestana:
    def __init__(self, url_ws):
        self.ws = websocket.create_connection(url_ws, timeout=60)
        self.n = 0

    def enviar(self, metodo, **params):
        self.n += 1
        self.ws.send(json.dumps({"id": self.n, "method": metodo, "params": params}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == self.n:
                return msg.get("result", {})

    def js(self, codigo):
        return self.enviar("Runtime.evaluate", expression=codigo, returnByValue=True, awaitPromise=True).get("result", {}).get("value")


def iniciar_sesion():
    cuerpo = json.dumps({"correo": os.environ["SIGMA_CORREO"], "password": os.environ["SIGMA_CLAVE"]}).encode()
    req = urllib.request.Request(API + "/iniciar-sesion", data=cuerpo, method="POST",
                                 headers={"Content-Type": "application/json", "Accept": "application/json"})
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read())["token_acceso"]


def main():
    salida = sys.argv[1]
    tema = sys.argv[2] if len(sys.argv) > 2 else "claro"
    os.makedirs(salida, exist_ok=True)
    proceso = subprocess.Popen([EDGE, "--headless=new", "--disable-gpu", f"--remote-debugging-port={PUERTO}",
                                f"--remote-allow-origins=http://127.0.0.1:{PUERTO}", f"--user-data-dir={tempfile.mkdtemp()}",
                                "about:blank"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(120):
            try:
                pestanas = json.loads(urllib.request.urlopen(f"http://127.0.0.1:{PUERTO}/json").read())
                pagina = next(p for p in pestanas if p["type"] == "page")
                break
            except Exception:
                time.sleep(0.5)
        p = Pestana(pagina["webSocketDebuggerUrl"])
        p.enviar("Page.enable")
        p.enviar("Page.navigate", url=WEB + "/iniciar-sesion")
        time.sleep(8)
        p.js(f"sessionStorage.clear(); localStorage.setItem('sigma.tema', '{tema}');")
        for ruta, con_sesion in [("/iniciar-sesion", False), ("/inicio", True)]:
            if con_sesion:
                p.js(f"sessionStorage.setItem('sigma.token', '{iniciar_sesion()}');")
            for ancho, alto in TAMANOS:
                p.enviar("Emulation.setDeviceMetricsOverride", width=ancho, height=alto, deviceScaleFactor=1, mobile=ancho < 500)
                p.enviar("Page.navigate", url=WEB + ruta)
                time.sleep(7 if con_sesion else 4)
                m = json.loads(p.js(MEDIR))
                nombre = f"{ruta.strip('/')}_{ancho}x{alto}_{tema}.png"
                with open(os.path.join(salida, nombre), "wb") as f:
                    f.write(base64.b64decode(p.enviar("Page.captureScreenshot", format="png")["data"]))
                extra = f"  fin KPI {m['kpi']:>4}px  encabezado {m['enc']:>3}px" if con_sesion else ""
                print(f"{ruta:16} {ancho}x{alto}: desplazamiento vertical {m['sv']:>5}px  horizontal {m['sh']:>3}px{extra}")
    finally:
        proceso.kill()


if __name__ == "__main__":
    main()

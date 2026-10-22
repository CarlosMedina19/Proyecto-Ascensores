"""Genera los íconos de la app (Android y web) a partir del logo del sitio: frontend/public/sigma-completo.svg.

Uso (con el panel corriendo en http://localhost:5173 para que cargue la fuente Michroma):
    pip install websocket-client
    python app/flutter_application_1/scripts/generar_iconos.py

Abre Edge sin interfaz, dibuja el SVG en cada tamaño con fondo transparente y guarda los PNG.
"""
import base64
import json
import os
import subprocess
import tempfile
import time
import urllib.request

import websocket

EDGE = os.environ.get("EDGE", r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe")
PUERTO = 9335
# Carpeta de la app (este script está en app/flutter_application_1/scripts).
APP = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
WEB = os.environ.get("SIGMA_WEB", "http://localhost:5173")

DESTINOS = {
    48: ["android/app/src/main/res/mipmap-mdpi/ic_launcher.png"],
    72: ["android/app/src/main/res/mipmap-hdpi/ic_launcher.png"],
    96: ["android/app/src/main/res/mipmap-xhdpi/ic_launcher.png"],
    144: ["android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png"],
    192: ["android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png", "web/icons/Icon-192.png", "web/icons/Icon-maskable-192.png"],
    512: ["web/icons/Icon-512.png", "web/icons/Icon-maskable-512.png"],
}
# A 32 px se usa la versión simplificada del logo (la misma del favicon del sitio).
REDUCIDOS = {32: ["web/favicon.png"]}


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


def main():
    proceso = subprocess.Popen([EDGE, "--headless=new", "--disable-gpu", f"--remote-debugging-port={PUERTO}",
                                f"--remote-allow-origins=http://127.0.0.1:{PUERTO}", f"--user-data-dir={tempfile.mkdtemp()}",
                                "about:blank"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(120):
            try:
                pagina = next(p for p in json.loads(urllib.request.urlopen(f"http://127.0.0.1:{PUERTO}/json").read()) if p["type"] == "page")
                break
            except Exception:
                time.sleep(0.5)
        p = Pestana(pagina["webSocketDebuggerUrl"])
        p.enviar("Page.enable")
        p.enviar("Emulation.setDefaultBackgroundColorOverride", color={"r": 0, "g": 0, "b": 0, "a": 0})
        trabajos = [(t, "/sigma-completo.svg", r) for t, r in DESTINOS.items()] + [(t, "/sigma.svg", r) for t, r in REDUCIDOS.items()]
        for tamano, svg, rutas in trabajos:
            p.enviar("Emulation.setDeviceMetricsOverride", width=tamano, height=tamano, deviceScaleFactor=1, mobile=False)
            # La página del panel carga la fuente Michroma; el SVG se pinta encima ocupando todo el cuadro.
            p.enviar("Page.navigate", url=WEB + svg)
            time.sleep(2)
            imagen = base64.b64decode(p.enviar("Page.captureScreenshot", format="png", clip={"x": 0, "y": 0, "width": tamano, "height": tamano, "scale": 1})["data"])
            for ruta in rutas:
                with open(os.path.join(APP, ruta), "wb") as archivo:
                    archivo.write(imagen)
                print(f"{tamano}px → {ruta}")
    finally:
        proceso.kill()


if __name__ == "__main__":
    main()

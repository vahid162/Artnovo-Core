import base64
import http.client
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time

root = Path(__file__).resolve().parent
output = Path(os.environ.get("ARTNOVO_FIXTURE_OUTPUT_DIR", str(Path(tempfile.gettempdir()) / "artnovo-core-fixtures")))
output.mkdir(mode=0o700, parents=True, exist_ok=True)
php = os.environ.get("ARTNOVO_PHP_BIN", "php")
if not os.environ.get("ARTNOVO_WP_ROOT"):
    raise RuntimeError("Set ARTNOVO_WP_ROOT to the WordPress installation used by the fixture.")
with socket.socket() as reservation:
    reservation.bind(("127.0.0.1", 0))
    port = reservation.getsockname()[1]

with (output / "multipart-server.log").open("wb") as log:
    server = subprocess.Popen(
        [php, "-d", "memory_limit=512M", "-d", "error_reporting=8191", "-S", f"127.0.0.1:{port}", "-t", str(root), str(root / "multipart-router.php")],
        stdout=log,
        stderr=log,
    )
    try:
        for _ in range(50):
            try:
                connection = http.client.HTTPConnection("127.0.0.1", port, timeout=20)
                connection.request("GET", "/?key=wc_order_receipt_qa")
                response = connection.getresponse()
                data = response.read()
                if response.status != 200:
                    raise RuntimeError(f"Fixture failed with HTTP {response.status}: {data[:400]!r}")
                state = json.loads(data)
                assert state["fixture_authorization"] == [True, 2147000081, 2147000081, True, True], state["fixture_authorization"]
                break
            except ConnectionRefusedError:
                time.sleep(0.1)
        else:
            raise RuntimeError("Local PHP fixture did not start.")

        png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jY3sAAAAASUVORK5CYII=")
        for name, image, expected in [
            ("real PNG multipart upload", png, "success"),
            ("invalid image multipart upload", b"fixture is not an image", "error"),
        ]:
            boundary = "ArtnovoReceiptFixtureBoundary"
            fields = {
                "artnovo_received_receipt": "1",
                "artnovo_received_receipt_nonce": state["nonce"],
            }
            body = b""
            for key, value in fields.items():
                body += f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n".encode()
            body += (
                f"--{boundary}\r\nContent-Disposition: form-data; name=\"wpvpn_receipt\"; filename=\"receipt.png\"\r\n"
                "Content-Type: image/png\r\n\r\n"
            ).encode() + image + f"\r\n--{boundary}--\r\n".encode()
            connection = http.client.HTTPConnection("127.0.0.1", port, timeout=20)
            connection.request("POST", "/?key=wc_order_receipt_qa", body, {"Content-Type": f"multipart/form-data; boundary={boundary}"})
            response = connection.getresponse()
            result = response.read()
            assert response.status == 303, (name, response.status, result[:400])
            assert response.getheader("Location") == state["return"], (name, response.getheader("Location"))
            assert response.getheader("X-ArtNovo-Test-Uploaded") == "1", name
            assert response.getheader("X-ArtNovo-Test-Notice") == expected, (name, result[:400])
            assert response.getheader("X-ArtNovo-Test-Saved") == ("1" if expected == "success" else "0"), name
            print("PASS", name, "uses native validation and returns to the same order with HTTP303")
    finally:
        server.terminate()
        try:
            server.wait(timeout=3)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=3)

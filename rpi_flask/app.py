import time
import requests
import json
from flask import Flask, Response, jsonify, request

app = Flask(__name__)

# Configuration
CONFIG = {
    "server_url": "http://your-server-ip/api",
    "hardware_key": "ss_hw_api_key_2052",
    "device_name": "RPi-Face-Node",
    "threshold": 0.55
}

@app.route('/api/status', methods=['GET'])
def get_status():
    return jsonify({
        "status": "online",
        "device": CONFIG["device_name"],
        "uptime": time.time()
    })

@app.route('/api/face-status', methods=['GET'])
def get_face_status():
    # Mock face detection logic
    return jsonify({
        "face_detected": False,
        "student_no": None,
        "student_name": None
    })

@app.route('/api/command', methods=['POST'])
def handle_command():
    data = request.json
    command = data.get('command')
    return jsonify({"status": "ok", "message": f"Executed: {command}"})

@app.route('/stream')
def video_stream():
    return "MJPEG Stream Proxy (Mock)"

def send_heartbeat():
    while True:
        try:
            requests.get(
                f"{CONFIG['server_url']}/heartbeat.php",
                params={
                    "device": "rpi",
                    "name": CONFIG["device_name"],
                    "hardware_key": CONFIG["hardware_key"]
                },
                timeout=5
            )
        except Exception as e:
            print(f"Heartbeat failed: {e}")
        time.sleep(30)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

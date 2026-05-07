/**
 * Web-Based Face Recognition System
 * UOP AMS
 */

function escapeHtml(unsafe) {
    if (!unsafe) return '-';
    return unsafe.toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Local and CDN model URLs
const LOCAL_MODEL_URL = './weights';
const CDN_MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';

let isModelsLoaded = false;
let faceStream = null;
let attendanceInterval = null;
let allDescriptors = [];
let faceMatcher = null;

async function loadFaceModels() {
    if (isModelsLoaded) return;
    // Try local first
    try {
        console.log("Loading Face API models from local path:", LOCAL_MODEL_URL);
        await faceapi.nets.ssdMobilenetv1.loadFromUri(LOCAL_MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(LOCAL_MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(LOCAL_MODEL_URL);
        isModelsLoaded = true;
        console.log("Face API models loaded successfully (local).");
        return;
    } catch (e) {
        console.warn("Local models failed, trying CDN fallback...", e);
    }

    // Then try CDN
    try {
        console.log("Loading Face API models from CDN...", CDN_MODEL_URL);
        await faceapi.nets.ssdMobilenetv1.loadFromUri(CDN_MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(CDN_MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(CDN_MODEL_URL);
        isModelsLoaded = true;
        console.log("Face API models loaded successfully (CDN).");
        return;
    } catch (e) {
        console.error("Failed to load Face API models from CDN:", e);
        alert("Failed to load Face Recognition models. Check your internet connection.");
    }
}

async function getCameras() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        return devices.filter(device => device.kind === 'videoinput');
    } catch (e) {
        console.error("Error getting cameras", e);
        return [];
    }
}

async function startCamera(videoElementId, deviceId = null) {
    const video = document.getElementById(videoElementId);
    if (!video) return null;

    const constraints = {
        video: deviceId ? { deviceId: { exact: deviceId } } : true,
        audio: false
    };

    try {
        faceStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = faceStream;
        return new Promise((resolve) => {
            video.onloadedmetadata = () => {
                video.play();
                resolve(video);
            };
        });
    } catch (err) {
        console.error("Camera error:", err);
        alert("Camera access denied or no camera found.");
        return null;
    }
}

function stopCamera(videoElementId) {
    const video = document.getElementById(videoElementId);
    if (faceStream) {
        faceStream.getTracks().forEach(track => track.stop());
        faceStream = null;
    }
    if (video) video.srcObject = null;
    if (attendanceInterval) {
        clearInterval(attendanceInterval);
        attendanceInterval = null;
    }
}

// ─── ENROLLMENT LOGIC ──────────────────────────────────────────────

window.startWebFaceEnrollment = async function (studentNo, studentName, captureCount = 7) {
    if (!studentNo) {
        alert("Please select a student and ensure Student No is filled.");
        return;
    }

    const container = document.getElementById('web-enroll-container');
    const video = document.getElementById('web-enroll-video');
    const btnStart = document.getElementById('btn-start-enroll');
    const btnCapture = document.getElementById('btn-capture-enroll');
    const btnCancel = document.getElementById('btn-cancel-enroll');
    const statusText = document.getElementById('web-enroll-status');

    if (!container || !video) return;

    container.classList.remove('d-none');
    btnStart.classList.add('d-none');
    statusText.innerText = "Loading Models...";

    await loadFaceModels();

    // Camera Selection Setup
    const cameras = await getCameras();
    const cameraSelect = document.getElementById('enroll-camera-select');
    if (cameras.length > 0 && cameraSelect) {
        cameraSelect.innerHTML = cameras.map((c, i) => `<option value="${c.deviceId}">${c.label || 'Camera ' + (i + 1)}</option>`).join('');
        cameraSelect.classList.remove('d-none');
    }

    statusText.innerText = "Starting Camera...";
    const initialDevice = cameraSelect && !cameraSelect.classList.contains('d-none') ? cameraSelect.value : null;
    await startCamera('web-enroll-video', initialDevice);

    statusText.innerHTML = `<span class="text-primary"><i class="bi bi-camera-video me-1"></i>Camera Active. Please face the camera and click Capture.</span>`;
    btnCapture.classList.remove('d-none');
    btnCancel.classList.remove('d-none');

    btnCapture.onclick = async () => {
        statusText.innerHTML = `<span class="text-primary fw-bold"><i class="bi bi-camera-video me-1"></i>Capturing 5 angles for high accuracy. Please slowly move your head...</span>`;
        btnCapture.disabled = true;

        const options = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 });
        const descriptors = [];

        for (let i = 0; i < captureCount; i++) {
            const detection = await faceapi.detectSingleFace(video, options).withFaceLandmarks().withFaceDescriptor();
            if (detection) {
                descriptors.push(Array.from(detection.descriptor));
                statusText.innerHTML = `<span class="text-primary fw-bold">Captured ${descriptors.length}/${captureCount} angles...</span>`;
            } else {
                statusText.innerHTML = `<span class="text-warning fw-bold">No face detected... (${descriptors.length}/${captureCount})</span>`;
            }
            await new Promise(r => setTimeout(r, 600));
        }

        if (descriptors.length === 0) {
            statusText.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Failed to detect any faces. Please try again.</span>`;
            btnCapture.disabled = false;
            return;
        }

        statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Captured ${descriptors.length}/${captureCount} angles! Saving...</span>`;

        const fd = new FormData();
        fd.append('action', 'link_web_face');
        fd.append('student_no', studentNo);
        fd.append('student_name', studentName || 'Unknown');
        fd.append('descriptor', JSON.stringify(descriptors));

        try {
            const res = await fetch('api/student.php', { method: 'POST', body: fd });
            const textData = await res.text();
            let data;
            try {
                data = JSON.parse(textData);
            } catch (jsonErr) {
                console.error("Non-JSON Response:", textData);
                throw new Error("Server returned non-JSON response. Ensure the database columns exist.");
            }

            if (data.status === 'success') {
                statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Successfully Enrolled Web Face!</span>`;
                setTimeout(() => {
                    stopCamera('web-enroll-video');
                    container.classList.add('d-none');
                    btnStart.classList.remove('d-none');
                    btnCapture.classList.add('d-none');
                    btnCancel.classList.add('d-none');
                    btnCapture.disabled = false;
                }, 2000);
            } else {
                statusText.innerHTML = `<span class="text-danger">Failed: ${escapeHtml(data.message)}</span>`;
                btnCapture.disabled = false;
            }
        } catch (e) {
            console.error("Save Error:", e);
            statusText.innerHTML = `<span class="text-danger">Error: ${escapeHtml(e.message || 'Network Error during save.')}</span>`;
            btnCapture.disabled = false;
        }
    };

    btnCancel.onclick = () => {
        stopCamera('web-enroll-video');
        container.classList.add('d-none');
        btnStart.classList.remove('d-none');
        btnCapture.classList.add('d-none');
        btnCancel.classList.add('d-none');
        const cameraSelect = document.getElementById('enroll-camera-select');
        if (cameraSelect) cameraSelect.classList.add('d-none');
    };
};

window.switchEnrollCamera = async function () {
    const cameraSelect = document.getElementById('enroll-camera-select');
    if (cameraSelect && faceStream) {
        stopCamera('web-enroll-video');
        await startCamera('web-enroll-video', cameraSelect.value);
    }
};

window.enrollUploadedImage = async function (fileInput) {
    const studentNo = document.getElementById('enroll-student-no').value;
    if (!studentNo) {
        alert("Please select a student and ensure Student No is filled.");
        fileInput.value = '';
        return;
    }
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const statusText = document.getElementById('web-enroll-status');
    const container = document.getElementById('web-enroll-container');
    const btnStart = document.getElementById('btn-start-enroll');
    const video = document.getElementById('web-enroll-video');

    // Stop camera if running
    stopCamera('web-enroll-video');

    container.classList.remove('d-none');
    btnStart.classList.add('d-none');
    video.classList.add('d-none'); // Hide video for image upload
    document.getElementById('btn-capture-enroll').classList.add('d-none');
    document.getElementById('btn-cancel-enroll').classList.add('d-none');

    statusText.innerHTML = "Processing Image...";

    await loadFaceModels();

    try {
        const img = await faceapi.bufferToImage(file);
        const detection = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();

        if (!detection) {
            statusText.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>No face detected in the image. Please try a clearer photo.</span>`;
            setTimeout(() => {
                container.classList.add('d-none');
                btnStart.classList.remove('d-none');
                video.classList.remove('d-none');
                fileInput.value = '';
                statusText.innerHTML = '';
            }, 3000);
            return;
        }

        statusText.innerHTML = `<span class="text-info"><i class="bi bi-hourglass-split me-1"></i>Face found! Saving to database...</span>`;

        const descriptorArray = Array.from(detection.descriptor);
        const fd = new FormData();
        fd.append('action', 'link_web_face');
        fd.append('student_no', studentNo);
        fd.append('descriptor', JSON.stringify(descriptorArray));

        const res = await fetch('api/student.php', { method: 'POST', body: fd });
        const textData = await res.text();
        const data = JSON.parse(textData);

        if (data.status === 'success') {
            statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Successfully Enrolled via Image!</span>`;
            setTimeout(() => {
                container.classList.add('d-none');
                btnStart.classList.remove('d-none');
                video.classList.remove('d-none');
                fileInput.value = '';
                statusText.innerHTML = '';
            }, 2000);
        } else {
            statusText.innerHTML = `<span class="text-danger">Failed: ${escapeHtml(data.message)}</span>`;
        }
    } catch (e) {
        console.error("Upload Error:", e);
        statusText.innerHTML = `<span class="text-danger">Error: ${escapeHtml(e.message)}</span>`;
    }
};

// ─── BULK IMAGE ENROLLMENT ──────────────────────────────────────────
/**
 * Accepts multiple image files. Filename format:  S-20-123.jpg  OR  S_20_123.png
 * The filename (without extension) is treated as the student number
 * after replacing hyphens/underscores → slashes: S-20-123 → S/20/123
 */
window.bulkEnrollFromImages = async function (fileInput) {
    const files = fileInput.files;
    if (!files || files.length === 0) return;

    const logEl = document.getElementById('bulk-upload-log');
    const progEl = document.getElementById('bulk-upload-progress');
    const progBar = document.getElementById('bulk-upload-bar');
    if (logEl) logEl.textContent = '';

    function log(msg) {
        if (logEl) { logEl.textContent += msg + '\n'; logEl.scrollTop = logEl.scrollHeight; }
        console.log('[BULK]', msg);
    }

    await loadFaceModels();
    log(`▶ Starting bulk enroll for ${files.length} image(s)...`);

    let done = 0, ok = 0, failed = 0;

    for (const file of Array.from(files)) {
        const rawName = file.name.replace(/\.[^/.]+$/, ''); // strip extension
        // Convert S-20-123 or S_20_123 → S/20/123
        const studentNo = rawName.replace(/[-_]/g, '/').toUpperCase();

        log(`[${done + 1}/${files.length}] Processing: ${file.name} → ${studentNo}`);

        try {
            const img = await faceapi.bufferToImage(file);
            const detection = await faceapi.detectSingleFace(img)
                .withFaceLandmarks().withFaceDescriptor();

            if (!detection) {
                log(`  ✗ No face found in ${file.name} — skipped.`);
                failed++;
            } else {
                const descriptor = Array.from(detection.descriptor);
                const fd = new FormData();
                fd.append('action', 'link_web_face');
                fd.append('student_no', studentNo);
                fd.append('descriptor', JSON.stringify([descriptor])); // always wrap in array

                const res = await fetch('api/student.php', { method: 'POST', body: fd });
                const result = await res.json();

                if (result.status === 'success') {
                    log(`  ✔ ${studentNo} enrolled successfully.`);
                    ok++;
                } else {
                    log(`  ✗ ${studentNo} DB error: ${result.message}`);
                    failed++;
                }
            }
        } catch (e) {
            log(`  ✗ ${file.name} exception: ${e.message}`);
            failed++;
        }

        done++;
        if (progBar) progBar.style.width = Math.round((done / files.length) * 100) + '%';
    }

    log(`\n✅ Done. Enrolled: ${ok} | Failed: ${failed}`);
    fileInput.value = '';
};

// ─── ATTENDANCE LOGIC ──────────────────────────────────────────────


async function fetchEnrolledDescriptors() {
    try {
        const fd = new FormData();
        fd.append('action', 'get_all_descriptors');
        const res = await fetch('api/student.php', { method: 'POST', body: fd });
        const data = await res.json();

        allDescriptors = [];
        const labeledDescriptors = [];

        data.forEach(item => {
            if (item.face_descriptor) {
                try {
                    const descArray = JSON.parse(item.face_descriptor);
                    const label = `${item.student_no}|${item.student_name}`;
                    const float32Arrays = [];

                    if (Array.isArray(descArray) && descArray.length > 0) {
                        if (Array.isArray(descArray[0])) {
                            // Multiple descriptors
                            descArray.forEach(d => {
                                if (d.length === 128) float32Arrays.push(new Float32Array(d));
                            });
                        } else if (descArray.length === 128) {
                            // Single descriptor
                            float32Arrays.push(new Float32Array(descArray));
                        }
                    }

                    if (float32Arrays.length > 0) {
                        labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(label, float32Arrays));
                    }
                } catch (e) {
                    console.error("Invalid descriptor format for", item.student_no);
                }
            }
        });

        if (labeledDescriptors.length > 0) {
            faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.55); // 0.55 optimal threshold for accuracy
            return true;
        } else {
            return false;
        }
    } catch (e) {
        console.error("Failed to fetch descriptors", e);
        return false;
    }
}

window.startWebFaceAttendance = async function () {
    if (!window.activeWebCourse) {
        const inputCourse = document.getElementById('start-course-input')?.value.trim();
        if (inputCourse && typeof startCourseWeb === 'function') {
            startCourseWeb(); // Auto-start the lecture
        } else {
            alert("Please start an Active Lecture from the 'Lecture & Quick Actions' card first.");
            return;
        }
    }
    const container = document.getElementById('web-attendance-container');
    const video = document.getElementById('web-attendance-video');
    const overlay = document.getElementById('web-attendance-overlay');
    const statusText = document.getElementById('web-attendance-status');
    const btnStart = document.getElementById('btn-start-attendance');
    const btnStop = document.getElementById('btn-stop-attendance');

    if (!container || !video) return;

    container.classList.remove('d-none');
    btnStart.classList.add('d-none');
    btnStop.classList.remove('d-none');
    statusText.innerHTML = "Loading Models and Enrolled Faces...";

    // Initialize speech synthesis on user interaction to bypass browser autoplay policies
    if ('speechSynthesis' in window) {
        let silent = new SpeechSynthesisUtterance('');
        silent.volume = 0;
        window.speechSynthesis.speak(silent);
    }

    await loadFaceModels();

    const hasFaces = await fetchEnrolledDescriptors();
    if (!hasFaces) {
        statusText.innerHTML = `<span class="text-danger">No web faces enrolled in the database. Please enroll students first.</span>`;
        btnStop.classList.add('d-none');
        btnStart.classList.remove('d-none');
        return;
    }

    // Camera Selection Setup
    const cameras = await getCameras();
    const cameraSelect = document.getElementById('attendance-camera-select');
    if (cameras.length > 0 && cameraSelect) {
        cameraSelect.innerHTML = cameras.map((c, i) => `<option value="${c.deviceId}">${c.label || 'Camera ' + (i + 1)}</option>`).join('');
        cameraSelect.classList.remove('d-none');
    }

    statusText.innerText = "Starting Camera...";
    const initialDevice = cameraSelect && !cameraSelect.classList.contains('d-none') ? cameraSelect.value : null;
    await startCamera('web-attendance-video', initialDevice);

    statusText.innerHTML = `<span class="text-primary fw-bold"><i class="bi bi-camera-video-fill me-1"></i>Scanning for faces...</span>`;

    startRecognitionLoop(video, overlay, statusText);

    btnStop.onclick = () => {
        stopCamera('web-attendance-video');
        container.classList.add('d-none');
        btnStart.classList.remove('d-none');
        btnStop.classList.add('d-none');
        const cameraSelect = document.getElementById('attendance-camera-select');
        if (cameraSelect) cameraSelect.classList.add('d-none');
        statusText.innerHTML = "";
    };
};

window.switchAttendanceCamera = async function () {
    const cameraSelect = document.getElementById('attendance-camera-select');
    if (cameraSelect && faceStream) {
        stopCamera('web-attendance-video');
        await startCamera('web-attendance-video', cameraSelect.value);

        const video = document.getElementById('web-attendance-video');
        const overlay = document.getElementById('web-attendance-overlay');
        const statusText = document.getElementById('web-attendance-status');
        startRecognitionLoop(video, overlay, statusText);
    }
};

// ─── ANTI-SPOOFING HELPERS ─────────────────────────────────────────

function eyeAspectRatio(pts, indices) {
    const d = (a, b) => Math.hypot(pts[a].x - pts[b].x, pts[a].y - pts[b].y);
    const A = d(indices[1], indices[5]), B = d(indices[2], indices[4]), C = d(indices[0], indices[3]);
    return C > 0 ? (A + B) / (2.0 * C) : 0;
}
const LEFT_EYE_IDX  = [36,37,38,39,40,41];
const RIGHT_EYE_IDX = [42,43,44,45,46,47];

// Measure pixel std-dev in face box — screens/photos are flatter than real skin
function textureVariance(video, box) {
    try {
        const sz = 48;
        const c  = document.createElement('canvas'); c.width = c.height = sz;
        const g  = c.getContext('2d');
        g.drawImage(video, box.x, box.y, box.width, box.height, 0, 0, sz, sz);
        const d  = g.getImageData(0, 0, sz, sz).data;
        let sum = 0, sum2 = 0, n = sz * sz;
        for (let i = 0; i < d.length; i += 4) {
            const v = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
            sum += v; sum2 += v * v;
        }
        const mean = sum / n;
        return Math.sqrt(sum2 / n - mean * mean);
    } catch { return 99; } // if cross-origin blocked, allow through
}

// Pick a random unpredictable challenge each session
const CHALLENGES = [
    { id:'BLINK2',  label:'👁  Blink TWICE (Slowly)',      emoji:'👁',  voice:'Please blink twice, slowly.'    },
    { id:'TURN_L',  label:'◀  Turn head LEFT',      emoji:'◀',  voice:'Turn your head left.'   },
    { id:'TURN_R',  label:'▶  Turn head RIGHT',     emoji:'▶',  voice:'Turn your head right.'  },
    { id:'NOD',     label:'▼  NOD your head down',  emoji:'▼',  voice:'Nod your head down.'    },
];

function pickChallenge() {
    return CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
}

function startRecognitionLoop(video, overlay, statusText) {
    if (attendanceInterval) clearInterval(attendanceInterval);

    let isProcessing  = false;
    let lastStudentNo = null;
    let noFaceCounter = 0;

    // ── Per-face liveness state ───────────────────────────────────
    let challenge     = null; 
    let challengeDone = false;

    // blink state (for BLINK2 challenge)
    let blinkCount    = 0;
    let eyeWasClosed  = false;
    
    // NEW: Rolling memory buffer for Eye Aspect Ratio
    let earHistory    = []; 

    // head-turn / nod state
    let baseNoseRatio = null; // nose_x / face_width at detection time (for turn)
    let baseNoseY     = null; // nose_y / face_height (for nod)

    function resetLiveness() {
        challenge     = pickChallenge();
        challengeDone = false;
        blinkCount    = 0; 
        eyeWasClosed  = false;
        baseNoseRatio = null; 
        baseNoseY     = null;
        earHistory    = []; // Clear memory when a new face appears
    }

    // Returns [passed, progressPct, hint]
    function checkChallenge(landmarks, box) {
        const pts  = landmarks.positions;
        const earL = eyeAspectRatio(pts, LEFT_EYE_IDX);
        const earR = eyeAspectRatio(pts, RIGHT_EYE_IDX);
        const ear  = (earL + earR) / 2;

        // nose x relative to eye-to-eye midpoint (0=far left, 1=far right)
        const eyeLeft  = pts[36]; const eyeRight = pts[45];
        const eyeSpan  = eyeRight.x - eyeLeft.x;
        const noseX    = pts[30].x;
        const noseRatio = eyeSpan > 0 ? (noseX - eyeLeft.x) / eyeSpan : 0.5;

        // nose y relative to face box height
        const noseYRel = box.height > 0 ? (pts[30].y - box.y) / box.height : 0.4;

        // Set baseline for nod/turn on first frame
        if (baseNoseRatio === null) { 
            baseNoseRatio = noseRatio; 
            baseNoseY = noseYRel; 
        }

        if (challenge.id === 'BLINK2') {
            // Keep the last ~1.5 seconds of eye size history (approx 20 frames at 80ms)
            earHistory.push(ear);
            if (earHistory.length > 20) {
                earHistory.shift(); 
            }

            // Only evaluate once we have enough data collected (about half a second)
            if (earHistory.length > 5) {
                // Find the BIGGEST your eye has been in the last 1.5 seconds
                const recentMax = Math.max(...earHistory);
                
                // face-api.js AI is bad at closing eyes. We only look for a microscopic absolute drop.
                const dropThreshold = recentMax - 0.025; 
                const openThreshold = recentMax - 0.010;

                if (!eyeWasClosed && ear < dropThreshold) {
                    eyeWasClosed = true;
                } else if (eyeWasClosed && ear >= openThreshold) { 
                    eyeWasClosed = false; 
                    blinkCount++; 
                    // Flush the memory to stop one messy blink from counting twice!
                    earHistory = []; 
                }
            }
            
            const pct = Math.min(100, Math.round((blinkCount / 2) * 100));
            return [blinkCount >= 2, pct, `Blinks: ${blinkCount}/2 (EAR: ${ear.toFixed(3)})`];
        }
        if (challenge.id === 'TURN_L') {
            // nose should move left: ratio drops below baseline - 0.12
            const delta = baseNoseRatio - noseRatio;
            const pct   = Math.min(100, Math.round((delta / 0.12) * 100));
            return [delta >= 0.12, pct, `Turn more left…`];
        }
        if (challenge.id === 'TURN_R') {
            const delta = noseRatio - baseNoseRatio;
            const pct   = Math.min(100, Math.round((delta / 0.12) * 100));
            return [delta >= 0.12, pct, `Turn more right…`];
        }
        if (challenge.id === 'NOD') {
            const delta = noseYRel - baseNoseY; // nod = nose moves down
            const pct   = Math.min(100, Math.round((delta / 0.08) * 100));
            return [delta >= 0.08, pct, `Nod lower…`];
        }
        return [false, 0, ''];
    }

    attendanceInterval = setInterval(async () => {
        if (isProcessing || !video || video.paused || video.ended) return;
        isProcessing = true;
        try {
            const displaySize = { width: video.offsetWidth, height: video.offsetHeight };
            if (overlay.width !== displaySize.width || overlay.height !== displaySize.height)
                faceapi.matchDimensions(overlay, displaySize);

            const opts       = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.35 });
            const detections = await faceapi.detectAllFaces(video, opts).withFaceLandmarks().withFaceDescriptors();
            const resized    = faceapi.resizeResults(detections, displaySize);
            const ctx        = overlay.getContext('2d');
            ctx.clearRect(0, 0, overlay.width, overlay.height);

            const badge = document.getElementById('web-head-count-badge');

            if (resized.length === 0) {
                if (badge) badge.classList.add('d-none');
                if (++noFaceCounter > 3) {
                    statusText.innerHTML = `<span class="text-primary fw-bold"><i class="bi bi-camera-video-fill me-1"></i>Scanning for faces...</span>`;
                    lastStudentNo = null; challenge = null;
                }
                isProcessing = false; return;
            }

            if (badge) { badge.classList.remove('d-none'); badge.innerText = `Faces: ${resized.length}`; }
            noFaceCounter = 0;

            const results = resized.map(d => faceMatcher.findBestMatch(d.descriptor));

            for (let i = 0; i < results.length; i++) {
                const result = results[i];
                const det    = resized[i];
                const box    = det.detection.box;

                // ── Texture check (flat photo/screen guard) ───────
                const texVar   = textureVariance(video, box);
                const texOk    = texVar > 14; // real skin > 14, screen photos < 14

                // ── Challenge check ───────────────────────────────
                if (!challenge) resetLiveness();
                const [chalPassed, chalPct, chalHint] = checkChallenge(det.landmarks, box);
                if (chalPassed) challengeDone = true;

                const live     = challengeDone && texOk;
                const boxColor = live ? '#28a745' : (challengeDone ? '#fd7e14' : '#dc3545');

                ctx.strokeStyle = boxColor; ctx.lineWidth = 2;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
                ctx.fillStyle = boxColor; ctx.font = '12px monospace';
                const topLabel = live ? result.toString() : (challenge?.emoji + ' ' + chalHint);
                ctx.fillText(topLabel, box.x + 4, box.y - 5);

                if (result.label === 'unknown') continue;

                const parts       = result.label.split('|');
                const studentNo   = parts[0];
                const studentName = parts[1] || studentNo;
                const mode = document.querySelector('input[name="webFaceMode"]:checked')?.value || 'attendance';

                if (studentNo !== lastStudentNo) {
                    lastStudentNo = studentNo;
                    resetLiveness();
                    window.hasAutoMarked = false;
                    if (mode === 'attendance') {
                        speakAnnouncement(`${studentNo} detected. ${challenge.voice}`);
                        
                        // NEW: Added a Bypass button to the warning UI box
                        statusText.innerHTML = `
                            <div class="alert alert-warning py-2 mb-0 shadow-sm">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">${escapeHtml(studentName)} <small class="fw-normal">(${escapeHtml(studentNo)})</small></div>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="fs-5">${challenge.emoji}</span>
                                            <span class="fw-bold text-dark" id="challenge-label">${challenge.label}</span>
                                        </div>
                                    </div>
                                    <button id="web-face-bypass-btn-${studentNo}" class="btn btn-sm btn-outline-danger fw-bold px-2 py-1" onclick="markWebFaceAttendance('${studentNo}')" title="Bypass challenge and force mark">
                                        Bypass
                                    </button>
                                </div>
                                <div class="progress mt-2" style="height:6px">
                                    <div class="progress-bar bg-warning" id="challenge-bar" style="width:0%"></div>
                                </div>
                                <div class="d-flex gap-2 mt-1 text-xs">
                                    <span id="lbl-texture" class="text-muted">Texture: checking…</span>
                                </div>
                            </div>`;
                    } else {
                        statusText.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-person-check-fill me-1"></i>${escapeHtml(studentName)} (${escapeHtml(studentNo)})</span>`;
                    }
                } else if (mode === 'attendance' && !challengeDone) {
                    // Update progress bar and texture label
                    const bar = document.getElementById('challenge-bar');
                    if (bar) bar.style.width = chalPct + '%';
                    const lbl = document.getElementById('lbl-texture');
                    if (lbl) lbl.textContent = texOk ? 'Texture: ✔ Real' : `Texture: ✗ Flat (σ=${texVar.toFixed(1)}) — show real face`;
                } else if (mode === 'attendance' && live) {
                    const isAuto = document.getElementById('web-attendance-auto')?.checked;
                    if (isAuto && !window.hasAutoMarked) {
                        window.hasAutoMarked = true;
                        speakAnnouncement(`${studentNo} verified. Marking attendance.`);
                        markWebFaceAttendance(studentNo);
                        statusText.innerHTML = `
                            <div class="alert alert-success py-2 mb-0 shadow-sm d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold d-block">${escapeHtml(studentName)}</span>
                                    <small>${escapeHtml(studentNo)} <span class="badge bg-success ms-1">✔ Verified &amp; Marked</span></small>
                                </div>
                            </div>`;
                    } else if (!isAuto && !window.hasAutoMarked) {
                        window.hasAutoMarked = true;
                        speakAnnouncement(`${studentNo} verified. Please confirm.`);
                        statusText.innerHTML = `
                            <div class="alert alert-success py-2 mb-0 shadow-sm d-flex justify-content-between align-items-center">
                                <div class="text-start lh-sm">
                                    <span class="fw-bold d-block">${escapeHtml(studentName)}</span>
                                    <small>${escapeHtml(studentNo)} <span class="badge bg-success ms-1">✔ Live</span></small>
                                </div>
                                <button id="web-face-mark-btn-${studentNo}" class="btn btn-sm btn-success fw-bold px-3"
                                    onclick="markWebFaceAttendance('${studentNo}')">
                                    <i class="bi bi-check2-circle me-1"></i>Mark Present
                                </button>
                            </div>`;
                    }
                }
            }
        } catch (e) { console.error('Recognition error:', e); }
        finally { isProcessing = false; }
    }, 80); // Fast interval to catch the quick blink
}



function speakAnnouncement(text) {
    const muteToggle = document.getElementById('web-attendance-mute');
    if (muteToggle && muteToggle.checked) return; // Do not speak if muted

    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // Cancel any ongoing speech
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;
        window.speechSynthesis.speak(utterance);
    }
}

window.markWebFaceAttendance = async function (studentNo) {
    let courseCode = window.activeWebCourse || document.getElementById('start-course-input')?.value.trim() || document.getElementById('manual-course-code')?.value.trim() || '';

    const fd = new FormData();
    fd.append('action', 'manual_attendance');
    fd.append('student_no', studentNo);
    if (courseCode) fd.append('course_code', courseCode);
    fd.append('modality', 'web_face');

    const token = ""; // if we want to send it directly to attendance.php

    try {
        const res = await fetch('api/student.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const textData = await res.text();
        const result = JSON.parse(textData);
        if (result.status === 'success') {
            // Success: Update normal mark button if it exists
            const btn = document.getElementById(`web-face-mark-btn-${studentNo}`);
            if (btn) {
                btn.classList.replace('btn-success', 'btn-secondary');
                btn.innerHTML = `<i class="bi bi-check-all me-1"></i>Logged!`;
                btn.disabled = true;
            }
            
            // NEW: Update Bypass button if it exists
            const bypassBtn = document.getElementById(`web-face-bypass-btn-${studentNo}`);
            if (bypassBtn) {
                bypassBtn.classList.replace('btn-outline-danger', 'btn-secondary');
                bypassBtn.innerHTML = `<i class="bi bi-check-all me-1"></i>Logged!`;
                bypassBtn.disabled = true;
            }
            
            speakAnnouncement("Attendance logged successfully.");

            if (typeof fetchLogs === 'function') {
                fetchLogs(); // refresh the dashboard logs
            }
            if (typeof fetchTodayAttendance === 'function') {
                fetchTodayAttendance();
            }
        } else {
            console.error("Attendance failed:", result.message);
        }
    } catch (e) {
        console.error("Failed to mark web face attendance", e);
    }
}

// ─── ADMIN WEB FACE ENROLLMENT ──────────────────────────────────────

let adminFaceStream = null;

window.startAdminWebFaceEnrollment = async function (adminName, captureCount = 7) {
    if (!adminName) {
        alert("Please enter an Admin Name.");
        return;
    }

    const container = document.getElementById('admin-web-enroll-container');
    const video = document.getElementById('admin-web-enroll-video');
    const btnStart = document.getElementById('btn-start-admin-enroll');
    const btnCapture = document.getElementById('btn-capture-admin-enroll');
    const btnCancel = document.getElementById('btn-cancel-admin-enroll');
    const statusText = document.getElementById('admin-web-enroll-status');

    if (!container || !video) return;

    container.classList.remove('d-none');
    btnStart.classList.add('d-none');
    statusText.innerText = "Loading Models...";

    await loadFaceModels();

    const cameras = await getCameras();
    const cameraSelect = document.getElementById('admin-enroll-camera-select');
    if (cameras.length > 0 && cameraSelect) {
        cameraSelect.innerHTML = cameras.map((c, i) => `<option value="${c.deviceId}">${c.label || 'Camera ' + (i + 1)}</option>`).join('');
        cameraSelect.classList.remove('d-none');
    }

    statusText.innerText = "Starting Camera...";
    const initialDevice = cameraSelect && !cameraSelect.classList.contains('d-none') ? cameraSelect.value : null;

    try {
        const constraints = {
            video: initialDevice ? { deviceId: { exact: initialDevice } } : true,
            audio: false
        };
        adminFaceStream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = adminFaceStream;
        await new Promise((resolve) => {
            video.onloadedmetadata = () => { video.play(); resolve(); };
        });
    } catch (err) {
        statusText.innerHTML = `<span class="text-danger">Camera error: ${err.message}</span>`;
        btnStart.classList.remove('d-none');
        container.classList.add('d-none');
        return;
    }

    statusText.innerHTML = `<span class="text-primary"><i class="bi bi-camera-video me-1"></i>Camera Active. Face the camera and click Capture.</span>`;
    btnCapture.classList.remove('d-none');
    btnCancel.classList.remove('d-none');

    btnCapture.onclick = async () => {
        statusText.innerHTML = `<span class="text-primary fw-bold">Capturing angles. Please slowly move your head...</span>`;
        btnCapture.disabled = true;

        const options = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 });
        const descriptors = [];

        for (let i = 0; i < captureCount; i++) {
            const detection = await faceapi.detectSingleFace(video, options).withFaceLandmarks().withFaceDescriptor();
            if (detection) {
                descriptors.push(Array.from(detection.descriptor));
                statusText.innerHTML = `<span class="text-primary fw-bold">Captured ${descriptors.length}/${captureCount} angles...</span>`;
            } else {
                statusText.innerHTML = `<span class="text-warning fw-bold">No face detected... (${descriptors.length}/${captureCount})</span>`;
            }
            await new Promise(r => setTimeout(r, 600));
        }

        if (descriptors.length === 0) {
            statusText.innerHTML = `<span class="text-danger">Failed to detect any faces.</span>`;
            btnCapture.disabled = false;
            return;
        }

        statusText.innerHTML = `<span class="text-success fw-bold">Captured ${descriptors.length}/${captureCount} angles! Saving...</span>`;

        const fd = new FormData();
        fd.append('action', 'link_admin_web_face');
        fd.append('admin_name', adminName);
        fd.append('descriptor', JSON.stringify(descriptors));

        try {
            const res = await fetch('api/student.php', { method: 'POST', body: fd });
            const textData = await res.text();
            let data;
            try { data = JSON.parse(textData); } catch (jsonErr) { throw new Error("Server returned non-JSON response."); }

            if (data.status === 'success') {
                statusText.innerHTML = `<span class="text-success fw-bold">Successfully Enrolled Admin Web Face!</span>`;
                setTimeout(() => {
                    stopAdminCamera();
                    container.classList.add('d-none');
                    btnStart.classList.remove('d-none');
                    btnCapture.classList.add('d-none');
                    btnCancel.classList.add('d-none');
                    btnCapture.disabled = false;
                }, 2000);
            } else {
                statusText.innerHTML = `<span class="text-danger">Failed: ${escapeHtml(data.message)}</span>`;
                btnCapture.disabled = false;
            }
        } catch (e) {
            statusText.innerHTML = `<span class="text-danger">Error: ${escapeHtml(e.message)}</span>`;
            btnCapture.disabled = false;
        }
    };

    btnCancel.onclick = () => {
        stopAdminCamera();
        container.classList.add('d-none');
        btnStart.classList.remove('d-none');
        btnCapture.classList.add('d-none');
        btnCancel.classList.add('d-none');
        if (cameraSelect) cameraSelect.classList.add('d-none');
    };
};

function stopAdminCamera() {
    const video = document.getElementById('admin-web-enroll-video');
    if (adminFaceStream) {
        adminFaceStream.getTracks().forEach(track => track.stop());
        adminFaceStream = null;
    }
    if (video) video.srcObject = null;
}

window.switchAdminEnrollCamera = async function () {
    const cameraSelect = document.getElementById('admin-enroll-camera-select');
    if (cameraSelect && adminFaceStream) {
        stopAdminCamera();
        const video = document.getElementById('admin-web-enroll-video');
        try {
            adminFaceStream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: { exact: cameraSelect.value } }, audio: false
            });
            video.srcObject = adminFaceStream;
        } catch (e) { console.error("Camera switch error:", e); }
    }
};

window.adminEnrollUploadedImage = async function (fileInput) {
    const adminName = document.getElementById('admin-webface-name').value.trim();
    if (!adminName) {
        alert("Please enter an Admin Name.");
        fileInput.value = '';
        return;
    }
    if (!fileInput.files || fileInput.files.length === 0) return;

    const file = fileInput.files[0];
    const container = document.getElementById('admin-web-enroll-container');
    const btnStart = document.getElementById('btn-start-admin-enroll');
    const statusText = document.getElementById('admin-web-enroll-status');
    const video = document.getElementById('admin-web-enroll-video');

    stopAdminCamera();
    container.classList.remove('d-none');
    btnStart.classList.add('d-none');
    video.classList.add('d-none');
    document.getElementById('btn-capture-admin-enroll').classList.add('d-none');
    document.getElementById('btn-cancel-admin-enroll').classList.add('d-none');

    statusText.innerHTML = "Processing Image...";
    await loadFaceModels();

    try {
        const img = await faceapi.bufferToImage(file);
        const detection = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();

        if (!detection) {
            statusText.innerHTML = `<span class="text-danger">No face detected in the image.</span>`;
            setTimeout(() => {
                container.classList.add('d-none');
                btnStart.classList.remove('d-none');
                video.classList.remove('d-none');
                fileInput.value = '';
                statusText.innerHTML = '';
            }, 3000);
            return;
        }

        statusText.innerHTML = `<span class="text-info">Face found! Saving to database...</span>`;

        const fd = new FormData();
        fd.append('action', 'link_admin_web_face');
        fd.append('admin_name', adminName);
        fd.append('descriptor', JSON.stringify(Array.from(detection.descriptor)));

        const res = await fetch('api/student.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.status === 'success') {
            statusText.innerHTML = `<span class="text-success fw-bold">Successfully Enrolled Admin via Image!</span>`;
            setTimeout(() => {
                container.classList.add('d-none');
                btnStart.classList.remove('d-none');
                video.classList.remove('d-none');
                fileInput.value = '';
                statusText.innerHTML = '';
            }, 2000);
        } else {
            statusText.innerHTML = `<span class="text-danger">Failed: ${escapeHtml(data.message)}</span>`;
        }
    } catch (e) {
        statusText.innerHTML = `<span class="text-danger">Error: ${escapeHtml(e.message)}</span>`;
    }
};

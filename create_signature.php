<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION['user_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['signature_data'])) {
    $data_uri = $_POST['signature_data'];
    $img_parts = explode(",", $data_uri);
    if (count($img_parts) < 2) { die("ข้อมูลรูปภาพไม่ถูกต้อง"); }

    $decoded_image = base64_decode($img_parts[1]);
    $file_name = "sig_" . $user_id . "_" . bin2hex(random_bytes(4)) . ".png";
    $dir_path = "uploads/signatures/";
    $file_path = $dir_path . $file_name;

    if (!is_dir($dir_path)) { mkdir($dir_path, 0777, true); }

    $check_sql = "SELECT path FROM signatures WHERE users_id = '$user_id' LIMIT 1";
    $result = mysqli_query($conn, $check_sql);
    $old_record = mysqli_fetch_assoc($result);

    if (file_put_contents($file_path, $decoded_image)) {
        if ($old_record) {
            $old_file_path = $dir_path . $old_record['path'];
            if (file_exists($old_file_path)) { unlink($old_file_path); }
            $sql = "UPDATE signatures SET path = '$file_name', created_at = NOW() WHERE users_id = '$user_id'";
        } else {
            $sql = "INSERT INTO signatures (path, users_id) VALUES ('$file_name', '$user_id')";
        }

        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('บันทึกลายเซ็นเรียบร้อย!'); window.history.go(-2);</script>";
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการลายเซ็น</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>#sig-canvas { touch-action: none; }</style>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">

    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl p-6 border border-slate-200">
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold text-slate-800">จัดการลายเซ็นดิจิทัล</h1>
            <p class="text-slate-500 text-sm">เลือกวิธีที่สะดวก: อัปโหลดรูป หรือ เซ็นใหม่</p>
        </div>

        <div class="mb-4">
            <label class="block mb-2 text-xs font-bold text-slate-400 uppercase">อัปโหลดรูปภาพลายเซ็น</label>
            <input type="file" id="image-upload" accept="image/*" 
                   class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"/>
        </div>

        <div class="relative bg-white border-2 border-dashed border-slate-300 rounded-xl mb-6 overflow-hidden">
            <canvas id="sig-canvas" class="w-full h-48 cursor-crosshair"></canvas>
            <button type="button" id="clear-btn" class="absolute bottom-2 right-2 text-xs bg-white/80 border px-3 py-1 rounded-lg shadow-sm hover:bg-red-50 hover:text-red-500 transition-all">ล้างหน้าจอ</button>
        </div>

        <form method="POST" id="sig-form">
            <input type="hidden" name="signature_data" id="signature_data">
            <div class="flex gap-3">
                <button type="button" onclick="window.history.back()" class="flex-1 py-3 text-slate-500 font-bold hover:bg-slate-50 rounded-xl text-sm">ย้อนกลับ</button>
                <button type="submit" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg text-sm transition-all">บันทึกลายเซ็น</button>
            </div>
        </form>
    </div>

    <script>
        const canvas = document.getElementById('sig-canvas');
        const ctx = canvas.getContext('2d');
        const form = document.getElementById('sig-form');
        const imageUpload = document.getElementById('image-upload');
        const clearBtn = document.getElementById('clear-btn');

        function resize() {
            const ratio = window.devicePixelRatio || 1;
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            ctx.scale(ratio, ratio);
            ctx.strokeStyle = "#0f172a"; ctx.lineWidth = 2.5; ctx.lineCap = "round";
        }
        window.addEventListener('resize', resize);
        resize();

        // --- ระบบอัปโหลดรูปแล้ว Preview บน Canvas ---
        imageUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height); // ล้างของเก่า
                        // คำนวณให้รูปอยู่ตรงกลางและไม่เบี้ยว
                        const scale = Math.min(canvas.offsetWidth / img.width, canvas.offsetHeight / img.height);
                        const x = (canvas.offsetWidth / 2) - (img.width / 2) * scale;
                        const y = (canvas.offsetHeight / 2) - (img.height / 2) * scale;
                        ctx.drawImage(img, x, y, img.width * scale, img.height * scale);
                    }
                    img.src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // --- ระบบวาด (เหมือนเดิม) ---
        let drawing = false;
        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            return { x: (e.clientX || e.touches[0].clientX) - rect.left, y: (e.clientY || e.touches[0].clientY) - rect.top };
        }
        canvas.addEventListener('mousedown', (e) => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
        canvas.addEventListener('mousemove', (e) => { if(!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
        window.addEventListener('mouseup', () => drawing = false);
        // Touch
        canvas.addEventListener('touchstart', (e) => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
        canvas.addEventListener('touchmove', (e) => { if(!drawing) return; e.preventDefault(); const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
        canvas.addEventListener('touchend', () => drawing = false);

        clearBtn.onclick = () => { ctx.clearRect(0, 0, canvas.width, canvas.height); imageUpload.value = ""; };

        form.onsubmit = (e) => {
            const blank = document.createElement('canvas');
            blank.width = canvas.width; blank.height = canvas.height;
            if (canvas.toDataURL() === blank.toDataURL()) {
                alert("กรุณาเซ็นชื่อหรือเลือกรูปก่อนครับจาร!");
                e.preventDefault(); return;
            }
            document.getElementById('signature_data').value = canvas.toDataURL('image/png');
        };
    </script>
</body>
</html>
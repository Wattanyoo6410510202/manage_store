<?php
require_once '../../config.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์มใบลาออนไลน์ - ProSystem</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8fafc; }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.375rem;
            margin-left: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-xl mx-auto">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 rounded-2xl shadow-lg mb-4">
                <i class="fas fa-file-signature text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black text-slate-800">แบบฟอร์มใบลา</h1>
            <p class="text-slate-500 text-sm">กรุณากรอกข้อมูลเพื่อแจ้งความประสงค์การลา</p>
        </div>

        <form id="leaveForm" class="bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 p-6 md:p-8 space-y-6">
            
            <div class="space-y-4">
                <h2 class="text-xs font-black text-indigo-600 uppercase tracking-widest border-b border-indigo-50 pb-2">ข้อมูลส่วนตัว</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" required class="form-input" placeholder="ระบุชื่อ-นามสกุล">
                    </div>
                    <div>
                        <label class="label">ตำแหน่ง</label>
                        <input type="text" name="position" required class="form-input" placeholder="ระบุตำแหน่ง">
                    </div>
                </div>

                <div>
                    <label class="label">แผนก / ฝ่าย</label>
                    <select name="department" required class="form-input appearance-none">
                        <option value="">เลือกแผนก</option>
                        <option value="จัดซื้อ">จัดซื้อ</option>
                        <option value="บัญชีการเงิน">บัญชีการเงิน</option>
                        <option value="บุคคล">บุคคล</option>
                        <option value="จัดเลี้ยง">จัดเลี้ยง</option>
                        <option value="ช่าง">ช่าง</option>
                        <option value="พนักงานขาย">พนักงานขาย</option>
                        <option value="การตลาด">การตลาด</option>
                        <option value="ร้านอาหาร">ร้านอาหาร</option>
                    </select>
                </div>
            </div>

            <div class="space-y-4 pt-2">
                <h2 class="text-xs font-black text-indigo-600 uppercase tracking-widest border-b border-indigo-50 pb-2">รายละเอียดการลา</h2>
                
                <div>
                    <label class="label">ประเภทการลา</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center p-3 border border-slate-100 rounded-xl bg-slate-50 cursor-pointer hover:bg-indigo-50 hover:border-indigo-100 transition">
                            <input type="radio" name="leave_type" value="ลาป่วย" required class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm font-bold text-slate-700">ลาป่วย</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-100 rounded-xl bg-slate-50 cursor-pointer hover:bg-indigo-50 hover:border-indigo-100 transition">
                            <input type="radio" name="leave_type" value="ลากิจ" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm font-bold text-slate-700">ลากิจ</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-100 rounded-xl bg-slate-50 cursor-pointer hover:bg-indigo-50 hover:border-indigo-100 transition">
                            <input type="radio" name="leave_type" value="ลาพักร้อน" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm font-bold text-slate-700">ลาพักร้อน</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-100 rounded-xl bg-slate-50 cursor-pointer hover:bg-indigo-50 hover:border-indigo-100 transition">
                            <input type="radio" name="leave_type" value="อื่นๆ" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm font-bold text-slate-700">อื่นๆ</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">ลาตั้งแต่วันที่</label>
                        <input type="date" name="start_date" required class="form-input">
                    </div>
                    <div>
                        <label class="label">ถึงวันที่</label>
                        <input type="date" name="end_date" required class="form-input">
                    </div>
                </div>

                <div>
                    <label class="label">จำนวนวันลา (วัน)</label>
                    <input type="number" step="0.5" name="duration" required class="form-input" placeholder="0">
                </div>

                <div>
                    <label class="label">สาเหตุการลา</label>
                    <textarea name="reason" rows="3" required class="form-input" placeholder="ระบุเหตุผล..."></textarea>
                </div>

                <div>
                    <label class="label">สถานที่ติดต่อในระหว่างลา / เบอร์โทรศัพท์</label>
                    <input type="text" name="contact" required class="form-input" placeholder="ระบุข้อมูลการติดต่อ">
                </div>
            </div>

            <div class="pt-6">
                <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl text-lg font-black hover:bg-indigo-700 shadow-xl shadow-indigo-600/20 transition-all active:scale-95">
                    ส่งใบลา <i class="fas fa-paper-plane ml-2"></i>
                </button>
                <a href="../../login.php" class="block text-center mt-4 text-slate-400 text-xs font-bold hover:text-slate-600 uppercase tracking-widest">
                    <i class="fas fa-times mr-1"></i> ยกเลิก
                </a>
            </div>
        </form>

        <p class="text-center mt-8 text-slate-300 text-[10px] font-bold uppercase tracking-[0.2em]">
            Powered by ProSystem E-Service
        </p>
    </div>

    <script>
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);

            Swal.fire({
                title: 'ยืนยันการส่งใบลา?',
                text: "ข้อมูลจะถูกบันทึกลงระบบ",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                heightAuto: false
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังส่งข้อมูล...',
                        text: 'กรุณารอสักครู่',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('save_leave.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'ส่งใบลาสำเร็จ!',
                                text: 'ข้อมูลของคุณถูกบันทึกเรียบร้อยแล้ว',
                                confirmButtonColor: '#4f46e5',
                                heightAuto: false
                            }).then(() => {
                                window.location.href = '../../login.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message || 'ไม่สามารถบันทึกข้อมูลได้',
                                confirmButtonColor: '#4f46e5',
                                heightAuto: false
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'ผิดพลาด!',
                            text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้',
                            confirmButtonColor: '#4f46e5',
                            heightAuto: false
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>
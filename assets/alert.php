<div id="alert-container" style="position: fixed; top: 25px; right: 25px; z-index: 9999; width: 100%; max-width: 380px;">
    <style>
        /* Animation การปรากฏตัวแบบนุ่มนวล */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .custom-alert {
            animation: slideInRight 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
            border: none !important;
            border-radius: 20px !important; /* ความโค้งมนสูงเข้ากับ Card UI */
            backdrop-filter: blur(10px); /* พื้นหลังเบลอเล็กน้อย */
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); /* เงาฟุ้งแบบทันสมัย */
            padding: 1.25rem !important;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin-bottom: 15px;
        }

        /* ขีดสีด้านซ้ายปรับให้มน */
        .custom-alert::before {
            content: "";
            position: absolute;
            left: 0;
            top: 15%;
            height: 70%;
            width: 5px;
            background: currentColor;
            border-radius: 0 5px 5px 0;
        }

        .alert-icon-wrap {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .slide-out-right {
            transform: translateX(120%) !important;
            opacity: 0 !important;
            transition: all 0.5s cubic-bezier(0.4, 0, 1, 1) !important;
        }
    </style>

    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    // รวมค่าจากทั้ง Session และ Get
    $msg_type = $_SESSION['flash_msg'] ?? $_GET['msg'] ?? null;

    if ($msg_type) {
        $alert_msg = "";
        $alert_color = ""; // ใช้สำหรับ background icon และเส้นข้าง
        $alert_icon = "";

        switch ($msg_type) {
            case 'success':
            case 'add_success':
                $alert_msg = "บันทึกข้อมูลเรียบร้อยแล้ว";
                $alert_color = "#10b981"; // Emerald 500
                $alert_icon = "bi-check-lg";
                break;
            case 'updated':
            case 'edit_success':
            case 'update_success':
                $alert_msg = "อัปเดตข้อมูลสำเร็จ";
                $alert_color = "#6366f1"; // Indigo 500
                $alert_icon = "bi-pencil-square";
                break;
            case 'deleted':
            case 'delete_success':
                $alert_msg = "ลบรายการเรียบร้อยแล้ว";
                $alert_color = "#f43f5e"; // Rose 500
                $alert_icon = "bi-trash3";
                break;
            case 'duplicate':
                $alert_msg = "ชื่อนี้มีในระบบแล้ว!";
                $alert_color = "#f59e0b"; // Amber 500
                $alert_icon = "bi-exclamation-circle";
                break;
            case 'error':
                $alert_msg = "เกิดข้อผิดพลาดบางอย่าง";
                $alert_color = "#64748b"; // Slate 500
                $alert_icon = "bi-x-circle";
                break;
        }

        if ($alert_msg != "") {
            echo '
            <div class="custom-alert alert-dismissible fade show mb-3" role="alert" style="color: '.$alert_color.';">
                <div class="alert-icon-wrap" style="background-color: '.$alert_color.'20;">
                    <i class="bi '.$alert_icon.' fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-dark fw-bold mb-0" style="font-size: 0.95rem;">'.$alert_msg.'</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="font-size: 0.7rem;"></button>
            </div>';
        }
        unset($_SESSION['flash_msg']);
    }
    ?>

    <script>
        (function () {
            const alerts = document.querySelectorAll('#alert-container .custom-alert');
            alerts.forEach(alertElement => {
                setTimeout(function () {
                    if (alertElement) {
                        alertElement.classList.add('slide-out-right');
                        setTimeout(() => alertElement.remove(), 600);
                    }
                }, 4000); // 4 วินาทีเพื่อให้คนอ่านทัน
            });
        })();
    </script>
</div>
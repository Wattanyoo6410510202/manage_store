/**
 * ฟังก์ชันสร้างสินค้าตามประเภทธุรกิจของลูกค้า (Context-Aware)
 */
function generateSmartProduct(customerName = "") {
    let category = "general"; // ค่าเริ่มต้น
    const name = customerName.toLowerCase();

    // 1. วิเคราะห์หมวดหมู่จากชื่อลูกค้า
    if (name.includes("ก่อสร้าง") || name.includes("วิศวกรรม") || name.includes("คอนสตรัคชั่น")) {
        category = "construction";
    } else if (name.includes("เทคโนโลยี") || name.includes("โซลูชั่น") || name.includes("ดิจิทัล") || name.includes("solution")) {
        category = "tech";
    } else if (name.includes("การตลาด") || name.includes("เอเจนซี่") || name.includes("ดีไซน์")) {
        category = "marketing";
    }

    // 2. คลังคำศัพท์ตามหมวดหมู่
    const database = {
        construction: {
            actions: ["จัดหา", "ติดตั้ง", "ออกแบบ", "เทพื้น", "วางโครงสร้าง", "เหมาค่าแรง"],
            subjects: ["เสาเข็มไมโครไพล์", "ปูนผสมเสร็จ CPAC", "เหล็กเส้นมอก.", "ระบบไฟฟ้าอาคาร", "งานฝ้าเพดาน", "พื้นลามิเนต"],
            targets: ["หน้างานระยะที่ 1", "โซนโกดังสินค้า", "ชั้น 2-4", "งานต่อเติม", "ตามแบบแปลน"]
        },
        tech: {
            actions: ["พัฒนา", "ติดตั้ง", "เช่าใช้", "Config ระบบ", "Implement", "Maintenance"],
            subjects: ["Cloud Server", "ระบบ Firewall", "โปรแกรมบัญชีออนไลน์", "API Gateway", "ฐานข้อมูล SQL", "ระบบ VPN"],
            targets: ["รายปี", "แบบ Enterprise", "20 Users", "Security Pack", "เวอร์ชันล่าสุด"]
        },
        marketing: {
            actions: ["วางแผน", "ผลิตสื่อ", "ยิงโฆษณา", "วิเคราะห์", "ออกแบบ", "จัดการ"],
            subjects: ["Facebook Ads", "Video Presentation", "Infographic", "Content Plan", "SEO Optimization", "Logo Rebranding"],
            targets: ["Campaign รายเดือน", "สำหรับเปิดตัวสินค้า", "10 ชิ้นงาน", "กลุ่มเป้าหมายกรุงเทพฯ", "Premium Set"]
        },
        general: {
            actions: ["บริการ", "จัดซื้อ", "จัดเตรียม", "จัดทำ"],
            subjects: ["วัสดุสิ้นเปลือง", "เครื่องเขียนสำนักงาน", "อะไหล่สำรอง", "อุปกรณ์ทั่วไป"],
            targets: ["ล็อตที่ 1", "ชุดมาตรฐาน", "สำหรับสำนักงาน", "ชุดประหยัด"]
        }
    };

    const db = database[category];
    const a = db.actions[Math.floor(Math.random() * db.actions.length)];
    const s = db.subjects[Math.floor(Math.random() * db.subjects.length)];
    const t = db.targets[Math.floor(Math.random() * db.targets.length)];

    return `${a}${s} ${t}`;
}

/**
 * ฟังก์ชันสุ่มข้อมูลแบบจำกัดสูงสุด 15 รายการ
 */
function fillRandomData() {
    const tbody = document.querySelector('#itemsTable tbody');
    const customerName = document.querySelector('[name="customer_id"] option:checked')?.text || ""; // ดึงชื่อลูกค้าจาก Select
    
    tbody.innerHTML = '';

    // สุ่มจำนวนรายการ 1 ถึง 15 (ไม่ให้เกินนี้)
    const randomCount = Math.floor(Math.random() * 30) + 1; 
    console.log(`Generating ${randomCount} smart items for: ${customerName}`);

    for (let i = 0; i < randomCount; i++) {
        if (typeof addItemRow === "function") {
            addItemRow(); 
            
            const rows = document.querySelectorAll('.item-row');
            const lastRow = rows[rows.length - 1];

            // 1. สุ่มสินค้าที่เข้ากับชื่อลูกค้า
            const textarea = lastRow.querySelector('textarea[name="item_desc[]"]');
            textarea.value = generateSmartProduct(customerName);
            if (typeof autoResize === "function") autoResize(textarea);

            // 2. สุ่มจำนวน (1-10)
            lastRow.querySelector('input[name="item_qty[]"]').value = Math.floor(Math.random() * 10) + 1;

            // 3. สุ่มราคาตามความเหมาะสม (Construction มักจะแพงกว่า General)
            let minPrice = 500, maxPrice = 20000;
            if (customerName.includes("ก่อสร้าง")) { minPrice = 5000; maxPrice = 100000; }
            
            const price = (Math.random() * (maxPrice - minPrice) + minPrice).toFixed(2);
            lastRow.querySelector('input[name="item_price[]"]').value = price;
        }
    }

    // สุ่มหมายเหตุที่เข้ากับธุรกิจ
    const notesField = document.querySelector('textarea[name="notes"]');
    if (notesField) {
        const notes = [
            "ราคานี้ยังไม่รวมภาษีหัก ณ ที่จ่าย 3%",
            "รับประกันงานติดตั้ง 1 ปี",
            "กำหนดยืนราคา 15 วัน",
            "แบ่งชำระ 3 งวด (40:30:30)"
        ];
        notesField.value = notes[Math.floor(Math.random() * notes.length)];
    }

    if (typeof calculateTotal === "function") calculateTotal();
}
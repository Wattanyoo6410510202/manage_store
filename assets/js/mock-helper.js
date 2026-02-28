/**
 * Mock Data Helper (Standard Edition)
 * รวมฐานข้อมูลชื่อ-นามสกุลไทย และบริษัทที่นิยมใช้ในการทดสอบระบบ
 */
const MockHelper = {
    // ฐานข้อมูลชื่อ (คละชาย-หญิง)
    names: [
        "สมชาย", "วิมล", "กิตติเดช", "นพรัตน์", "สิริพร", "อานนท์", "ปิยบุตร", "วรัญญา", "ชลลดา", "ธนบดี",
        "เกรียงศักดิ์", "ณัฐพงศ์", "สุพรรณษา", "พงศธร", "กัญญารัตน์", "จตุพล", "อัครเดช", "รุ่งโรจน์", "นันทนา", "ชัยยุทธ",
        "ทิพวรรณ", "บุญส่ง", "ประเสริฐ", "นงลักษณ์", "ธนาวัฒน์", "ศิริชัย", "สุธา", "ภาณุพงศ์", "ธรรศ", "พรรณราย",
        "กมล", "พิชญ", "มธุรส", "ชูชาติ", "มานพ", "ศิรินทร์", "ยุพดี", "พวงทอง", "สัญชัย", "เอกราช"
        // ... (สามารถเพิ่มได้เรื่อยๆ)
    ],

    // ฐานข้อมูลนามสกุล
    surnames: [
        "ใจดี", "มั่งคั่ง", "แสงทอง", "ประเสริฐยิ่ง", "รักชาติ", "เจริญผล", "วงศ์สุวรรณ", "กมลวานิช", "รุ่งเรืองกิจ", "ทองคำ",
        "มิตรรักษ์", "ศรีสวัสดิ์", "พงษ์พาณิชย์", "วัฒนสิริ", "ปัญญาดี", "ดีเลิศ", "งามจิต", "ไพศาล", "สิริโชติ", "บุญเกื้อ",
        "ทรัพย์อนันต์", "คงกระพัน", "สุขเกษม", "มั่นคง", "ไชยชนะ", "เจริญศรี", "พานิชกิจ", "รัตนากร", "ศิริพงษ์", "สุวรรณรัตน์"
    ],

    // ฐานข้อมูลประเภทธุรกิจ + ชื่อบริษัท
    bizPrefix: ["บจก.", "หจก.", "บมจ.", "ร้าน", "กลุ่มวิสาหกิจ"],
    bizNames: [
        "ไทยเจริญ", "นำเกียรติ", "สยามเทรดดิ้ง", "เทคโนวิศวกรรม", "นวัตกรรมล้ำสมัย", "เกษตรยั่งยืน", "ก่อสร้างรวดเร็ว",
        "ทรัพย์อนันต์", "ซีพีดี ดีไซน์", "สมาร์ทโซลูชั่น", "พรีเมี่ยมฟู้ด", "ลอจิสติกส์ไทย", "อสังหาพัฒนากิจ", "กรีนเนอร์ยี่",
        "แมชชีนเนอรี่", "รวมมิตรบริการ", "โกลบอลเน็ตเวิร์ค", "สิริพาณิชย์", "คลาสสิคอินทีเรีย", "พลาสติกไทย"
    ],

    // สุ่มตัวเลข
    randomDigits: (length) => {
        let result = '';
        for (let i = 0; i < length; i++) result += Math.floor(Math.random() * 10);
        return result;
    },

    // เลือกตัวอย่างแบบสุ่มจาก Array
    pick: function(list) {
        return list[Math.floor(Math.random() * list.length)];
    },

    // ฟังก์ชันสร้างที่อยู่สมจริง
    generateAddress: function() {
        const road = ["พระราม 2", "สุขุมวิท", "ลาดพร้าว", "พหลโยธิน", "รัชดาภิเษก", "รามคำแหง", "นวมินทร์", "บางนา-ตราด"];
        const sub = ["แขวงจอมพล", "ต.ในเมือง", "แขวงสวนหลวง", "ต.บางรัก", "ต.ตลาดขวัญ", "ต.สำโรง"];
        const district = ["เขตจตุจักร", "อ.เมือง", "เขตประเวศ", "อ.เมืองเชียงใหม่", "อ.ปากเกร็ด"];
        const provinces = ["กรุงเทพฯ", "เชียงใหม่", "ชลบุรี", "นนทบุรี", "ขอนแก่น", "สมุทรปราการ", "ภูเก็ต"];
        
        return `${Math.floor(Math.random() * 888) + 1}/${Math.floor(Math.random() * 50)} ซ. ${Math.floor(Math.random() * 99) + 1} ถ.${this.pick(road)} ${this.pick(sub)} ${this.pick(district)} จ.${this.pick(provinces)} ${Math.floor(Math.random() * 10000) + 10000}`;
    },

    /**
     * สำหรับสุ่มข้อมูลลูกค้า (Customer)
     */
    customer: function() {
        const name = this.pick(this.names);
        const surname = this.pick(this.surnames);
        return {
            name: `${this.pick(this.bizPrefix)} ${this.pick(this.bizNames)} (${this.randomDigits(2)})`,
            contact: `คุณ${name} ${surname}`,
            taxId: (Math.random() < 0.5 ? "0" : "3") + this.randomDigits(12),
            phone: "0" + this.pick(["8", "9", "6"]) + this.randomDigits(8),
            email: `test@${this.pick(['gmail.com', 'outlook.com', 'company.co.th'])}`,
            address: this.generateAddress()
        };
    },

    /**
     * สำหรับสุ่มข้อมูลผู้ใช้ (User)
     */
    user: function() {
        const fname = this.pick(this.names);
        const userPrefix = ["staff", "admin", "user", "manager"];
        return {
            fullname: `คุณ${fname} ${this.pick(this.surnames)}`,
            username: this.pick(userPrefix) + this.randomDigits(3),
            role: this.pick(["admin", "staff", "gm", "viewer"])
        };
    }
};
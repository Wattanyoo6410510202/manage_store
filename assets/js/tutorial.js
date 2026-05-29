const driver = window.driver.js.driver;

const driverConfig = {
  showProgress: true,
  animate: true,
  doneBtnText: 'เสร็จสิ้น',
  closeBtnText: 'ปิด',
  nextBtnText: 'ถัดไป',
  prevBtnText: 'ย้อนกลับ',
};

function startTutorial() {
  const currentPage = window.location.pathname.split("/").pop();
  let steps = [];

  // Default steps for Header & Sidebar
  const commonSteps = [
    {
      element: '#btn-tutorial',
      popover: {
        title: 'ปุ่มสอนใช้งาน',
        description: 'กดตรงนี้เพื่อเริ่มบทเรียนสอนการใช้งานในแต่ละหน้าครับ',
        side: "bottom",
        align: 'start'
      }
    },
    {
      element: '#sidebar',
      popover: {
        title: 'เมนูหลัก',
        description: 'แถบเมนูด้านซ้ายสำหรับเข้าถึงส่วนต่างๆ ของระบบ',
        side: "right",
        align: 'start'
      }
    }
  ];

  if (currentPage === 'index.php' || currentPage === '') {
    steps = [
      ...commonSteps,
      {
        element: '.grid.grid-cols-2',
        popover: {
          title: 'สร้างเอกสารด่วน',
          description: 'คุณสามารถสร้าง ใบเสนอราคา, ใบขอซื้อ, ใบสั่งซื้อ หรือใบแจ้งหนี้ ได้ทันทีจากปุ่มเหล่านี้',
          side: "bottom",
          align: 'start'
        }
      },
      {
        element: '#customerTable_wrapper',
        popover: {
          title: 'ตารางรายชื่อลูกค้า',
          description: 'แสดงรายชื่อลูกค้าทั้งหมดในระบบ คุณสามารถคลิกที่แถวเพื่อเลือกเข้าทำรายการ',
          side: "top",
          align: 'start'
        }
      },
      {
        element: '#customerFormSection',
        popover: {
          title: 'จัดการข้อมูลลูกค้า',
          description: 'ส่วนสำหรับเพิ่มหรือแก้ไขข้อมูลลูกค้า',
          side: "left",
          align: 'start'
        }
      }
    ];
  } else if (currentPage === 'e_service.php') {
    steps = [
        ...commonSteps,
        {
          element: '#nav-home',
          popover: {
            title: 'หน้าหลัก E-Service',
            description: 'รวมบริการอิเล็กทรอนิกส์ต่างๆ เช่น การแจ้งซ่อม การขอซื้อ',
            side: "bottom",
            align: 'start'
          }
        }
    ];
  } else if (currentPage === 'request_buy.php') {
    steps = [
      ...commonSteps,
      {
        element: '#supplier_select',
        popover: {
          title: 'เลือกผู้ขาย',
          description: 'เลือกบริษัทที่คุณต้องการสั่งซื้อสินค้าด้วย ข้อมูลบริษัทจะแสดงในแถบด้านขวาครับ',
          side: "bottom",
          align: 'start'
        }
      },
      {
        element: 'div.grid.grid-cols-2.md\\:grid-cols-4',
        popover: {
          title: 'ข้อมูลเบื้องต้น',
          description: 'ระบุความสำคัญ วันที่ต้องการสินค้า และเงื่อนไขการชำระเงิน',
          side: "bottom",
          align: 'start'
        }
      },
      {
        element: '#expense_card_content',
        popover: {
          title: 'งบประมาณ',
          description: 'ระบุประเภทค่าใช้จ่ายและงบประมาณที่เกี่ยวข้อง เพื่อการตรวจสอบความถูกต้อง',
          side: "top",
          align: 'start'
        }
      },
      {
        element: '#itemsTable',
        popover: {
          title: 'รายการสินค้า',
          description: 'เพิ่มรายการสินค้าที่คุณต้องการสั่งซื้อ ระบุจำนวนและราคาต่อหน่วย',
          side: "top",
          align: 'start'
        }
      },
      {
        element: '#grandtotal_display',
        popover: {
          title: 'ยอดรวมสุทธิ',
          description: 'ระบบจะคำนวณยอดรวม ภาษี และหัก ณ ที่จ่าย ให้อัตโนมัติครับ',
          side: "top",
          align: 'start'
        }
      }
    ];
  } else {
    steps = [
        ...commonSteps,
        {
            popover: {
                title: 'พร้อมเรียนรู้หรือยัง?',
                description: 'ในหน้านี้ยังไม่มีบทเรียนเฉพาะเจาะจง แต่คุณสามารถสำรวจเมนูต่างๆ ได้ด้วยตัวเองครับ',
            }
        }
    ];
  }

  const driverObj = driver({
    ...driverConfig,
    steps: steps
  });

  driverObj.drive();
}

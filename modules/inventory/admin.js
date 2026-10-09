/**
 * modules/inventory/admin.js
 *
 * ลงทะเบียน route ของโมดูล inventory
 * ไฟล์นี้ถูกโหลดอัตโนมัติโดย index.php (scandir modules/) จึงไม่ต้องแก้ไฟล์กลาง
 *
 * เส้นทางตั้งชื่อให้ตรงกับ module= ของระบบเดิม เพื่อให้ลิงก์ที่ผู้ใช้คุ้นเคย
 * และที่จดไว้ยังเดาถูก
 *
 * ⚠️ โมดูลนี้ไม่มีฟังก์ชันช่วยฝั่งหน้าเว็บเลย — ตารางทุกหน้าใช้ data-format
 * ของแกนได้หมด ถ้าจะเพิ่มฟังก์ชันใหม่ต้องแน่ใจก่อนว่าเทมเพลตเรียกใช้จริง
 * (ของเดิมมีฟังก์ชันเอกสารซื้อ/ขายของ oas ค้างอยู่ 11 ตัวโดยไม่มีใครเรียก)
 */
EventManager.on('router:initialized', () => {
    RouterManager.register('/inventory-setup', {
        template: 'inventory/inventories.html',
        title: '{LNG_List of} {LNG_Inventory}',
        requireAuth: true
    });
    RouterManager.register('/inventory-write', {
        template: 'inventory/product-edit.html',
        title: '{LNG_Inventory}',
        menuPath: '/inventory-setup',
        requireAuth: true
    });
    // เลขครุภัณฑ์ของพัสดุหนึ่งรายการ ใช้ ?id=<พัสดุ> ร่วมกับหน้าแก้ไข
    // และผูก menuPath ไว้ที่ทะเบียนพัสดุ เมนูด้านข้างจึงยังชี้ที่เดิม
    RouterManager.register('/inventory-barcode', {
        template: 'inventory/product-items.html',
        title: '{LNG_Serial/Registration No.}',
        menuPath: '/inventory-setup',
        requireAuth: true
    });
    RouterManager.register('/inventory-categories', {
        template: 'inventory/categories.html',
        title: '{LNG_Category}',
        requireAuth: true
    });
    RouterManager.register('/inventory-settings', {
        template: 'inventory/settings.html',
        title: '{LNG_Module Settings}',
        requireAuth: true
    });
    RouterManager.register('/inventory-import', {
        template: 'inventory/import.html',
        title: '{LNG_Import}',
        menuPath: '/inventory-setup',
        requireAuth: true
    });
});

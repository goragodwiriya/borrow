/**
 * modules/borrow/admin.js
 *
 * ลงทะเบียน route และ helper ของโมดูลยืม-คืน
 */
EventManager.on('router:initialized', () => {
  RouterManager.register('/', {
    template: 'borrow/dashboard.html',
    title: '{LNG_Dashboard}',
    requireAuth: true
  });

  RouterManager.register('/borrow', {
    template: 'borrow/index.html',
    title: '{LNG_Add Borrow}',
    menuPath: '/borrow',
    requireAuth: true
  });

  RouterManager.register('/myborrow', {
    template: 'borrow/myborrow.html',
    title: '{LNG_My Borrow}',
    requireAuth: true
  });

  RouterManager.register('/borrow-inventory', {
    template: 'borrow/inventory.html',
    title: '{LNG_List of} {LNG_Equipment}',
    requireAuth: true
  });

  RouterManager.register('/borrow-report', {
    template: 'borrow/report.html',
    title: '{LNG_Borrow Report}',
    requireAuth: true
  });

  RouterManager.register('/borrow-order', {
    template: 'borrow/order.html',
    title: '{LNG_Transaction details}',
    menuPath: '/borrow-report',
    requireAuth: true
  });

  RouterManager.register('/borrow-settings', {
    template: 'borrow/settings.html',
    title: '{LNG_Module Settings} {LNG_Borrow} & {LNG_Return}',
    requireAuth: true
  });
});

/**
 * คอลัมน์กำหนดคืน เน้นสีแดงเมื่อครบกำหนดคืนแล้วแต่ยังไม่คืน
 * (แถวที่เซิร์ฟเวอร์ตั้ง overdue = 1)
 *
 * @param {HTMLElement} cell
 * @param {*} rawValue
 * @param {Object} rowData
 * @param {Object} attributes
 */
function formatBorrowReturnDate(cell, rawValue, rowData, attributes) {
  if (!rawValue) {
    cell.textContent = '-';
    return;
  }

  const text = TableManager.formatValue(rawValue, 'date');

  if (rowData && parseInt(rowData.overdue, 10) === 1) {
    const span = document.createElement('span');
    span.className = 'borrow-overdue';
    span.textContent = text;
    span.title = Now.translate('Un-Returned items');
    cell.innerHTML = '';
    cell.appendChild(span);
    return;
  }

  cell.textContent = text;
}

/**
 * ตรวจสอบจำนวนที่ขอยืมของแต่ละแถวในตารางรายการพัสดุ ไม่ให้เกินจำนวนคงเหลือ
 * (stock = -1 คือไม่นับสต็อก ยืมได้ไม่จำกัด) เรียกโดย LineItemsManager
 * ผ่าน data-on-calculate ทุกครั้งที่เพิ่ม รวม แก้ไข หรือลบรายการ
 *
 * @param {Object} ctx {items, instance}
 *
 * @return {Object}
 */
window.calculateBorrowItems = function(ctx = {}) {
  const {items = [], instance} = ctx;

  // ค่าที่อ่านจากช่องกรอกอาจมีตัวคั่นหลักพัน ตัดออกก่อนแปลงเป็นตัวเลข
  const toNumber = (value) => parseFloat(String(value ?? '').replace(/,/g, ''));

  const updatedItems = items.map((item, index) => {
    const stock = toNumber(item.stock);
    const unlimited = !Number.isFinite(stock) || stock < 0;
    let quantity = Math.floor(toNumber(item.quantity));

    if (!Number.isFinite(quantity) || quantity < 1) {
      quantity = 1;
    }
    if (!unlimited) {
      quantity = Math.max(1, Math.min(quantity, Math.floor(stock)));
    }

    // จำกัดจำนวนสูงสุดที่กรอกได้ ตามจำนวนคงเหลือของแถวนั้น (เหมือนระบบเดิม)
    // ช่องจำนวนที่สร้างโดย ElementManager ไม่มี data-field ค้นหาจากชื่อ items[n][quantity] ด้วย
    const rowElement = instance?.rows?.[index]?.element;
    const input = rowElement?.querySelector('input[data-field="quantity"], input[name*="[quantity]"]');
    if (input) {
      const elementInstance = window.ElementManager?.getInstanceByElement?.(input);
      if (unlimited) {
        input.removeAttribute('max');
      } else {
        input.max = stock;
      }
      if (elementInstance?.config) {
        elementInstance.config.max = unlimited ? null : stock;
      }
    }

    return {quantity};
  });

  return {items: updatedItems};
};

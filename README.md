# E-Borrow — ระบบยืม-คืนพัสดุออนไลน์

![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-InnoDB-4479A1?logo=mysql&logoColor=white)
![Version](https://img.shields.io/badge/version-7.0.4-4f46e5)
![License](https://img.shields.io/badge/license-MIT-green)

**E-Borrow** คือเว็บแอปพลิเคชันสำหรับบริหารการ **ยืม-คืนพัสดุ/ครุภัณฑ์** ขององค์กร โรงเรียน หรือหน่วยงาน
ตั้งแต่การยื่นคำขอ อนุมัติ ตัดสต็อก ไปจนถึงรับคืนและออกรายงาน พร้อมแจ้งเตือนผ่านอีเมล LINE และ Telegram

สร้างบน [Now.js](https://github.com/goragodwiriya/nowjs) (front-end) และ [Kotchasan](https://www.kotchasan.com/) (PHP back-end)
รองรับภาษา **ไทย** และ **อังกฤษ** ใช้งานบนมือถือได้ในรูปแบบ PWA

---

## สารบัญ

- [คุณสมบัติ](#คุณสมบัติ)
- [ความต้องการของระบบ](#ความต้องการของระบบ)
- [การติดตั้ง](#การติดตั้ง)
- [การอัปเกรด](#การอัปเกรด)
- [การใช้งานเบื้องต้น](#การใช้งานเบื้องต้น)
- [โครงสร้างโปรเจ็ค](#โครงสร้างโปรเจ็ค)
- [ฐานข้อมูล](#ฐานข้อมูล)
- [การตั้งค่า](#การตั้งค่า)
- [สำหรับนักพัฒนา](#สำหรับนักพัฒนา)
- [ความปลอดภัย](#ความปลอดภัย)
- [การมีส่วนร่วม](#การมีส่วนร่วม)
- [ลิขสิทธิ์](#ลิขสิทธิ์)

---

## คุณสมบัติ

### การยืม-คืน
- ยื่นใบยืมหลายรายการต่อหนึ่งใบ เลือกพัสดุจากคลังด้วย autocomplete
- เลขที่ใบยืมอัตโนมัติ กำหนดรูปแบบได้ (ค่าเริ่มต้น `B%Y%M-` + เลข 4 หลัก)
- สถานะรายการ: **รออนุมัติ → อนุมัติ / ไม่อนุมัติ → คืนแล้ว**
- ผู้ยืมดูประวัติการยืมของตนเอง (My Borrow)
- ผู้ดูแลจัดการสถานะ ตรวจรับคืน และดูสรุปจากหน้า Dashboard
- รายงานการยืม-คืน

### คลังพัสดุ (Inventory)
- จัดการพัสดุ หมวดหมู่ ประเภท รุ่น และหน่วยนับ
- ติดตามสต็อกและความเคลื่อนไหว (stock movement) พร้อมต้นทุนแบบ cost layer
- แนบรูปภาพสินค้า (jpg, jpeg, png, webp)
- **นำเข้าพัสดุจากไฟล์ CSV** (มีไฟล์ตัวอย่าง [product.csv](product.csv) และปุ่มดาวน์โหลดแม่แบบในหน้านำเข้า)

### เอกสารและการส่งออก
- พิมพ์ใบยืม / สลิป / แผ่นงาน จากแม่แบบ HTML ที่แก้ไขได้ (`modules/export/views/`)
- ส่งออกข้อมูลเป็น CSV

### การแจ้งเตือน
- อีเมล, **LINE** และ **Telegram** แจ้งผู้ที่เกี่ยวข้องเมื่อมีใบยืมใหม่หรือสถานะเปลี่ยน
- ผู้ใช้ผูกบัญชี LINE / Telegram กับโปรไฟล์ของตนเอง (webhook อยู่ใน `line/` และ `telegram/`)

### ผู้ใช้และระบบ
- สมัครสมาชิก / เข้าสู่ระบบ / ลืมรหัสผ่าน / ยืนยันอีเมล / เข้าสู่ระบบผ่านโซเชียล
- กำหนดสถานะผู้ใช้และสิทธิ์การใช้งาน, ป้องกัน brute-force ด้วย login attempt limit
- จัดการภาษา เมนู หมวดหมู่ และตั้งค่าระบบผ่านหน้าเว็บ
- บันทึกประวัติการใช้งาน (usage log)
- ผู้ช่วย AI เสริม (Claude, Gemini, DeepSeek, OpenAI-compatible) ผ่าน `Gcms/Ai`
- PWA: ติดตั้งบนหน้าจอโฮมได้ มี service worker และ manifest

---

## ความต้องการของระบบ

| รายการ | เวอร์ชัน |
|---|---|
| PHP | 7.4 ขึ้นไป (ต้องมี `mysqli`/PDO MySQL, `mbstring`, `gd`) |
| ฐานข้อมูล | MySQL หรือ MariaDB (InnoDB, utf8mb4) |
| เว็บเซิร์ฟเวอร์ | Apache พร้อม `mod_rewrite` และอนุญาต `.htaccess` |
| Node.js | เฉพาะนักพัฒนาที่ต้องการ build ไฟล์ front-end เอง |

> ไฟล์ `Now/dist/*` (front-end ที่ build แล้ว) ถูกรวมมาในโปรเจ็คแล้ว ผู้ใช้ทั่วไปไม่ต้องติดตั้ง Node.js

---

## การติดตั้ง

1. **ดาวน์โหลดโปรเจ็ค**

   ```bash
   git clone https://github.com/goragodwiriya/borrow.git
   cd borrow
   ```

   หรือดาวน์โหลดเป็น ZIP แล้วแตกไฟล์ไปยัง document root ของเว็บเซิร์ฟเวอร์

2. **สร้างฐานข้อมูล** ว่างชุดหนึ่งใน MySQL/MariaDB (collation `utf8mb4_unicode_ci`)

3. **กำหนดสิทธิ์เขียน** ให้โฟลเดอร์ `settings/` และ `datas/` (และไฟล์ที่ตัวติดตั้งจะสร้างในนั้น)

   ```bash
   chmod -R 775 settings datas
   ```

4. **เปิดตัวติดตั้งผ่านเบราว์เซอร์**

   ```
   http://localhost/borrow/install/
   ```

   ทำตามขั้นตอน: ตรวจความพร้อม → ตั้งค่าฐานข้อมูล → สร้างตารางและนำเข้าภาษา → สร้างผู้ดูแลระบบสูงสุด

5. **ลบหรือปิดการเข้าถึงโฟลเดอร์ `install/`** หลังติดตั้งเสร็จ

### ติดตั้งผ่านบรรทัดคำสั่ง (ทางเลือก)

```bash
php install/cli-fresh.php <dbname> [prefix] --admin=<username> --password=<password>
```

---

## การอัปเกรด

1. สำรองฐานข้อมูลและโฟลเดอร์ `settings/`, `datas/`
2. เขียนทับไฟล์โปรเจ็คด้วยรุ่นใหม่ (ห้ามทับ `settings/` และ `datas/`)
3. เปิด `http://your-host/install/` ระบบจะตรวจพบรุ่นเก่าและเข้าสู่โหมดปรับรุ่นเอง
   ตัวปรับรุ่นจะ **ตรวจก่อนแตะฐานข้อมูล** (`install/preflight.php`) และไม่ลบหรือเปลี่ยนชื่อข้อมูลธุรกิจ

ตัวปรับรุ่นของแต่ละโมดูลอยู่ใน `modules/<module>/install/upgrade.php`

---

## การใช้งานเบื้องต้น

1. เข้าสู่ระบบด้วยบัญชีผู้ดูแลที่สร้างตอนติดตั้ง
2. **เพิ่มหมวดหมู่ ประเภท รุ่น หน่วยนับ** ที่เมนูคลังพัสดุ
3. **เพิ่มพัสดุ** ทีละรายการ หรือนำเข้าจาก CSV ตามรูปแบบใน [product.csv](product.csv)

   ```csv
   product_no,topic,description,category,model,type,unit,stock,is_active
   P87-0057,"ASUS A550JX",,เครื่องใช้ไฟฟ้า,Asus,เครื่องคอมพิวเตอร์,เครื่อง,5,1
   ```

4. ผู้ใช้ทั่วไปสร้างใบยืมที่เมนู **ยืม** ผู้ดูแลอนุมัติหรือไม่อนุมัติที่หน้าจัดการใบยืม
5. เมื่อรับพัสดุคืน ผู้ดูแลเปลี่ยนสถานะเป็น **คืนแล้ว**
6. ดูรายงานและพิมพ์ใบยืมได้จากหน้ารายงาน

---

## โครงสร้างโปรเจ็ค

```
borrow/
├── index.php            หน้าหลักของเว็บ
├── api.php              จุดเรียก API (ใช้กับ front-end และ Bearer token)
├── export.php           ดาวน์โหลด/ส่งออกไฟล์ (CSV ฯลฯ)
├── load.php             กำหนดค่าคงที่ (ROOT_PATH, DEBUG, DATA_FOLDER ...)
├── manifest.json        PWA manifest
├── service-worker.js    PWA service worker
├── Kotchasan/           PHP framework
├── Now/                 Now.js framework (source ใน Now/js, build แล้วใน Now/dist)
├── Gcms/                ไลบรารีกลางของ GCMS (Config, Api, Line, Telegram, Sms, Ai ...)
├── modules/
│   ├── index/           ระบบสมาชิก ผู้ใช้ สิทธิ์ ภาษา เมนู ตั้งค่า dashboard
│   ├── borrow/          ใบยืม-คืน สถานะ รายงาน การแจ้งเตือน
│   ├── inventory/       พัสดุ หมวดหมู่ สต็อก นำเข้า/ส่งออก
│   ├── export/          แม่แบบพิมพ์เอกสาร (print, sheet, slip)
│   ├── download/        ระบบอัปโหลด/ดาวน์โหลดไฟล์
│   └── timeline/        ไทม์ไลน์กิจกรรม
├── templates/           แม่แบบหน้าเว็บ (HTML) ของ front-end
├── language/            ไฟล์ภาษา th / en
├── install/             ตัวติดตั้ง ตัวปรับรุ่น และสคริปต์ CLI
├── line/  telegram/     webhook ของ LINE และ Telegram
├── settings/            ค่ากำหนด (config.php, database.php) — สร้างตอนติดตั้ง
└── datas/               ข้อมูลที่ผู้ใช้อัปโหลด แคช รูปภาพ
```

โมดูลแต่ละตัวเป็นอิสระ มี `controllers/`, `models/`, `install/database.sql` และ `install/upgrade.php` ของตัวเอง
จึงคัดลอกไปใช้ในโปรเจ็ค Now.js อื่นได้

---

## ฐานข้อมูล

ตารางทั้งหมดใช้ prefix ที่กำหนดตอนติดตั้ง (ค่าเริ่มต้น `app_`)

| โมดูล | ตาราง |
|---|---|
| core | `category`, `language`, `logs`, `login_attempt`, `migration`, `number`, `user`, `user_meta`, `user_session` |
| borrow | `borrow`, `borrow_items` |
| inventory | `inventory`, `inventory_items`, `inventory_meta`, `inventory_stock`, `inventory_stock_movement`, `inventory_cost_layer`, `inventory_cost_allocation` |
| timeline | `timeline_idempotency` |

นิยามตารางของแต่ละโมดูลอยู่ที่ `modules/<module>/install/database.sql` ที่เดียว
ทั้งตัวติดตั้งและตัวปรับรุ่นอ่านจากไฟล์นั้น

---

## การตั้งค่า

| ไฟล์ | หน้าที่ |
|---|---|
| `settings/database.php` | การเชื่อมต่อฐานข้อมูลและ prefix |
| `settings/config.php` | ชื่อเว็บ เขตเวลา รูปแบบเลขที่ใบยืม ขนาดรูปพัสดุ ฯลฯ |
| `load.php` | `DEBUG` (0 = เฉพาะ error ร้ายแรง, 2 = แสดงบนหน้าจอ ใช้เฉพาะตอนพัฒนา) และ `LOG_DESTINATION` |

ค่าหลักใน `config.php`:

```php
'web_title'      => 'E-Borrow',
'timezone'       => 'Asia/Bangkok',
'borrow_prefix'  => 'B%Y%M-',   // นำหน้าเลขที่ใบยืม
'borrow_no'      => '%04d',     // รูปแบบตัวเลข
'inventory_w'    => 600,        // ความกว้างรูปพัสดุ (px)
```

การแจ้งเตือนอีเมล / LINE / Telegram / SMS และผู้ให้บริการ AI ตั้งค่าได้จากหน้าตั้งค่าระบบหลังเข้าสู่ระบบ
(Telegram: `telegram_chat_id` ฯลฯ)

---

## สำหรับนักพัฒนา

```bash
npm install
npm run dev          # Vite dev server
npm run build        # build ทุกชุดไฟล์ใน Now/dist
npm run build:core   # build เฉพาะ core
npm test             # vitest
```

สคริปต์ CLI ในโฟลเดอร์ `install/`:

| สคริปต์ | ใช้ทำอะไร |
|---|---|
| `cli-fresh.php` | ติดตั้งใหม่ลงฐานเปล่า |
| `cli-upgrade.php` | ปรับรุ่นฐานที่มีอยู่ |
| `cli-verify.php` | ตรวจสคีมาที่ติดตั้งกับที่ควรเป็น |
| `cli-language.php` | นำเข้า/ซิงก์ภาษา |
| `cli-check-core.php`, `cli-sync-core.php` | ตรวจและซิงก์ไฟล์ core กับต้นแบบ |
| `cli-testdb.php`, `cli-testsecurity.php` | ทดสอบฐานข้อมูลและความปลอดภัย |

แนวทางที่ใช้ในโปรเจ็ค:

- ฝั่ง PHP ใช้ namespace ตามโมดูล เช่น `Borrow\Order\Model`, `Inventory\Items\Controller`
- ฝั่ง front-end เขียนเป็น HTML template + `admin.js` ของแต่ละโมดูล (Now.js)
- ข้อความทั้งหมดผ่านระบบภาษา (`language/th.json`, `language/en.json`) ไม่ฮาร์ดโค้ด

---

## ความปลอดภัย

- `.htaccess` ปิด directory listing และบล็อกการเข้าถึงไฟล์ `.md`, `.sql`, `.log`, `.bak` ฯลฯ
- รหัสผ่านถูกเก็บแบบ hash, จำกัดจำนวนครั้งการเข้าสู่ระบบผิด
- **หลังติดตั้งต้องลบหรือปิดโฟลเดอร์ `install/`**
- ใช้ `DEBUG = 0` บน production และอย่า commit `settings/` หรือ `datas/` (ถูกใส่ใน `.gitignore` แล้ว)
- รหัสผ่านเริ่มต้นของ `cli-fresh.php` (`admin`) ใช้สำหรับทดสอบเท่านั้น ต้องระบุ `--password` ที่ปลอดภัยเสมอเมื่อใช้งานจริง

พบช่องโหว่ด้านความปลอดภัย กรุณาแจ้งผู้พัฒนาทางอีเมลโดยตรงก่อนเปิดเผยสาธารณะ

---

## การมีส่วนร่วม

ยินดีรับ Issue และ Pull Request

1. Fork โปรเจ็ค
2. สร้าง branch ใหม่ `git checkout -b feature/my-feature`
3. Commit การเปลี่ยนแปลง
4. Push แล้วเปิด Pull Request พร้อมอธิบายสิ่งที่แก้ไข

---

## ลิขสิทธิ์

เผยแพร่ภายใต้สัญญาอนุญาต [MIT](LICENSE) — Copyright © 2026 [Goragod Wiriya](https://www.goragod.com)

สร้างด้วย [Kotchasan Framework](https://www.kotchasan.com/) และ [Now.js](https://github.com/goragodwiriya/nowjs)

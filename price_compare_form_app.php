<?php
// เนื้อหาแบบฟอร์มทำใบเปรียบเทียบราคา (แสดงใน iframe ของ price_compare_form.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    echo "<script>window.top.location.href='login.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>แบบฟอร์มเปรียบเทียบราคาสินค้า/บริการ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:'Sarabun',Tahoma,sans-serif;font-size:14px;color:#1f2937;background:#e9eef5}
.toolbar{position:sticky;top:0;z-index:9;display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 20px;background:#1e3a5f}
.toolbar b{color:#fff;margin-right:auto;font-size:16px}
.btn{font:inherit;font-weight:600;border:0;border-radius:6px;padding:7px 14px;cursor:pointer;background:#fff;color:#1e3a5f}
.btn.green{background:#16a34a;color:#fff}
.btn.red{background:#fee2e2;color:#b91c1c}
.btn.yellow{background:#fde68a;color:#78350f}
.page{max-width:1400px;margin:20px auto;padding:24px 28px;background:#fff;box-shadow:0 4px 16px rgba(0,0,0,.08)}
.head{display:flex;justify-content:space-between;align-items:flex-start}
.logo{width:180px;height:70px;border:1px dashed #bbb;display:flex;align-items:center;justify-content:center;color:#999;cursor:pointer;position:relative;font-size:12px;text-align:center}
.logo.has{border-color:transparent;justify-content:flex-start}
.logo img{max-width:100%;max-height:100%}
.logo .x{position:absolute;top:-10px;right:-10px;display:none;background:#fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.logo:hover .x{display:block}
.code{text-align:right;font-size:13px;line-height:1.8}
.code input{width:150px;border-bottom:1px dotted #555}
.internal{display:inline-block;border:1px solid #333;padding:2px 8px;margin-top:6px;font-size:12px}
h1{text-align:center;font-size:20px;margin:6px 0 10px}
.meta{display:flex;gap:24px;justify-content:center;margin-bottom:12px}
.meta input{width:170px;border-bottom:1px dotted #555}
.hint{background:#fffbeb;border:1px solid #fde68a;padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-bottom:12px}
.card{padding:12px 14px;border-radius:10px;background:#f8fafc;border-left:6px solid #94a3b8}
.card .l{font-size:13px;color:#64748b}
.card .v{font-size:22px;font-weight:700;line-height:1.3}
.card .s{font-size:12px;color:#475569}
.card.ok{background:#ecfdf5;border-color:#16a34a}.card.ok .v{color:#15803d}
.card.warn{background:#fef2f2;border-color:#dc2626}.card.warn .v{color:#b91c1c}
.card.info{background:#eff6ff;border-color:#2563eb}.card.info .v{color:#1d4ed8}
.bars{margin-bottom:14px}
.bar{display:flex;align-items:center;gap:8px;margin:4px 0}
.bar .n{width:150px;text-align:right;font-weight:600}
.bar .t{flex:1;background:#f1f5f9;border-radius:4px;height:22px}
.bar .f{height:100%;border-radius:4px;background:#94a3b8;color:#fff;font-size:12px;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;white-space:nowrap}
.bar.best .f{background:#16a34a}
.method{margin:8px 0}
.box{display:inline-block;margin:0 14px 0 6px}
.box::before{content:'☐';font-size:17px;margin-right:4px}
.box.on{font-weight:700}.box.on::before{content:'☑';color:#16a34a}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #475569;padding:2px 4px;text-align:center;vertical-align:middle}
th{background:#f1f5f9;font-weight:600;font-size:13px}
td.lbl{text-align:left;font-weight:600;padding-left:8px}
td.calc{text-align:right;background:#f8fafc;font-weight:600}
td.net{font-size:15px;font-weight:700}
.best{background:#dcfce7!important;color:#166534}
.na{color:#b45309;font-size:12px}
input,select{font:inherit;font-size:13px;width:100%;padding:3px;border:1px solid transparent;border-radius:3px;background:transparent;color:inherit}
input:hover,select:hover{border-color:#cbd5e1}
input:focus,select:focus{outline:none;border-color:#3b82f6;background:#eff6ff}
.money{text-align:right}
.x{border:0;background:none;color:#dc2626;cursor:pointer;font-size:14px}
input[type=radio]{width:18px;height:18px;accent-color:#16a34a;cursor:pointer}
.att{font-size:12px;text-align:left;padding:2px}
.att .f{display:flex;align-items:center;gap:4px}
.att a{color:#1d4ed8;cursor:pointer;text-decoration:underline;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.att .missing{color:#b45309}
.btn-s{font:inherit;font-size:12px;border:1px dashed #3b82f6;background:#eff6ff;color:#1d4ed8;border-radius:4px;padding:1px 8px;cursor:pointer;margin-top:3px;width:100%}
.btn-s.read{border-color:#16a34a;background:#f0fdf4;color:#166534}
.reason{margin-top:8px;display:flex;gap:6px;align-items:center}
.reason input{border-bottom:1px dotted #555;flex:1}
.notes{font-size:13px;margin-top:10px;line-height:1.7}
.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:36px;text-align:center}
.sign input{text-align:center}
/* หน้าต่างเทียบใบเสนอราคา */
.modal{position:fixed;inset:0;background:rgba(15,23,42,.88);z-index:50;display:none;flex-direction:column}
.modal.open{display:flex}
.modal-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 20px;color:#fff}
.modal-body{flex:1;display:grid;gap:10px;padding:0 10px 10px;overflow:auto}
.col{background:#fff;border-radius:8px;display:flex;flex-direction:column;min-width:0;overflow:hidden}
.col-h{padding:8px 12px;border-bottom:4px solid #94a3b8;font-weight:700;font-size:15px}
.col-h small{font-weight:400;font-size:13px}
.col.win .col-h{background:#dcfce7;border-color:#16a34a;color:#166534}
.col-b{flex:1;overflow:auto;display:flex;flex-direction:column;gap:8px;padding:8px;background:#f1f5f9}
.col-b iframe{width:100%;min-height:75vh;border:0;background:#fff}
.col-b img{width:100%;background:#fff}
.empty{margin:auto;color:#94a3b8;text-align:center;font-size:15px}
/* หน้าต่างอ่านราคา */
.dlg{position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:60;display:none;align-items:center;justify-content:center;padding:20px}
.dlg.open{display:flex}
.dlg-box{background:#fff;border-radius:12px;width:min(760px,100%);max-height:90vh;overflow:auto;padding:18px 22px;box-shadow:0 10px 40px rgba(0,0,0,.3)}
.dlg-box h3{margin:0 0 8px}
.dlg-box h4{margin:14px 0 4px}
.dlg .btn{border:1px solid #cbd5e1}
.prog{height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;margin:10px 0}
.prog div{height:100%;background:#2563eb;transition:width .2s}
.badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700}
.badge.high{background:#dcfce7;color:#166534}.badge.mid{background:#fef9c3;color:#854d0e}.badge.low{background:#fee2e2;color:#b91c1c}
.done-msg{background:#ecfdf5;border:1px solid #86efac;color:#166534;padding:6px 10px;border-radius:6px;margin:8px 0}
.frm{display:grid;grid-template-columns:130px 1fr;gap:8px 12px;align-items:center;margin:12px 0}
.frm input,.frm select{border:1px solid #cbd5e1;font-size:15px;padding:6px}
.cand{display:flex;align-items:center;gap:8px;padding:5px 8px;border:1px solid #e2e8f0;border-radius:6px;margin:4px 0;cursor:pointer}
.cand:hover{background:#f8fafc}
.cand.sel{border-color:#16a34a;background:#f0fdf4}
.cand .t{flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569}
.cand .a{font-weight:700;white-space:nowrap}
.tag{font-size:11px;padding:1px 6px;border-radius:4px;background:#e2e8f0;white-space:nowrap}
.tag.total{background:#16a34a;color:#fff}.tag.vat{background:#f59e0b;color:#fff}.tag.sub{background:#2563eb;color:#fff}.tag.disc{background:#a855f7;color:#fff}
pre.raw{white-space:pre-wrap;font-size:12px;background:#f8fafc;padding:8px;max-height:200px;overflow:auto}
.dlg-foot{display:flex;gap:8px;justify-content:flex-end;margin-top:14px}
@media print{
 @page{size:A4 landscape;margin:8mm}
 body{background:#fff;font-size:12px}
 .no-print{display:none!important}
 .page{box-shadow:none;margin:0;padding:0;max-width:none}
 .logo:not(.has){border:0}
 input,select{border:0!important;background:none!important;appearance:none;-webkit-appearance:none}
 input::-webkit-calendar-picker-indicator{display:none}
 .card .v{font-size:16px}
 *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
</head>
<body>

<div class="toolbar no-print">
  <b>📊 ระบบเปรียบเทียบราคาอัตโนมัติ</b>
  <button class="btn" onclick="addItem()">+ เพิ่มรายการ</button>
  <button class="btn" onclick="addVendor()">+ เพิ่มร้านค้า</button>
  <button class="btn yellow" onclick="openViewer()">📑 เทียบใบเสนอราคา</button>
  <button class="btn" onclick="exportFile()">💾 บันทึกเป็นไฟล์</button>
  <button class="btn" onclick="$('file').click()">📂 เปิดไฟล์</button>
  <button class="btn red" onclick="resetAll()">🗑 เริ่มใบใหม่</button>
  <button class="btn green" onclick="window.print()">🖨️ พิมพ์ / PDF</button>
</div>
<input type="file" id="file" accept=".json" hidden onchange="importFile(this)">
<input type="file" id="logoFile" accept="image/*" hidden onchange="setLogo(this)">
<input type="file" id="attFile" accept=".pdf,image/*" multiple hidden onchange="addFiles(this)">

<div class="page">
  <div class="head">
    <div class="logo" id="logo" onclick="$('logoFile').click()" title="คลิกเพื่อใส่/เปลี่ยนโลโก้"></div>
    <div class="code">
      Rev : <input type="date" data-m="rev"><br>
      <span class="internal">เอกสารใช้ภายใน (INTERNAL USE ONLY)</span>
    </div>
  </div>
  <h1>แบบฟอร์มเปรียบเทียบราคาสินค้า/บริการ</h1>
  <div class="meta">
    <label>เลขที่เอกสาร <input data-m="docNo"></label>
    <label>วันที่ <input type="date" data-m="date"></label>
  </div>

  <div class="hint no-print">💡 แนบใบเสนอราคาของแต่ละร้าน แล้วระบบจะอ่านราคาให้อัตโนมัติ ตรวจสอบตัวเลข แล้วกดยืนยัน ส่วนราคารวม VAT การจัดอันดับ ร้านที่แนะนำ และวิธีจัดซื้อ ระบบคำนวณให้เอง</div>

  <div class="cards" id="cards"></div>
  <div class="bars" id="bars"></div>

  <div class="method">1) วิธีการเสนอราคา
    <span class="box" id="m1">ตกลงราคา</span><span class="box" id="m2">สอบราคา</span><span class="box" id="m3">ประกวดราคา</span>
    <span class="no-print" style="color:#64748b">(ระบบเลือกให้ตามยอดเงิน)</span>
  </div>

  <table id="tbl"></table>

  <div class="reason"><b>เหตุผลประกอบการพิจารณา:</b><input data-m="reason" placeholder="เช่น ราคาต่ำสุด เงื่อนไขครบถ้วน รับประกัน 1 ปี"></div>

  <div class="notes">
    <u><b>หมายเหตุ</b></u><br>
    1. วิธีตกลงราคา คือ การซื้อหรือการจ้างครั้งหนึ่ง ซึ่งมีราคาไม่เกิน 100,000 บาท<br>
    2. วิธีสอบราคา คือ การซื้อหรือการจ้างครั้งหนึ่ง ซึ่งมีราคาเกิน 100,000 บาท แต่ไม่เกิน 2,000,000 บาท<br>
    3. วิธีประกวดราคา คือ การซื้อหรือการจ้างครั้งหนึ่ง ซึ่งมีราคาเกิน 2,000,000 บาท
  </div>

  <div class="sign">
    <div>ลงชื่อ..............................................ผู้จัดทำ<input data-m="s1"><input data-m="p1"></div>
    <div>ลงชื่อ..............................................ผู้ตรวจสอบ<input data-m="s2"><input data-m="p2"></div>
    <div>ลงชื่อ..............................................ผู้อนุมัติ<input data-m="s3"><input data-m="p3"></div>
  </div>
</div>

<div class="modal no-print" id="viewer">
  <div class="modal-head">
    <b>📑 เทียบใบเสนอราคาจริงของแต่ละร้าน <small style="font-weight:400;opacity:.8">ตรวจดูว่าราคาในเอกสารตรงกับที่กรอกในระบบ</small></b>
    <button class="btn" onclick="closeViewer()">✕ ปิด</button>
  </div>
  <div class="modal-body" id="vbody"></div>
</div>

<div class="dlg no-print" id="readDlg"><div class="dlg-box" id="readBox"></div></div>

<script>
const KEY = 'priceCompare_FO-PC6906004', LOGO_KEY = 'priceCompare_logo';
const MAXV = 5, DEFAULT_REV = '2026-06-27';
const PDF_JS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const PDF_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const TESS_JS = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';

const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2,8);
const blankVendor = n => ({id:uid(), name:n, vat:'inc', delivery:'', payment:'', warranty:''});
const blankItem = () => ({pr:'',dept:'',name:'',qty:'1',unit:'',budget:'',mid:'',prices:S.vendors.map(()=> '')});

function defaults(){ return {
  meta:{docNo:'', date:new Date().toISOString().slice(0,10), rev:DEFAULT_REV, reason:'',
        s1:'( นายเจนวิทย์ คชศิริ )', p1:'เจ้าหน้าที่จัดซื้อ',
        s2:'( นางสาว ภฤศญา สุวรรณวงศ์ )', p2:'ผู้จัดการฝ่ายบริหารสำนักงาน',
        s3:'( นางรุจิรา อังศุวรรณ )', p3:'ประธานเจ้าหน้าที่ฝ่ายการเงิน'},
  vendors:[blankVendor('ช่างมนูญ'), blankVendor('P and V'), blankVendor('โชคูเอ็กซ์')],
  items:[{pr:'',dept:'ซ่อมบำรุง',name:'งานย้ายแอร์และติดตั้ง โรงแรมเอส',qty:'1',unit:'งาน',budget:'',mid:'',
          prices:['17,334.00','23,968.00','22,898.00']}],
  choice:null, approve:null
};}
function migrate(s){
  s.meta = s.meta || {};
  if(!s.meta.rev) s.meta.rev = DEFAULT_REV;
  s.vendors.forEach(v => { if(!v.id) v.id = uid(); });
  return s;
}

let S; try{ S = JSON.parse(localStorage.getItem(KEY)); }catch(e){}
S = migrate(S || defaults());

/* ---------- helpers ---------- */
const $ = id => document.getElementById(id);
const num = v => { const n = parseFloat(String(v ?? '').replace(/,/g,'')); return isNaN(n) ? 0 : n; };
const empty = v => String(v ?? '').trim() === '';
const fmt = n => n.toLocaleString('th-TH',{minimumFractionDigits:2, maximumFractionDigits:2});
const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
const save = () => localStorage.setItem(KEY, JSON.stringify(S));
const card = (cls,l,v,s) => `<div class="card ${cls}"><div class="l">${l}</div><div class="v">${v}</div><div class="s">${s}</div></div>`;

function bahtText(n){
  n = Math.round(n*100)/100; if(!n) return 'ศูนย์บาทถ้วน';
  const d=['','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'], p=['','สิบ','ร้อย','พัน','หมื่น','แสน'];
  const conv = s => {
    let out='';
    if(s.length>6){ out += conv(s.slice(0,-6)) + 'ล้าน'; s = s.slice(-6); }
    const L = s.length;
    for(let i=0;i<L;i++){
      const c=+s[i], pos=L-i-1; if(!c) continue;
      if(pos===0 && c===1 && L>1) out+='เอ็ด';
      else if(pos===1 && c===2) out+='ยี่';
      else if(pos===1 && c===1) out+='';
      else out+=d[c];
      out+=p[pos];
    }
    return out;
  };
  const [b,s] = n.toFixed(2).split('.');
  return (+b ? conv(String(+b))+'บาท' : '') + (+s ? conv(String(+s))+'สตางค์' : 'ถ้วน');
}

/* ---------- ฐานข้อมูลไฟล์แนบ (IndexedDB) ---------- */
const DB = new Promise((ok,no) => {
  const r = indexedDB.open('priceCompareFiles', 1);
  r.onupgradeneeded = () => r.result.createObjectStore('files', {keyPath:'id'});
  r.onsuccess = () => ok(r.result);
  r.onerror = () => no(r.error);
});
async function tx(mode, fn){
  const db = await DB;
  return new Promise((ok,no) => {
    const t = db.transaction('files', mode), req = fn(t.objectStore('files'));
    t.oncomplete = () => ok(req && req.result);
    t.onerror = () => no(t.error);
  });
}
const dbAll = () => tx('readonly', s => s.getAll());
const dbPut = rec => tx('readwrite', s => s.put(rec));
const dbDel = id => tx('readwrite', s => s.delete(id));
const dbClear = () => tx('readwrite', s => s.clear());

let FILES = [];
const filesOf = vid => FILES.filter(f => f.vid === vid);
const fileURL = f => f.url || (f.url = URL.createObjectURL(f.blob));
const isPdf = f => (f.type || '').includes('pdf') || /\.pdf$/i.test(f.name);
const isImg = f => (f.type || '').startsWith('image/');
let pickVendor = null;

function pickFile(i){ pickVendor = i; $('attFile').click(); }
async function addFiles(input){
  const vi = pickVendor, v = S.vendors[vi], list = [...input.files]; input.value = '';
  if(!v) return;
  const ids = [];
  for(const f of list){
    const rec = {id:uid(), vid:v.id, name:f.name, type:f.type || '', blob:f};
    try{ await dbPut(rec); FILES.push(rec); ids.push(rec.id); }
    catch(e){ alert('บันทึกไฟล์ไม่สำเร็จ: ' + f.name); }
  }
  render();
  if(ids.length) readVendor(vi, ids);   // อ่านราคาอัตโนมัติทันทีหลังแนบ
}
function openFile(id){ const f = FILES.find(x => x.id === id); if(f) window.open(fileURL(f), '_blank'); }
async function delFile(id){
  if(!confirm('ลบไฟล์แนบนี้?')) return;
  await dbDel(id); FILES = FILES.filter(f => f.id !== id); render();
}
function attachCell(v,i){
  const fs = filesOf(v.id);
  const list = fs.map(f => `<div class="f"><span>${isImg(f) ? '🖼️' : '📄'}</span>
      <a onclick="openFile('${f.id}')" title="${esc(f.name)}">${esc(f.name)}</a>
      <button class="x no-print" title="ลบไฟล์" onclick="delFile('${f.id}')">✕</button></div>`).join('');
  return `<div class="att">${list || '<span class="missing">⚠ ยังไม่แนบ</span>'}
    <button class="btn-s no-print" onclick="pickFile(${i})">📎 แนบใบเสนอราคา</button>
    ${fs.length ? `<button class="btn-s read no-print" onclick="readVendor(${i})">🔍 อ่านราคาจากไฟล์</button>` : ''}</div>`;
}

/* ---------- อ่านราคาจากไฟล์ (PDF.js + OCR ภาษาไทย) ---------- */
const LIBS = {};
function loadScript(src){
  if(!LIBS[src]) LIBS[src] = new Promise((ok,no) => {
    const s = document.createElement('script'); s.src = src; s.onload = ok;
    s.onerror = () => { delete LIBS[src]; no(new Error('โหลดโปรแกรมอ่านไฟล์ไม่สำเร็จ กรุณาตรวจสอบอินเทอร์เน็ต')); };
    document.head.appendChild(s);
  });
  return LIBS[src];
}

async function ocrImages(images, prog){
  prog('กำลังเตรียมระบบอ่านภาษาไทย (ครั้งแรกจะช้าหน่อย)...', 0);
  await loadScript(TESS_JS);
  let n = 0;
  const worker = await Tesseract.createWorker('tha+eng', 1, {
    logger: m => { if(m.status === 'recognizing text') prog(`กำลังอ่านตัวอักษร (OCR) หน้า ${n+1}/${images.length}`, (n + m.progress) / images.length); }
  });
  let text = '';
  try{
    for(const im of images){ const {data} = await worker.recognize(im); text += data.text + '\n'; n++; }
  } finally { await worker.terminate(); }
  return text;
}

async function readPdf(blob, prog, forceOcr){
  prog('กำลังเปิด PDF...', 0);
  await loadScript(PDF_JS);
  pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
  const pdf = await pdfjsLib.getDocument({data: new Uint8Array(await blob.arrayBuffer())}).promise;
  const N = Math.min(pdf.numPages, 5);
  if(!forceOcr){
    let text = '';
    for(let p=1; p<=N; p++){
      const tc = await (await pdf.getPage(p)).getTextContent();
      const rows = [];
      for(const it of tc.items){
        if(!it.str || !it.str.trim()) continue;
        const x = it.transform[4], y = it.transform[5];
        let r = rows.find(r => Math.abs(r.y - y) < 4);
        if(!r){ r = {y, parts:[]}; rows.push(r); }
        r.parts.push({x, s:it.str});
      }
      rows.sort((a,b) => b.y - a.y);
      text += rows.map(r => r.parts.sort((a,b) => a.x - b.x).map(q => q.s).join(' ')).join('\n') + '\n';
    }
    if(text.replace(/\s/g,'').length > 30) return {text, ocr:false};
  }
  const canvases = [];
  for(let p=1; p<=N; p++){
    prog(`กำลังแปลง PDF เป็นภาพ หน้า ${p}/${N}`, 0);
    const page = await pdf.getPage(p), vp = page.getViewport({scale:2});
    const c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
    await page.render({canvasContext:c.getContext('2d'), viewport:vp}).promise;
    canvases.push(c);
  }
  return {text: await ocrImages(canvases, prog), ocr:true};
}

/* วิเคราะห์ข้อความหา "ยอดรวมทั้งสิ้น" */
const NUM_RE = /(?<![\d.,])(\d{1,3}(?:[.,]\d{3})+(?:[.,]\d{2})?|\d+[.,]\d{2})(?![.,]?\d)(?!\s*%)/g;
function normLine(s){
  return s.replace(/[๐-๙]/g, c => '๐๑๒๓๔๕๖๗๘๙'.indexOf(c))
          .replace(/(\d)\s*([,.])\s*(?=\d)/g, '$1$2');
}
function toNum(s){
  const dec = /[.,]\d{2}$/.test(s);
  return parseFloat((dec ? s.slice(0,-3) : s).replace(/[.,]/g,'') + (dec ? '.' + s.slice(-2) : ''));
}
function classify(line){
  const c = line.replace(/\s+/g,'').toLowerCase();
  if(/รวมทั้งสิ้น|ทั้งสิ้น|ยอดสุทธิ|รวมสุทธิ|ราคาสุทธิ|เงินสุทธิ|ยอดชำระ|ต้องชำระ|grandtotal|nettotal|totalamount|amountdue|totaldue|netamount/.test(c)) return 'total';
  if(/(ไม่รวม|ก่อน)(ภาษี|vat)|excl|before/.test(c)) return 'sub';
  if(/รวม(ภาษี|vat)|incl/.test(c)) return 'total';
  if(/ภาษี|vat/.test(c)) return 'vat';
  if(/ส่วนลด|discount/.test(c)) return 'disc';
  if(/รวม|total/.test(c)) return 'sub';
  return 'item';
}
function analyze(text){
  const all = [];
  text.split(/\n/).map(normLine).forEach(line => {
    const ms = [...line.matchAll(NUM_RE)];
    if(!ms.length) return;
    const amt = toNum(ms[ms.length-1][1]);
    if(!(amt > 0)) return;
    const type = classify(line);
    if(!all.some(x => x.amt === amt && x.type === type)) all.push({line:line.trim(), amt, type});
  });
  const ORDER = {total:0, sub:1, vat:2, disc:3, item:4};
  const cands = all.sort((a,b) => ORDER[a.type] - ORDER[b.type]).slice(0,40);
  const by = t => cands.filter(c => c.type === t);
  const maxOf = arr => arr.reduce((a,b) => b.amt > a.amt ? b : a);
  const near = (a,b) => Math.abs(a-b) <= 1;
  const compact = text.replace(/\s+/g,'').toLowerCase();
  const noVatWord = /(ไม่รวม|ยังไม่รวม)(ภาษี|vat)|excludingvat|exclusiveofvat|exclvat/.test(compact);
  const totals = by('total'), subs = by('sub'), vats = by('vat');
  let pick = null, vat = 'inc', conf = 'low', why = '';

  if(totals.length){
    pick = maxOf(totals); conf = 'mid'; why = 'พบบรรทัดยอดรวมทั้งสิ้น/ยอดสุทธิ';
    const vatOk = vats.some(v => near(v.amt, pick.amt*7/107)) || subs.some(s => vats.some(v => near(s.amt + v.amt, pick.amt)));
    if(vatOk){ conf = 'high'; why += ' และยอดก่อนภาษีบวก VAT ตรงกับยอดรวม'; }
    if(compact.includes(bahtText(pick.amt).replace(/ถ้วน$/,''))){ conf = 'high'; why += ' และตรงกับจำนวนเงินตัวอักษร'; }
    if(!vats.length && noVatWord) vat = 'exc';
  }
  if(!pick && subs.length && vats.length){
    const s = subs.find(s => vats.some(v => near(v.amt, s.amt*0.07)));
    if(s){ pick = s; vat = 'exc'; conf = 'mid'; why = 'พบยอดก่อนภาษีและ VAT 7% (ระบบจะบวก VAT ให้)'; }
  }
  if(!pick && subs.length){
    pick = maxOf(subs); vat = noVatWord ? 'exc' : 'inc';
    why = 'พบแค่ยอดรวม ไม่พบยอดรวมทั้งสิ้น กรุณาตรวจเรื่อง VAT';
  }
  if(!pick && cands.length){ pick = maxOf(cands); why = 'ไม่พบคำว่า "รวม" ระบบเลือกตัวเลขที่มากที่สุด'; }
  return {cands, pick, vat, conf, why};
}
const hasSum = text => analyze(text).cands.some(c => c.type === 'total' || c.type === 'sub');

let READ = null, READING = false;
function showProgress(name, msg, p){
  $('readDlg').classList.add('open');
  $('readBox').innerHTML = `<h3>🔍 กำลังอ่านใบเสนอราคา: ${esc(name)}</h3>
    <div>${esc(msg)}</div><div class="prog"><div style="width:${Math.round((p||0)*100)}%"></div></div>
    <small style="color:#64748b">ประมวลผลในเครื่องนี้ ไม่ได้ส่งไฟล์ไปที่ใด</small>`;
}
async function readVendor(i, ids){
  if(READING) return;
  const v = S.vendors[i], name = v.name || `ร้านที่ ${i+1}`;
  const fs = ids ? FILES.filter(f => ids.includes(f.id)) : filesOf(v.id);
  const usable = fs.filter(f => isPdf(f) || isImg(f));
  if(!usable.length) return alert('ไม่มีไฟล์ PDF หรือรูปภาพให้อ่าน');
  READING = true;
  let text = '', usedOcr = false;
  try{
    for(const f of usable){
      const prog = (msg,p) => showProgress(name, `${f.name}: ${msg}`, p);
      if(isPdf(f)){
        let r = await readPdf(f.blob, prog, false);
        if(!r.ocr && !hasSum(r.text)){
          prog('ไม่พบยอดรวมในข้อความ ลองอ่านแบบภาพ...', 0);
          r = await readPdf(f.blob, prog, true);
        }
        text += r.text + '\n'; usedOcr = usedOcr || r.ocr;
      } else {
        text += await ocrImages([f.blob], prog) + '\n'; usedOcr = true;
      }
    }
  }catch(e){
    READING = false;
    $('readBox').innerHTML = `<h3>⚠ อ่านไฟล์ไม่สำเร็จ</h3><p>${esc(e.message || e)}</p>
      <p>กรุณาพิมพ์ราคาลงตารางเอง หรือลองใหม่อีกครั้ง</p>
      <div class="dlg-foot"><button class="btn" onclick="closeRead()">ปิด</button></div>`;
    return;
  }
  READING = false;
  READ = {i, res:analyze(text), text, usedOcr, row:0, done:''};
  showResult();
}
function showResult(){
  const {i, res, text, usedOcr, row, done} = READ, v = S.vendors[i];
  const pick = done ? null : res.pick;
  const CONF = {high:['high','✔ มั่นใจสูง'], mid:['mid','ควรตรวจสอบ'], low:['low','⚠ ไม่แน่ใจ กรุณาตรวจสอบ']}[res.conf];
  const TL = {total:'ยอดรวมทั้งสิ้น', vat:'VAT', sub:'ยอดรวม', disc:'ส่วนลด', item:'รายการ'};
  const vatSel = done ? v.vat : res.vat;
  const rows = S.items.map((it,r) => `<option value="${r}" ${r===row?'selected':''}>${r+1}. ${esc(it.name || '(ยังไม่มีชื่อรายการ)')} (${esc(it.qty)} ${esc(it.unit)})</option>`).join('');
  $('readDlg').classList.add('open');
  $('readBox').innerHTML = `
    <h3>🔍 ผลการอ่านราคา: ${esc(v.name || 'ร้านที่ ' + (i+1))}</h3>
    ${done ? `<div class="done-msg">${esc(done)}</div>` :
      res.pick ? `<span class="badge ${CONF[0]}">${CONF[1]}</span> <small>${esc(res.why)}${usedOcr ? ' · อ่านจากภาพ (OCR)' : ''}</small>`
               : '<span class="badge low">อ่านราคาไม่ได้</span> <small>กรุณาพิมพ์ยอดเงินเอง</small>'}
    <div class="frm">
      <label><b>ยอดเงิน (บาท)</b></label><input id="rdAmt" inputmode="decimal" style="text-align:right;font-weight:700" value="${pick ? fmt(pick.amt) : ''}">
      <label>ภาษีมูลค่าเพิ่ม</label><select id="rdVat">
        <option value="inc" ${vatSel==='inc'?'selected':''}>ยอดนี้รวม VAT 7% แล้ว</option>
        <option value="exc" ${vatSel==='exc'?'selected':''}>ยอดนี้ยังไม่รวม VAT (ระบบบวก 7% ให้)</option>
        <option value="none" ${vatSel==='none'?'selected':''}>ไม่มี VAT</option></select>
      <label>ใส่ในรายการ</label><select id="rdRow" ${S.items.length < 2 ? 'disabled' : ''}>${rows}</select>
    </div>
    <small style="color:#64748b">ยอดนี้คือราคารวมของรายการที่เลือก ระบบหารด้วยจำนวนเพื่อหาราคาต่อหน่วยให้เอง</small>
    <h4>ตัวเลขที่พบในเอกสาร (คลิกเพื่อเลือกแทน)</h4>
    ${res.cands.map((c,k) => `<div class="cand ${c===pick?'sel':''}" onclick="pickCand(${k})">
        <span class="tag ${c.type}">${TL[c.type]}</span><span class="t" title="${esc(c.line)}">${esc(c.line)}</span>
        <span class="a">${fmt(c.amt)}</span></div>`).join('') || '<small>ไม่พบตัวเลขจำนวนเงินในเอกสาร</small>'}
    <details style="margin-top:8px"><summary>ข้อความทั้งหมดที่ระบบอ่านได้</summary><pre class="raw">${esc(text)}</pre></details>
    <div class="dlg-foot">
      <button class="btn" onclick="closeRead()">${done ? 'เสร็จสิ้น' : 'ยกเลิก'}</button>
      <button class="btn green" onclick="applyRead()">✔ ใส่ราคาลงตาราง</button>
    </div>`;
}
function pickCand(k){
  const c = READ.res.cands[k];
  $('rdAmt').value = fmt(c.amt);
  if(c.type === 'total') $('rdVat').value = 'inc';
  if(c.type === 'sub' && READ.res.cands.some(x => x.type === 'vat')) $('rdVat').value = 'exc';
  document.querySelectorAll('.cand').forEach((e,j) => e.classList.toggle('sel', j === k));
}
function applyRead(){
  const amt = num($('rdAmt').value);
  if(!(amt > 0)) return alert('กรุณาระบุยอดเงิน');
  const i = READ.i, r = +$('rdRow').value, it = S.items[r];
  it.prices[i] = fmt(amt / (num(it.qty) || 1));
  S.vendors[i].vat = $('rdVat').value;
  render();
  if(S.items.length > 1 && r < S.items.length - 1){
    READ.row = r + 1;
    READ.done = `ใส่ราคารายการที่ ${r+1} แล้ว ✔ เลือกตัวเลขของรายการถัดไป หรือกด "เสร็จสิ้น"`;
    showResult();
  } else closeRead();
}
function closeRead(){ if(!READING){ $('readDlg').classList.remove('open'); READ = null; } }

/* ---------- โลโก้ ---------- */
function renderLogo(){
  const L = localStorage.getItem(LOGO_KEY), el = $('logo');
  el.classList.toggle('has', !!L);
  el.innerHTML = L
    ? `<img src="${L}"><button class="x no-print" title="ลบโลโก้" onclick="event.stopPropagation();removeLogo()">✕</button>`
    : '<span class="no-print">📷 คลิกเพื่อใส่โลโก้</span>';
}
function setLogo(input){
  const f = input.files[0]; input.value = ''; if(!f) return;
  const img = new Image();
  img.onload = () => {
    const sc = Math.min(1, 480/img.width, 200/img.height), c = document.createElement('canvas');
    c.width = Math.round(img.width*sc); c.height = Math.round(img.height*sc);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    try{ localStorage.setItem(LOGO_KEY, c.toDataURL('image/png')); }catch(e){ alert('ไฟล์โลโก้ใหญ่เกินไป'); }
    URL.revokeObjectURL(img.src); renderLogo();
  };
  img.onerror = () => alert('เปิดไฟล์รูปไม่ได้');
  img.src = URL.createObjectURL(f);
}
function removeLogo(){ if(confirm('ลบโลโก้?')){ localStorage.removeItem(LOGO_KEY); renderLogo(); } }

/* ---------- build table ---------- */
function render(){
  const V = S.vendors, n = V.length;
  const inp = (attrs,val,cls='') => `<input class="${cls}" ${attrs} value="${esc(val)}">`;

  let h = `<thead><tr>
    <th rowspan="3">ลำดับ</th><th rowspan="3">เลขที่<br>ใบขอซื้อ</th><th rowspan="3">หน่วยงาน<br>ขอซื้อ</th>
    <th rowspan="3" style="min-width:200px">รายการสินค้า/บริการ</th>
    <th rowspan="3" style="width:55px">จำนวน</th><th rowspan="3" style="width:60px">หน่วย</th>
    <th rowspan="3">งบประมาณ<br>ที่กำหนด</th><th rowspan="3">ราคากลาง<br>ต่อหน่วย</th>
    <th colspan="${n*2}">บริษัท/ห้าง/ร้าน และราคาที่เสนอ</th>
    <th rowspan="3" class="no-print" style="border:0;background:none"></th></tr><tr>`;
  V.forEach((v,i) => h += `<th colspan="2" id="vh-${i}"><div style="display:flex">
      ${inp(`data-k="name" data-v="${i}" placeholder="ชื่อร้านค้า" style="text-align:center;font-weight:700"`, v.name)}
      ${n>2 ? `<button class="x no-print" title="ลบร้านนี้" onclick="delVendor(${i})">✕</button>` : ''}</div></th>`);
  h += '</tr><tr>' + V.map(()=>'<th>ราคาต่อหน่วย</th><th>ราคารวม</th>').join('') + '</tr></thead><tbody>';

  S.items.forEach((it,r) => {
    const a = k => `data-k="${k}" data-r="${r}"`;
    h += `<tr><td>${r+1}</td>
      <td>${inp(a('pr'), it.pr)}</td>
      <td>${inp(a('dept'), it.dept)}</td>
      <td>${inp(a('name')+' style="text-align:left"', it.name)}</td>
      <td>${inp(a('qty')+' inputmode="decimal" style="text-align:center"', it.qty)}</td>
      <td>${inp(a('unit'), it.unit)}</td>
      <td>${inp(a('budget')+' inputmode="decimal"', it.budget, 'money')}</td>
      <td>${inp(a('mid')+' inputmode="decimal"', it.mid, 'money')}</td>`;
    V.forEach((v,i) => h += `<td>${inp(`data-k="price" data-r="${r}" data-v="${i}" inputmode="decimal"`, it.prices[i], 'money')}</td>
      <td class="calc" id="lt-${r}-${i}"></td>`);
    h += `<td class="no-print" style="border:0">${S.items.length>1 ? `<button class="x" title="ลบรายการ" onclick="delItem(${r})">✕</button>` : ''}</td></tr>`;
  });

  const row = (label,pref,fn,cls='') =>
    `<tr><td colspan="8" class="lbl">${label}</td>${V.map((v,i)=>`<td colspan="2" class="${cls}" id="${pref}-${i}">${fn(v,i)}</td>`).join('')}<td class="no-print" style="border:0"></td></tr>`;
  const sel = (v,i) => `<select data-k="vat" data-v="${i}">
      <option value="inc" ${v.vat==='inc'?'selected':''}>* รวม VAT 7% แล้ว</option>
      <option value="exc" ${v.vat==='exc'?'selected':''}>ยังไม่รวม VAT (+7%)</option>
      <option value="none" ${v.vat==='none'?'selected':''}>ไม่มี VAT</option></select>`;

  h += row('เงื่อนไขภาษีมูลค่าเพิ่ม', 'vat', sel);
  h += row('ราคารวมสุทธิ (รวมภาษีมูลค่าเพิ่มแล้ว)', 'net', ()=>'', 'net');
  h += row('อันดับราคา', 'rank', ()=>'');
  h += row('2) การส่งมอบ', 'dl', (v,i)=>inp(`data-k="delivery" data-v="${i}" placeholder="เช่น 7 วัน"`, v.delivery));
  h += row('3) เงื่อนไขชำระเงิน', 'pay', (v,i)=>inp(`data-k="payment" data-v="${i}" placeholder="เช่น เครดิต 30 วัน"`, v.payment));
  h += row('4) การบริการ/การรับประกัน/อื่นๆ (ถ้ามี)', 'wr', (v,i)=>inp(`data-k="warranty" data-v="${i}" placeholder="เช่น รับประกัน 1 ปี"`, v.warranty));
  h += row('📎 ใบเสนอราคาที่แนบ', 'att', attachCell);
  h += row('5) ความคิดเห็นของแผนกจัดซื้อ ราคารวมภาษีมูลค่าเพิ่มแล้ว', 'ch', (v,i)=>`<input type="radio" name="choice" data-k="choice" data-v="${i}">`);
  h += row('6) พิจารณาอนุมัติ', 'ap', (v,i)=>`<input type="radio" name="approve" data-k="approve" data-v="${i}">`);
  h += '</tbody>';

  $('tbl').innerHTML = h;
  document.querySelectorAll('[data-m]').forEach(e => e.value = S.meta[e.dataset.m] ?? '');
  calc();
}

/* ---------- calculation ---------- */
let RES = {R:[], valid:[], best:null};
function calc(){
  const V = S.vendors;
  const R = V.map((v,i) => {
    let sum = 0, missing = 0;
    S.items.forEach((it,r) => {
      const p = it.prices[i], t = num(p) * num(it.qty);
      $(`lt-${r}-${i}`).textContent = empty(p) ? '' : fmt(t);
      if(empty(p)) missing++; else sum += t;
    });
    const net = v.vat === 'exc' ? sum * 1.07 : sum;
    return {i, name: v.name || `ร้านที่ ${i+1}`, net, ok: missing===0 && sum>0};
  });

  const valid = R.filter(x => x.ok).sort((a,b) => a.net - b.net);
  const best = valid[0], worst = valid[valid.length-1];
  RES = {R, valid, best};

  document.querySelectorAll('.best').forEach(e => e.classList.remove('best'));
  R.forEach(x => {
    $(`net-${x.i}`).innerHTML = x.ok ? fmt(x.net) : (x.net>0 ? '<span class="na">⚠ เสนอราคาไม่ครบ</span>' : '-');
    const rk = valid.indexOf(x);
    $(`rank-${x.i}`).innerHTML = rk<0 ? '-' : rk===0 ? '🏆 อันดับ 1 (ต่ำสุด)' : `อันดับ ${rk+1} (แพงกว่า ${fmt(x.net-best.net)})`;
  });
  if(best){
    ['vh','net','rank','ch'].forEach(p => $(`${p}-${best.i}`).classList.add('best'));
    S.items.forEach((_,r) => $(`lt-${r}-${best.i}`).classList.add('best'));
  }

  const choice = S.choice ?? (best ? best.i : null);
  document.querySelectorAll('input[name=choice]').forEach(e => e.checked = +e.dataset.v === choice);
  document.querySelectorAll('input[name=approve]').forEach(e => e.checked = +e.dataset.v === S.approve);

  const chosen = R[choice];
  const amt = chosen && chosen.ok ? chosen.net : 0;
  const m = amt<=0 ? 0 : amt<=100000 ? 1 : amt<=2000000 ? 2 : 3;
  [1,2,3].forEach(k => $('m'+k).classList.toggle('on', k===m));

  const budget = S.items.reduce((s,it) => s + num(it.budget), 0);
  const mid = S.items.reduce((s,it) => s + num(it.mid)*num(it.qty), 0);
  const methods = ['-','ตกลงราคา','สอบราคา','ประกวดราคา'];
  let c = '';
  if(best){
    c += card('ok','🏆 ร้านที่แนะนำ (ราคาต่ำสุด)', esc(best.name), `${fmt(best.net)} บาท<br>(${bahtText(best.net)})`);
    const diff = worst.net - best.net;
    c += card('info','💰 ประหยัดได้ (เทียบราคาสูงสุด)', `${fmt(diff)} บาท`,
              valid.length>1 ? `ประหยัด ${(diff/worst.net*100).toFixed(1)}% เทียบกับ ${esc(worst.name)}` : 'มีผู้เสนอราคาครบเพียงร้านเดียว');
    const ref = budget>0 ? ['งบประมาณ',budget] : mid>0 ? ['ราคากลาง',mid] : null;
    if(ref){
      const d = ref[1] - best.net;
      c += d>=0 ? card('ok',`📋 เทียบ${ref[0]}`,'อยู่ในงบ ✔',`ต่ำกว่า${ref[0]} ${fmt(d)} บาท (${ref[0]} ${fmt(ref[1])})`)
                : card('warn',`📋 เทียบ${ref[0]}`,'เกินงบ ✖',`เกิน${ref[0]} ${fmt(-d)} บาท (${ref[0]} ${fmt(ref[1])})`);
    } else c += card('','📋 เทียบงบประมาณ','ยังไม่ระบุ','กรอกงบประมาณหรือราคากลางเพื่อให้ระบบตรวจสอบ');
    c += card('info','📑 วิธีการจัดซื้อ', methods[m], `ตามยอด ${fmt(amt)} บาท`);

    const noAtt = R.filter(x => x.net>0 && !filesOf(V[x.i].id).length).map(x => esc(x.name));
    c += noAtt.length
      ? card('warn','📎 ยังไม่แนบใบเสนอราคา', `${noAtt.length} ร้าน`, noAtt.join(', '))
      : card('ok','📎 ใบเสนอราคา','แนบครบทุกร้าน ✔','กดปุ่ม "เทียบใบเสนอราคา" เพื่อดูเอกสารจริง');

    if(S.choice!=null && S.choice!==best.i && chosen && chosen.ok)
      c += card('warn','⚠ แผนกจัดซื้อเลือกร้านที่ไม่ใช่ราคาต่ำสุด', esc(chosen.name),
                `แพงกว่าร้านที่ถูกสุด ${fmt(chosen.net-best.net)} บาท กรุณาระบุเหตุผลประกอบ`);
  } else {
    c = card('warn','ยังไม่มีข้อมูลเพียงพอ','กรุณากรอกราคา','ต้องมีอย่างน้อย 1 ร้านที่เสนอราคาครบทุกรายการ');
  }
  $('cards').innerHTML = c;

  const max = Math.max(...valid.map(x => x.net), 1);
  $('bars').innerHTML = valid.map((x,k) =>
    `<div class="bar ${k===0?'best':''}"><div class="n">${esc(x.name)}</div>
     <div class="t"><div class="f" style="width:${Math.max(x.net/max*100,10)}%">${fmt(x.net)} บาท</div></div></div>`).join('');

  save();
}

/* ---------- หน้าต่างเทียบใบเสนอราคา ---------- */
function openViewer(){
  const V = S.vendors;
  $('vbody').style.gridTemplateColumns = `repeat(${V.length}, minmax(320px,1fr))`;
  $('vbody').innerHTML = V.map((v,i) => {
    const x = RES.R[i], rk = RES.valid.indexOf(x), fs = filesOf(v.id);
    const body = fs.length ? fs.map(f => {
      const u = fileURL(f);
      if(isImg(f)) return `<img src="${u}" alt="${esc(f.name)}">`;
      if(isPdf(f)) return `<iframe src="${u}" title="${esc(f.name)}"></iframe>`;
      return `<a href="${u}" download="${esc(f.name)}">ดาวน์โหลด ${esc(f.name)}</a>`;
    }).join('') : '<div class="empty">⚠ ยังไม่ได้แนบใบเสนอราคา</div>';
    const info = x && x.ok ? `ราคาในระบบ ${fmt(x.net)} บาท · อันดับ ${rk+1}` : 'ราคาในระบบยังไม่ครบ';
    return `<div class="col ${rk===0 ? 'win' : ''}"><div class="col-h">${rk===0 ? '🏆 ' : ''}${esc(x ? x.name : v.name)}<br><small>${info}</small></div>
            <div class="col-b">${body}</div></div>`;
  }).join('');
  $('viewer').classList.add('open');
}
function closeViewer(){ $('viewer').classList.remove('open'); }
document.addEventListener('keydown', e => { if(e.key === 'Escape'){ closeViewer(); closeRead(); } });

/* ---------- events ---------- */
function onEdit(e){
  const el = e.target;
  if(el.closest && el.closest('#readDlg')) return;
  const k = el.dataset && el.dataset.k, m = el.dataset && el.dataset.m;
  if(m){ S.meta[m] = el.value; save(); return; }
  if(!k) return;
  const r = el.dataset.r, v = el.dataset.v;
  if(k==='choice') S.choice = +v;
  else if(k==='approve') S.approve = +v;
  else if(k==='price') S.items[r].prices[v] = el.value;
  else if(r !== undefined) S.items[r][k] = el.value;
  else S.vendors[v][k] = el.value;
  calc();
}
document.addEventListener('input', onEdit);
document.addEventListener('change', onEdit);
document.addEventListener('focusout', e => {
  const el = e.target;
  if(el.classList && el.classList.contains('money') && !empty(el.value)){ el.value = fmt(num(el.value)); onEdit({target:el}); }
});

function addItem(){ S.items.push(blankItem()); render(); }
function delItem(r){ if(confirm('ลบรายการนี้?')){ S.items.splice(r,1); render(); } }
function addVendor(){
  if(S.vendors.length >= MAXV) return alert('เพิ่มได้สูงสุด ' + MAXV + ' ร้าน');
  S.vendors.push(blankVendor('')); S.items.forEach(it => it.prices.push('')); render();
}
async function delVendor(i){
  const v = S.vendors[i];
  if(!confirm(`ลบร้าน "${v.name}" พร้อมใบเสนอราคาที่แนบ?`)) return;
  for(const f of filesOf(v.id)) await dbDel(f.id);
  FILES = FILES.filter(f => f.vid !== v.id);
  S.vendors.splice(i,1); S.items.forEach(it => it.prices.splice(i,1));
  S.choice = null; S.approve = null; render();
}
async function resetAll(){
  if(!confirm('ล้างข้อมูลและไฟล์แนบทั้งหมดเพื่อเริ่มใบใหม่? (แนะนำให้กด "บันทึกเป็นไฟล์" ก่อน)')) return;
  const keep = S.meta; S = defaults();
  ['s1','p1','s2','p2','s3','p3','rev'].forEach(k => S.meta[k] = keep[k]);
  S.vendors = [1,2,3].map(() => blankVendor('')); S.items = [blankItem()];
  try{ await dbClear(); }catch(e){}
  FILES = []; render();
}

/* ---------- บันทึก / เปิดไฟล์ (รวมโลโก้และใบเสนอราคา) ---------- */
const toDataURL = b => new Promise(ok => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(b); });
const fromDataURL = d => {
  const [h,b] = d.split(','), bin = atob(b), u = new Uint8Array(bin.length);
  for(let i=0;i<bin.length;i++) u[i] = bin.charCodeAt(i);
  return new Blob([u], {type: h.slice(5).split(';')[0]});
};
async function exportFile(){
  const files = await Promise.all(FILES.map(async f =>
    ({id:f.id, vid:f.vid, name:f.name, type:f.type, data: await toDataURL(f.blob)})));
  const pack = {state:S, logo:localStorage.getItem(LOGO_KEY), files};
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(pack)], {type:'application/json'}));
  a.download = `เปรียบเทียบราคา_${S.meta.docNo || S.meta.date || 'draft'}.json`;
  a.click();
}
async function importFile(input){
  const f = input.files[0]; input.value = ''; if(!f) return;
  if(!confirm('เปิดไฟล์นี้แทนข้อมูลปัจจุบัน?')) return;
  try{
    const d = JSON.parse(await f.text());
    S = migrate(d.state || d);
    if(d.logo) localStorage.setItem(LOGO_KEY, d.logo);
    await dbClear(); FILES = [];
    for(const x of d.files || []){
      const rec = {id:x.id, vid:x.vid, name:x.name, type:x.type, blob:fromDataURL(x.data)};
      await dbPut(rec); FILES.push(rec);
    }
    renderLogo(); render();
  }catch(e){ alert('ไฟล์ไม่ถูกต้อง'); }
}

/* ---------- เริ่มต้น ---------- */
(async () => {
  try{ FILES = await dbAll(); }catch(e){ FILES = []; }
  renderLogo(); render();
})();
</script>
</body>
</html>
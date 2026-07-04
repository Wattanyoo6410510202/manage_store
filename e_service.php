<?php
require_once 'config.php';
include('header.php');
?>

<style>
.landing-glow { text-shadow: 0 0 40px rgba(99,102,241,.4); }
.landing-shine {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 50%, #ef4444 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.coming-badge {
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    box-shadow: 0 4px 20px rgba(239,68,68,.35);
}
.tool-card {
    background: rgba(255,255,255,.06);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,.1);
    transition: all .4s cubic-bezier(.4,0,.2,1);
}
.tool-card:hover {
    background: rgba(255,255,255,.12);
    transform: translateY(-6px) scale(1.02);
    border-color: rgba(255,255,255,.25);
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.brand-card {
    transition: all .4s cubic-bezier(.4,0,.2,1);
}
.brand-card:hover {
    transform: translateY(-8px) scale(1.01);
    box-shadow: 0 30px 80px rgba(0,0,0,.25);
}
.glow-dot {
    width: 6px; height: 6px;
    background: #22c55e;
    border-radius: 50%;
    display: inline-block;
    animation: pulse-dot 1.5s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity: 1; transform: scale(1); }
    50% { opacity: .5; transform: scale(1.5); }
}
</style>

<div class="landing min-h-screen">

    <!-- Hero Full -->
    <div class="relative overflow-hidden rounded-3xl min-h-[420px] flex items-center" style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #4c1d95 100%);">
        <!-- Decorative blobs -->
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] rounded-full opacity-20" style="background: radial-gradient(circle, #818cf8 0%, transparent 70%);"></div>
        <div class="absolute -bottom-32 -left-32 w-[400px] h-[400px] rounded-full opacity-20" style="background: radial-gradient(circle, #f59e0b 0%, transparent 70%);"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full opacity-10" style="background: radial-gradient(circle, #a855f7 0%, transparent 70%);"></div>

        <!-- Grid pattern overlay -->
        <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(rgba(255,255,255,.3) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.3) 1px,transparent 1px); background-size: 60px 60px;"></div>

        <div class="relative z-10 px-8 md:px-14 py-16 w-full">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full text-sm font-semibold text-white mb-6 coming-badge">
                        <span class="glow-dot"></span>
                        กำลังจะเปิดให้บริการเร็วๆ นี้
                    </div>
                    <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white leading-[1.1] tracking-tight">
                        <span class="block">TOOL Smart</span>
                        <span class="landing-shine">ยกระดับธุรกิจสู่ยุคดิจิทัล</span>
                    </h1>
                    <p class="text-slate-300 text-base md:text-lg mt-4 max-w-xl leading-relaxed">
                        ระบบจัดการสำหรับ S Hotel Manonta และ NIJUNI JAPANE RESTAURANT
                        พร้อมเครื่องมืออัจฉริยะ ครบจบในที่เดียว
                    </p>
                    <div class="flex flex-wrap gap-4 mt-8">
                        <button class="px-8 py-3.5 bg-white text-slate-900 font-bold rounded-xl hover:shadow-2xl hover:shadow-white/20 transition-all hover:-translate-y-0.5 text-sm tracking-wide">
                            <i class="fas fa-bell mr-2"></i> แจ้งเตือนเมื่อพร้อม
                        </button>
                        <button class="px-8 py-3.5 border border-white/20 text-white font-semibold rounded-xl hover:bg-white/10 transition-all text-sm tracking-wide backdrop-blur-sm">
                            ดูรายละเอียดเพิ่มเติม <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
                <div class="hidden lg:flex items-center justify-center">
                    <div class="w-56 h-56 rounded-3xl bg-white/5 backdrop-blur-xl border border-white/10 flex items-center justify-center shadow-2xl">
                        <div class="text-center">
                            <div class="text-6xl mb-2">🚀</div>
                            <div class="text-white font-bold text-lg">ProSystem</div>
                            <div class="text-slate-400 text-xs">All-in-One</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LINE Official Account -->
    <div class="mb-10 mt-10">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden">
            <div class="p-8 md:p-10 flex flex-col md:flex-row items-center gap-8">
                <div class="shrink-0">
                    <img src="https://qr-official.line.me/gs/L_952uzgli_GW.png?oat_content=qr"
                         alt="LINE QR Code"
                         class="w-44 h-44 rounded-2xl shadow-lg">
                </div>
                <div>
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-green-50 text-green-600 text-xs font-bold rounded-full mb-4">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        พร้อมใช้งาน
                    </div>
                    <h3 class="text-xl font-black text-slate-800">LINE Official Account</h3>
                    <p class="text-slate-500 text-sm mt-2 leading-relaxed max-w-md">
                        แอด LINE Official Account เพื่อรับการแจ้งเตือนสถานะ PR, PO และเอกสารต่างๆ
                        ถึงคุณโดยตรง
                    </p>
                    <div class="mt-4 flex items-center gap-3">
                        <span class="text-sm text-slate-400">LINE ID:</span>
                        <code class="px-4 py-2 bg-slate-50 rounded-xl text-green-600 font-bold text-lg border border-slate-200">@952uzgli</code>
                        <button onclick="navigator.clipboard.writeText('@952uzgli').then(()=>{this.innerHTML='<i class=\'fas fa-check\'></i> คัดลอกแล้ว';setTimeout(()=>{this.innerHTML='<i class=\'fas fa-copy\'></i> คัดลอก'},2000)})" class="px-4 py-2 bg-green-50 text-green-600 rounded-xl hover:bg-green-100 transition text-sm font-semibold">
                            <i class="fas fa-copy"></i> คัดลอก
                        </button>
                    </div>
                    <div class="mt-4">
                        <a href="https://line.me/R/ti/p/%40952uzgli" target="_blank" class="inline-flex items-center gap-2 px-6 py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition shadow-lg shadow-green-200 text-sm">
                            <i class="fab fa-line text-lg"></i> เพิ่มเพื่อน
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Brand Divider -->
    <div class="text-center my-14">
        <p class="text-xs uppercase tracking-[.25em] text-slate-400 font-semibold">ระบบที่กำลังจะมาถึง</p>
        <div class="flex items-center justify-center gap-4 mt-4">
            <div class="h-px w-12 bg-gradient-to-r from-transparent to-slate-300"></div>
            <i class="fas fa-star text-amber-400 text-sm"></i>
            <i class="fas fa-star text-amber-400 text-sm"></i>
            <i class="fas fa-star text-amber-400 text-sm"></i>
            <div class="h-px w-12 bg-gradient-to-l from-transparent to-slate-300"></div>
        </div>
    </div>

    <!-- Smart Tool Section -->
    <div class="mb-16">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-[.2em] text-indigo-500">System 01</span>
                <h2 class="text-2xl md:text-3xl font-black text-slate-800 mt-1">
                    TOOL Smart
                    <span class="inline-flex items-center gap-1.5 ml-2 px-2.5 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full align-middle">
                        <i class="fas fa-clock"></i> Coming Soon
                    </span>
                </h2>
                <p class="text-sm text-slate-400 mt-1">รวมเครื่องมืออัจฉริยะเพื่อการทำงานที่รวดเร็ว</p>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="tool-card rounded-2xl p-6 text-white">
                <div class="w-14 h-14 bg-gradient-to-br from-sky-400 to-blue-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg mb-4">📱</div>
                <h3 class="font-bold text-lg">QR Code</h3>
                <p class="text-slate-400 text-sm mt-1">สร้างและสแกน QR Code ได้ในที่เดียว</p>
                <span class="inline-block mt-4 text-[10px] uppercase tracking-wider text-sky-300 font-semibold">กำลังพัฒนา</span>
            </div>
            <div class="tool-card rounded-2xl p-6 text-white">
                <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg mb-4">📄</div>
                <h3 class="font-bold text-lg">OCR</h3>
                <p class="text-slate-400 text-sm mt-1">แปลงรูปภาพเป็นข้อความ พิมพ์น้อยลง</p>
                <span class="inline-block mt-4 text-[10px] uppercase tracking-wider text-emerald-300 font-semibold">กำลังพัฒนา</span>
            </div>
            <div class="tool-card rounded-2xl p-6 text-white">
                <div class="w-14 h-14 bg-gradient-to-br from-rose-400 to-rose-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg mb-4">📎</div>
                <h3 class="font-bold text-lg">PDF Tools</h3>
                <p class="text-slate-400 text-sm mt-1">รวม แยก บีบอัด PDF ได้ทันที</p>
                <span class="inline-block mt-4 text-[10px] uppercase tracking-wider text-rose-300 font-semibold">กำลังพัฒนา</span>
            </div>
            <div class="tool-card rounded-2xl p-6 text-white">
                <div class="w-14 h-14 bg-gradient-to-br from-amber-400 to-orange-600 rounded-2xl flex items-center justify-center text-2xl shadow-lg mb-4">📊</div>
                <h3 class="font-bold text-lg">Barcode</h3>
                <p class="text-slate-400 text-sm mt-1">สร้างบาร์โค้ดสินค้า จัดการสต็อก</p>
                <span class="inline-block mt-4 text-[10px] uppercase tracking-wider text-amber-300 font-semibold">กำลังพัฒนา</span>
            </div>
        </div>
    </div>

    <!-- Central Hub 2 Hotels 1 Restaurant -->
    <div class="mb-16">
        <div class="flex items-center justify-between mb-8">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-[.2em] text-amber-500">System 02</span>
                <h2 class="text-2xl md:text-3xl font-black text-slate-800 mt-1">
                    S Hotel Manonta &amp; NIJUNI JAPANE RESTAURANT
                    <span class="inline-flex items-center gap-1.5 ml-2 px-2.5 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold rounded-full align-middle">
                        <i class="fas fa-clock"></i> Coming Soon
                    </span>
                </h2>
                <p class="text-sm text-slate-400 mt-1">ระบบเดียว จัดการได้ทุกที่</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Hotel Manonta -->
            <div class="brand-card bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden">
                <div class="h-48 bg-gradient-to-br from-indigo-500 via-indigo-600 to-purple-700 relative">
                    <div class="absolute inset-0 bg-black/10"></div>
                    <div class="absolute top-5 left-5">
                        <span class="px-3 py-1 bg-white/20 backdrop-blur-md text-white text-[10px] font-bold rounded-full">Hotel</span>
                    </div>
                    <div class="absolute bottom-5 right-5 text-white text-right">
                        <div class="text-4xl font-black leading-none">01</div>
                        <div class="text-xs opacity-70">Hotel</div>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="font-black text-slate-800 text-lg">S Hotel Manonta</h3>
                    <p class="text-sm text-slate-400 mt-1">ระบบจัดการห้องพัก ครบวงจร</p>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg"><i class="fas fa-bed mr-1"></i> จัดการห้อง</span>
                        <span class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg"><i class="fas fa-calendar-check mr-1"></i> การจอง</span>
                        <span class="px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-lg"><i class="fas fa-chart-line mr-1"></i> รายงาน</span>
                    </div>
                </div>
            </div>
            <!-- Restaurant -->
            <div class="brand-card bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden">
                <div class="h-48 bg-gradient-to-br from-rose-400 via-rose-500 to-pink-700 relative">
                    <div class="absolute inset-0 bg-black/10"></div>
                    <div class="absolute top-5 left-5">
                        <span class="px-3 py-1 bg-white/20 backdrop-blur-md text-white text-[10px] font-bold rounded-full">Restaurant</span>
                    </div>
                    <div class="absolute bottom-5 right-5 text-white text-right">
                        <div class="text-4xl font-black leading-none">01</div>
                        <div class="text-xs opacity-70">Restaurant</div>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="font-black text-slate-800 text-lg">NIJUNI JAPANE RESTAURANT</h3>
                    <p class="text-sm text-slate-400 mt-1">ระบบจัดการร้านอาหาร ครบวงจร</p>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg"><i class="fas fa-utensils mr-1"></i> สั่งอาหาร</span>
                        <span class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg"><i class="fas fa-chair mr-1"></i> จัดการโต๊ะ</span>
                        <span class="px-3 py-1.5 bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg"><i class="fas fa-box mr-1"></i> สต็อก</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Banner -->
    <div class="rounded-3xl overflow-hidden relative" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%);">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 25% 50%, #fff 0%, transparent 50%);"></div>
        <div class="relative z-10 px-8 md:px-14 py-12 md:py-16 flex flex-col md:flex-row items-center justify-between gap-6">
            <div>
                <h3 class="text-2xl md:text-3xl font-black text-white">พร้อมที่จะเริ่มต้นหรือยัง?</h3>
                <p class="text-indigo-200 mt-1 text-sm">สมัครรับข่าวสาร แล้วเราจะแจ้งให้คุณทราบเมื่อระบบพร้อมใช้งาน</p>
            </div>
            <div class="flex gap-3 shrink-0">
                <input type="email" placeholder="อีเมลของคุณ" class="px-5 py-3.5 rounded-xl border-0 bg-white/10 backdrop-blur-sm text-white placeholder:text-slate-400 text-sm focus:ring-2 focus:ring-white/30 outline-none w-56">
                <button class="px-6 py-3.5 bg-white text-slate-900 font-bold rounded-xl hover:shadow-2xl transition-all text-sm">แจ้งเตือนฉัน</button>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center py-8 text-xs text-slate-300">
        <i class="fas fa-crown text-amber-400 mr-1"></i> ProSystem &mdash; All-in-One Management Platform
    </div>

</div>

<?php include('footer.php'); ?>

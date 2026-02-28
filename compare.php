<?php
session_start();
include('header.php');
?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">
    <div class="bg-white p-6 md:p-10 rounded-3xl shadow-sm border border-slate-200 shadow-indigo-500/5">
        <div class="max-w-2xl mx-auto text-center space-y-4">
            <div
                class="bg-indigo-100 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto text-indigo-600 mb-2">
                <i class="fas fa-robot text-2xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-bold text-slate-800">AI Price Comparison</h2>
            <p class="text-slate-500 text-sm md:text-base">เลือก Model ที่ต้องการและพิมพ์ชื่อสินค้าเพื่อเปรียบเทียบราคา
            </p>

            <div class="flex flex-wrap justify-center gap-3 mt-4">
                <label class="cursor-pointer">
                    <input type="radio" name="ai_model" value="api/api_ai_bridge.php" class="peer sr-only" checked>
                    <div
                        class="px-4 py-2 rounded-xl border-2 border-slate-100 bg-slate-50 text-slate-600 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-600 font-bold transition-all text-sm">
                        <i class="fas fa-stars mr-1"></i> Gemini
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="ai_model" value="api/api_pathumma_bridge.php" class="peer sr-only">
                    <div
                        class="px-4 py-2 rounded-xl border-2 border-slate-100 bg-slate-50 text-slate-600 peer-checked:border-pink-600 peer-checked:bg-pink-50 peer-checked:text-pink-600 font-bold transition-all text-sm">
                        <i class="fas fa-leaf mr-1"></i> Pathumma (8B)
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="ai_model" value="api/api_groq_bridge.php" class="peer sr-only">
                    <div
                        class="px-4 py-2 rounded-xl border-2 border-slate-100 bg-slate-50 text-slate-600 peer-checked:border-orange-600 peer-checked:bg-orange-50 peer-checked:text-orange-600 font-bold transition-all text-sm">
                        <i class="fas fa-bolt mr-1"></i> Groq (Fast)
                    </div>
                </label>
            </div>

            <div class="relative mt-6">
                <input type="text" id="productSearch"
                    class="w-full pl-12 pr-4 md:pr-36 py-4 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:border-indigo-500 focus:ring-0 transition-all outline-none text-base"
                    placeholder="เช่น เหล็กฉาก, ปูนซีเมนต์...">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                    <i class="fas fa-search"></i>
                </div>
                <button onclick="askAI()"
                    class="mt-3 md:mt-0 md:absolute md:right-2 md:top-2 md:bottom-2 w-full md:w-auto bg-indigo-600 text-white px-8 py-3 md:py-0 rounded-xl font-bold hover:bg-indigo-700 transition-all flex items-center justify-center gap-2">
                    <span id="btnText">เปรียบเทียบ</span>
                    <i class="fas fa-magic"></i>
                </button>
            </div>
        </div>
    </div>

    <div id="loading" class="hidden">
        <div class="flex flex-col items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-slate-200 border-b-indigo-600 mb-4"></div>
            <p class="text-slate-500 animate-pulse">AI กำลังวิเคราะห์และเจรจาราคาให้คุณ...</p>
        </div>
    </div>

    <div id="aiResult" class="space-y-4"></div>
</div>

<script>
    async function askAI() {
        const productName = document.getElementById('productSearch').value;
        if (!productName) return Swal.fire('กรุณาระบุชื่อสินค้า', '', 'warning');

        const selectedApi = document.querySelector('input[name="ai_model"]:checked').value;
        const btnText = document.getElementById('btnText');
        const loading = document.getElementById('loading');
        const resultDiv = document.getElementById('aiResult');

        btnText.innerText = 'กำลังประมวลผล...';
        btnText.disabled = true;
        loading.classList.remove('hidden');
        resultDiv.innerHTML = '';

        try {
            const response = await fetch(selectedApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product: productName })
            });

            const rawText = await response.text();
            console.log("Raw response from AI:", rawText); // สำหรับตรวจสอบใน Console

            let cleanData;
            try {
                // --- ขั้นตอนการสกัด JSON แบบขั้นสุด (Extreme Clean) ---

                // 1. หาตำแหน่งเริ่มต้นของ [ และตำแหน่งสุดท้ายของ ]
                const startIdx = rawText.indexOf('[');
                const endIdx = rawText.lastIndexOf(']');

                if (startIdx !== -1 && endIdx !== -1 && endIdx > startIdx) {
                    // ดึงเอาเฉพาะก้อนที่มี [ ] อยู่ข้างใน
                    const jsonString = rawText.substring(startIdx, endIdx + 1);
                    cleanData = JSON.parse(jsonString);
                } else {
                    // หากไม่เจอ [ ] เลย ให้ลองเช็คว่ามันเป็น Error JSON หรือเปล่า
                    const possibleError = JSON.parse(rawText);
                    if (possibleError.error) throw new Error(possibleError.error);
                    throw new Error("AI ไม่ได้ตอบกลับในรูปแบบรายการราคา");
                }

                renderResult(cleanData, productName);

            } catch (e) {
                console.error("Parse Error Details:", e);
                // ถ้าพังจริงๆ ให้พ่น Error ที่เข้าใจง่าย
                throw new Error("AI ตอบกลับมาเป็นข้อความบรรยาย (ไม่ใช่ JSON)");
            }

        } catch (error) {
            console.error("AskAI Error:", error);
            renderMockResult(productName, error.message);
        } finally {
            btnText.innerText = 'เปรียบเทียบ';
            btnText.disabled = false;
            loading.classList.add('hidden');
        }
    }

    function renderResult(data, name) {
        const resultDiv = document.getElementById('aiResult');
        let priceList = Array.isArray(data) ? data : [];

        if (priceList.length === 0) {
            resultDiv.innerHTML = `<div class="p-8 text-center text-slate-400 bg-white rounded-3xl border">ไม่พบข้อมูล</div>`;
            return;
        }

        const prices = priceList.map(p => parseFloat(p.price)).filter(p => !isNaN(p));
        const minPrice = Math.min(...prices);

        let html = `
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-2 mb-2">
            <div>
                <h3 class="font-bold text-slate-800 text-lg">ผลการค้นหา: ${name}</h3>
                <p class="text-xs text-slate-400">แหล่งข้อมูลถูกวิเคราะห์โดย AI</p>
            </div>
            <div id="selectionBadge" class="hidden">
                <div class="flex items-center gap-2 bg-indigo-600 text-white pl-4 pr-2 py-2 rounded-2xl border border-indigo-700">
                    <span class="text-sm font-bold">เลือก <span id="selectedCount">0</span> รายการ</span>
                    <button onclick="processSelectedShops()" class="bg-white/20 hover:bg-white/30 px-3 py-1 rounded-xl text-xs font-bold transition-colors">
                        ทำใบเปรียบเทียบ <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="space-y-3">
    `;

        priceList.forEach((item, index) => {
            const currentPrice = parseFloat(item.price);
            const isBest = currentPrice === minPrice;

            html += `
        <div id="row-${index}" class="group relative bg-white p-4 rounded-3xl border border-slate-200 transition-colors hover:border-indigo-300">
            <div class="flex items-start gap-4">
                <div class="pt-1">
                    <label class="relative flex items-center cursor-pointer">
                        <input type="checkbox" class="shop-checkbox peer sr-only" 
                               data-supplier="${item.supplier}" 
                               data-price="${item.price}"
                               onchange="updateSelection(this, 'row-${index}')">
                        <div class="w-6 h-6 bg-slate-100 border-2 border-slate-200 rounded-lg peer-checked:bg-indigo-600 peer-checked:border-indigo-600 transition-all flex items-center justify-center">
                            <i class="fas fa-check text-white text-[10px] opacity-0 peer-checked:opacity-100"></i>
                        </div>
                    </label>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <h4 class="font-bold text-slate-800 leading-tight group-hover:text-indigo-600 transition-colors">${item.supplier}</h4>
                            <p class="text-[10px] text-slate-400 mt-1"><i class="fas fa-shield-alt mr-1"></i>Verified by AI Assistant</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xl font-black ${isBest ? 'text-emerald-600' : 'text-slate-700'}">
                                ฿${currentPrice.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </span>
                            ${isBest ? '<div class="text-[9px] font-bold text-emerald-500 uppercase tracking-tighter">ราคาดีที่สุด</div>' : ''}
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <p class="text-xs text-slate-500 italic">"${item.note || 'ไม่มีระบุหมายเหตุ'}"</p>
                    </div>
                </div>
            </div>
        </div>`;
        });

        resultDiv.innerHTML = html + `</div>`;
    }

    function updateSelection(checkbox, rowId) {
        const row = document.getElementById(rowId);
        const checkboxes = document.querySelectorAll('.shop-checkbox:checked');
        const badge = document.getElementById('selectionBadge');
        const countText = document.getElementById('selectedCount');

        if (checkbox.checked) {
            row.classList.add('border-indigo-500', 'bg-indigo-50/20', 'ring-1', 'ring-indigo-500');
        } else {
            row.classList.remove('border-indigo-500', 'bg-indigo-50/20', 'ring-1', 'ring-indigo-500');
        }

        countText.innerText = checkboxes.length;
        if (checkboxes.length > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function processSelectedShops() {
        const selected = Array.from(document.querySelectorAll('.shop-checkbox:checked')).map(cb => ({
            supplier: cb.dataset.supplier,
            price: cb.dataset.price,
            note: cb.closest('.group').querySelector('.italic').innerText.replace(/"/g, '')
        }));

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'compare_page.php';

        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'selected_data'; input.value = JSON.stringify(selected);

        const productInput = document.createElement('input');
        productInput.type = 'hidden'; productInput.name = 'product_name'; productInput.value = document.getElementById('productSearch').value;

        form.appendChild(input);
        form.appendChild(productInput);
        document.body.appendChild(form);
        form.submit();
    }

    function renderMockResult(name) {
        document.getElementById('aiResult').innerHTML = `
            <div class="p-12 text-center bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <i class="fas fa-exclamation-triangle text-orange-400 text-4xl mb-3"></i>
                <p class="text-slate-800 font-bold">การเชื่อมต่อขัดข้อง</p>
                <p class="text-slate-500 text-sm">Model ที่เลือกอาจไม่พร้อมใช้งานชั่วคราว หรือ API Key หมดอายุ</p>
            </div>`;
    }
</script>
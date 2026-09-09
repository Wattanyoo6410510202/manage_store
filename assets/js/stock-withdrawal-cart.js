(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.StockWithdrawalCart = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  function normalizeQuantity(value, available) {
    const numericValue = Number(value);
    const numericAvailable = Math.max(0, Math.floor(Number(available) || 0));
    if (!Number.isFinite(numericValue) || numericValue <= 0 || numericAvailable <= 0) return 0;
    return Math.min(Math.floor(numericValue), numericAvailable);
  }

  function normalizeQuantityInputValue(value, available, minimum = 1) {
    const rawValue = String(value ?? '').trim();
    if (rawValue === '') return '';
    const normalized = normalizeQuantity(rawValue, available);
    if (normalized > 0) return String(normalized);
    const wholeAvailable = Math.max(0, Math.floor(Number(available) || 0));
    const wholeMinimum = Math.max(0, Math.floor(Number(minimum) || 0));
    return String(Math.min(wholeMinimum, wholeAvailable));
  }

  function blocksNonIntegerKey(key) {
    return ['.', ',', 'e', 'E', '+', '-'].includes(String(key));
  }

  function mountWholeQuantityInputs(root) {
    if (!root) return;
    root.querySelectorAll('[data-stock-whole-quantity]').forEach((input) => {
      if (input.dataset.stockWholeQuantityBound === '1') return;
      input.dataset.stockWholeQuantityBound = '1';
      input.addEventListener('keydown', (event) => {
        if (blocksNonIntegerKey(event.key)) event.preventDefault();
      });
      input.addEventListener('input', () => {
        input.value = normalizeQuantityInputValue(input.value, input.max, input.min);
      });
    });
  }

  function addCartItem(cart, product, quantity) {
    const productId = Number(product.id);
    const current = cart.find((item) => Number(item.id) === productId);
    const nextQuantity = normalizeQuantity((current?.quantity || 0) + Number(quantity || 0), product.available);
    if (nextQuantity <= 0) return cart.slice();
    if (current) {
      return cart.map((item) => Number(item.id) === productId ? { ...item, quantity: nextQuantity } : item);
    }
    return cart.concat({
      id: productId,
      name: String(product.name || ''),
      unit: String(product.unit || ''),
      available: Math.max(0, Math.floor(Number(product.available) || 0)),
      quantity: nextQuantity,
    });
  }

  function setCartItemQuantity(cart, productId, quantity) {
    const targetId = Number(productId);
    const current = cart.find((item) => Number(item.id) === targetId);
    if (!current) return cart.slice();
    const nextQuantity = normalizeQuantity(quantity, current.available);
    if (nextQuantity <= 0) return removeCartItem(cart, targetId);
    return cart.map((item) => Number(item.id) === targetId ? { ...item, quantity: nextQuantity } : item);
  }

  function removeCartItem(cart, productId) {
    const targetId = Number(productId);
    return cart.filter((item) => Number(item.id) !== targetId);
  }

  function summarizeCart(cart) {
    return {
      lineCount: cart.length,
      unitCount: cart.reduce((total, item) => total + Number(item.quantity || 0), 0),
    };
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatQuantity(value) {
    return Number(value).toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function mountWithdrawalCart(root) {
    if (!root) return;
    const cartButton = root.querySelector('[data-stock-cart-button]');
    const cartPanel = root.querySelector('[data-stock-cart-panel]');
    const backdrop = root.querySelector('[data-stock-cart-backdrop]');
    const closeButtons = root.querySelectorAll('[data-stock-cart-close]');
    const cartCount = root.querySelector('[data-stock-cart-count]');
    const cartItems = root.querySelector('[data-stock-cart-items]');
    const emptyState = root.querySelector('[data-stock-cart-empty]');
    const submitButton = root.querySelector('[data-stock-cart-submit]');
    const summary = root.querySelector('[data-stock-cart-summary]');
    const liveRegion = root.querySelector('[data-stock-cart-live]');
    let cart = [];

    if (!cartButton || !cartPanel || !backdrop || !cartItems) return;

    function openCart() {
      backdrop.classList.remove('hidden');
      requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
      cartPanel.classList.remove('translate-y-full', 'md:translate-x-full');
      cartPanel.classList.add('translate-y-0', 'md:translate-x-0');
      cartPanel.setAttribute('aria-hidden', 'false');
      cartButton.setAttribute('aria-expanded', 'true');
      document.body.classList.add('overflow-hidden');
    }

    function closeCart() {
      backdrop.classList.remove('opacity-100');
      cartPanel.classList.remove('translate-y-0', 'md:translate-x-0');
      cartPanel.classList.add('translate-y-full', 'md:translate-x-full');
      cartPanel.setAttribute('aria-hidden', 'true');
      cartButton.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('overflow-hidden');
      window.setTimeout(() => {
        if (cartPanel.getAttribute('aria-hidden') === 'true') backdrop.classList.add('hidden');
      }, 200);
    }

    function announce(message) {
      if (!liveRegion) return;
      liveRegion.textContent = '';
      window.setTimeout(() => { liveRegion.textContent = message; }, 20);
    }

    function renderCart() {
      const totals = summarizeCart(cart);
      cartCount.textContent = String(totals.lineCount);
      cartCount.classList.toggle('hidden', totals.lineCount === 0);
      emptyState.classList.toggle('hidden', totals.lineCount > 0);
      if (summary) summary.textContent = totals.lineCount > 0 ? `${totals.lineCount} รายการพัสดุ` : 'ยังไม่มีพัสดุ';
      if (submitButton) submitButton.disabled = totals.lineCount === 0;
      cartItems.innerHTML = cart.map((item) => `
        <div class="rounded-2xl border border-slate-200 bg-white p-4" data-stock-cart-item="${item.id}">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-semibold text-slate-900">${escapeHtml(item.name)}</div>
              <div class="mt-1 text-xs text-slate-500">คงเหลือ ${formatQuantity(item.available)} ${escapeHtml(item.unit)}</div>
            </div>
            <button type="button" data-stock-cart-remove class="shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600" aria-label="ลบ ${escapeHtml(item.name)} ออกจากตะกร้า"><i class="fas fa-trash-alt"></i></button>
          </div>
          <div class="mt-3 flex items-center justify-between gap-3">
            <span class="text-xs font-semibold text-slate-600">จำนวนที่ขอเบิก</span>
            <div class="flex items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
              <button type="button" data-stock-cart-decrement class="h-10 w-10 text-slate-600 transition-colors hover:bg-slate-100" aria-label="ลดจำนวน"><i class="fas fa-minus text-xs"></i></button>
              <input data-stock-cart-quantity name="quantity[${item.id}]" type="number" min="1" max="${item.available}" step="1" inputmode="numeric" value="${item.quantity}" class="h-10 w-20 border-x border-slate-200 text-center font-bold text-slate-900 outline-none focus:bg-indigo-50">
              <button type="button" data-stock-cart-increment class="h-10 w-10 text-slate-600 transition-colors hover:bg-slate-100" aria-label="เพิ่มจำนวน"><i class="fas fa-plus text-xs"></i></button>
            </div>
          </div>
        </div>
      `).join('');
    }

    root.querySelectorAll('[data-stock-product-row]').forEach((row) => {
      const quantityInput = row.querySelector('[data-stock-product-quantity]');
      const addButton = row.querySelector('[data-stock-add-to-cart]');
      quantityInput?.addEventListener('keydown', (event) => {
        if (blocksNonIntegerKey(event.key)) event.preventDefault();
      });
      quantityInput?.addEventListener('input', () => {
        quantityInput.value = normalizeQuantityInputValue(quantityInput.value, row.dataset.available);
      });
      row.querySelector('[data-stock-product-decrement]')?.addEventListener('click', () => {
        quantityInput.value = String(Math.max(1, Math.floor(Number(quantityInput.value || 1)) - 1));
      });
      row.querySelector('[data-stock-product-increment]')?.addEventListener('click', () => {
        quantityInput.value = String(Math.min(Math.floor(Number(row.dataset.available)), Math.floor(Number(quantityInput.value || 0)) + 1));
      });
      addButton?.addEventListener('click', () => {
        const product = {
          id: Number(row.dataset.productId),
          name: row.dataset.productName,
          unit: row.dataset.unit,
          available: Number(row.dataset.available),
        };
        const requested = normalizeQuantity(quantityInput.value, product.available);
        if (requested <= 0) return;
        cart = addCartItem(cart, product, requested);
        renderCart();
        announce(`เพิ่ม ${product.name} ลงตะกร้าแล้ว`);
        addButton.classList.add('bg-emerald-600');
        addButton.innerHTML = '<i class="fas fa-check mr-1.5"></i>เพิ่มแล้ว';
        window.setTimeout(() => {
          addButton.classList.remove('bg-emerald-600');
          addButton.innerHTML = '<i class="fas fa-cart-plus mr-1.5"></i>เพิ่มลงตะกร้า';
        }, 900);
      });
    });

    cartItems.addEventListener('click', (event) => {
      const itemElement = event.target.closest('[data-stock-cart-item]');
      if (!itemElement) return;
      const productId = Number(itemElement.dataset.stockCartItem);
      const item = cart.find((entry) => Number(entry.id) === productId);
      if (!item) return;
      if (event.target.closest('[data-stock-cart-remove]')) cart = removeCartItem(cart, productId);
      if (event.target.closest('[data-stock-cart-decrement]')) cart = setCartItemQuantity(cart, productId, item.quantity - 1);
      if (event.target.closest('[data-stock-cart-increment]')) cart = setCartItemQuantity(cart, productId, item.quantity + 1);
      renderCart();
    });

    cartItems.addEventListener('change', (event) => {
      if (!event.target.matches('[data-stock-cart-quantity]')) return;
      const itemElement = event.target.closest('[data-stock-cart-item]');
      cart = setCartItemQuantity(cart, Number(itemElement.dataset.stockCartItem), event.target.value);
      renderCart();
    });

    cartItems.addEventListener('keydown', (event) => {
      if (event.target.matches('[data-stock-cart-quantity]') && blocksNonIntegerKey(event.key)) {
        event.preventDefault();
      }
    });

    cartItems.addEventListener('input', (event) => {
      if (!event.target.matches('[data-stock-cart-quantity]')) return;
      const itemElement = event.target.closest('[data-stock-cart-item]');
      const item = cart.find((entry) => Number(entry.id) === Number(itemElement.dataset.stockCartItem));
      if (!item) return;
      event.target.value = normalizeQuantityInputValue(event.target.value, item.available);
    });

    cartButton.addEventListener('click', openCart);
    backdrop.addEventListener('click', closeCart);
    closeButtons.forEach((button) => button.addEventListener('click', closeCart));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && cartPanel.getAttribute('aria-hidden') === 'false') closeCart();
    });
    root.addEventListener('submit', (event) => {
      if (cart.length === 0) {
        event.preventDefault();
        openCart();
        announce('กรุณาเพิ่มพัสดุอย่างน้อย 1 รายการ');
      }
    });

    renderCart();
  }

  return {
    addCartItem,
    setCartItemQuantity,
    removeCartItem,
    summarizeCart,
    normalizeQuantityInputValue,
    blocksNonIntegerKey,
    mountWholeQuantityInputs,
    mountWithdrawalCart,
  };
});

(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.StockWithdrawalFilter = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  function normalize(value) {
    return String(value || '').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
  }

  function filterWithdrawalProducts(products, query, category) {
    const normalizedQuery = normalize(query);
    const selectedCategory = String(category || '');
    return products.filter((product) => {
      const matchesName = normalizedQuery === '' || normalize(product.name).includes(normalizedQuery);
      const matchesCategory = selectedCategory === '' || String(product.category || '') === selectedCategory;
      return matchesName && matchesCategory;
    });
  }

  function splitHighlightSegments(text, query) {
    const source = String(text || '');
    const needle = String(query || '').trim();
    if (needle === '') return [{ text: source, match: false }];
    const lowerSource = source.toLocaleLowerCase('th-TH');
    const lowerNeedle = needle.toLocaleLowerCase('th-TH');
    const segments = [];
    let cursor = 0;
    let matchIndex = lowerSource.indexOf(lowerNeedle, cursor);
    while (matchIndex !== -1) {
      if (matchIndex > cursor) segments.push({ text: source.slice(cursor, matchIndex), match: false });
      const matchEnd = matchIndex + needle.length;
      segments.push({ text: source.slice(matchIndex, matchEnd), match: true });
      cursor = matchEnd;
      matchIndex = lowerSource.indexOf(lowerNeedle, cursor);
    }
    if (cursor < source.length) segments.push({ text: source.slice(cursor), match: false });
    return segments.length > 0 ? segments : [{ text: source, match: false }];
  }

  function renderHighlight(element, name, query) {
    const fragment = document.createDocumentFragment();
    splitHighlightSegments(name, query).forEach((segment) => {
      if (!segment.match) {
        fragment.append(document.createTextNode(segment.text));
        return;
      }
      const mark = document.createElement('mark');
      mark.className = 'rounded bg-amber-200 px-0.5 text-inherit';
      mark.textContent = segment.text;
      fragment.append(mark);
    });
    element.replaceChildren(fragment);
  }

  function mountWithdrawalFilter(root) {
    if (!root) return;
    const searchInput = root.querySelector('[data-stock-withdrawal-search]');
    const categorySelect = root.querySelector('[data-stock-category-filter]');
    const resultCount = root.querySelector('[data-stock-withdrawal-result-count]');
    const emptyState = root.querySelector('[data-stock-category-empty]');
    const rows = Array.from(root.querySelectorAll('[data-stock-product-row]'));
    if (!searchInput || !categorySelect || !resultCount) return;

    const products = rows.map((row, index) => ({
      id: index,
      name: row.dataset.productName || '',
      category: row.dataset.category || '',
      row,
      nameElement: row.querySelector('[data-stock-product-name-text]'),
    }));

    function applyFilters() {
      const visibleProducts = filterWithdrawalProducts(products, searchInput.value, categorySelect.value);
      const visibleIds = new Set(visibleProducts.map((product) => product.id));
      products.forEach((product) => {
        product.row.classList.toggle('hidden', !visibleIds.has(product.id));
        if (product.nameElement) renderHighlight(product.nameElement, product.name, searchInput.value);
      });
      resultCount.textContent = `พบ ${visibleProducts.length} จาก ${products.length} รายการ`;
      if (emptyState) emptyState.classList.toggle('hidden', products.length === 0 || visibleProducts.length > 0);
    }

    searchInput.addEventListener('input', applyFilters);
    categorySelect.addEventListener('change', applyFilters);
    applyFilters();
  }

  return { filterWithdrawalProducts, splitHighlightSegments, mountWithdrawalFilter };
});

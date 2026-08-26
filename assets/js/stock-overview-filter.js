(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.StockOverviewFilter = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  function normalize(value) {
    return String(value || '').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
  }

  function filterStockProducts(products, query, category) {
    const normalizedQuery = normalize(query);
    const selectedCategory = String(category || '');
    return products.filter((product) => {
      const matchesName = normalizedQuery === '' || normalize(product.name).includes(normalizedQuery);
      const productCategory = String(product.category || '');
      const matchesCategory = selectedCategory === ''
        || (selectedCategory === '__uncategorized__' ? productCategory === '' : productCategory === selectedCategory);
      return matchesName && matchesCategory;
    });
  }

  function splitOverviewHighlightSegments(text, query) {
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

  function renderOverviewHighlight(element, name, query) {
    const fragment = document.createDocumentFragment();
    splitOverviewHighlightSegments(name, query).forEach((segment) => {
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

  function mountStockOverviewFilter(root) {
    if (!root) return;
    const searchInput = root.querySelector('[data-stock-overview-search]');
    const categorySelect = root.querySelector('[data-stock-overview-category]');
    const resultCount = root.querySelector('[data-stock-overview-result-count]');
    const emptyState = root.querySelector('[data-stock-overview-empty]');
    const rows = Array.from(root.querySelectorAll('[data-stock-overview-row]'));
    if (!searchInput || !categorySelect || !resultCount) return;

    const products = rows.map((row, index) => ({
      id: index,
      name: row.dataset.productName || '',
      category: row.dataset.category || '',
      row,
      nameElement: row.querySelector('[data-stock-overview-name-text]'),
    }));

    function applyFilters() {
      const visibleProducts = filterStockProducts(products, searchInput.value, categorySelect.value);
      const visibleIds = new Set(visibleProducts.map((product) => product.id));
      products.forEach((product) => {
        product.row.classList.toggle('hidden', !visibleIds.has(product.id));
        if (product.nameElement) renderOverviewHighlight(product.nameElement, product.name, searchInput.value);
      });
      resultCount.textContent = `พบ ${visibleProducts.length} จาก ${products.length} รายการ`;
      if (emptyState) emptyState.classList.toggle('hidden', products.length === 0 || visibleProducts.length > 0);
    }

    searchInput.addEventListener('input', applyFilters);
    categorySelect.addEventListener('change', applyFilters);
    applyFilters();
  }

  return { filterStockProducts, splitOverviewHighlightSegments, mountStockOverviewFilter };
});

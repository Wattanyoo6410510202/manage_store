(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.StockProductAutocomplete = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  function normalize(value) {
    return String(value || '').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
  }

  function rankStockProducts(products, query) {
    const normalizedQuery = normalize(query);
    if (!normalizedQuery) return [];
    const words = normalizedQuery.split(' ').filter(Boolean);
    return products
      .map((product, index) => {
        const name = normalize(product.name);
        let score = Number.POSITIVE_INFINITY;
        if (name === normalizedQuery) score = 0;
        else if (name.startsWith(normalizedQuery)) score = 10;
        else if (name.includes(normalizedQuery)) score = 20;
        else if (words.every((word) => name.includes(word))) score = 30;
        return { product, score, index };
      })
      .filter((entry) => Number.isFinite(entry.score))
      .sort((left, right) => left.score - right.score || left.product.name.length - right.product.name.length || left.index - right.index)
      .map((entry) => entry.product);
  }

  function resolveStockUnitConversion(product, purchaseUnit) {
    const stockUnit = String(product?.unit || '').trim();
    const normalizedPurchaseUnit = normalize(purchaseUnit);
    const conversions = product?.unit_conversions || {};
    const rememberedEntry = Object.entries(conversions).find(([unit]) => normalize(unit) === normalizedPurchaseUnit);
    if (rememberedEntry && Number(rememberedEntry[1]) > 0) {
      return { stockUnit, factor: Number(rememberedEntry[1]) };
    }
    return {
      stockUnit,
      factor: normalize(stockUnit) === normalizedPurchaseUnit ? 1 : null,
    };
  }

  return { rankStockProducts, resolveStockUnitConversion };
});

const assert = require('node:assert/strict');
const { filterStockProducts, splitOverviewHighlightSegments } = require('../assets/js/stock-overview-filter.js');

const products = [
  { id: 1, name: 'แชมพู', category: 'ของใช้ในห้องพัก' },
  { id: 2, name: 'เจลล้างมือ', category: 'ของใช้สำนักงาน' },
  { id: 3, name: 'กระดาษ A4', category: 'ของใช้สำนักงาน' },
  { id: 4, name: 'USB Cable', category: 'อุปกรณ์ไอที' },
  { id: 5, name: 'สินค้าไม่มีหมวด', category: '' },
];

assert.deepEqual(
  filterStockProducts(products, 'เ', '').map((product) => product.id),
  [2],
  'typing the first character must immediately keep products whose names contain it'
);
assert.deepEqual(
  filterStockProducts(products, 'กระ', 'ของใช้สำนักงาน').map((product) => product.id),
  [3],
  'name search and category selection must be applied together'
);
assert.deepEqual(
  filterStockProducts(products, '', 'ของใช้สำนักงาน').map((product) => product.id),
  [2, 3],
  'an empty search must still allow category-only filtering'
);
assert.deepEqual(
  filterStockProducts(products, ' usb ', '').map((product) => product.id),
  [4],
  'search must ignore surrounding spaces and Latin letter case'
);
assert.deepEqual(
  filterStockProducts(products, '', '__uncategorized__').map((product) => product.id),
  [5],
  'the category dropdown must be able to isolate products without a category'
);
assert.deepEqual(
  splitOverviewHighlightSegments('เจลล้างมือ เจล', 'เจล'),
  [
    { text: 'เจล', match: true },
    { text: 'ล้างมือ ', match: false },
    { text: 'เจล', match: true },
  ],
  'every matching part of an overview product name must be isolated for highlighting'
);

console.log('stock overview filter behavior: PASS');

const assert = require('node:assert/strict');
const {
  filterWithdrawalProducts,
  splitHighlightSegments,
} = require('../assets/js/stock-withdrawal-filter.js');

const products = [
  { id: 1, name: 'เจลล้างมือ', category: 'ของใช้สำนักงาน' },
  { id: 2, name: 'กระดาษ A4', category: 'ของใช้สำนักงาน' },
  { id: 3, name: 'ผ้าเช็ดตัว', category: 'ของใช้ในห้องพัก' },
];

assert.deepEqual(
  filterWithdrawalProducts(products, 'เ', '').map((product) => product.id),
  [1, 3],
  'typing the first character must immediately filter withdrawal products by name'
);
assert.deepEqual(
  filterWithdrawalProducts(products, 'เจล', 'ของใช้สำนักงาน').map((product) => product.id),
  [1],
  'withdrawal name search and category selection must work together'
);
assert.deepEqual(
  splitHighlightSegments('เจลล้างมือ', 'เจล'),
  [
    { text: 'เจล', match: true },
    { text: 'ล้างมือ', match: false },
  ],
  'the matching part of a Thai product name must be isolated for highlighting'
);
assert.deepEqual(
  splitHighlightSegments('USB Cable usb', 'usb'),
  [
    { text: 'USB', match: true },
    { text: ' Cable ', match: false },
    { text: 'usb', match: true },
  ],
  'every case-insensitive occurrence must be highlighted without changing the original text'
);

console.log('stock withdrawal filter behavior: PASS');

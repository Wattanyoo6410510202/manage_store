const assert = require('node:assert/strict');
const { rankStockProducts, resolveStockUnitConversion } = require('../assets/js/stock-product-autocomplete.js');

const products = [
  { id: 1, name: 'แชมพู', category: 'ของใช้ในห้องพัก', unit: 'ขวด', quantity: 12 },
  { id: 2, name: 'แชมพูสระผม สูตรอ่อนโยน', category: 'ของใช้ในห้องพัก', unit: 'ขวด', quantity: 4 },
  { id: 3, name: 'ครีมอาบน้ำ', category: 'ของใช้ในห้องพัก', unit: 'ขวด', quantity: 8 },
  { id: 4, name: 'น้ำยาทำความสะอาดพื้น', category: 'แม่บ้าน', unit: 'แกลลอน', quantity: 2 },
];

assert.deepEqual(
  rankStockProducts(products, 'แชม').map((product) => product.id),
  [1, 2],
  'typing part of a Thai product name must show every close substring match'
);
assert.equal(rankStockProducts(products, 'แชมพู')[0].id, 1, 'an exact product name must rank first');
assert.deepEqual(
  rankStockProducts(products, 'ทำความ พื้น').map((product) => product.id),
  [4],
  'all query words must be allowed to match within the same product name'
);
assert.deepEqual(rankStockProducts(products, 'กาแฟ'), [], 'unrelated products must not appear');

assert.deepEqual(
  resolveStockUnitConversion({ unit: 'ชิ้น', unit_conversions: { ลัง: 24 } }, 'ลัง'),
  { stockUnit: 'ชิ้น', factor: 24 },
  'a remembered purchase unit must prefill its conversion'
);
assert.deepEqual(
  resolveStockUnitConversion({ unit: 'ขวด', unit_conversions: {} }, 'ขวด'),
  { stockUnit: 'ขวด', factor: 1 },
  'matching purchase and Stock units must default to one'
);
assert.deepEqual(
  resolveStockUnitConversion({ unit: 'ชิ้น', unit_conversions: {} }, 'ลัง'),
  { stockUnit: 'ชิ้น', factor: null },
  'an unknown case size must require procurement input'
);

console.log('stock product autocomplete behavior: PASS');

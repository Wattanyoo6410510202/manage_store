const assert = require('node:assert/strict');
const {
  addCartItem,
  setCartItemQuantity,
  removeCartItem,
  summarizeCart,
  normalizeQuantityInputValue,
  blocksNonIntegerKey,
} = require('../assets/js/stock-withdrawal-cart.js');

const pen = { id: 11, name: 'ปากกาลูกลื่น', unit: 'ด้าม', available: 3 };
const paper = { id: 22, name: 'กระดาษ A4', unit: 'รีม', available: 10 };

assert.equal(normalizeQuantityInputValue('1.5', 10), '1', 'a pasted decimal must be converted to a whole quantity immediately');
assert.equal(normalizeQuantityInputValue('20', 10), '10', 'typed quantities must remain capped at available Stock');
assert.equal(normalizeQuantityInputValue('', 10), '', 'an input may be temporarily empty while editing');
assert.equal(normalizeQuantityInputValue('0.5', 3, 0), '0', 'received quantity may use whole-number zero but never a decimal');
assert.equal(blocksNonIntegerKey('.'), true, 'the decimal-point key must be blocked');
assert.equal(blocksNonIntegerKey('e'), true, 'scientific notation must be blocked');
assert.equal(blocksNonIntegerKey('5'), false, 'whole-number keys must remain available');

let cart = addCartItem([], pen, 2);
assert.deepEqual(cart, [{ ...pen, quantity: 2 }], 'adding a product must put the selected quantity in the cart');

cart = addCartItem(cart, pen, 2);
assert.equal(cart[0].quantity, 3, 'adding the same product again must accumulate without exceeding current Stock');

cart = addCartItem(cart, paper, 1.5);
assert.deepEqual(
  summarizeCart(cart),
  { lineCount: 2, unitCount: 4 },
  'withdrawal quantities must be normalized to whole units'
);

cart = setCartItemQuantity(cart, 22, 2.75);
assert.equal(cart.find((item) => item.id === 22).quantity, 2, 'editing a cart line must keep a whole quantity');

cart = setCartItemQuantity(cart, 22, 20);
assert.equal(cart.find((item) => item.id === 22).quantity, 10, 'editing a cart line must remain capped at available Stock');

cart = setCartItemQuantity(cart, 11, 0);
assert.equal(cart.some((item) => item.id === 11), false, 'setting a cart line to zero must remove it');

cart = removeCartItem(cart, 22);
assert.deepEqual(cart, [], 'the user must be able to remove a product from the cart');

console.log('stock withdrawal cart behavior: PASS');

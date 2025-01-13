function sum(a, b) {
  return a + b;
}

test('sums two numbers', () => {
  expect(sum(1, 2)).toBe(3);
});

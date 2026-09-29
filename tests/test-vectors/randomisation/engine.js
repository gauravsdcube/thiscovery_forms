/**
 * The same draws as RandomisationEngine.php. A change to either file should fail this check.
 */
const fs = require('fs');
const path = require('path');

function step(seed) {
  return Number((BigInt(seed) * 1664525n + 1013904223n) & 0x7fffffffn);
}

function shuffle(items, seed) {
  items = items.slice();
  for (let i = items.length - 1; i > 0; i--) {
    seed = step(seed);
    const j = seed % (i + 1);
    const tmp = items[i];
    items[i] = items[j];
    items[j] = tmp;
  }
  return items;
}

function rotate(items, offset) {
  items = items.slice();
  const n = items.length;
  if (n < 2) return items;
  offset = offset % n;
  if (offset < 0) offset += n;
  if (offset === 0) return items;
  return items.slice(offset).concat(items.slice(0, offset));
}

function weightedUnit(arms) {
  const unit = [];
  for (const arm of arms) {
    const code = String(arm.code || '').trim();
    const weight = Math.max(0, parseInt(arm.weight, 10) || 0);
    if (!code || weight < 1) continue;
    for (let i = 0; i < weight; i++) unit.push(code);
  }
  return unit;
}

function block(arms, blockSize, seed) {
  const unit = weightedUnit(arms);
  if (!unit.length) return [];
  blockSize = Math.max(unit.length, blockSize);
  const slots = [];
  while (slots.length < blockSize) {
    for (const code of unit) {
      slots.push(code);
      if (slots.length >= blockSize) break;
    }
  }
  return shuffle(slots, seed);
}

function weightedPick(arms, seed) {
  const unit = weightedUnit(arms);
  if (!unit.length) return '';
  seed = step(seed);
  return unit[seed % unit.length];
}

function leastFilled(counts, eligible, seed) {
  const unique = [];
  for (const code of eligible.map(String)) {
    if (!unique.includes(code)) unique.push(code);
  }
  if (!unique.length) return '';
  let best = null;
  for (const code of unique) {
    const n = counts[code] || 0;
    if (best === null || n < best) best = n;
  }
  const tied = unique.filter((code) => (counts[code] || 0) === best).sort();
  seed = step(seed);
  return tied[seed % tied.length];
}

function crc32(text) {
  let crc = 0xffffffff;
  for (let i = 0; i < text.length; i++) {
    crc ^= text.charCodeAt(i);
    for (let bit = 0; bit < 8; bit++) {
      crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

const expected = JSON.parse(fs.readFileSync(path.join(__dirname, 'seeds.json'), 'utf8'));
const fails = [];
const eq = (a, b, message) => {
  if (JSON.stringify(a) !== JSON.stringify(b)) fails.push(message + ' got ' + JSON.stringify(a));
};
eq(shuffle(['a', 'b', 'c', 'd', 'e'], 42), expected.shuffle_seed_42, 'shuffle');
eq(rotate(['a', 'b', 'c'], 1), expected.rotate_offset_1, 'rotate');
eq(block([{ code: 'usual', weight: 1 }, { code: 'new', weight: 1 }], 4, 7), expected.block_size_4, 'block');
eq(leastFilled({ usual: 2, new: 2 }, ['new', 'usual'], 3), expected.least_filled, 'least filled');
eq(weightedPick([{ code: 'usual', weight: 1 }, { code: 'new', weight: 2 }], 9), expected.weighted, 'weighted');
eq((crc32('ab:options:colour') & 0x7fffffff) >>> 0, expected.seed_int, 'seed');

if (fails.length) {
  console.error(fails.join('\n'));
  process.exit(1);
}
console.log('PASS');

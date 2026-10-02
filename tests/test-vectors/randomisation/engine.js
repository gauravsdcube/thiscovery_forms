/**
 * The same draws as RandomisationEngine.php. A change to either file should fail this check.
 */
const fs = require('fs');
const path = require('path');

const crypto = require('crypto');

// Mirror of RandomisationEngine::stream(): SHA-256 counter blocks, 4-byte big-endian draws,
// rejection sampling so every value in [0, bound) is equally likely.
function stream(seed) {
  let counter = 0;
  let buffer = Buffer.alloc(0);
  return function (bound) {
    if (bound < 2) return 0;
    const limit = Math.floor(0x100000000 / bound) * bound;
    for (;;) {
      if (buffer.length < 4) {
        const block = crypto.createHash('sha256').update(String(seed) + ':' + counter).digest();
        buffer = Buffer.concat([buffer, block]);
        counter++;
      }
      const value = buffer.readUInt32BE(0);
      buffer = buffer.subarray(4);
      if (value < limit) return value % bound;
    }
  };
}

function shuffle(items, seed) {
  items = items.slice();
  const draw = stream(seed);
  for (let i = items.length - 1; i > 0; i--) {
    const j = draw(i + 1);
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
  blockSize = Math.ceil(blockSize / unit.length) * unit.length;
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
  return unit[stream(seed)(unit.length)];
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
  return tied[stream(seed)(tied.length)];
}

// Mirror of RandomisationEngine::seedInt(): HMAC-SHA256 keyed by the response seed.
function seedInt(seedHex, scope) {
  return BigInt('0x' + crypto.createHmac('sha256', seedHex).update(scope).digest('hex').slice(0, 15)).toString();
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
eq(seedInt('ab', 'options:colour'), expected.seed_int, 'seed');
eq(shuffle(['a', 'b', 'c', 'd', 'e'], BigInt(expected.seed_int)), expected.shuffle_big_seed, 'shuffle with a 60-bit seed');

if (fails.length) {
  console.error(fails.join('\n'));
  process.exit(1);
}
console.log('PASS');

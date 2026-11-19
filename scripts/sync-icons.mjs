/**
 * Copies the Tabler icons the views use into resources/icons/ as small inline SVGs,
 * so the app ships only the icons it needs (no icon font).
 *
 * Found automatically: <x-icon name="...">, icon="..." attributes and 'icon' => '...'
 * array entries. Add names built at runtime to SAFELIST.
 *
 * Run: npm run icons   (also runs before npm run build)
 */
import { existsSync, mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

// Icons whose names are built in PHP/JS (arrays, ternaries) and so can't be found by the patterns below.
const SAFELIST = [
    'sun', 'moon', 'device-desktop', 'circle-check', 'alert-triangle', 'alert-circle', 'info-circle', 'x',
    'trending-up', 'trending-down',
    'brand-facebook', 'brand-instagram', 'speakerphone', 'building', 'user-plus', 'shield-lock',
    'chart-area-line', 'photo', 'users-group', 'messages', 'list-check', 'refresh',
    'cash', 'eye', 'users', 'click', 'percentage', 'receipt',
    // Google Ads, BSB and response times
    'brand-google', 'brand-meta', 'message-2', 'brand-whatsapp', 'device-mobile-message', 'send', 'checks', 'hourglass',
    'circle-x', 'target-arrow', 'coin', 'wallet', 'hand-click', 'heart', 'player-play', 'stopwatch', 'clock-pause',
    'message-circle-exclamation', 'clock-hour-4', 'message-circle', 'clock',
];
const SCAN = ['resources/views', 'app'];
const SOURCE = 'node_modules/@tabler/icons/icons/outline';
const TARGET = 'resources/icons';

const patterns = [
    /<x-icon\b[^>]*?\bname="([a-z0-9-]+)"/g,
    /\bicon="([a-z0-9-]+)"/g,
    /'icon'\s*=>\s*'([a-z0-9-]+)'/g,
];

function walk(dir, out = []) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) walk(path, out);
        else if (path.endsWith('.php')) out.push(path);
    }
    return out;
}

const names = new Set(SAFELIST);
for (const file of SCAN.flatMap((dir) => walk(dir))) {
    const text = readFileSync(file, 'utf8');
    for (const pattern of patterns) {
        for (const match of text.matchAll(pattern)) names.add(match[1]);
    }
}

mkdirSync(TARGET, { recursive: true });
const missing = [];
for (const name of [...names].sort()) {
    const source = join(SOURCE, `${name}.svg`);
    if (!existsSync(source)) {
        missing.push(name);
        continue;
    }
    // Collapse whitespace and drop Tabler's invisible bounding-box path.
    const svg = readFileSync(source, 'utf8')
        .replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/, '')
        .replace(/\s+/g, ' ')
        .replace(/>\s+</g, '><')
        .trim();
    writeFileSync(join(TARGET, `${name}.svg`), svg + '\n');
}

console.log(`Icons: ${names.size - missing.length} copied to ${TARGET}.`);
if (missing.length) {
    console.warn(`Not found in Tabler (check the names): ${missing.join(', ')}`);
    process.exitCode = 1;
}

// Builds the front kit's copies of sscss and Minim from node_modules (npm run front).
// sscss.json: the sscss core and one rule per class (with h-/m-/l- variants), so PHP can generate each page's CSS
// (App\Core\Front\FrontCss) the way `sscss page.html --write` does. minim-lite.min.js: Minim lite, inlined by the kit.
import { readFileSync, writeFileSync } from 'node:fs';
import { core, vocab, ruleFor } from '@labs_kai/sscss';

const here = new URL('.', import.meta.url);
const dir = (name) => new URL(`../../node_modules/${name}/`, import.meta.url);
const version = (name) => JSON.parse(readFileSync(new URL('package.json', dir(name)), 'utf8')).version;

const globals = [];
const rules = {};
const names = Object.keys(vocab);
const plain = names.filter((name) => !name.startsWith('an-'));

for (const name of [...names, ...plain.flatMap((n) => ['h-' + n, 'm-' + n, 'l-' + n])]) {
    const rule = ruleFor(name);
    if (!rule) continue;
    const ids = rule.global.map((css) => (globals.includes(css) ? globals : globals.push(css) && globals).indexOf(css));
    rules[name] = [rule.rule, rule.variant, ids];
}

writeFileSync(new URL('sscss.json', here), JSON.stringify({ version: version('@labs_kai/sscss'), core, globals, rules }) + '\n');

const minim = readFileSync(new URL('lite/minim.min.js', dir('@labs_kai/minim')), 'utf8').trim();
if (minim.includes('</script')) throw new Error('minim-lite.min.js contains </script');
writeFileSync(new URL('minim-lite.min.js', here), `/*! Minim ${version('@labs_kai/minim')} lite | MPL-2.0 | github.com/hertonwork/minim */\n${minim}\n`);

console.log(`sscss ${version('@labs_kai/sscss')}: ${Object.keys(rules).length} rules · minim ${version('@labs_kai/minim')} lite`);

// The PHP endpoint uses the same approved edition and benefits as the website.
import fs from 'node:fs';
import vm from 'node:vm';
const context = {window: {}};
vm.runInNewContext(fs.readFileSync('dist/edition.js', 'utf8'), context, {timeout: 1000});
const config = context.window.IJSBAAN;
const edition = config.editions.find(e => e.id === config.activeEdition);
const content = JSON.stringify({edition: {id: edition.id, place: edition.place, season: edition.season}, editions: config.editions.map(({id, place, season}) => ({id, place, season})), packages: config.sponsorPackages}, null, 2) + '\n';
const target = 'server/catalog.json';
if (process.argv.includes('--check')) {
  if (fs.readFileSync(target, 'utf8') !== content) throw new Error('Werk server/catalog.json bij met node scripts/update_sponsor_catalog.mjs');
} else fs.writeFileSync(target, content);

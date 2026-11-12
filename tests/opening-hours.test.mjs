import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';

const context = { window: {} };
for (const name of ['edition.js', 'opening-hours.js']) {
  vm.runInNewContext(fs.readFileSync(new URL(`../dist/${name}`, import.meta.url), 'utf8'), context);
}
const edition = context.window.IJSBAAN.editions[0];
const hours = context.window.IJSBAAN_HOURS;
const expected = [
  ['2026-12-11','19:00,22:00'], ['2026-12-12','10:00,20:00'], ['2026-12-13','closed'],
  ['2026-12-14','15:00,20:00'], ['2026-12-15','15:00,20:00'], ['2026-12-16','15:00,20:00'],
  ['2026-12-17','15:00,20:00'], ['2026-12-18','15:00,20:00'], ['2026-12-19','10:00,20:00'],
  ['2026-12-20','closed'], ['2026-12-21','10:00,20:00'], ['2026-12-22','10:00,20:00'],
  ['2026-12-23','10:00,20:00'], ['2026-12-24','10:00,16:00'], ['2026-12-25','closed'],
  ['2026-12-26','closed'], ['2026-12-27','closed'], ['2026-12-28','10:00,20:00'],
  ['2026-12-29','10:00,20:00'], ['2026-12-30','10:00,20:00'], ['2026-12-31','10:00,16:00'],
  ['2027-01-01','closed'], ['2027-01-02','10:00,14:00'], ['2027-01-03','closed']
];
test('every published day follows the user-confirmed timetable', () => {
  for (const [iso, value] of expected) {
    const day = hours.day(edition, iso);
    assert.equal(day.closed ? 'closed' : day.times.join(','), value, iso);
  }
});
test('today notice starts at Dutch midnight on opening day and stops beyond the known schedule', () => {
  for (const instant of ['2026-11-12T12:00:00Z','2026-12-10T22:59:59Z','2027-01-03T23:00:00Z']) {
    assert.equal(hours.today(edition, new Date(instant)), null, instant);
  }
  const opening = hours.today(edition, new Date('2026-12-10T23:00:00Z'));
  assert.equal(opening.iso, '2026-12-11');
  assert.equal(opening.text, 'Vandaag is de ijsbaan geopend van 19.00 tot 22.00 uur.');
  assert.match(opening.note, /zonder entree/);
  assert.equal(hours.today(edition, new Date('2027-01-03T22:59:59Z')).text, 'Vandaag is de ijsbaan gesloten.');
});
test('school sessions and holidays have the right public wording', () => {
  assert.equal(hours.today(edition,new Date('2026-12-14T09:00:00Z')).text,'Vandaag is de ijsbaan geopend van 15.00 tot 20.00 uur.');
  assert.match(hours.day(edition,'2026-12-14').note,/alleen scholen/);
  assert.equal(hours.today(edition,new Date('2026-12-25T12:00:00Z')).reason,'Eerste kerstdag');
  assert.equal(hours.today({...edition,openingHours:{...edition.openingHours,status:'provisional'}}),null);
});
test('mounted notice stays hidden before opening, then refreshes across midnight and on return', () => {
  let now = '2026-12-10T22:59:59Z';
  const text = {}, note = {};
  const notice = { hidden:true, querySelector:s => s === '[data-today-text]' ? text : note };
  let interval, visibility;
  const document = { hidden:false, querySelector:() => notice, addEventListener:(event,fn) => { if(event==='visibilitychange') visibility=fn; } };
  const window = {...context.window,IJSBAAN_HOURS:{today:e=>hours.today(e,new Date(now))}};
  vm.runInNewContext(fs.readFileSync(new URL('../dist/today.js',import.meta.url),'utf8'),{window,document,setInterval:fn=>{interval=fn;}});
  assert.equal(notice.hidden,true);
  now='2026-12-10T23:00:00Z'; interval();
  assert.equal(notice.hidden,false); assert.match(text.textContent,/19\.00 tot 22\.00/);
  now='2026-12-24T23:00:00Z'; visibility();
  assert.equal(text.textContent,'Vandaag is de ijsbaan gesloten.'); assert.equal(note.textContent,'Eerste kerstdag');
  now='2027-01-03T23:00:00Z'; interval(); assert.equal(notice.hidden,true);
});

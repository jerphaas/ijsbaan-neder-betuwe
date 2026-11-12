// Both the day notice and the timetable use this confirmed edition schedule.
(() => {
  'use strict';
  const localDate = now => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(now);
  const clock = value => value.replace(':', '.');
  function day(edition, iso) {
    const hours = edition?.openingHours;
    if (!hours || !edition.start || !hours.through || iso < edition.start || iso > hours.through || (edition.end && iso > edition.end)) return null;
    const weekday = new Date(`${iso}T12:00:00Z`).getUTCDay();
    const reason = hours.closedDates?.[iso] || '';
    const closed = Boolean(reason) || (hours.closedWeekdays || []).includes(weekday);
    const special = hours.specialDates?.[iso];
    const school = !closed && iso >= hours.schoolStart && iso <= hours.schoolEnd;
    const reservation = hours.schoolReservation?.map(clock).join('–');
    const note = closed ? '' : special?.note || (school ? `${reservation ? reservation + ' uur: ' : ''}alleen scholen` : '');
    return { iso, weekday, reason, closed, note, times: special?.times || (school ? hours.schoolDay : hours.dayOff) };
  }
  function today(edition, now = new Date()) {
    if (edition?.openingHours?.status !== 'confirmed') return null;
    const current = day(edition, localDate(now));
    if (!current) return null;
    const text = current.closed ? 'Vandaag is de ijsbaan gesloten.' : current.times.length === 1
      ? `Vandaag is de ijsbaan geopend vanaf ${clock(current.times[0])} uur.`
      : `Vandaag is de ijsbaan geopend van ${clock(current.times[0])} tot ${clock(current.times[1])} uur.`;
    return { ...current, text };
  }
  window.IJSBAAN_HOURS = Object.freeze({ day, today, localDate });
})();

const API = document.body.dataset.api;
document.querySelectorAll('[data-auto]').forEach(el => el.addEventListener('change', () => el.form.submit()));

async function jget(params) {
  const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null));
  return (await fetch(API + '?' + new URLSearchParams(clean), { credentials: 'same-origin' })).json();
}
function fill(sel, items, placeholder, keep) {
  sel.innerHTML = ''; 
  sel.add(new Option(placeholder, ''));
  items.forEach(i => { const o = new Option(i.label, i.value); o.disabled = !!i.disabled; sel.add(o); });
  if (keep && [...sel.options].some(o => o.value === String(keep) && !o.disabled)) sel.value = String(keep);
}
function durOptions(sel, type, pref) {
  const o = type === 'lab' ? [[120, '2 hours'], [180, '3 hours']] : [[90, '1.5 hours'], [120, '2 hours']];
  sel.innerHTML = ''; 
  o.forEach(([v, l]) => sel.add(new Option(l, v)));
  if (pref && o.some(x => x[0] == pref)) 
    sel.value = pref;
}
/* Free-slot picker: only CURRENTLY free slots/rooms (checked against live bookings, not the general timetable). */
function picker(o) {
  async function slots(keepSlot, keepRoom) {
    const c = o.ctx();
    if (!o.date.value || !c) { 
      fill(o.slot, [], '— choose date & class first —'); 
      fill(o.room, [], '— choose slot first —'); 
      return; 
    }
    const d = await jget({ mode: 'slots', date: o.date.value, dur: o.dur.value, ignore: o.ignore ? o.ignore() : '', ...c });
    fill(o.slot, d.items || [], d.msg || '— choose a free slot —', keepSlot);
    await rooms(keepRoom);
  }
  async function rooms(keepRoom) {
    const c = o.ctx();
    if (!o.slot.value || !c) {
       fill(o.room, [], '— choose slot first —'); 
       return;
      }
    const d = await jget({ mode: 'rooms', date: o.date.value, start: o.slot.value, dur: o.dur.value, ignore: o.ignore ? o.ignore() : '', ...c });
    fill(o.room, d.items || [], '— choose a free room —', keepRoom);
  }
  [o.date, o.dur].forEach(x => x.addEventListener('change', () => slots()));
  o.slot.addEventListener('change', () => rooms());
  return { slots, rooms };
}
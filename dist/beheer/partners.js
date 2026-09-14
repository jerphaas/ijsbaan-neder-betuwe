document.querySelector('[data-profile-form]')?.addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = form.querySelector('button[type="submit"]');
  const status = form.querySelector('[data-profile-status]');
  const logo = form.elements.logo.files[0];
  if (logo && logo.size > 2 * 1024 * 1024) { status.textContent = 'Kies een logo van maximaal 2 MB.'; form.elements.logo.focus(); return; }
  button.disabled = true; status.textContent = 'Sponsor wordt opgeslagen…';
  try {
    const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {Accept: 'application/json'}});
    const result = await response.json();
    if (!response.ok || !result.ok) {
      status.textContent = result.message || 'Opslaan is niet gelukt. Probeer het opnieuw.';
      const field = Object.keys(result.fields || {})[0];
      if (field && form.elements[field]) form.elements[field].focus();
      return;
    }
    window.location.assign(result.url);
  } catch {
    status.textContent = 'De verbinding werd onderbroken. Vernieuw eerst het overzicht om te controleren of de wijziging is opgeslagen.';
  } finally { button.disabled = false; }
});

(() => {
  const params = new URLSearchParams(location.hash.slice(1));
  const ticket = params.get('ticket');
  if (!ticket) return;
  // A short-lived one-time ticket stays out of access logs and referrers; remove it immediately.
  history.replaceState(null, '', location.pathname);
  const status = document.getElementById('login-status');
  if (!status) return;
  status.textContent = 'Je wordt veilig ingelogd…';
  const data = new FormData(); data.set('action', 'login'); data.set('ticket', ticket);
  fetch('/beheer/', {method: 'POST', body: data, credentials: 'same-origin', signal: AbortSignal.timeout(15000)})
    .then(async response => {
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Inloggen is niet gelukt.');
      location.replace('/beheer/');
    })
    .catch(error => {
      status.textContent = error.name === 'TimeoutError' || error instanceof TypeError
        ? 'De verbinding is onderbroken. Open Sponsorbeheer opnieuw op je pc.' : error.message;
      status.classList.add('login-error');
    });
})();

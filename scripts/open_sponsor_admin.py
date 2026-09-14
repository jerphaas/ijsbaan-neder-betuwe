"""Open the private overview using the existing locally encrypted authorization."""
import webbrowser
from sponsor_admin import action
from hosting import CONFIG


def main():
    result = action('admin-link')
    # Never print a ticket, maintenance key, SMTP login or database password.
    ticket = result['ticket']
    if len(ticket) != 64 or any(c not in '0123456789abcdef' for c in ticket):
        raise RuntimeError('De inloglink is niet geldig.')
    if not webbrowser.open(CONFIG['publicUrl'] + 'beheer/#ticket=' + ticket):
        raise RuntimeError('De standaardbrowser kon niet worden geopend.')
    print('Sponsorbeheer is geopend in je browser. Bewaar https://ijsbaannederbetuwe.nl/beheer/ als bladwijzer.')


if __name__ == '__main__':
    try:
        main()
    except Exception:
        print('Sponsorbeheer kon niet openen. Controleer de internetverbinding en de lokaal opgeslagen toegang in deze projectmap.')
        raise SystemExit(1)

// Eén centrale plek voor de actieve editie en toekomstige locaties.
window.IJSBAAN = {
  activeEdition: 'opheusden-2026',
  editions: [
    {
      id: 'opheusden-2026',
      place: 'Opheusden',
      season: '2026 / 2027',
      start: '2026-12-11',
      end: null,
      location: 'Grasveld bij het gemeentehuis',
      status: 'current',
      sponsorForm: 'downloads/sponsorformulier-2026.pdf'
    },
    { id: 'kesteren-2027', place: 'Kesteren', season: '2027 / 2028', start: null, end: null, location: null, status: 'next' }
  ],
  contact: { name: 'Ton Keuken', email: 'tonkeuken@gmail.com', phone: '0653638778' },
  sponsorPackages: {
    250: { coins: 60, benefits: ['60 schaatsmunten om uit te delen aan klanten, medewerkers, familie of kinderen.'] },
    500: { coins: 125, benefits: ['125 schaatsmunten.', 'De baan gratis afhuren van 20.00 tot 22.00 uur, op afspraak en bij beschikbaarheid.'] },
    750: { coins: 170, benefits: ['170 schaatsmunten.', 'Vermelding op het combinatie-sponsordoek aan de binnenkant van de boarding en op de afzethekken, plus sociale media.', 'De baan gratis afhuren van 20.00 tot 22.00 uur, op afspraak en bij beschikbaarheid.'] },
    1000: { coins: 215, benefits: ['215 schaatsmunten.', 'Naamsvermelding in de krant, extra grote vermelding op de binnenkant van de boarding en op sociale media.', 'De baan gratis afhuren van 20.00 tot 22.00 uur, op afspraak en bij beschikbaarheid.'] },
    2500: { coins: 520, benefits: ['520 schaatsmunten.', 'Advertentie in de krant, extra grote vermelding op de binnenkant van de boarding en op sociale media.', 'De baan gratis afhuren van 20.00 tot 22.00 uur, op afspraak en bij beschikbaarheid.'] },
    5000: { coins: null, benefits: ['Hoofdsponsor: vier pakketten in totaal.', 'Een spandoek op een buitenzijde van de boarding, een advertentie in de krant en aandacht op sociale media.', 'Verdere invulling in overleg met de organisatie.', 'De baan gratis afhuren van 20.00 tot 22.00 uur, op afspraak en bij beschikbaarheid.'] }
  }
};

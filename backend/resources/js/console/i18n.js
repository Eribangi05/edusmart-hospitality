// UI strings. Companies can override any key per language in Settings > Labels (settings key "terminology").
// NOTE: French and Kinyarwanda copy should be reviewed by native speakers before launch.
const en = {
    dashboard: 'Dashboard', rooms: 'Rooms', board: 'Room board', reservations: 'Reservations', guests: 'Guests', room_types: 'Room types', rate_plans: 'Rate plans',
    pos: 'Restaurant & Bar', orders: 'Orders', new_order: 'New order', kitchen: 'Kitchen / Bar screen', menu: 'Menu & products', categories: 'Categories', tables: 'Tables', shifts: 'Shifts',
    tours: 'Tours & Activities', bookings: 'Bookings', departures: 'Departures', services: 'Services', resources: 'Guides & vehicles',
    inventory: 'Inventory', stock: 'Stock', items: 'Items', suppliers: 'Suppliers', recipes: 'Recipes', purchase_orders: 'Purchase orders',
    staff: 'Staff', employees: 'Employees', attendance: 'Attendance', leave: 'Leave', payroll: 'Payroll', housekeeping: 'Housekeeping', tasks: 'Tasks',
    finance: 'Finance', reports: 'Reports', expenses: 'Expenses', settings: 'Settings', company: 'Company & properties', modules: 'Modules', users: 'Users', roles: 'Roles & permissions',
    taxes: 'Taxes & charges', payment_methods: 'Payment methods', custom_fields: 'Custom fields', labels: 'Labels & preferences', devices: 'Devices', audit: 'Audit log', billing: 'Subscription',
    save: 'Save', cancel: 'Cancel', delete: 'Delete', edit: 'Edit', add: 'Add', search: 'Search', actions: 'Actions', close: 'Close', back: 'Back', confirm: 'Confirm', refresh: 'Refresh',
    logout: 'Log out', loading: 'Loading…', no_data: 'Nothing here yet.', total: 'Total', status: 'Status', date: 'Date', name: 'Name', phone: 'Phone', price: 'Price', amount: 'Amount',
    yes: 'Yes', no: 'No', pay: 'Pay', paid: 'Paid', balance: 'Balance', export: 'Export CSV', from: 'From', to: 'To', language: 'Language', property: 'Property', all_properties: 'All properties',
    check_in: 'Check in', check_out: 'Check out', nights: 'Nights', guest: 'Guest', room: 'Room', free: 'free', occupied: 'Occupied', vacant: 'Vacant', reserved: 'Reserved',
    read_only: 'Your subscription is suspended: the workspace is read-only. Open Subscription to renew.', grace: 'Your subscription has expired and is in a grace period.',
    timeline: 'Timeline', calendar: 'Tours calendar', vouchers: 'Promo codes', workspaces: 'Workspaces', notifications: 'Notifications', appearance: 'Appearance', pinned: 'Pinned', search_all: 'Search pages, guests, bookings, orders…', no_alerts: 'All clear. Nothing needs attention.', profile: 'Profile', print_invoice: 'Print invoice', setup_guide: 'Setup guide', activity: 'Recent activity', trends: 'Last 14 days',
};
const fr = {
    dashboard: 'Tableau de bord', rooms: 'Chambres', board: 'Plan des chambres', reservations: 'Réservations', guests: 'Clients', room_types: 'Types de chambre', rate_plans: 'Tarifs',
    pos: 'Restaurant & Bar', orders: 'Commandes', new_order: 'Nouvelle commande', kitchen: 'Écran cuisine / bar', menu: 'Menu et produits', categories: 'Catégories', tables: 'Tables', shifts: 'Services de caisse',
    tours: 'Tours & Activités', bookings: 'Réservations', departures: 'Départs', services: 'Services', resources: 'Guides et véhicules',
    inventory: 'Stock', stock: 'Niveaux de stock', items: 'Articles', suppliers: 'Fournisseurs', recipes: 'Recettes', purchase_orders: 'Bons de commande',
    staff: 'Personnel', employees: 'Employés', attendance: 'Présences', leave: 'Congés', payroll: 'Paie', housekeeping: 'Ménage', tasks: 'Tâches',
    finance: 'Finance', reports: 'Rapports', expenses: 'Dépenses', settings: 'Paramètres', company: 'Société et établissements', modules: 'Modules', users: 'Utilisateurs', roles: 'Rôles et droits',
    taxes: 'Taxes et frais', payment_methods: 'Modes de paiement', custom_fields: 'Champs personnalisés', labels: 'Libellés et préférences', devices: 'Appareils', audit: 'Journal d’audit', billing: 'Abonnement',
    save: 'Enregistrer', cancel: 'Annuler', delete: 'Supprimer', edit: 'Modifier', add: 'Ajouter', search: 'Rechercher', actions: 'Actions', close: 'Fermer', back: 'Retour', confirm: 'Confirmer', refresh: 'Actualiser',
    logout: 'Se déconnecter', loading: 'Chargement…', no_data: 'Rien pour le moment.', total: 'Total', status: 'Statut', date: 'Date', name: 'Nom', phone: 'Téléphone', price: 'Prix', amount: 'Montant',
    yes: 'Oui', no: 'Non', pay: 'Payer', paid: 'Payé', balance: 'Solde', export: 'Exporter CSV', from: 'Du', to: 'Au', language: 'Langue', property: 'Établissement', all_properties: 'Tous les établissements',
    check_in: 'Arrivée', check_out: 'Départ', nights: 'Nuits', guest: 'Client', room: 'Chambre', free: 'libres', occupied: 'Occupée', vacant: 'Libre', reserved: 'Réservée',
    read_only: 'Votre abonnement est suspendu : l’espace est en lecture seule. Ouvrez Abonnement pour renouveler.', grace: 'Votre abonnement a expiré et est en période de grâce.',
    timeline: 'Planning', calendar: 'Calendrier des tours', vouchers: 'Codes promo', workspaces: 'Espaces de travail', notifications: 'Notifications', appearance: 'Apparence', pinned: 'Épinglés', search_all: 'Rechercher pages, clients, réservations, commandes…', no_alerts: 'Tout est en ordre.', profile: 'Profil', print_invoice: 'Imprimer la facture', setup_guide: 'Guide de démarrage', activity: 'Activité récente', trends: '14 derniers jours',
};
const rw = {
    dashboard: 'Incamake', rooms: 'Ibyumba', board: 'Imiterere y’ibyumba', reservations: 'Kubika ibyumba', guests: 'Abashyitsi', room_types: 'Ubwoko bw’ibyumba', rate_plans: 'Ibiciro',
    pos: 'Resitora & Akabari', orders: 'Komande', new_order: 'Komande nshya', kitchen: 'Igikoni / Akabari', menu: 'Menu n’ibicuruzwa', categories: 'Ibyiciro', tables: 'Ameza', shifts: 'Amasaha y’akazi',
    tours: 'Ingendo n’ibikorwa', bookings: 'Ibyabikijwe', departures: 'Ingendo ziteganyijwe', services: 'Serivisi', resources: 'Abayobozi n’imodoka',
    inventory: 'Ububiko', stock: 'Ibiri mu bubiko', items: 'Ibintu', suppliers: 'Abatanga ibicuruzwa', recipes: 'Uburyo bwo guteka', purchase_orders: 'Komande z’ibicuruzwa',
    staff: 'Abakozi', employees: 'Abakozi', attendance: 'Iyitabira', leave: 'Ikiruhuko', payroll: 'Imishahara', housekeeping: 'Isuku', tasks: 'Imirimo',
    finance: 'Imari', reports: 'Raporo', expenses: 'Amafaranga yakoreshejwe', settings: 'Igenamiterere', company: 'Ikigo n’amashami', modules: 'Ibice', users: 'Abakoresha', roles: 'Inshingano n’uburenganzira',
    taxes: 'Imisoro n’andi mafaranga', payment_methods: 'Uburyo bwo kwishyura', custom_fields: 'Ibisabwa byihariye', labels: 'Amagambo n’ibyifuzo', devices: 'Ibikoresho', audit: 'Amateka y’ibyakozwe', billing: 'Kwiyandikisha',
    save: 'Bika', cancel: 'Reka', delete: 'Siba', edit: 'Hindura', add: 'Ongeraho', search: 'Shakisha', actions: 'Ibikorwa', close: 'Funga', back: 'Subira inyuma', confirm: 'Emeza', refresh: 'Vugurura',
    logout: 'Sohoka', loading: 'Biraje…', no_data: 'Nta kintu kiriho.', total: 'Igiteranyo', status: 'Imiterere', date: 'Itariki', name: 'Izina', phone: 'Telefone', price: 'Igiciro', amount: 'Amafaranga',
    yes: 'Yego', no: 'Oya', pay: 'Ishyura', paid: 'Byishyuwe', balance: 'Asigaye', export: 'Sohora CSV', from: 'Guhera', to: 'Kugeza', language: 'Ururimi', property: 'Ishami', all_properties: 'Amashami yose',
    check_in: 'Kwinjira', check_out: 'Gusohoka', nights: 'Amajoro', guest: 'Umushyitsi', room: 'Icyumba', free: 'zisigaye', occupied: 'Cyuzuye', vacant: 'Kirimo ubusa', reserved: 'Cyabikijwe',
    read_only: 'Kwiyandikisha kwawe kwarahagaritswe: ushobora kureba gusa. Fungura Kwiyandikisha kugira ngo wongere.', grace: 'Kwiyandikisha kwawe kwarangiye, uri mu gihe cy’inyongera.',
    timeline: 'Igihe cy’ibyumba', calendar: 'Kalendari y’ingendo', vouchers: 'Kode z’igabanywa', workspaces: 'Ahakorerwa', notifications: 'Ibimenyetso', appearance: 'Imiterere', pinned: 'Ibyakomejwe', search_all: 'Shakisha amapaji, abashyitsi, ibyabikijwe, komande…', no_alerts: 'Byose bimeze neza.', profile: 'Umwirondoro', print_invoice: 'Sohora fagitire', setup_guide: 'Uko utangira', activity: 'Ibyakozwe vuba', trends: 'Iminsi 14 ishize',
};
export const dictionaries = { en, fr, rw };

let current = 'en';
let overrides = {};
export const setLocale = (l) => (current = dictionaries[l] ? l : 'en');
export const getLocale = () => current;
export const setOverrides = (o) => (overrides = o && typeof o === 'object' ? o : {});
/** Translate a key; per-company terminology overrides win, then locale, then English, then the key itself. */
export const t = (key) => overrides?.[key]?.[current] ?? dictionaries[current]?.[key] ?? en[key] ?? key;

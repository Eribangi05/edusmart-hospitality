# Workspaces: one layout per job

Instead of one screen for everyone, each person lands in the layout built for their work. After sign-in the platform decides where to send them from their permissions and the company's enabled modules (`App\Support\Workspaces`). Anyone with several workspaces sees the launcher at `/workspaces` and can switch from the top bar. Each entry point checks the same permissions the API enforces.

| Entry | URL | For | Layout |
|---|---|---|---|
| Public website | `/`, `/features`, `/solutions`, `/pricing`, `/contact` | prospects | multi-page marketing site; live prices from the plans table; contact form (stored, read in the platform console) |
| Sign-in / sign-up | `/login`, `/register`, `/forgot-password` | everyone | split-screen with brand panel |
| Launcher | `/workspaces` | staff with 2+ workspaces | card grid |
| Manager console | `/app` | owners, managers, accountants, storekeepers | sidebar + top bar: dashboard with charts, setup guide, activity, all configuration |
| Sales terminal | `/terminal` | waiters, cashiers | full-screen touch POS: table floor plan, menu tiles, live ticket, pay screen with cash quick-amounts / MoMo / card / charge-to-room, promo codes, discounts, split bill, shift open/close, barcode wedge |
| Kitchen & bar display | `/kds` | kitchen, bartenders | dark full-screen board: New → Cooking → Ready, age timers (amber 10 min, red 20), sound on new tickets, fullscreen, station filter |
| Front desk | `/desk` | receptionists | tabs: Today (arrivals / in-house / departures / next 7 days), rooms-by-days Timeline (click empty cell to book), Tours calendar with manifests, Guest lookup with 360° profile and loyalty tier |
| My shift | `/staff` | housekeepers, guides | mobile-first app shell with bottom tabs: Tasks, Trips (with call button), Clock in/out, Me (appearance) |
| Platform admin | `/admin` | platform owner | tenants, plans, metrics, website messages, audit; 2FA required |

## Shared experience features
- **Command palette** (Ctrl/⌘ K) in the console: jump to any page, open a workspace, or search guests, reservations, bookings, orders, menu items and staff (permission-aware `GET /api/v1/search`).
- **Notification centre** (🔔): computed alerts — arrivals/departures, guests leaving with a balance, dirty rooms, pending mobile-money, long-open shifts, unassigned tours, low stock, urgent tasks, trial/subscription status (`GET /api/v1/notifications`).
- **Appearance panel** (🎨): light / dark / auto, six accent colours, comfortable or compact density, text size, language. Saved per browser and applied on every entry point.
- **Pinned pages** (★) in the sidebar.
- **Charts** without a library: money collected, occupancy, best sellers, payment mix, busiest hours (`GET /api/v1/reports/trends`).
- **Setup guide** that tracks real progress (`GET /api/v1/onboarding`).
- **Promo codes**: percent or amount, minimum total, validity dates, usage limits; applied at the terminal, in the console order drawer path via API, and in the Android order screen.
- **Print**: guest invoices, booking invoices, tour manifests, till receipts (clean print windows).
- **Guest 360**: stays, nights, tours, lifetime spend and a Silver/Gold/Platinum tier.
- **Android**: Home shows the same alerts; order screen accepts promo codes.

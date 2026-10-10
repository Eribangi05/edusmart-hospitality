# Configuring the platform for different businesses

Companies run very different operations, so almost nothing is hard-coded. Everything below is editable by the company owner in the web console (Settings) and is picked up by the Android app on the next sync.

| What | Where (web console) | Notes |
|---|---|---|
| **Modules** | Settings → Modules | Rooms, POS, Tourism, Inventory, Shop, Staff, Housekeeping, Finance. Limited by the plan. Turning one off hides it in web and app; data is kept. |
| **Properties / outlets** | Company & properties | A company can have hotels, restaurants, shops, tour desks. Most lists can be filtered per property. |
| **Roles & permissions** | Roles & permissions | Module × view/create/edit/delete plus special actions: `pos.void`, `pos.discount`, `pos.price_override`, `rooms.rate_override`, `rooms.folio`, `tourism.assign`, `inventory.adjust`, `staff.payroll_export`, `finance.export`… Create your own roles. The owner role always has everything. |
| **Custom fields** | Custom fields | Add fields to `guest`, `reservation`, `booking`, `catalog_item`, `room_type`, `room`, `employee`, `supplier`, `inventory_item`, `expense`, `property`: text, textarea, number, date, select, multiselect, boolean; required or optional; labels in en/fr/rw. The server validates them; web and Android render them automatically. |
| **Labels** | Labels & preferences → Rename things | Override any UI label per language (e.g. "Guest" → "Visitor"). |
| **Taxes & charges** | Taxes & charges | VAT, service charge, tourism levy or your own: rate in %, inclusive or exclusive, per kind (tax / service charge / levy). Per catalog item you can pick exactly which rates apply (`tax_rate_ids`), e.g. zero-rate a service. |
| **Payment methods** | Payment methods | Cash, MTN MoMo, card, bank, voucher, charge-to-room, or your own. |
| **Operating preferences** | Labels & preferences | Check-in/out times, ID required at check-in, manager PIN for voids, tips, POS service charge, tour deposit %, receipt footer. Free-form `settings` keys (tenant- or property-level) let new options ship without schema changes. |
| **Menu, products, services** | Menu & products, Services | One catalog for everything sold. `kind` = menu, drink, product, service, tour, activity, transfer, rental, package, spa, event, other. |

## Pricing models for services (tourism, rentals, spa…)

`pricing_model` plus the `options` JSON on a catalog item define how a price is calculated:

| `pricing_model` | Price is |
|---|---|
| `flat` / `per_group` | one price regardless of party size |
| `per_person` | price × adults + child price × children |
| `per_day` / `per_night` / `per_hour` | price × units |

`options` (all optional): `child_percent` (child price as % of adult), `tiers` (`[{"min_pax":4,"price":90000}]` group breaks), `max_pax`, `requires_assignment` (a guide/vehicle must be assigned before the trip starts), `components` (`[{"catalog_item_id":"…","quantity":1}]` — makes it a **package** of other items). A departure can override the price (`price_override`).

## Adding a new simple business object

Simple CRUD resources are one entry in `backend/app/Support/ResourceRegistry.php` (model, module, permission, validation rules, search/filter columns). The generic controller provides list/search/filter/create/update/delete with tenant scoping, audit, version-conflict detection and custom fields. Add the table (migration), a model extending `TenantModel`, a registry entry, an entry in `SyncRegistry` if the app should receive it, and a schema in `resources/js/console/schemas.js` for the web form.

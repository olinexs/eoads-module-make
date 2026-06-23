# eoads/module-make

> Artisan command to scaffold Laravel modules following the EO-ADS standard structure — backend + frontend together.

---

## Requirements

- PHP ^8.2
- Laravel 11 or 12

---

## Installation

```bash
composer require eoads/module-make
```

> If you are starting a new project, install [`eoads/eoads-starter-kit`](https://github.com/olinexs/starter-kit) instead — it includes this package and sets up the full project scaffold.

---

## Usage

**Backend + frontend (default)**
```bash
php artisan module:make PurchaseOrder
```

**Backend only**
```bash
php artisan module:make PurchaseOrder --no-frontend
```

**Overwrite an existing module**
```bash
php artisan module:make PurchaseOrder --force
```

---

## Generated structure

Assumes the following project layout:

```
my-project/
├── backend/    ← run artisan from here
└── frontend/
```

### Backend — `backend/Modules/PurchaseOrder/`

```
Modules/PurchaseOrder/
├── app/
│   ├── Actions/
│   ├── DTOs/
│   ├── Enums/
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   ├── Notifications/
│   ├── Observers/
│   ├── Policies/
│   ├── Repositories/
│   │   ├── PurchaseOrderRepositoryInterface.php
│   │   └── EloquentPurchaseOrderRepository.php
│   ├── Services/
│   └── Support/
├── Providers/
│   └── PurchaseOrderServiceProvider.php
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── module.json
```

### Frontend — `frontend/resources/js/modules/purchaseOrder/`

```
purchaseOrder/
├── services/
│   └── purchaseOrderService.js   ← all axios calls
├── stores/
│   └── purchaseOrderStore.js     ← Pinia store
├── views/
│   └── PurchaseOrderView.vue     ← page component
├── components/                   ← local components
└── routes.js                     ← vue-router definitions
```

---

## Auto-registered

After scaffolding the command automatically:

- Adds `Modules\PurchaseOrder\` → `Modules/PurchaseOrder/app/` to `composer.json` PSR-4
- Enables the module in `modules_statuses.json`
- Imports the frontend module route into `resources/{js,ts}/plugins/router/routes.*`
  (spread into the `DefaultLayout` children) so the page renders inside the app shell
- Adds a nav entry for the module to `resources/{js,ts}/layouts/DefaultLayout.vue`

> TypeScript projects (a `resources/ts` folder or a root `tsconfig.json`) scaffold
> into `resources/ts` with `.ts` files automatically; pass `--ts` to force it.
> Wiring is idempotent and skips gracefully if the router/layout files aren't found.

Then run:

```bash
composer dump-autoload
```

---

## Architecture conventions

| Layer | Rule |
|---|---|
| Controller | Thin — orchestrate only, no business logic |
| FormRequest | All validation here — never `$request->validate()` inline |
| Action | Single-purpose business logic |
| Service | Stateful / multi-step business logic |
| Repository | All data access — bound via interface in ServiceProvider |
| Migration | Always in `backend/database/migrations/` — never inside Modules/ |

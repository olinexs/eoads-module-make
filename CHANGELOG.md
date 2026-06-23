# Changelog

All notable changes to this package will be documented in this file.

## [1.1.0] - 2026-06-23

### Added
- Backend scaffold now generates a `config/` directory and
  `database/{seeders,factories}/` directories.

### Changed
- Reorganized the backend scaffold: `Providers/` (ServiceProvider) now lives at the
  module root, and the generated directory set was streamlined to
  Http (Controllers/Requests/Resources), Actions, Services, Repositories
  (interface + Eloquent implementation), Models, Enums, Events, Observers,
  Notifications, and Policies.
- Clarified that migrations are NOT scaffolded inside the module — per the
  architecture rule they live in `backend/database/migrations/`.

## [1.0.0] - 2026-06-23

### Added
- Initial release
- `module:make {name}` Artisan command to scaffold a full module structure
  (backend + frontend together by default)
- `--no-frontend` flag to scaffold the backend module only
- `--force` flag to overwrite an existing module
- Backend scaffold under `backend/Modules/{Name}/`: Actions, DTOs, Enums, Events,
  Http (Controllers/Requests/Resources), Models, Notifications, Observers,
  Policies, Repositories (interface + Eloquent implementation), Services, Support,
  ServiceProvider, `routes/api.php`, `routes/web.php`, tests, and `module.json`
- Frontend scaffold under `frontend/resources/{js,ts}/modules/{name}/`: services,
  stores (Pinia), views, components, and `routes`
- TypeScript detection (a `resources/ts` folder or root `tsconfig.json`) scaffolds
  into `resources/ts` with `.ts` files automatically; `--ts` forces it
- Auto-registration of the PSR-4 namespace in `composer.json`
- Auto-enables the module in `modules_statuses.json`
- Auto-wiring: imports the module's `routes` into
  `resources/{js,ts}/plugins/router/routes.*` (spread into the `DefaultLayout`
  children) and adds a nav entry to `layouts/DefaultLayout.vue`. Wiring is
  idempotent and skips gracefully when the target files aren't found.

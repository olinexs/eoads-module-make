<?php

namespace Eoads\LaravelModuleMake\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make
                            {name : Module name in PascalCase (e.g. PurchaseOrder)}
                            {--no-frontend : Skip frontend scaffold}
                            {--ts : Generate TypeScript frontend files}
                            {--force : Overwrite existing module}';

    protected $description = 'Scaffold a new module — backend (Modules/) and frontend (resources/js/modules/)';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[A-Z][A-Za-z0-9]+$/', $name)) {
            $this->error('Module name must be PascalCase, no spaces or special characters (e.g. PurchaseOrder).');
            return self::FAILURE;
        }

        $backendPath  = base_path("Modules/{$name}");
        $frontendRoot = base_path('../frontend');
        $force        = $this->option('force');
        $useTs        = $this->option('ts') || $this->detectTypeScript($frontendRoot);
        $srcDir       = $useTs ? 'resources/ts' : 'resources/js';
        $frontendSrc  = $frontendRoot . '/' . $srcDir;
        $frontendPath = $frontendSrc . '/modules/' . Str::camel($name);

        if (is_dir($backendPath) && ! $force) {
            $this->error("Module [{$name}] already exists. Use --force to overwrite.");
            return self::FAILURE;
        }

        $this->scaffoldBackend($name, $backendPath);

        if (! $this->option('no-frontend')) {
            $this->newLine();
            $this->scaffoldFrontend($name, $frontendPath, $force, $useTs, $srcDir);
            $this->wireFrontendRoute($name, $frontendSrc, $useTs);
            $this->wireFrontendNav($name, $frontendSrc);
        }

        $this->registerInComposer($name);
        $this->registerInModuleStatuses($name);

        $this->newLine();
        $this->components->info("Module [{$name}] scaffolded successfully.");
        $this->newLine();

        $this->line('  <fg=cyan>Next steps:</>');
        $this->line("  1. Run <comment>composer dump-autoload</comment>");
        $this->line("  2. Add API endpoints to <comment>Modules/{$name}/routes/api.php</comment>");
        $this->line("  3. Build out the view in the scaffolded module — route and nav are already wired.");

        return self::SUCCESS;
    }

    // ─── Backend scaffold ─────────────────────────────────────────────────────

    private function scaffoldBackend(string $name, string $modulePath): void
    {
        $this->line("  <fg=cyan>BACKEND</> Modules/{$name}");

        $dirs = [
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Http/Resources',
            'app/Models',
            'app/Services',
            'app/Repositories',
            'app/Policies',
            'app/Providers',
            'config',
            'database/migrations',
            'database/seeders',
            'database/factories',
            'routes',
        ];

        foreach ($dirs as $dir) {
            $path = "{$modulePath}/{$dir}";
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $this->line("  <fg=green>CREATE</> Modules/{$name}/{$dir}/");
        }

        $files = [
            'module.json'                                              => $this->stubModuleJson($name),
            "app/Providers/{$name}ServiceProvider.php"                => $this->stubServiceProvider($name),
            "app/Repositories/{$name}RepositoryInterface.php"         => $this->stubRepositoryInterface($name),
            "app/Repositories/Eloquent{$name}Repository.php"          => $this->stubEloquentRepository($name),
            'routes/api.php'                                           => $this->stubApiRoutes($name),
            'routes/web.php'                                           => $this->stubWebRoutes(),
            'database/migrations/.gitkeep'                             => '',
            'database/seeders/.gitkeep'                                => '',
            'database/factories/.gitkeep'                              => '',
        ];

        foreach ($files as $relativePath => $content) {
            file_put_contents("{$modulePath}/{$relativePath}", $content);
            $this->line("  <fg=green>CREATE</> Modules/{$name}/{$relativePath}");
        }
    }

    // ─── Frontend scaffold ────────────────────────────────────────────────────

    private function scaffoldFrontend(string $name, string $frontendPath, bool $force, bool $useTs, string $srcDir): void
    {
        $camel = Str::camel($name);
        $ext   = $useTs ? 'ts' : 'js';

        if (is_dir($frontendPath) && ! $force) {
            $this->warn("  Frontend module [{$camel}] already exists — use --force to overwrite.");
            return;
        }

        $this->line("  <fg=cyan>FRONTEND</> frontend/{$srcDir}/modules/{$camel}");

        foreach (['services', 'stores', 'views', 'components'] as $dir) {
            $path = "{$frontendPath}/{$dir}";
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $this->line("  <fg=green>CREATE</> frontend/{$srcDir}/modules/{$camel}/{$dir}/");
        }

        $files = [
            "services/{$camel}Service.{$ext}" => $useTs
                ? $this->stubFrontendServiceTs($name)
                : $this->stubFrontendService($name),

            "stores/{$camel}Store.{$ext}" => $useTs
                ? $this->stubFrontendStoreTs($name)
                : $this->stubFrontendStore($name),

            "views/{$name}View.vue" => $useTs
                ? $this->stubFrontendViewTs($name)
                : $this->stubFrontendView($name),

            "routes.{$ext}" => $useTs
                ? $this->stubFrontendRoutesTs($name)
                : $this->stubFrontendRoutes($name),
        ];

        foreach ($files as $relativePath => $content) {
            file_put_contents("{$frontendPath}/{$relativePath}", $content);
            $this->line("  <fg=green>CREATE</> frontend/{$srcDir}/modules/{$camel}/{$relativePath}");
        }
    }

    private function detectTypeScript(string $frontendRoot): bool
    {
        return is_dir($frontendRoot . '/resources/ts')
            || file_exists($frontendRoot . '/tsconfig.json');
    }

    // ─── Auto-wire route + nav ─────────────────────────────────────────────────

    /**
     * Import the module's route file into the SPA router and spread it into the
     * DefaultLayout children array, so the page renders inside the app shell.
     */
    private function wireFrontendRoute(string $name, string $frontendSrc, bool $useTs): void
    {
        $ext   = $useTs ? 'ts' : 'js';
        $camel = Str::camel($name);
        $path  = "{$frontendSrc}/plugins/router/routes.{$ext}";

        if (! file_exists($path)) {
            $this->line("  <fg=yellow>SKIP</>  router wiring (routes.{$ext} not found — import {$camel}Routes manually)");
            return;
        }

        $content = file_get_contents($path);
        $import  = "import {$camel}Routes from '@/modules/{$camel}/routes'";

        if (str_contains($content, $import)) {
            $this->line("  <fg=yellow>SKIP</>  router wiring (already imported)");
            return;
        }

        // Prepend the import at the top of the file.
        $content = $import . "\n" . $content;

        // Spread the module routes into the DefaultLayout children array.
        $injected = preg_replace(
            "/(DefaultLayout\\.vue'\\),\\s*\\n\\s*children:\\s*\\[\\n)/",
            "$1      ...{$camel}Routes,\n",
            $content,
            1,
            $count
        );

        if ($count === 0) {
            $this->line("  <fg=yellow>SKIP</>  router wiring (DefaultLayout children not found — spread ...{$camel}Routes manually)");
            return;
        }

        file_put_contents($path, $injected);
        $this->line("  <fg=green>UPDATE</> frontend router (wired {$camel}Routes into DefaultLayout)");
    }

    /**
     * Add a navigation entry for the module to DefaultLayout.vue's navItems array.
     */
    private function wireFrontendNav(string $name, string $frontendSrc): void
    {
        $kebab = Str::kebab(Str::snake($name));
        $title = Str::headline($name);
        $path  = "{$frontendSrc}/layouts/DefaultLayout.vue";

        if (! file_exists($path)) {
            $this->line("  <fg=yellow>SKIP</>  nav wiring (DefaultLayout.vue not found — add nav item manually)");
            return;
        }

        $content = file_get_contents($path);

        if (str_contains($content, "to: '/{$kebab}'")) {
            $this->line("  <fg=yellow>SKIP</>  nav wiring (already present)");
            return;
        }

        $item     = "  { title: '{$title}', icon: 'ri-circle-line', to: '/{$kebab}' },";
        $injected = preg_replace(
            "/(const navItems = \\[\\n)/",
            "$1{$item}\n",
            $content,
            1,
            $count
        );

        if ($count === 0) {
            $this->line("  <fg=yellow>SKIP</>  nav wiring (navItems array not found — add nav item manually)");
            return;
        }

        file_put_contents($path, $injected);
        $this->line("  <fg=green>UPDATE</> frontend nav (added '{$title}' menu item)");
    }

    // ─── Composer / module statuses ───────────────────────────────────────────

    private function registerInComposer(string $name): void
    {
        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            $this->warn('  composer.json not found — skipping namespace registration.');
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (! is_array($composer)) {
            $this->warn('  composer.json could not be parsed — skipping.');
            return;
        }

        $key  = "Modules\\{$name}\\";
        $val  = "Modules/{$name}/app/";
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        if (isset($psr4[$key])) {
            $this->line("  <fg=yellow>SKIP</>  composer.json (namespace already registered)");
            return;
        }

        // Insert after last Modules\ entry
        $new            = [];
        $lastModulesKey = null;

        foreach (array_keys($psr4) as $k) {
            if (str_starts_with($k, 'Modules\\')) {
                $lastModulesKey = $k;
            }
        }

        foreach ($psr4 as $k => $v) {
            $new[$k] = $v;
            if ($k === $lastModulesKey) {
                $new[$key] = $val;
            }
        }

        if (! isset($new[$key])) {
            $new[$key] = $val;
        }

        $composer['autoload']['psr-4'] = $new;

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $this->line("  <fg=green>UPDATE</> composer.json (added {$key})");
    }

    private function registerInModuleStatuses(string $name): void
    {
        $path     = base_path('modules_statuses.json');
        $statuses = file_exists($path)
            ? json_decode(file_get_contents($path), true) ?? []
            : [];

        if (isset($statuses[$name])) {
            $this->line("  <fg=yellow>SKIP</>  modules_statuses.json (already registered)");
            return;
        }

        $statuses[$name] = true;

        file_put_contents($path, json_encode($statuses, JSON_PRETTY_PRINT) . "\n");
        $this->line("  <fg=green>UPDATE</> modules_statuses.json (enabled {$name})");
    }

    // =========================================================================
    // BACKEND STUBS
    // =========================================================================

    private function stubModuleJson(string $name): string
    {
        return json_encode([
            'name'        => $name,
            'alias'       => Str::lower($name),
            'description' => Str::headline($name) . ' module',
            'version'     => '1.0.0',
            'providers'   => [
                "Modules\\{$name}\\app\\Providers\\{$name}ServiceProvider",
            ],
            'aliases' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function stubServiceProvider(string $name): string
    {
        return <<<PHP
        <?php

        namespace Modules\\{$name}\\app\\Providers;

        use Illuminate\\Support\\ServiceProvider;
        use Modules\\{$name}\\app\\Repositories\\{$name}RepositoryInterface;
        use Modules\\{$name}\\app\\Repositories\\Eloquent{$name}Repository;

        class {$name}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                \$this->app->bind(
                    {$name}RepositoryInterface::class,
                    Eloquent{$name}Repository::class,
                );
            }

            public function boot(): void
            {
                \$this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
            }
        }
        PHP;
    }

    private function stubRepositoryInterface(string $name): string
    {
        return <<<PHP
        <?php

        namespace Modules\\{$name}\\app\\Repositories;

        interface {$name}RepositoryInterface
        {
            public function all(array \$filters = []): mixed;
            public function find(int|string \$id): mixed;
            public function create(array \$data): mixed;
            public function update(int|string \$id, array \$data): mixed;
            public function delete(int|string \$id): bool;
        }
        PHP;
    }

    private function stubEloquentRepository(string $name): string
    {
        return <<<PHP
        <?php

        namespace Modules\\{$name}\\app\\Repositories;

        class Eloquent{$name}Repository implements {$name}RepositoryInterface
        {
            public function all(array \$filters = []): mixed
            {
                return collect();
            }

            public function find(int|string \$id): mixed
            {
                return null;
            }

            public function create(array \$data): mixed
            {
                return null;
            }

            public function update(int|string \$id, array \$data): mixed
            {
                return null;
            }

            public function delete(int|string \$id): bool
            {
                return false;
            }
        }
        PHP;
    }

    private function stubApiRoutes(string $name): string
    {
        $prefix = Str::kebab(Str::snake($name));

        return <<<PHP
        <?php

        use Illuminate\\Support\\Facades\\Route;

        Route::middleware('auth:sanctum')->prefix('{$prefix}')->name('api.{$prefix}.')->group(function () {
            //
        });
        PHP;
    }

    private function stubWebRoutes(): string
    {
        return <<<PHP
        <?php

        use Illuminate\\Support\\Facades\\Route;

        // SPA — all routing handled by the frontend.
        PHP;
    }

    // =========================================================================
    // FRONTEND STUBS — JavaScript
    // =========================================================================

    private function stubFrontendService(string $name): string
    {
        $camel = Str::camel($name);
        $kebab = Str::kebab(Str::snake($name));

        return <<<JS
        import { api } from '@/plugins/axios'

        const BASE = '/api/{$kebab}'

        export const {$camel}Service = {
          index: (params = {}) => api.get(BASE, { params }),
          show:  (id)          => api.get(`\${BASE}/\${id}`),
          store: (payload)     => api.post(BASE, payload),
          update:(id, payload) => api.put(`\${BASE}/\${id}`, payload),
          destroy:(id)         => api.delete(`\${BASE}/\${id}`),
        }
        JS;
    }

    private function stubFrontendStore(string $name): string
    {
        $camel = Str::camel($name);

        return <<<JS
        import { defineStore } from 'pinia'
        import { ref } from 'vue'
        import { {$camel}Service } from '../services/{$camel}Service'
        import { useToastStore } from '@/stores/toastStore'

        export const use{$name}Store = defineStore('{$camel}', () => {
          const items   = ref([])
          const item    = ref(null)
          const loading = ref(false)
          const meta    = ref({})

          async function fetchAll(params = {}) {
            loading.value = true
            try {
              const res   = await {$camel}Service.index(params)
              items.value = res.data.data
              meta.value  = res.data.meta ?? {}
            } finally {
              loading.value = false
            }
          }

          async function fetchOne(id) {
            loading.value = true
            try {
              const res  = await {$camel}Service.show(id)
              item.value = res.data.data
            } finally {
              loading.value = false
            }
          }

          async function create(payload) {
            const toast = useToastStore()
            const res   = await {$camel}Service.store(payload)
            items.value.unshift(res.data.data)
            toast.success(res.data.message ?? 'Created successfully')
            return res.data.data
          }

          async function update(id, payload) {
            const toast = useToastStore()
            const res   = await {$camel}Service.update(id, payload)
            const idx   = items.value.findIndex(i => i.id === id)
            if (idx !== -1) items.value[idx] = res.data.data
            toast.success(res.data.message ?? 'Updated successfully')
            return res.data.data
          }

          async function destroy(id) {
            const toast = useToastStore()
            await {$camel}Service.destroy(id)
            items.value = items.value.filter(i => i.id !== id)
            toast.success('Deleted successfully')
          }

          return { items, item, loading, meta, fetchAll, fetchOne, create, update, destroy }
        })
        JS;
    }

    private function stubFrontendView(string $name): string
    {
        $camel = Str::camel($name);
        $title = Str::headline($name);

        return <<<VUE
        <script setup>
        import { onMounted } from 'vue'
        import { use{$name}Store } from '../stores/{$camel}Store'

        const store = use{$name}Store()

        onMounted(() => store.fetchAll())
        </script>

        <template>
          <v-container fluid>
            <div class="d-flex align-center justify-space-between mb-4">
              <h1 class="text-h5 font-weight-bold">{$title}</h1>
            </div>

            <v-card rounded="lg" elevation="1">
              <v-data-table
                :items="store.items"
                :loading="store.loading"
                density="compact"
              />
            </v-card>
          </v-container>
        </template>
        VUE;
    }

    private function stubFrontendRoutes(string $name): string
    {
        $camel = Str::camel($name);
        $kebab = Str::kebab(Str::snake($name));
        $title = Str::headline($name);

        return <<<JS
        export default [
          {
            path: '/{$kebab}',
            name: '{$camel}',
            component: () => import('./views/{$name}View.vue'),
            meta: { title: '{$title}', requiresAuth: true },
          },
        ]
        JS;
    }

    // =========================================================================
    // FRONTEND STUBS — TypeScript
    // =========================================================================

    private function stubFrontendServiceTs(string $name): string
    {
        $camel = Str::camel($name);
        $kebab = Str::kebab(Str::snake($name));

        return <<<TS
        import { api } from '@/plugins/axios'
        import type { AxiosResponse } from 'axios'

        const BASE = '/api/{$kebab}'

        export const {$camel}Service = {
          index: (params = {}): Promise<AxiosResponse>  => api.get(BASE, { params }),
          show:  (id: string | number): Promise<AxiosResponse> => api.get(`\${BASE}/\${id}`),
          store: (payload: Record<string, unknown>): Promise<AxiosResponse> => api.post(BASE, payload),
          update:(id: string | number, payload: Record<string, unknown>): Promise<AxiosResponse> => api.put(`\${BASE}/\${id}`, payload),
          destroy:(id: string | number): Promise<AxiosResponse> => api.delete(`\${BASE}/\${id}`),
        }
        TS;
    }

    private function stubFrontendStoreTs(string $name): string
    {
        $camel = Str::camel($name);

        return <<<TS
        import { defineStore } from 'pinia'
        import { ref } from 'vue'
        import { {$camel}Service } from '../services/{$camel}Service'
        import { useToastStore } from '@/stores/toastStore'

        export const use{$name}Store = defineStore('{$camel}', () => {
          const items   = ref<Record<string, unknown>[]>([])
          const item    = ref<Record<string, unknown> | null>(null)
          const loading = ref(false)
          const meta    = ref<Record<string, unknown>>({})

          async function fetchAll(params = {}) {
            loading.value = true
            try {
              const res   = await {$camel}Service.index(params)
              items.value = res.data.data
              meta.value  = res.data.meta ?? {}
            } finally {
              loading.value = false
            }
          }

          async function fetchOne(id: string | number) {
            loading.value = true
            try {
              const res  = await {$camel}Service.show(id)
              item.value = res.data.data
            } finally {
              loading.value = false
            }
          }

          async function create(payload: Record<string, unknown>) {
            const toast = useToastStore()
            const res   = await {$camel}Service.store(payload)
            items.value.unshift(res.data.data)
            toast.success(res.data.message ?? 'Created successfully')
            return res.data.data
          }

          async function update(id: string | number, payload: Record<string, unknown>) {
            const toast = useToastStore()
            const res   = await {$camel}Service.update(id, payload)
            const idx   = items.value.findIndex((i: any) => i.id === id)
            if (idx !== -1) items.value[idx] = res.data.data
            toast.success(res.data.message ?? 'Updated successfully')
            return res.data.data
          }

          async function destroy(id: string | number) {
            const toast = useToastStore()
            await {$camel}Service.destroy(id)
            items.value = items.value.filter((i: any) => i.id !== id)
            toast.success('Deleted successfully')
          }

          return { items, item, loading, meta, fetchAll, fetchOne, create, update, destroy }
        })
        TS;
    }

    private function stubFrontendViewTs(string $name): string
    {
        $camel = Str::camel($name);
        $title = Str::headline($name);

        return <<<VUE
        <script setup lang="ts">
        import { onMounted } from 'vue'
        import { use{$name}Store } from '../stores/{$camel}Store'

        const store = use{$name}Store()

        onMounted(() => store.fetchAll())
        </script>

        <template>
          <v-container fluid>
            <div class="d-flex align-center justify-space-between mb-4">
              <h1 class="text-h5 font-weight-bold">{$title}</h1>
            </div>

            <v-card rounded="lg" elevation="1">
              <v-data-table
                :items="store.items"
                :loading="store.loading"
                density="compact"
              />
            </v-card>
          </v-container>
        </template>
        VUE;
    }

    private function stubFrontendRoutesTs(string $name): string
    {
        $camel = Str::camel($name);
        $kebab = Str::kebab(Str::snake($name));
        $title = Str::headline($name);

        return <<<TS
        import type { RouteRecordRaw } from 'vue-router'

        const routes: RouteRecordRaw[] = [
          {
            path: '/{$kebab}',
            name: '{$camel}',
            component: () => import('./views/{$name}View.vue'),
            meta: { title: '{$title}', requiresAuth: true },
          },
        ]

        export default routes
        TS;
    }
}

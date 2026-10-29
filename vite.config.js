import laravel from 'laravel-vite-plugin'
import { existsSync, readFileSync, readdirSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { VueRouterAutoImports, getPascalCaseRouteName } from 'unplugin-vue-router'
import VueRouter from 'unplugin-vue-router/vite'
import { defineConfig } from 'vite'
import VueDevTools from 'vite-plugin-vue-devtools'
import MetaLayouts from 'vite-plugin-vue-meta-layouts'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'

const ROOT = fileURLToPath(new URL('.', import.meta.url))

const kebab = s => s.replace(/([a-z\d])([A-Z])/g, '$1-$2').toLowerCase()
const abs = p => path.join(ROOT, p)

// Danh sách module đang bật trong modules_statuses.json
function enabledModules() {
  let statuses = {}
  try {
    statuses = JSON.parse(readFileSync(abs('modules_statuses.json'), 'utf-8'))
  } catch (e) {
    return []
  }
  const dir = abs('Modules')
  if (!existsSync(dir)) return []
  
  return readdirSync(dir, { withFileTypes: true })
    .filter(d => d.isDirectory() && statuses[d.name] === true)
    .map(d => d.name)
}

const MODULES = enabledModules()

// Mỗi subdir (components, views, stores...) là 1 thư mục riêng trong module,
// KHÔNG nối lồng nhau. pick() trả về mảng đường dẫn tồn tại cho từng subdir.
const pick = (...subdirs) =>
  MODULES.flatMap(m => subdirs.map(s => path.posix.join('Modules', m, 'resources', 'js', s)))
    .filter(p => existsSync(abs(p)))

// pages/ → route tự động, prefix /admin/<module-kebab>/
const moduleRoutesFolders = pick('pages')
  .map(src => ({ src, path: `admin/${kebab(src.split('/')[1])}/` }))

// components/ + views/ → auto-import component theo tên PascalCase
const moduleComponentDirs = pick('components', 'views')

// composables/ utils/ stores/ → auto-import composable, store, helper
const moduleAutoImportDirs = pick('composables', 'utils', 'stores').map(d => `./${d}`)

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [// Docs: https://github.com/posva/unplugin-vue-router
  // ℹ️ This plugin should be placed before vue plugin
    VueRouter({
      getRouteName: routeNode => {
      // Convert pascal case to kebab case
        return getPascalCaseRouteName(routeNode)
          .replace(/([a-z\d])([A-Z])/g, '$1-$2')
          .toLowerCase()
      },

      routesFolder: [
        { src: 'resources/js/pages' },
        ...moduleRoutesFolders,
      ],
    }),
    vue({
      template: {
        compilerOptions: {
          isCustomElement: tag => tag === 'swiper-container' || tag === 'swiper-slide',
        },

        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    laravel({
      input: ['resources/js/main.js'],
      refresh: true,
    }),
    vueJsx(), // Docs: https://github.com/vuetifyjs/vuetify-loader/tree/master/packages/vite-plugin
    vuetify({
      styles: {
        configFile: 'resources/styles/variables/_vuetify.scss',
      },
    }), // Docs: https://github.com/dishait/vite-plugin-vue-meta-layouts?tab=readme-ov-file
    MetaLayouts({
      target: './resources/js/layouts',
      defaultLayout: 'default',
    }), // Docs: https://github.com/antfu/unplugin-vue-components#unplugin-vue-components
    Components({
      dirs: ['resources/js/@core/components', 'resources/js/views/demos', 'resources/js/components', ...moduleComponentDirs],
      dts: true,
      resolvers: [
        componentName => {
        // Auto import `VueApexCharts`
          if (componentName === 'VueApexCharts')
            return { name: 'default', from: 'vue3-apexcharts', as: 'VueApexCharts' }
        },
      ],
    }), // Docs: https://github.com/antfu/unplugin-auto-import#unplugin-auto-import
    AutoImport({
      imports: ['vue', VueRouterAutoImports, '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      dirs: [
        './resources/js/@core/utils',
        './resources/js/@core/composable/',
        './resources/js/composables/',
        './resources/js/utils/',
        './resources/js/plugins/*/composables/*',
        ...moduleAutoImportDirs,
      ],
      vueTemplate: true,

      // ℹ️ Disabled to avoid confusion & accidental usage
      ignore: ['useCookies', 'useStorage'],
      eslintrc: {
        enabled: true,
        filepath: './.eslintrc-auto-import.json',
      },
    }), // Docs: https://github.com/intlify/bundle-tools/tree/main/packages/unplugin-vue-i18n#intlifyunplugin-vue-i18n
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [
        fileURLToPath(new URL('./resources/js/plugins/i18n/locales/**', import.meta.url)),
      ],
    }),
    svgLoader(),
  ],
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@core-scss': fileURLToPath(new URL('./resources/styles/@core', import.meta.url)),
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      '@themeConfig': fileURLToPath(new URL('./themeConfig.js', import.meta.url)),
      '@core': fileURLToPath(new URL('./resources/js/@core', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./resources/js/@layouts', import.meta.url)),
      '@images': fileURLToPath(new URL('./resources/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./resources/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(new URL('./resources/styles/variables/_template.scss', import.meta.url)),
      '@db': fileURLToPath(new URL('./resources/js/plugins/fake-api/handlers/', import.meta.url)),
      '@api-utils': fileURLToPath(new URL('./resources/js/plugins/fake-api/utils/', import.meta.url)),
      '@modules': fileURLToPath(new URL('./Modules', import.meta.url)),
    },
  },
  build: {
    chunkSizeWarningLimit: 5000,
  },
  optimizeDeps: {
    exclude: ['vuetify'],
    entries: [
      './resources/js/**/*.vue',
      './Modules/*/resources/js/**/*.vue',
    ],
  },
})

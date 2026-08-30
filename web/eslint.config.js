import js from '@eslint/js'
import tseslint from 'typescript-eslint'
import pluginVue from 'eslint-plugin-vue'

export default tseslint.config(
    {
        ignores: ['dist/**', 'node_modules/**', '*.config.js', '*.config.ts', 'scripts/**']
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser
            }
        }
    },
    {
        languageOptions: {
            globals: {
                // Browser globals
                window: 'readonly',
                document: 'readonly',
                navigator: 'readonly',
                console: 'readonly',
                fetch: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
                localStorage: 'readonly',
                sessionStorage: 'readonly',
                alert: 'readonly',
                confirm: 'readonly',
                URL: 'readonly',
                URLSearchParams: 'readonly',
                FormData: 'readonly',
                Blob: 'readonly',
                File: 'readonly',
                FileReader: 'readonly',
                Image: 'readonly',
                IntersectionObserver: 'readonly',
                ResizeObserver: 'readonly',
                MutationObserver: 'readonly',
                // DOM types
                HTMLElement: 'readonly',
                HTMLInputElement: 'readonly',
                HTMLTextAreaElement: 'readonly',
                HTMLImageElement: 'readonly',
                HTMLVideoElement: 'readonly',
                HTMLCanvasElement: 'readonly',
                Element: 'readonly',
                Node: 'readonly',
                Event: 'readonly',
                MouseEvent: 'readonly',
                KeyboardEvent: 'readonly',
                TouchEvent: 'readonly',
                DragEvent: 'readonly',
                ClipboardEvent: 'readonly',
                InputEvent: 'readonly',
            }
        },
        rules: {
            // Vue rules - be practical
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'off',
            'vue/require-default-prop': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/attributes-order': 'off',
            'vue/html-closing-bracket-spacing': 'off',
            'vue/first-attribute-linebreak': 'off',

            // TypeScript rules
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-unused-vars': ['error', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrorsIgnorePattern: '^_'
            }],
            '@typescript-eslint/no-empty-object-type': 'off',

            // General rules
            'no-console': 'warn',
            'no-debugger': 'error'
        }
    },

    /**
     * L'administration ne remonte pas dans le bundle public.
     *
     * `stores/auth.ts` importait le baril `@/services/adminApi`, qui charge ses dix
     * sous-services de manière eager. Le store étant importé par `main.ts` et par le routeur,
     * tout atterrissait dans le chunk d'entrée : 15 chemins d'API d'administration en clair,
     * téléchargés par chaque visiteur du site public.
     *
     * Une seule arête d'import suffit à tout ramener. Cette règle la refuse dans l'éditeur,
     * en deux secondes ; `scripts/check-admin-isolation.js` fait le même contrôle sur
     * l'artefact construit, au cas où l'import passerait par un chemin que le linter ne suit
     * pas. Les deux sont nécessaires : le linter voit l'intention, l'artefact voit le résultat.
     */
    {
        files: ['src/**/*.{ts,vue}'],
        ignores: ['src/views/admin/**', 'src/components/admin/**', 'src/services/admin/**', 'src/services/adminApi.ts'],
        rules: {
            'no-restricted-imports': ['error', {
                paths: [{
                    name: '@/services/adminApi',
                    message: "Le baril d'administration charge ses dix sous-services d'un coup : l'importer hors de l'admin les fait entrer dans le bundle public. Importer le sous-service précis depuis une vue d'administration."
                }],
                patterns: [{
                    // `baseAdmin` est exempté : c'est la classe de BASE, elle ne porte aucun
                    // chemin d'API. Ce que la règle vise, ce sont les dix sous-services et le
                    // baril qui les charge d'un coup. `uploadService` en dépend légitimement —
                    // il est admin-only mais vit hors du sous-arbre, ce que D2 corrigera.
                    group: ['@/services/admin/*', './admin/*', '../services/admin/*'],
                    importNamePattern: '^(?!.*BaseAdminService).*$',
                    message: "Service d'administration importé hors du sous-arbre admin : il partirait dans le bundle de chaque visiteur public."
                }]
            }]
        }
    }
)

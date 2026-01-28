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
    }
)

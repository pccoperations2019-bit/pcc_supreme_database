import js from '@eslint/js';
import vuePlugin from 'eslint-plugin-vue';
import prettierConfig from 'eslint-config-prettier';
import prettierPlugin from 'eslint-plugin-prettier';

export default [
  // Base recommended configs
  js.configs.recommended,
  ...vuePlugin.configs['flat/recommended'],

  // Global ignores
  {
    ignores: [
      'vendor/**',
      'node_modules/**',
      'public/build/**',
      'storage/**',
      'bootstrap/cache/**',
      'resources/js/components/ui/**'
    ]
  },

  // Main configuration for JS and Vue files
  {
    files: ['resources/js/**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        navigator: 'readonly',
        console: 'readonly',
        // Node globals (for Vite config, etc.)
        process: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly',
        module: 'readonly',
        require: 'readonly'
      }
    },
    plugins: {
      prettier: prettierPlugin
    },
    rules: {
      // Vue-specific rules
      'vue/multi-word-component-names': 'off',
      'vue/require-default-prop': 'off',
      'vue/html-self-closing': [
        'error',
        {
          html: {
            void: 'always',
            normal: 'always',
            component: 'always'
          }
        }
      ],
      // Prettier integration
      'prettier/prettier': 'error'
    }
  },

  // Prettier config (should be last to override other configs)
  prettierConfig
];

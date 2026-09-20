import js from '@eslint/js'
import prettier from 'eslint-config-prettier'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
  { ignores: ['public/build/**', 'vendor/**', 'node_modules/**', 'storage/**'] },
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['resources/js/**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser, route: 'readonly' },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      'no-empty': ['error', { allowEmptyCatch: true }],
      'vue/no-mutating-props': ['error', { shallowOnly: true }],
    },
  },
  {
    files: ['resources/js/Components/Form/FormField.vue'],
    rules: { 'vue/no-v-html': 'off' },
  },
  prettier,
]

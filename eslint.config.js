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
      // route() is Ziggy's global, injected by the @routes Blade directive.
      globals: { ...globals.browser, route: 'readonly' },
    },
    rules: {
      // Inertia resolves pages by path, so Index.vue and Edit.vue are correct names.
      'vue/multi-word-component-names': 'off',
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      // The parent owns an Inertia useForm object and expects children to write
      // into it; reassigning the prop binding itself is still an error.
      'vue/no-mutating-props': ['error', { shallowOnly: true }],
    },
  },
  {
    // The only v-html in the codebase renders a Markdown preview that escapes
    // & < > before adding its own tags, so it cannot inject markup.
    files: ['resources/js/Components/Form/FormField.vue'],
    rules: { 'vue/no-v-html': 'off' },
  },
  prettier,
]

// ESLint v9 flat config.
// Strict-but-pragmatic: lean on TypeScript for type rules (tsc already runs
// in CI), use eslint for stylistic + React rules tsc doesn't cover.

import js from '@eslint/js';
import tsParser from '@typescript-eslint/parser';
import tsPlugin from '@typescript-eslint/eslint-plugin';
import reactPlugin from 'eslint-plugin-react';
import reactHooksPlugin from 'eslint-plugin-react-hooks';
import reactRefreshPlugin from 'eslint-plugin-react-refresh';

export default [
    js.configs.recommended,

    {
        files: ['**/*.{ts,tsx}'],
        languageOptions: {
            parser: tsParser,
            parserOptions: {
                ecmaVersion: 2022,
                sourceType: 'module',
                ecmaFeatures: { jsx: true },
            },
            globals: {
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                localStorage: 'readonly',
                fetch: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                process: 'readonly',
            },
        },
        plugins: {
            '@typescript-eslint': tsPlugin,
            react: reactPlugin,
            'react-hooks': reactHooksPlugin,
            'react-refresh': reactRefreshPlugin,
        },
        settings: { react: { version: '18.3' } },
        rules: {
            ...tsPlugin.configs.recommended.rules,
            ...reactPlugin.configs.recommended.rules,
            ...reactHooksPlugin.configs.recommended.rules,

            // tsc already enforces unused/typing — disable duplicate eslint rules
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': 'off',
            '@typescript-eslint/no-explicit-any': 'warn',

            // TS handles undefined-identifier errors AND understands type-only
            // references (React.FormEvent, HTMLInputElement, KeyboardEvent, etc).
            // ESLint's no-undef can't see these and spits false positives.
            'no-undef': 'off',

            // React 17+ JSX transform — no React import needed
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',           // we use TS interfaces
            'react/no-unescaped-entities': 'off',  // accountant-friendly copy

            // React fast-refresh: components-only exports per file
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],

            // App-specific guardrails
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-debugger': 'error',
            'no-alert': 'error',
        },
    },

    {
        ignores: ['dist/', 'node_modules/', 'coverage/', '*.config.{js,ts}'],
    },
];

export default [
    {
        ignores: ['public/**', 'node_modules/**'],
    },
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                document: 'readonly',
                fetch: 'readonly',
                JSON: 'readonly',
                localStorage: 'readonly',
                Uint32Array: 'readonly',
                window: 'readonly',
            },
        },
        rules: {
            'no-console': 'warn',
            'no-constant-condition': 'error',
            'no-duplicate-case': 'error',
            'no-duplicate-imports': 'error',
            'no-unreachable': 'error',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
];

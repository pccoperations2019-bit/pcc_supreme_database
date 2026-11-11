# Install Vue Inertia Linting

## Description

The project currently lacks proper linting and formatting tools for the Vue 3 + Inertia.js frontend stack. This ticket implements ESLint, Prettier, and Stylelint configuration according to the established coding standards defined in `.github/instructions/coding-standards-vilt.instructions.md`.

This implementation will enforce code quality standards, improve developer experience, and ensure consistent code formatting across the Vue.js components and JavaScript modules. The setup will follow the VILT (Vue, Inertia, Laravel, Tailwind) stack requirements and integrate with the existing development workflow.

## Requirements

### Functional Requirements

1. **Install and configure ESLint** with Vue 3 and JavaScript support
2. **Install and configure Prettier** with Tailwind CSS plugin for consistent formatting
3. **Install and configure Stylelint** for CSS/SCSS linting (optional but recommended)
4. **Add npm scripts** for linting and formatting operations
5. **Resolve any existing lint errors** in the current codebase
6. **Configure IDE integration** support through proper configuration files

### Non-Functional Requirements

1. **Performance**: Linting should not significantly impact development build times
2. **Developer Experience**: Clear error messages and auto-fix capabilities where possible
3. **Maintainability**: Configuration should be easily updatable and extensible
4. **Compatibility**: Must work with existing Vite + Laravel setup

### Acceptance Criteria

- [ ] ESLint is installed and configured with Vue 3 + JavaScript support
- [ ] Prettier is installed with Tailwind CSS plugin integration
- [ ] Stylelint is installed and configured for CSS linting
- [ ] Configuration files (`.eslintrc.json`, `.prettierrc`, `.stylelintrc.json`) are created
- [ ] NPM scripts for `lint`, `lint:fix`, and `format` are added to `package.json`
- [ ] All existing Vue components and JS files pass linting without errors
- [ ] Documentation is updated with linting workflow instructions
- [ ] Configuration follows the coding standards specified in the project guidelines

## Technical Specifications

### Package Installation

Install the following development dependencies:

```bash
npm i -D eslint @eslint/js eslint-plugin-vue \
       prettier eslint-config-prettier eslint-plugin-prettier \
       stylelint stylelint-config-standard \
       prettier-plugin-tailwindcss
```

### Configuration Files

#### `.eslintrc.json` Configuration
```json
{
  "root": true,
  "env": { 
    "browser": true, 
    "es2023": true,
    "node": true
  },
  "extends": [
    "eslint:recommended",
    "plugin:vue/vue3-recommended",
    "plugin:prettier/recommended"
  ],
  "parserOptions": { 
    "ecmaVersion": "latest", 
    "sourceType": "module" 
  },
  "rules": {
    "vue/multi-word-component-names": "off",
    "vue/require-default-prop": "off",
    "vue/html-self-closing": ["error", {
      "html": {
        "void": "always",
        "normal": "always",
        "component": "always"
      }
    }]
  },
  "ignorePatterns": [
    "vendor/**",
    "node_modules/**",
    "public/build/**",
    "storage/**"
  ]
}
```

#### `.prettierrc` Configuration
```json
{
  "semi": false,
  "singleQuote": true,
  "trailingComma": "all",
  "tabWidth": 2,
  "useTabs": false,
  "printWidth": 80,
  "plugins": ["prettier-plugin-tailwindcss"]
}
```

#### `.prettierignore` Configuration
```
vendor/
node_modules/
public/build/
storage/
*.min.js
*.min.css
```

#### `.stylelintrc.json` Configuration (Optional)
```json
{
  "extends": ["stylelint-config-standard"],
  "rules": {
    "at-rule-no-unknown": [
      true,
      {
        "ignoreAtRules": [
          "tailwind",
          "apply",
          "variants",
          "responsive",
          "screen"
        ]
      }
    ]
  }
}
```

### NPM Scripts Update

Add the following scripts to `package.json`:

```json
{
  "scripts": {
    "build": "vite build",
    "dev": "vite",
    "lint": "eslint \"resources/js/**/*.{js,vue}\"",
    "lint:fix": "npm run lint -- --fix",
    "format": "prettier --write \"resources/js/**/*.{js,vue}\" \"resources/css/**/*.css\"",
    "format:check": "prettier --check \"resources/js/**/*.{js,vue}\" \"resources/css/**/*.css\"",
    "style:lint": "stylelint \"resources/css/**/*.css\"",
    "style:fix": "stylelint \"resources/css/**/*.css\" --fix"
  }
}
```

### Files to Lint and Format

Target the following file patterns:
- `resources/js/**/*.{js,vue}` - All JavaScript and Vue files
- `resources/css/**/*.css` - All CSS files
- Configuration files in project root

### Integration with Development Workflow

1. **Manual Linting**: Developers can run `npm run lint` to check for issues
2. **Auto-fixing**: Use `npm run lint:fix` and `npm run format` for automatic fixes
3. **Pre-commit Integration**: Foundation for future git hooks implementation
4. **IDE Integration**: Configuration files enable automatic linting in VS Code and other editors

### Error Resolution Strategy

1. **Analyze existing codebase** for lint violations
2. **Apply auto-fixes** where possible using `--fix` flags
3. **Manual fixes** for complex violations that require code restructuring
4. **Document any disabled rules** with justification in comments

## Risk Assessment

### Technical Risks
- **Existing Code Violations**: Large number of lint errors in existing Vue components may require significant cleanup
- **Build Performance**: Additional linting steps may slow down development builds
- **Configuration Conflicts**: ESLint and Prettier rules may conflict with existing code patterns

### Mitigation Strategies
- **Incremental Implementation**: Fix lint errors file by file rather than attempting bulk fixes
- **Performance Optimization**: Configure lint caching and exclude unnecessary directories
- **Rule Customization**: Adjust rules that conflict with established project patterns

### Dependencies
- Node.js and npm environment properly configured
- Existing Vue 3 and Vite setup must remain functional
- No conflicts with existing Prettier configuration (version 2.8.8 already installed)

## Additional Notes

### References
- Project coding standards: `.github/instructions/coding-standards-vilt.instructions.md`
- Current package.json: Contains Prettier 2.8.8 already installed
- Vue components directory: `resources/js/Pages/`, `resources/js/Layouts/`, `resources/js/components/`

### Future Considerations
- Integration with GitHub Actions for automated linting in CI/CD
- Pre-commit hooks using husky for automatic linting before commits
- ESLint extensions for additional Vue 3 Composition API patterns
- TypeScript support if the project migrates from JavaScript

### Development Workflow
1. Install dependencies using npm
2. Create configuration files
3. Run initial lint check to assess current state
4. Fix lint errors systematically
5. Test linting integration with development build process
6. Update documentation with new workflow instructions
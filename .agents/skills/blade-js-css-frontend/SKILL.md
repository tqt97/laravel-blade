---
name: blade-js-css-frontend
description: Build, refactor, debug, or review Laravel Blade interfaces using HTML, Blade components, JavaScript, and CSS, including responsive UI, forms, tables, modals, accessibility, progressive enhancement, asset loading, and frontend performance. Use for Blade-first pages; do not convert them to React, Vue, Livewire, or a new CSS framework unless requested.
---

# Blade, JavaScript and CSS Frontend

Create accessible, resilient Blade-first interfaces that match the project's existing design system and backend contracts.

## Inspect before changing

Read the relevant layout, Blade components, view composers/shared data, routes, controller/resource payload, validation errors, localization, Vite entrypoints, JavaScript conventions, CSS tokens/utilities, and browser tests. Reuse existing components and styles. Do not introduce a framework, package, icon set, font, or build tool without a demonstrated need and user approval when it expands scope.

## Blade and HTML

- Prefer semantic HTML and native browser behavior before ARIA or JavaScript.
- Use Blade components for repeated UI with a stable contract; avoid fragmenting one-off markup into excessive components.
- Escape output by default. Use raw output only for deliberately sanitized trusted HTML.
- Keep authorization enforced on the server; hiding a control is only a UX measure.
- Support Laravel validation with field association, retained input, an error summary when useful, and localized messages.
- Give empty, loading, success, error, forbidden, expired-session, and destructive-action states deliberate UI.

## JavaScript

- Use progressive enhancement: core navigation and form submission should remain understandable when JavaScript fails unless the product explicitly requires an app-like runtime.
- Prefer small modules and event delegation for dynamic lists. Avoid duplicate listeners after partial navigation or repeated initialization.
- Treat the DOM as an external boundary: validate queried elements, use `data-*` hooks instead of style classes, and keep selectors scoped.
- Prevent double submission and race conditions; use `AbortController` for replaceable requests such as search.
- Preserve CSRF handling, distinguish validation/auth/session/server/network errors, and restore usable UI after failure.
- For dialogs and menus, manage focus, Escape, focus return, scroll behavior, and outside-click semantics correctly.

## CSS

- Extend existing tokens, cascade layers, utilities, naming, and breakpoints.
- Design mobile-first, test content wrapping and zoom, and avoid fixed dimensions that break localization.
- Keep visible focus states, sufficient contrast, touch targets, reduced-motion support, and readable line lengths.
- Prefer layout systems such as Grid/Flexbox over positional hacks; minimize specificity and `!important`.
- Reserve space for images and asynchronous content to reduce layout shift.

## Performance and verification

- Keep Vite entrypoints scoped, defer non-critical JavaScript, avoid shipping unused libraries, and prevent duplicated CSS/JS initialization.
- Use pagination or deliberate virtualization for large datasets; debounce only where it improves interaction and cancel stale work.
- Verify keyboard-only navigation, screen-reader labels/relationships, mobile/desktop layouts, long translations, validation, server errors, session expiry, slow network, and JavaScript-disabled fallback when relevant.
- Run the repository's formatter, lint, build, backend feature tests, and browser/E2E tests. Report exactly what ran.

When reviewing UI, report reproducible findings by severity and include the affected viewport/input method and a concrete correction.

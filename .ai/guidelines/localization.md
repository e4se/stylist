# Localization

- This application supports two languages: English and Russian.
- Store all user-facing text in translation files under `lang/`; update both English and Russian translations when adding or changing copy.
- For React/Inertia UI text, use `useLaravelReactI18n().t(...)` with strings from `lang/en.json` and `lang/ru.json`.
- For PHP and Blade text, use Laravel translation helpers such as `__()` or `@lang`, and use keyed PHP translation files under `lang/en` and `lang/ru` when that better matches the domain.
- Do not hard-code visible copy in controllers, routes, Blade files, React components, emails, breadcrumbs, page titles, labels, placeholders, empty states, validation messages, toasts, or accessibility labels.

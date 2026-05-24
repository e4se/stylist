# Breadcrumbs

- Breadcrumbs are route-bound and are shared with Inertia as the `breadcrumbs` prop by the breadcrumbs middleware.
- For every named web/Inertia route rendered in the app layout, add a matching definition in `routes/breadcrumbs.php` with `Breadcrumbs::for('<route.name>', ...)`.
- The breadcrumb name must match the Laravel route name exactly.
- Build hierarchy with `$trail->parent('<parent.route.name>')` before pushing the current item.
- Push translated titles with `$trail->push(__('Title'), route('<route.name>'))`; add every new breadcrumb title to both `lang/en.json` and `lang/ru.json`.
- For dynamic routes, accept the route parameters or models in the breadcrumb closure and pass them to both `$trail->parent(...)` and `route(...)` as needed.

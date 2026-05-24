<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

Breadcrumbs::for('dashboard', function (BreadcrumbTrail $trail): void {
    $trail->push(__('Dashboard'), route('dashboard'));
});

Breadcrumbs::for('wardrobe.index', function (BreadcrumbTrail $trail): void {
    $trail->push(__('Wardrobe'), route('wardrobe.index'));
});

Breadcrumbs::for('settings', function (BreadcrumbTrail $trail): void {
    $trail->push(__('Settings'), route('settings.index'));
});

Breadcrumbs::for('profile.edit', function (BreadcrumbTrail $trail): void {
    $trail->parent('settings');
    $trail->push(__('Profile settings'), route('profile.edit'));
});

Breadcrumbs::for('security.edit', function (BreadcrumbTrail $trail): void {
    $trail->parent('settings');
    $trail->push(__('Security settings'), route('security.edit'));
});

Breadcrumbs::for('appearance.edit', function (BreadcrumbTrail $trail): void {
    $trail->parent('settings');
    $trail->push(__('Appearance settings'), route('appearance.edit'));
});

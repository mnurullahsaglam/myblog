<?php

declare(strict_types=1);

use App\Http\Requests\Admin\AdminRequest;

/**
 * The guarantee: a new request in this namespace cannot exist without declaring
 * an area, because AdminRequest::area() is abstract. This test is what notices
 * if someone sidesteps the base class.
 */
it('routes every admin request through the base class', function (): void {
    $offenders = [];

    foreach (glob(app_path('Http/Requests/Admin/*.php')) ?: [] as $file) {
        $class = 'App\\Http\\Requests\\Admin\\'.basename($file, '.php');

        if ($class === AdminRequest::class) {
            continue;
        }

        if (! is_subclass_of($class, AdminRequest::class)) {
            $offenders[] = $class;
        }
    }

    expect($offenders)->toBe([], 'These bypass the area check: '.implode(', ', $offenders));
});

it('declares an area for every admin request', function (): void {
    foreach (glob(app_path('Http/Requests/Admin/*.php')) ?: [] as $file) {
        $class = 'App\\Http\\Requests\\Admin\\'.basename($file, '.php');

        if ($class === AdminRequest::class) {
            continue;
        }

        $method = new ReflectionMethod($class, 'area');

        expect($method->isAbstract())->toBeFalse("{$class} never implements area()");
    }
});

it('covers every request this application has', function (): void {
    expect(glob(app_path('Http/Requests/Admin/*.php')) ?: [])->toHaveCount(20);
});

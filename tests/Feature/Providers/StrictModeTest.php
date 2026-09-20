<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');

    Model::shouldBeStrict();
    URL::forceHttps(false);
    DB::prohibitDestructiveCommands(false);
});

it('prevents lazy loading outside production, so an N+1 surfaces as a failure', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('relaxes lazy loading in production rather than serving a 500', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    new AppServiceProvider(app())->boot();

    expect(Model::preventsLazyLoading())->toBeFalse()
        ->and(Model::preventsAccessingMissingAttributes())->toBeFalse();
});

it('restores strict mode for the rest of the suite', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

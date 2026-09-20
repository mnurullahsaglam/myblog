<?php

declare(strict_types=1);

use App\Exports\ResourceExport;
use App\Forms\ResourceForm;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Api\V1\ApiResourceController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminRequest;
use App\Http\Resources\Api\V1\ApiResource;
use App\Tables\ResourceTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

arch()->preset()->php();

arch()->preset()->security();

arch()->preset()->laravel()
    ->ignoring(['App\Http\Controllers', 'App\Console\Commands']);

arch('actions expose a single entry point')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->ignoring(['App\Actions\Fortify', 'App\Actions\Resources\Concerns']);

arch('every class is final')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring([
        ResourceTable::class,
        ResourceForm::class,
        ResourceExport::class,
        Controller::class,
        AdminResourceController::class,
        AdminRequest::class,
        ApiResourceController::class,
        ApiResource::class,
    ]);

arch('the seven base classes stay abstract')
    ->expect([
        ResourceTable::class,
        ResourceForm::class,
        ResourceExport::class,
        Controller::class,
        AdminResourceController::class,
        AdminRequest::class,
        ApiResourceController::class,
        ApiResource::class,
    ])
    ->toBeAbstract();

arch('actions are final')
    ->expect('App\Actions')
    ->toBeFinal()
    ->ignoring(['App\Actions\Fortify', 'App\Actions\Resources\Concerns']);

arch('controllers do not reach for the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(DB::class);

arch('no debug helpers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('controllers do not talk to the queue or the filesystem directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        DB::class,
        Queue::class,
    ]);

arch('controllers are not models in disguise')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('everything declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models live only in the model layer and what legitimately consumes them')
    ->expect('App\Models')
    ->toOnlyBeUsedIn([
        'App\Models',
        'App\Contracts',
        'App\Actions',
        'App\Console',
        'App\Exports',
        'App\Forms',
        'App\Http',
        'App\Notifications',
        'App\Observers',
        'App\Providers',
        'App\Services',
        'App\Support',
        'App\Tables',
        'App\Traits',
        'App\View',
        'Database\Factories',
        'Database\Seeders',
    ]);

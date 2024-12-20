<?php

namespace App\Filament\Pages;

class Login extends \Filament\Pages\Auth\Login
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public function mount(): void
    {
        parent::mount();

        if (app()->isLocal()) {
            $this->form->fill([
                'email' => config('app.admin_email'),
                'password' => 'password'
            ]);
        }
    }
}

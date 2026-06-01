<?php

declare(strict_types=1);

namespace App\Traits;

trait RedirectToList
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

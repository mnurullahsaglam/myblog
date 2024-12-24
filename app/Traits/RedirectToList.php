<?php

namespace App\Traits;

trait RedirectToList
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

declare(strict_types=1);

namespace App\Traits;

trait RedirectToList
{
    protected function getRedirectUrl(): string
    {
        $url = $this->getResource()::getUrl('index');

        return is_string($url) ? $url : '';
    }
}

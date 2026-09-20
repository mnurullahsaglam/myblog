<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ConvertsCurrency;
use App\Enums\Currencies;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ExchangeRateService implements ConvertsCurrency
{
    private readonly string $openExchangeApiKey;

    private readonly string $metalsApiKey;

    private string $baseCurrency = 'TRY';

    public function __construct()
    {
        $openExchangeApiKey = config('services.open_exchange_rates.api_key');
        $this->openExchangeApiKey = is_string($openExchangeApiKey) ? $openExchangeApiKey : '';

        $metalsApiKey = config('services.metals.api_key');
        $this->metalsApiKey = is_string($metalsApiKey) ? $metalsApiKey : '';
    }

    /**
     * @return array<string, float|string>
     */
    public function getConvertedAmount(float $amount, string $fromCurrency, ?string $displayCurrency = null): array
    {
        $displayCurrency ??= $this->baseCurrency;

        if ($fromCurrency === $displayCurrency) {
            return [
                'amount' => $amount,
                'currency' => $fromCurrency,
                'formatted' => $this->formatMoney($amount, $fromCurrency),
            ];
        }

        $convertedAmount = $this->convert($amount, $fromCurrency, $displayCurrency);

        return [
            'original_amount' => $amount,
            'original_currency' => $fromCurrency,
            'amount' => $convertedAmount,
            'currency' => $displayCurrency,
            'formatted' => $this->formatMoney($convertedAmount, $displayCurrency),
            'original_formatted' => $this->formatMoney($amount, $fromCurrency),
        ];
    }

    public function formatMoney(float $amount, string $currency): string
    {
        $currencyEnum = Currencies::tryFrom($currency);
        $symbol = $currencyEnum?->getSymbol() ?? $currency;

        return $symbol.' '.number_format($amount, 2);
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency, $toCurrency);

        return round($amount * $rate, 2);
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rates = $this->getAllRates();

        $fromRate = $rates[$fromCurrency] ?? 1;
        $toRate = $rates[$toCurrency] ?? 1;

        return $toRate / $fromRate;
    }

    /**
     * @return array<string, float>
     */
    public function getAllRates(): array
    {
        return Cache::remember('exchange_rates', 3600, fn (): array => $this->fetchRatesFromApi());
    }

    /**
     * @return array<string, float>
     */
    private function fetchRatesFromApi(): array
    {
        $rates = ['USD' => 1.0];

        try {
            if ($this->openExchangeApiKey) {
                $currencyRates = $this->fetchCurrencyRates();
                $rates = array_merge($rates, $currencyRates);
            }

            if ($this->metalsApiKey) {
                $metalRates = $this->fetchMetalRates();
                $rates = array_merge($rates, $metalRates);
            } else {
                $rates['XAU'] = 0.01555175;
                $rates['XAG'] = 0.99531;
            }

            Log::info('Exchange rates fetched successfully', ['rates_count' => count($rates)]);

        } catch (Exception $exception) {
            Log::error('Failed to fetch exchange rates: '.$exception->getMessage());

            return $this->getFallbackRates();
        }

        return $rates;
    }

    /**
     * @return array<string, float>
     */
    private function fetchCurrencyRates(): array
    {
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'TRY'];

        $response = Http::timeout(10)->get('https://openexchangerates.org/api/latest.json', [
            'app_id' => $this->openExchangeApiKey,
            'symbols' => implode(',', $supportedCurrencies),
        ]);

        if ($response->successful()) {
            $rates = $response->json('rates', []);

            /** @var array<string, float> $rates */
            $rates = is_array($rates) ? $rates : [];

            return $rates;
        }

        throw new Exception('OpenExchangeRates API failed: '.$response->body());
    }

    /**
     * @return array<string, float>
     */
    private function fetchMetalRates(): array
    {
        return [
            'XAU' => 0.01555175,
            'XAG' => 0.99531,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function getFallbackRates(): array
    {
        return [
            'USD' => 1.0,
            'EUR' => 0.85,
            'GBP' => 0.73,
            'TRY' => 32.50,
            'XAU' => 0.01555175,
            'XAG' => 0.99531,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getPopularCurrencies(): array
    {
        $popular = ['TRY', 'USD', 'EUR', 'GBP', 'XAU', 'XAG'];

        $result = [];

        foreach ($popular as $code) {
            $currency = Currencies::tryFrom($code);
            $result[$code] = $currency->getLabel().' ('.$currency->getSymbol().')';
        }

        return $result;
    }

    public function refreshRates(): bool
    {
        try {
            Cache::forget('exchange_rates');
            $this->getAllRates();

            return true;
        } catch (Exception $exception) {
            Log::error('Failed to refresh exchange rates: '.$exception->getMessage());

            return false;
        }
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function setBaseCurrency(string $currency): void
    {
        $this->baseCurrency = $currency;
    }
}

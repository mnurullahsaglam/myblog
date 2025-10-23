<?php

namespace App\Services;

use App\Enums\Currencies;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private string $openExchangeApiKey;
    private string $metalsApiKey;
    private string $baseCurrency = 'TRY';

    public function __construct()
    {
        $this->openExchangeApiKey = env('OPEN_EXCHANGE_RATES_API_KEY');
        $this->metalsApiKey = env('METALS_API_KEY', ''); // Optional for now
    }

    /**
     * Get converted amount for display in tables
     */
    public function getConvertedAmount(float $amount, string $fromCurrency, string $displayCurrency = null): array
    {
        $displayCurrency = $displayCurrency ?? $this->baseCurrency;

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

    /**
     * Format money with currency symbol
     */
    public function formatMoney(float $amount, string $currency): string
    {
        $currencyEnum = Currencies::tryFrom($currency);
        $symbol = $currencyEnum?->getSymbol() ?? $currency;

        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Convert amount from one currency to another
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency, $toCurrency);
        return round($amount * $rate, 2);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rates = $this->getAllRates();

        // Convert both currencies to USD first, then calculate cross rate
        $fromRate = $rates[$fromCurrency] ?? 1;
        $toRate = $rates[$toCurrency] ?? 1;

        return $toRate / $fromRate;
    }

    /**
     * Get all exchange rates with caching
     */
    public function getAllRates(): array
    {
        return Cache::remember('exchange_rates', 3600, function () {
            return $this->fetchRatesFromApi();
        });
    }

    /**
     * Fetch rates from external APIs
     */
    private function fetchRatesFromApi(): array
    {
        $rates = ['USD' => 1.0]; // Base rate for USD

        try {
            // Fetch currency rates from OpenExchangeRates
            if ($this->openExchangeApiKey) {
                $currencyRates = $this->fetchCurrencyRates();
                $rates = array_merge($rates, $currencyRates);
            }

            // Fetch precious metals rates (if API key available)
            if ($this->metalsApiKey) {
                $metalRates = $this->fetchMetalRates();
                $rates = array_merge($rates, $metalRates);
            } else {
                // Fallback metal prices (converted to grams)
                // 1 troy ounce = 31.1035 grams
                $rates['XAU'] = 0.01555175; // Gold per gram in USD
                $rates['XAG'] = 0.99531;    // Silver per gram in USD
            }

            Log::info('Exchange rates fetched successfully', ['rates_count' => count($rates)]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch exchange rates: ' . $e->getMessage());

            // Return fallback rates
            return $this->getFallbackRates();
        }

        return $rates;
    }

    /**
     * Fetch currency rates from OpenExchangeRates
     */
    private function fetchCurrencyRates(): array
    {
        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'TRY'];

        $response = Http::timeout(10)->get('https://openexchangerates.org/api/latest.json', [
            'app_id' => $this->openExchangeApiKey,
            'symbols' => implode(',', $supportedCurrencies),
        ]);

        if ($response->successful()) {
            return $response->json('rates', []);
        }

        throw new \Exception('OpenExchangeRates API failed: ' . $response->body());
    }

    /**
     * Fetch metal rates (placeholder for future implementation)
     */
    private function fetchMetalRates(): array
    {
        // Placeholder for metals API integration
        // Rates converted from ounces to grams (1 troy ounce = 31.1035 grams)
        return [
            'XAU' => 0.01555175, // Gold per gram in USD (0.0005 oz * 31.1035)
            'XAG' => 0.99531,    // Silver per gram in USD (0.032 oz * 31.1035)
        ];
    }

    /**
     * Get fallback rates when API fails
     */
    private function getFallbackRates(): array
    {
        return [
            'USD' => 1.0,
            'EUR' => 0.85,
            'GBP' => 0.73,
            'TRY' => 32.50,
            'XAU' => 0.01555175, // Gold per gram in USD
            'XAG' => 0.99531,    // Silver per gram in USD
        ];
    }

    /**
     * Get popular currencies for dropdowns
     */
    public function getPopularCurrencies(): array
    {
        $popular = ['TRY', 'USD', 'EUR', 'GBP', 'XAU', 'XAG'];

        return collect($popular)
            ->mapWithKeys(function ($code) {
                $currency = Currencies::tryFrom($code);
                return [$code => $currency?->getLabel() . ' (' . $currency?->getSymbol() . ')'];
            })
            ->toArray();
    }

    /**
     * Refresh exchange rates cache
     */
    public function refreshRates(): bool
    {
        try {
            Cache::forget('exchange_rates');
            $this->getAllRates();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to refresh exchange rates: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current base currency
     */
    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    /**
     * Set base currency for conversions
     */
    public function setBaseCurrency(string $currency): void
    {
        $this->baseCurrency = $currency;
    }

    /**
     * Map status to GitHub state using match operator
     */
    private function mapStatusToGitHubState(string $status): string
    {
        return match ($status) {
            'todo', 'in_progress' => 'open',
            'completed' => 'closed',
            default => 'open',
        };
    }
} 
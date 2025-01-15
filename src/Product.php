<?php

declare(strict_types=1);

namespace App;

use DateTime;
use DateTimeZone;

class Product
{
    public function __construct(
        public readonly string $title,
        public readonly float $price,
        public readonly string $imageUrl,
        public readonly int $capacityMB,
        public readonly string $colour,
        public readonly string $availabilityText,
        public readonly bool $isAvailable,
        public readonly string $shippingText,
        public readonly ?string $shippingDate
    ) {
        $this->validateProperties();
    }

    private function validateProperties(): void
    {
        if (empty($this->title)) {
            trigger_error('Title is empty', E_USER_WARNING);
        }

        if ($this->price < 0) {
            trigger_error('Negative price detected', E_USER_WARNING);
        }

        if ($this->capacityMB < 0) {
            trigger_error(' Negative capacity detected', E_USER_WARNING);
        }
    }

    public static function fromRawData(
        string $title,
        string $price,
        string $imageUrl,
        string $capacity,
        string $colour,
        string $availabilityText,
        string $shippingText
    ): self {
        $normalisedTitle = trim($title);
        $normalisedColour = strtolower(trim($colour));
        $normalisedAvailability = trim($availabilityText);

        return new self(
            title: $normalisedTitle,
            price: self::parsePrice($price),
            imageUrl: $imageUrl,
            capacityMB: self::parseCapacityToMB($capacity),
            colour: $normalisedColour,
            availabilityText: $normalisedAvailability,
            isAvailable: str_contains(strtolower($normalisedAvailability), 'in stock'),
            shippingText: trim($shippingText),
            shippingDate: self::parseShippingDate($shippingText)
        );
    }

    private static function parseCapacityToMB(string $capacity): int
    {
        if (empty($capacity)) {
            trigger_error('Empty capacity value', E_USER_WARNING);
            return 0;
        }

        if (! preg_match('/(\d+)\s*(GB|MB)/i', $capacity, $matches)) {
            trigger_error('Invalid capacity format: '.$capacity, E_USER_WARNING);
            return 0;
        }

        $value = (int) $matches[1];
        $unit = strtoupper($matches[2]);

        if ($value < 0) {
            trigger_error(' Negative capacity value detected', E_USER_WARNING);
            return 0;
        }

        return $unit === 'GB' ? $value * 1000 : $value;
    }

    private static function parsePrice(string $price): float
    {
        $cleanPrice = (float) preg_replace('/[^0-9.]/', '', $price);

        if ($cleanPrice < 0) {
            trigger_error('Negative price value', E_USER_WARNING);
            return 0.0;
        }


        return round($cleanPrice, 2);
    }

    private static function parseShippingDate(?string $shippingText): ?string
    {
        if (! $shippingText) {
            return null;
        }

        // Array of patterns to try
        $patterns = [
            // Standard date format (e.g., "25 March 2024")
            '/(\d{1,2})\s+(\w+)\s+(\d{4})/',
            // ISO format (e.g., "2024-03-25")
            '/\d{4}-\d{2}-\d{2}/',
            // Day with suffix format (e.g., "Monday 25th March 2024")
            '/\w+day\s+\d{1,2}(?:st|nd|rd|th)\s+\w+\s+\d{4}/',
            // Short format with suffix (e.g., "25th March")
            '/(\d{1,2})(?:st|nd|rd|th)\s+([A-Za-z]+)/',
            // Month abbreviations
            '/\b\d{1,2}(?:th|st|nd|rd)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4}\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $shippingText, $matches)) {
                try {
                    if (count($matches) === 1) {
                        return date('Y-m-d', strtotime($matches[0]));
                    } else {
                        $dateStr = $matches[0];
                        if (! str_contains($dateStr, date('Y'))) {
                            $dateStr .= ' '.date('Y');
                        }
                        return date('Y-m-d', strtotime($dateStr));
                    }
                } catch (\Exception $e) {
                    trigger_error('Failed to parse date from: '.$matches[0], E_USER_WARNING);
                    continue;
                }
            }
        }

        // Special cases
        if (stripos($shippingText, 'tomorrow') !== false) {
            return date('Y-m-d', strtotime('tomorrow'));
        }

        if (stripos($shippingText, 'next day') !== false) {
            return date('Y-m-d', strtotime('tomorrow'));
        }

        if (! empty($shippingText)) {
            trigger_error('Could not parse shipping date from: '.$shippingText, E_USER_WARNING);
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'price' => $this->price,
            'imageUrl' => $this->imageUrl,
            'capacityMB' => $this->capacityMB,
            'colour' => $this->colour,
            'availabilityText' => $this->availabilityText,
            'isAvailable' => $this->isAvailable,
            'shippingText' => $this->shippingText,
            'shippingDate' => $this->shippingDate,
        ];
    }
}

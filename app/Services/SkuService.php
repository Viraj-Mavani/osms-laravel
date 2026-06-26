<?php

namespace App\Services;

/**
 * Stateless SKU + barcode generator (PHP port of the original sku.ts).
 *  SKU:     {TYPE}-{BRAND}-{6CHAR}   e.g. FRM-OAKLE-K3M9PA
 *  Barcode: 12-digit numeric         e.g. 938204517632  (Code128-compatible)
 */
class SkuService
{
    private const TYPE_PREFIX = [
        'frame' => 'FRM',
        'lens' => 'LNS',
        'contact_lens' => 'CLN',
        'accessory' => 'ACC',
    ];

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // unambiguous chars

    public function generateSku(string $itemType, ?string $brand): string
    {
        $prefix = self::TYPE_PREFIX[$itemType] ?? 'GEN';
        return "{$prefix}-{$this->brandSlug($brand)}-{$this->randomBase36(6)}";
    }

    public function generateBarcode(): string
    {
        // 12 numeric digits, non-zero leading digit.
        $out = (string) random_int(1, 9);
        for ($i = 0; $i < 11; $i++) {
            $out .= (string) random_int(0, 9);
        }
        return $out;
    }

    private function brandSlug(?string $brand): string
    {
        if (! $brand) {
            return 'GEN';
        }
        $slug = preg_replace('/[^A-Z0-9]/', '', strtoupper($brand));
        $slug = substr((string) $slug, 0, 5);
        return $slug !== '' ? $slug : 'GEN';
    }

    private function randomBase36(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }
        return $out;
    }
}

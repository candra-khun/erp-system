<?php

declare(strict_types=1);

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeGeneratorService
{
    private BarcodeGeneratorSVG $generator;

    public function __construct()
    {
        $this->generator = new BarcodeGeneratorSVG;
    }

    /**
     * Generate SVG barcode for a given SKU/code.
     */
    public function generateSvg(string $code): string
    {
        if (empty($code)) {
            return '';
        }

        return $this->generator->getBarcode($code, $this->generator::TYPE_CODE_128, 2, 40);
    }

    /**
     * Generate base64-encoded SVG barcode for inline embedding.
     */
    public function generateBase64(string $code): string
    {
        $svg = $this->generateSvg($code);

        if (empty($svg)) {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

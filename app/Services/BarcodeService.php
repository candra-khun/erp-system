<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /**
     * Generate barcode SVG for a given SKU/code.
     */
    public function generateSvg(string $code): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($code, $generator::TYPE_CODE_128);
    }

    /**
     * Generate barcode and save to storage.
     * Returns the relative path to the saved file.
     */
    public function generateAndSave(string $code, string $directory = 'barcodes'): string
    {
        $svg = $this->generateSvg($code);
        $filename = $code.'.svg';
        $path = $directory.'/'.$filename;

        Storage::disk('public')->put($path, $svg);

        return Storage::disk('public')->url($path);
    }
}

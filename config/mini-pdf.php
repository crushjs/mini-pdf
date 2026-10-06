<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page defaults
    |--------------------------------------------------------------------------
    |
    | Paper: A3, A4, A5, Letter, Legal (or [width, height] in mm).
    | Orientation: portrait or landscape. Margins are in millimetres.
    |
    */

    'paper' => 'A4',

    'orientation' => 'portrait',

    'margins' => [
        'top' => 15,
        'right' => 15,
        'bottom' => 15,
        'left' => 15,
        'header' => 8,
        'footer' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default font
    |--------------------------------------------------------------------------
    */

    'font' => [
        'family' => 'dejavusans',
        'size' => 11,
    ],

    /*
    |--------------------------------------------------------------------------
    | Khmer support
    |--------------------------------------------------------------------------
    |
    | When enabled, Khmer text (U+1780–U+17FF) is detected automatically and
    | rendered with the font below, with full OpenType shaping (subscript
    | consonants, vowel re-ordering) and dictionary-based line breaking.
    |
    | "khmeros" ships with mPDF. To use another font (e.g. Battambang,
    | Kantumruy Pro, Noto Sans Khmer) register it under "fonts" below and
    | put its name here.
    |
    */

    'khmer' => [
        'enabled' => true,
        'font' => 'khmeros',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom fonts
    |--------------------------------------------------------------------------
    |
    | Put .ttf files in "path" and register each family by its CSS name:
    |
    |   'battambang' => [
    |       'R' => 'Battambang-Regular.ttf',
    |       'B' => 'Battambang-Bold.ttf',
    |   ],
    |
    | OpenType layout (needed for Khmer) is enabled for every custom font.
    |
    */

    'fonts' => [
        'path' => resource_path('fonts'),
        'families' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary directory
    |--------------------------------------------------------------------------
    |
    | Writable directory used by mPDF for its font cache.
    |
    */

    'temp_dir' => storage_path('framework/cache/mini-pdf'),

    /*
    |--------------------------------------------------------------------------
    | Raw mPDF options
    |--------------------------------------------------------------------------
    |
    | Anything here is passed straight to the mPDF constructor.
    | https://mpdf.github.io/reference/mpdf-variables/overview.html
    |
    */

    'options' => [],

];

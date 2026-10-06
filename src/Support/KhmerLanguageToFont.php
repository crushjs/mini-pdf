<?php

namespace Crushjs\MiniPdf\Support;

use Mpdf\Language\LanguageToFont;

/**
 * Routes Khmer text to the configured font instead of mPDF's hard-coded "khmeros".
 */
class KhmerLanguageToFont extends LanguageToFont
{
    public function __construct(protected string $font) {}

    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $lang = strtolower(explode('-', (string) $llcc)[0]);

        if (in_array($lang, ['km', 'khm'], true)) {
            return [false, $this->font];
        }

        return parent::getLanguageOptions($llcc, $adobeCJK);
    }
}

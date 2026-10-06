<?php

namespace Crushjs\MiniPdf\Facades;

use Crushjs\MiniPdf\Pdf;
use Crushjs\MiniPdf\PdfManager;
use Crushjs\MiniPdf\Testing\PdfFake;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Pdf view(string $view, array $data = [])
 * @method static Pdf html(string $html)
 * @method static Pdf file(string $path)
 * @method static Pdf make()
 *
 * @see PdfManager
 */
class MiniPdf extends Facade
{
    /**
     * Swap the manager for a fake that records PDFs instead of rendering them.
     */
    public static function fake(): PdfFake
    {
        static::swap($fake = new PdfFake(static::getFacadeApplication()));

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'minipdf';
    }
}

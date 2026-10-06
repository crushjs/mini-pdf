<?php

use Crushjs\MiniPdf\Pdf;
use Crushjs\MiniPdf\PdfManager;

if (! function_exists('pdf')) {
    /**
     * pdf() returns the manager; pdf('invoice', $data) renders a view.
     */
    function pdf(?string $view = null, array $data = []): PdfManager|Pdf
    {
        $manager = app('minipdf');

        return $view === null ? $manager : $manager->view($view, $data);
    }
}

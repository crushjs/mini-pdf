<?php

namespace Crushjs\MiniPdf\Tests;

use Crushjs\MiniPdf\MiniPdfServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [MiniPdfServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['MiniPdf' => \Crushjs\MiniPdf\Facades\MiniPdf::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('view.paths', [__DIR__ . '/Fixtures/views']);
        $app['config']->set('mini-pdf.temp_dir', sys_get_temp_dir() . '/mini-pdf-tests');
    }

    protected function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type /Page\b(?!s)#', $pdf);
    }
}

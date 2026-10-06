<?php

namespace Crushjs\MiniPdf;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class MiniPdfServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mini-pdf.php',
            'mini-pdf'
        );

        $this->app->singleton('minipdf', function ($app) {
            return new PdfManager($app);
        });

        $this->app->alias('minipdf', PdfManager::class);
    }

    public function boot()
    {
        // publish config
        $this->publishes([
            __DIR__ . '/../config/mini-pdf.php' => config_path('mini-pdf.php'),
        ], 'mini-pdf-config');

        // @pageBreak
        Blade::directive('pageBreak', function () {
            return '<?php echo \'<pagebreak />\'; ?>';
        });
    }
}

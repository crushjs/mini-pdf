<?php

namespace Crushjs\MiniPdf;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Traits\Macroable;

class PdfManager
{
    use Macroable;

    public function __construct(protected Application $app)
    {
    }

    /**
     * Start a blank PDF using the configured defaults.
     */
    public function make(): Pdf
    {
        return new Pdf($this->app['view'], $this->config());
    }

    public function view(string $view, array $data = []): Pdf
    {
        return $this->make()->view($view, $data);
    }

    public function html(string $html): Pdf
    {
        return $this->make()->html($html);
    }

    public function file(string $path): Pdf
    {
        return $this->make()->file($path);
    }

    protected function config(): array
    {
        return $this->app['config']->get('mini-pdf', []);
    }
}

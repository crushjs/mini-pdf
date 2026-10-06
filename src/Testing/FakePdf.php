<?php

namespace Crushjs\MiniPdf\Testing;

use Crushjs\MiniPdf\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;

class FakePdf extends Pdf
{
    public function __construct(ViewFactory $views, array $config, protected PdfFake $fake)
    {
        parent::__construct($views, $config);
    }

    protected function generate(): string
    {
        // Still render the HTML so broken views fail the test.
        $this->toHtml();

        return "%PDF-1.4\n% mini-pdf fake\n%%EOF";
    }

    protected function recordOutput(string $action, ?string $target = null, ?string $disk = null): void
    {
        $this->fake->record($this, $action, $target, $disk);
    }
}

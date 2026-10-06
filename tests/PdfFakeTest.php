<?php

namespace Crushjs\MiniPdf\Tests;

use Crushjs\MiniPdf\Facades\MiniPdf;
use Crushjs\MiniPdf\Pdf;
use PHPUnit\Framework\AssertionFailedError;

class PdfFakeTest extends TestCase
{
    public function test_fake_records_outputs(): void
    {
        $fake = MiniPdf::fake();

        $fake->assertNothingRendered();

        MiniPdf::view('invoice', ['number' => 7, 'customer' => 'Sok'])->download('invoice-7.pdf');
        pdf()->html('<p>Report</p>')->save('reports/a.pdf', disk: 'local');
        MiniPdf::html('<p>Inline</p>')->stream();

        $fake->assertDownloaded('invoice-7.pdf', fn (Pdf $pdf) => $pdf->getData()['number'] === 7)
            ->assertSaved('reports/a.pdf', disk: 'local')
            ->assertStreamed('document.pdf')
            ->assertSee('Invoice #7')
            ->assertRenderedCount(3);

        $this->assertFileDoesNotExist(base_path('reports/a.pdf'));
    }

    public function test_fake_assertions_fail(): void
    {
        MiniPdf::fake();

        $this->expectException(AssertionFailedError::class);

        MiniPdf::fake()->assertDownloaded();
    }
}

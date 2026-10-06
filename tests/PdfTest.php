<?php

namespace Crushjs\MiniPdf\Tests;

use Crushjs\MiniPdf\Enums\Orientation;
use Crushjs\MiniPdf\Enums\Paper;
use Crushjs\MiniPdf\Facades\MiniPdf;
use Crushjs\MiniPdf\Pdf;
use Crushjs\MiniPdf\PdfManager;
use Crushjs\MiniPdf\Support\KhmerLanguageToFont;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PdfTest extends TestCase
{
    public function test_it_renders_html_to_pdf_bytes(): void
    {
        $pdf = MiniPdf::html('<h1>Hello</h1>')->render();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, $this->pageCount($pdf));
    }

    public function test_it_renders_a_blade_view_with_data(): void
    {
        $pdf = MiniPdf::view('invoice', ['number' => 42, 'customer' => 'Dara']);

        $this->assertStringContainsString('Invoice #42', $pdf->toHtml());
        $this->assertStringStartsWith('%PDF-', $pdf->render());
    }

    public function test_it_loads_a_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mpdf') . '.html';
        file_put_contents($path, '<p>From file</p>');

        $this->assertSame('<p>From file</p>', MiniPdf::file($path)->toHtml());

        $this->expectException(InvalidArgumentException::class);
        MiniPdf::file('/missing/file.html');
    }

    public function test_helper_returns_manager_or_pdf(): void
    {
        $this->assertInstanceOf(PdfManager::class, pdf());
        $this->assertInstanceOf(Pdf::class, pdf('invoice', ['number' => 1, 'customer' => 'A']));
    }

    public function test_khmer_text_is_rendered_with_the_khmer_font(): void
    {
        $pdf = MiniPdf::view('khmer')->render();

        $this->assertMatchesRegularExpression('#/BaseFont /[A-Z]+\+KhmerOS#', $pdf);
    }

    public function test_khmer_can_be_disabled(): void
    {
        config(['mini-pdf.khmer.enabled' => false]);

        $pdf = MiniPdf::html('<p>abc</p>')->render();

        $this->assertDoesNotMatchRegularExpression('#KhmerOS#', $pdf);
    }

    public function test_khmer_font_can_be_customised(): void
    {
        $router = new KhmerLanguageToFont('battambang');

        $this->assertSame([false, 'battambang'], $router->getLanguageOptions('km', false));
        $this->assertSame([false, 'battambang'], $router->getLanguageOptions('khm-KH', false));
        $this->assertNotSame('battambang', $router->getLanguageOptions('th', false)[1]);
    }

    public function test_paper_and_orientation(): void
    {
        $portrait = MiniPdf::html('x')->paper(Paper::A4)->render();
        $landscape = MiniPdf::html('x')->paper('A4', Orientation::Landscape)->render();
        $custom = MiniPdf::html('x')->paper([100, 50])->render();

        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 595\.2\d+ 841\.8\d+\]#', $portrait);
        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 841\.8\d+ 595\.2\d+\]#', $landscape);
        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 283\.4\d+ 141\.7\d+\]#', $custom);
        $this->assertSame(Orientation::Landscape, MiniPdf::html('x')->landscape()->getOrientation());
    }

    public function test_page_break_directive(): void
    {
        $this->assertSame(2, $this->pageCount(MiniPdf::view('two-pages')->render()));
    }

    public function test_header_footer_and_page_numbers(): void
    {
        $pdf = MiniPdf::html('<p>Body</p>')
            ->headerView('header', ['company' => 'Acme'])
            ->pageNumbers('ទំព័រ {PAGENO} / {nbpg}')
            ->render();

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_metadata(): void
    {
        $pdf = MiniPdf::html('x')->title('Monthly Report')->author('Crushjs')->render();

        $this->assertStringContainsString(mb_convert_encoding('Monthly Report', 'UTF-16BE'), $pdf);
        $this->assertStringContainsString(mb_convert_encoding('Crushjs', 'UTF-16BE'), $pdf);
    }

    public function test_password_protection(): void
    {
        $pdf = MiniPdf::html('secret')->password('1234');

        $this->assertTrue($pdf->isProtected());
        $this->assertStringContainsString('/Encrypt', $pdf->render());
    }

    public function test_watermark_and_conditionable(): void
    {
        $pdf = MiniPdf::html('x')
            ->when(true, fn (Pdf $pdf) => $pdf->watermark('សម្ងាត់'))
            ->unless(true, fn (Pdf $pdf) => $pdf->landscape())
            ->render();

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_download_response_supports_khmer_filenames(): void
    {
        $response = MiniPdf::html('x')->download('វិក្កយបត្រ-001.pdf');

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename=document.pdf;', $disposition);
        $this->assertStringContainsString("filename*=utf-8''", $disposition);
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_it_can_be_returned_from_a_route(): void
    {
        Route::get('/invoice', fn () => MiniPdf::html('x')->name('invoice'));

        $this->get('/invoice')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=invoice.pdf');
    }

    public function test_save_to_path_and_disk(): void
    {
        $path = sys_get_temp_dir() . '/mini-pdf-tests/out/' . uniqid() . '.pdf';
        MiniPdf::html('x')->save($path);
        $this->assertStringStartsWith('%PDF-', file_get_contents($path));

        Storage::fake('s3');
        MiniPdf::html('x')->save('invoices/1.pdf', disk: 's3');
        Storage::disk('s3')->assertExists('invoices/1.pdf');
    }

    public function test_raw_options_and_mpdf_callback(): void
    {
        $called = false;

        MiniPdf::html('x')
            ->option('default_font_size', 14)
            ->withMpdf(function ($mpdf) use (&$called) {
                $called = $mpdf->default_font_size === 14;
            })
            ->render();

        $this->assertTrue($called);
    }

    public function test_macros(): void
    {
        Pdf::macro('receipt', fn () => $this->paper([80, 200])->margins(4));

        $this->assertSame([80, 200], MiniPdf::html('x')->receipt()->getPaper());
    }
}

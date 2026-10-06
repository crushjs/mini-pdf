# Mini PDF

A lightweight, fluent PDF generator for Laravel with first-class **Khmer (ខ្មែរ)** support.

```php
return MiniPdf::view('invoices.show', compact('invoice'))
    ->landscape()
    ->pageNumbers('ទំព័រ {PAGENO} / {nbpg}')
    ->name("invoice-{$invoice->id}");
```

## Features

- Fluent, chainable API with `Paper` / `Orientation` enums and a `pdf()` helper
- Return a PDF straight from a controller (it's `Responsable`)
- **Khmer works out of the box**: automatic script detection, correct shaping (subscripts, vowel re-ordering) and Khmer line breaking
- Use your own fonts (Battambang, Kantumruy Pro, Noto Sans Khmer…)
- Headers and footers with page numbers, watermarks, password protection, metadata
- Save to a local path or any filesystem disk (`s3`, `public`, …)
- `MiniPdf::fake()` with test assertions
- `@pageBreak` Blade directive, `when()` / `unless()`, macros
- Powered by [mPDF](https://mpdf.github.io), pure PHP: no Chrome, Node or binaries

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13
- `ext-mbstring`, `ext-gd`

## Installation

```bash
composer require crushjs/mini-pdf
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=mini-pdf-config
```

---

# Usage

## Create a PDF

```php
use Crushjs\MiniPdf\Facades\MiniPdf;

MiniPdf::view('invoice', ['invoice' => $invoice]);   // Blade view
MiniPdf::html('<h1>Hello</h1>');                      // raw HTML
MiniPdf::file(resource_path('templates/letter.html'));

// or the helper
pdf('invoice', ['invoice' => $invoice]);
pdf()->html('<h1>Hello</h1>');
```

## Output

```php
// From a controller: just return it (shown inline in the browser)
return MiniPdf::view('invoice', $data)->name('invoice-001');

$pdf->download('invoice.pdf');           // force download
$pdf->stream('invoice.pdf');             // show in browser
$pdf->save(storage_path('app/a.pdf'));   // local path
$pdf->save('invoices/a.pdf', disk: 's3'); // filesystem disk
$pdf->render();                          // raw bytes
$pdf->base64();                          // e.g. for email/API payloads
```

## Page setup

```php
use Crushjs\MiniPdf\Enums\Orientation;
use Crushjs\MiniPdf\Enums\Paper;

$pdf->paper(Paper::A4)                   // A3, A4, A5, Letter, Legal
    ->paper('Letter', Orientation::Landscape)
    ->paper([80, 200])                   // custom size in mm (e.g. receipts)
    ->landscape()                        // or ->portrait()
    ->margins(10)                        // CSS shorthand, in mm
    ->margins(top: 20, right: 10)
    ->font('dejavusans', 12);
```

## Headers, footers & page numbers

`{PAGENO}` and `{nbpg}` are replaced with the current page and the total page count. The body margins grow automatically so content never overlaps.

```php
$pdf->header('<div style="text-align:right">ACME Co.</div>')
    ->headerView('pdf.header', ['company' => $company])
    ->footerView('pdf.footer')
    ->pageNumbers()                            // "1 / 3"
    ->pageNumbers('Page {PAGENO} of {nbpg}', align: 'right');
```

Force a new page in Blade:

```blade
<section>Page one</section>
@pageBreak
<section>Page two</section>
```

## Watermark, password & metadata

```php
$pdf->watermark('CONFIDENTIAL', opacity: 0.1)
    ->watermark('សម្ងាត់')                       // Khmer watermarks work too
    ->watermarkImage(public_path('logo.png'))
    ->password('user-pass', ownerPassword: 'owner-pass', permissions: ['print'])
    ->title('Invoice #001')
    ->author('ACME')
    ->subject('Billing')
    ->keywords(['invoice', 'billing']);
```

## Conditionals & macros

```php
$pdf->when($invoice->isDraft(), fn ($pdf) => $pdf->watermark('DRAFT'));

// AppServiceProvider::boot()
\Crushjs\MiniPdf\Pdf::macro('receipt', fn () => $this->paper([80, 200])->margins(4));

MiniPdf::view('receipt', $data)->receipt()->download();
```

---

# Khmer support

Khmer text is detected automatically and rendered with full OpenType shaping (coeng subscripts, pre-base vowels, `្រ` re-ordering) and dictionary-based line breaking, since Khmer has no spaces between words. Mixed Khmer and English content just works:

```blade
<h1>វិក្កយបត្រ / Invoice</h1>
<p>អតិថិជន: {{ $customer->name }}, សរុប {{ $total }} រៀល</p>
```

Khmer filenames are supported too: `->download('វិក្កយបត្រ.pdf')`.

### Using a different Khmer font

mPDF ships with **Khmer OS**. To use another font, put the `.ttf` files in `resources/fonts` and register them in `config/mini-pdf.php`:

```php
'khmer' => [
    'enabled' => true,
    'font' => 'battambang',
],

'fonts' => [
    'path' => resource_path('fonts'),
    'families' => [
        'battambang' => [
            'R' => 'Battambang-Regular.ttf',
            'B' => 'Battambang-Bold.ttf',
        ],
    ],
],
```

Custom fonts can also be used directly in CSS: `font-family: battambang;`.

---

# Testing

```php
use Crushjs\MiniPdf\Facades\MiniPdf;
use Crushjs\MiniPdf\Pdf;

public function test_invoice_can_be_downloaded(): void
{
    $fake = MiniPdf::fake();

    $this->get('/invoices/1/pdf')->assertOk();

    $fake->assertDownloaded('invoice-1.pdf', fn (Pdf $pdf) => $pdf->getView() === 'invoice')
        ->assertSee('Invoice #1');
}
```

Available assertions: `assertDownloaded`, `assertStreamed`, `assertSaved`, `assertRendered`, `assertRenderedCount`, `assertNothingRendered`, `assertSee`.

The fake still renders your Blade views (so broken templates fail the test) but skips PDF generation, so it's fast.

---

# Advanced

Pass any [mPDF option](https://mpdf.github.io/reference/mpdf-variables/overview.html), or work with the mPDF instance directly:

```php
$pdf->option('dpi', 150)
    ->option(['useSubstitutions' => true])
    ->withMpdf(fn (\Mpdf\Mpdf $mpdf) => $mpdf->SetDirectionality('rtl'));

$mpdf = $pdf->toMpdf(); // fully configured instance
```

## License

MIT
# mini-pdf

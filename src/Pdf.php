<?php

namespace Crushjs\MiniPdf;

use Closure;
use Crushjs\MiniPdf\Enums\Orientation;
use Crushjs\MiniPdf\Enums\Paper;
use Crushjs\MiniPdf\Support\KhmerLanguageToFont;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;

class Pdf implements Htmlable, Responsable
{
    use Conditionable, Macroable;

    protected string $html = '';

    protected ?string $view = null;

    protected array $data = [];

    protected string|array $paper;

    protected Orientation $orientation;

    protected array $margins;

    protected ?string $fontFamily;

    protected ?float $fontSize;

    protected ?string $header = null;

    protected ?string $footer = null;

    protected array $meta = [];

    protected ?array $watermark = null;

    protected ?array $protection = null;

    protected array $options = [];

    /** @var array<int, Closure(Mpdf): void> */
    protected array $mpdfCallbacks = [];

    protected string $filename = 'document.pdf';

    public function __construct(protected ViewFactory $views, protected array $config = [])
    {
        $this->paper = $config['paper'] ?? Paper::A4->value;
        $this->orientation = Orientation::from($config['orientation'] ?? 'portrait');
        $this->margins = array_merge(
            ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15, 'header' => 8, 'footer' => 8],
            $config['margins'] ?? []
        );
        $this->fontFamily = $config['font']['family'] ?? null;
        $this->fontSize = $config['font']['size'] ?? null;
    }

    // ---------------------------------------------------------------------
    // Content
    // ---------------------------------------------------------------------

    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->data = $data;

        return $this;
    }

    public function html(string $html): static
    {
        $this->view = null;
        $this->html = $html;

        return $this;
    }

    public function file(string $path): static
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("File [{$path}] does not exist.");
        }

        return $this->html(file_get_contents($path));
    }

    // ---------------------------------------------------------------------
    // Page setup
    // ---------------------------------------------------------------------

    /**
     * @param  Paper|string|array{0: float, 1: float}  $paper  A named size or [width, height] in mm.
     */
    public function paper(Paper|string|array $paper, Orientation|string|null $orientation = null): static
    {
        $this->paper = $paper instanceof Paper ? $paper->value : $paper;

        if ($orientation !== null) {
            $this->orientation($orientation);
        }

        return $this;
    }

    public function orientation(Orientation|string $orientation): static
    {
        $this->orientation = $orientation instanceof Orientation
            ? $orientation
            : Orientation::from(strtolower($orientation));

        return $this;
    }

    public function landscape(): static
    {
        return $this->orientation(Orientation::Landscape);
    }

    public function portrait(): static
    {
        return $this->orientation(Orientation::Portrait);
    }

    /**
     * Margins in mm, CSS shorthand style: margins(10), margins(10, 20), margins(10, 20, 30, 40).
     */
    public function margins(float $top, ?float $right = null, ?float $bottom = null, ?float $left = null): static
    {
        $right ??= $top;
        $bottom ??= $top;
        $left ??= $right;

        $this->margins = array_merge($this->margins, compact('top', 'right', 'bottom', 'left'));

        return $this;
    }

    public function font(string $family, ?float $size = null): static
    {
        $this->fontFamily = $family;
        $this->fontSize = $size ?? $this->fontSize;

        return $this;
    }

    // ---------------------------------------------------------------------
    // Header & footer ({PAGENO} and {nbpg} are replaced with page numbers)
    // ---------------------------------------------------------------------

    public function header(string $html): static
    {
        $this->header = $html;

        return $this;
    }

    public function headerView(string $view, array $data = []): static
    {
        return $this->header($this->views->make($view, $data)->render());
    }

    public function footer(string $html): static
    {
        $this->footer = $html;

        return $this;
    }

    public function footerView(string $view, array $data = []): static
    {
        return $this->footer($this->views->make($view, $data)->render());
    }

    /**
     * Add a simple page-number footer, e.g. "Page {PAGENO} of {nbpg}" or "ទំព័រ {PAGENO}".
     */
    public function pageNumbers(string $format = '{PAGENO} / {nbpg}', string $align = 'center'): static
    {
        return $this->footer(sprintf(
            '<div style="text-align: %s; font-size: 9pt; color: #666;">%s</div>',
            e($align),
            $format
        ));
    }

    // ---------------------------------------------------------------------
    // Extras
    // ---------------------------------------------------------------------

    public function watermark(string $text, float $opacity = 0.1): static
    {
        $this->watermark = ['type' => 'text', 'value' => $text, 'opacity' => $opacity];

        return $this;
    }

    public function watermarkImage(string $path, float $opacity = 0.2): static
    {
        $this->watermark = ['type' => 'image', 'value' => $path, 'opacity' => $opacity];

        return $this;
    }

    /**
     * Encrypt the PDF. Permissions: print, copy, modify, annot-forms, fill-forms, extract, assemble, print-highres.
     */
    public function password(string $password, ?string $ownerPassword = null, array $permissions = ['print']): static
    {
        $this->protection = compact('password', 'ownerPassword', 'permissions');

        return $this;
    }

    public function title(string $title): static
    {
        return $this->meta('title', $title);
    }

    public function author(string $author): static
    {
        return $this->meta('author', $author);
    }

    public function subject(string $subject): static
    {
        return $this->meta('subject', $subject);
    }

    public function keywords(string|array $keywords): static
    {
        return $this->meta('keywords', implode(', ', (array) $keywords));
    }

    public function creator(string $creator): static
    {
        return $this->meta('creator', $creator);
    }

    protected function meta(string $key, string $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /**
     * Default filename used by download(), stream() and when returned from a controller.
     */
    public function name(string $filename): static
    {
        $this->filename = str_ends_with(strtolower($filename), '.pdf') ? $filename : "{$filename}.pdf";

        return $this;
    }

    /**
     * Pass raw options to the mPDF constructor.
     */
    public function option(string|array $key, mixed $value = null): static
    {
        $this->options = array_merge($this->options, is_array($key) ? $key : [$key => $value]);

        return $this;
    }

    /**
     * Escape hatch: tweak the mPDF instance right before the HTML is written.
     *
     * @param  Closure(Mpdf): void  $callback
     */
    public function withMpdf(Closure $callback): static
    {
        $this->mpdfCallbacks[] = $callback;

        return $this;
    }

    // ---------------------------------------------------------------------
    // Output
    // ---------------------------------------------------------------------

    /**
     * Get the raw PDF bytes.
     */
    public function render(): string
    {
        $this->recordOutput('render');

        return $this->generate();
    }

    public function base64(): string
    {
        return base64_encode($this->render());
    }

    /**
     * Save to a local path, or to a filesystem disk: save('invoices/1.pdf', disk: 's3').
     */
    public function save(string $path, ?string $disk = null): static
    {
        $this->recordOutput('save', $path, $disk);

        $contents = $this->generate();

        if ($disk !== null) {
            Storage::disk($disk)->put($path, $contents);

            return $this;
        }

        if (! is_dir($directory = dirname($path))) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);

        return $this;
    }

    public function download(?string $filename = null): Response
    {
        $filename = $filename ?? $this->filename;

        $this->recordOutput('download', $filename);

        return $this->response($filename, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    /**
     * Show the PDF in the browser.
     */
    public function stream(?string $filename = null): Response
    {
        $filename = $filename ?? $this->filename;

        $this->recordOutput('stream', $filename);

        return $this->response($filename, HeaderUtils::DISPOSITION_INLINE);
    }

    public function toResponse($request): Response
    {
        return $this->stream();
    }

    /**
     * Build a fully configured mPDF instance with the content written to it.
     */
    public function toMpdf(): Mpdf
    {
        $mpdf = new Mpdf($this->mpdfConfig());

        foreach ($this->meta as $key => $value) {
            $mpdf->{'Set'.ucfirst($key)}($value);
        }

        if ($this->header !== null) {
            $mpdf->SetHTMLHeader($this->header);
        }

        if ($this->footer !== null) {
            $mpdf->SetHTMLFooter($this->footer);
        }

        if ($this->watermark !== null) {
            ['type' => $type, 'value' => $value, 'opacity' => $opacity] = $this->watermark;

            if ($type === 'text') {
                $mpdf->SetWatermarkText($value, $opacity);
                $mpdf->showWatermarkText = true;

                if ($this->containsKhmer($value)) {
                    $mpdf->watermark_font = $this->khmerFont();
                }
            } else {
                $mpdf->SetWatermarkImage($value, $opacity);
                $mpdf->showWatermarkImage = true;
            }
        }

        if ($this->protection !== null) {
            $mpdf->SetProtection(
                $this->protection['permissions'],
                $this->protection['password'],
                $this->protection['ownerPassword']
            );
        }

        foreach ($this->mpdfCallbacks as $callback) {
            $callback($mpdf);
        }

        $mpdf->WriteHTML($this->toHtml());

        return $mpdf;
    }

    // ---------------------------------------------------------------------
    // Inspection
    // ---------------------------------------------------------------------

    public function toHtml(): string
    {
        return $this->view !== null
            ? $this->views->make($this->view, $this->data)->render()
            : $this->html;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getPaper(): string|array
    {
        return $this->paper;
    }

    public function getOrientation(): Orientation
    {
        return $this->orientation;
    }

    public function isProtected(): bool
    {
        return $this->protection !== null;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    protected function generate(): string
    {
        return $this->toMpdf()->Output('', Destination::STRING_RETURN);
    }

    /**
     * Hook for the testing fake.
     */
    protected function recordOutput(string $action, ?string $target = null, ?string $disk = null): void
    {
        //
    }

    protected function response(string $filename, string $disposition): Response
    {
        // ASCII fallback for old clients; modern browsers use the UTF-8 filename* (e.g. Khmer names).
        $fallback = preg_match('/^[\x20-\x7e]+$/', $filename) && ! preg_match('/[\/\\\\%]/', $filename)
            ? $filename
            : 'document.pdf';

        return new Response($this->generate(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
        ]);
    }

    protected function mpdfConfig(): array
    {
        $fontDirs = (new ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new FontVariables())->getDefaults()['fontdata'];

        $customPath = $this->config['fonts']['path'] ?? null;

        if ($customPath && is_dir($customPath)) {
            $fontDirs[] = $customPath;
        }

        foreach ($this->config['fonts']['families'] ?? [] as $family => $files) {
            $fontData[strtolower($family)] = array_merge(['useOTL' => 0xFF], $files);
        }

        $tempDir = $this->config['temp_dir'] ?? sys_get_temp_dir().'/mini-pdf';

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $config = [
            'mode' => 'utf-8',
            'format' => $this->paper,
            'orientation' => $this->orientation->toMpdf(),
            'margin_top' => $this->margins['top'],
            'margin_right' => $this->margins['right'],
            'margin_bottom' => $this->margins['bottom'],
            'margin_left' => $this->margins['left'],
            'margin_header' => $this->margins['header'],
            'margin_footer' => $this->margins['footer'],
            'tempDir' => $tempDir,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
        ];

        // Keep headers/footers from overlapping the body.
        if ($this->header !== null) {
            $config['setAutoTopMargin'] = 'stretch';
        }

        if ($this->footer !== null) {
            $config['setAutoBottomMargin'] = 'stretch';
        }

        if ($this->fontFamily) {
            $config['default_font'] = strtolower($this->fontFamily);
        }

        if ($this->fontSize) {
            $config['default_font_size'] = $this->fontSize;
        }

        if ($this->config['khmer']['enabled'] ?? true) {
            $config['autoScriptToLang'] = true;
            $config['autoLangToFont'] = true;
            $config['languageToFont'] = new KhmerLanguageToFont($this->khmerFont());
        }

        return array_merge($config, $this->config['options'] ?? [], $this->options);
    }

    protected function khmerFont(): string
    {
        return strtolower($this->config['khmer']['font'] ?? 'khmeros');
    }

    protected function containsKhmer(string $text): bool
    {
        return (bool) preg_match('/[\x{1780}-\x{17FF}\x{19E0}-\x{19FF}]/u', $text);
    }
}

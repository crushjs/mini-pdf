<?php

namespace Crushjs\MiniPdf\Testing;

use Closure;
use Crushjs\MiniPdf\Pdf;
use Crushjs\MiniPdf\PdfManager;
use PHPUnit\Framework\Assert as PHPUnit;

class PdfFake extends PdfManager
{
    /** @var array<int, array{pdf: Pdf, action: string, target: ?string, disk: ?string}> */
    protected array $outputs = [];

    public function make(): Pdf
    {
        return new FakePdf($this->app['view'], $this->config(), $this);
    }

    /**
     * @internal
     */
    public function record(Pdf $pdf, string $action, ?string $target, ?string $disk): void
    {
        $this->outputs[] = compact('pdf', 'action', 'target', 'disk');
    }

    /**
     * @param  (Closure(Pdf): bool)|null  $callback
     */
    public function assertDownloaded(?string $filename = null, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching('download', $filename, $callback)->isNotEmpty(),
            $filename ? "PDF [{$filename}] was not downloaded." : 'No PDF was downloaded.'
        );

        return $this;
    }

    /**
     * @param  (Closure(Pdf): bool)|null  $callback
     */
    public function assertStreamed(?string $filename = null, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching('stream', $filename, $callback)->isNotEmpty(),
            $filename ? "PDF [{$filename}] was not streamed." : 'No PDF was streamed.'
        );

        return $this;
    }

    /**
     * @param  (Closure(Pdf): bool)|null  $callback
     */
    public function assertSaved(string $path, ?Closure $callback = null, ?string $disk = null): static
    {
        $found = $this->matching('save', $path, $callback)
            ->filter(fn ($output) => $disk === null || $output['disk'] === $disk);

        PHPUnit::assertTrue($found->isNotEmpty(), "PDF was not saved to [{$path}].");

        return $this;
    }

    /**
     * Assert any PDF was produced (rendered, saved, downloaded or streamed).
     *
     * @param  (Closure(Pdf): bool)|null  $callback
     */
    public function assertRendered(?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching(null, null, $callback)->isNotEmpty(),
            'No matching PDF was rendered.'
        );

        return $this;
    }

    public function assertRenderedCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->outputs, "Expected {$count} PDFs, got ".count($this->outputs).'.');

        return $this;
    }

    public function assertNothingRendered(): static
    {
        PHPUnit::assertEmpty($this->outputs, count($this->outputs).' unexpected PDF(s) were rendered.');

        return $this;
    }

    /**
     * Assert a produced PDF's HTML contains the given text.
     */
    public function assertSee(string $text): static
    {
        return $this->assertRendered(fn (Pdf $pdf) => str_contains($pdf->toHtml(), $text));
    }

    protected function matching(?string $action, ?string $target, ?Closure $callback)
    {
        return collect($this->outputs)->filter(function ($output) use ($action, $target, $callback) {
            return ($action === null || $output['action'] === $action)
                && ($target === null || $output['target'] === $target)
                && ($callback === null || $callback($output['pdf']));
        });
    }
}

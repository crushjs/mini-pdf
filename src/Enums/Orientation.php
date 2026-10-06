<?php

namespace Crushjs\MiniPdf\Enums;

enum Orientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function toMpdf(): string
    {
        return $this === self::Landscape ? 'L' : 'P';
    }
}

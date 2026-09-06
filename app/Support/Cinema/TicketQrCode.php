<?php

namespace App\Support\Cinema;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class TicketQrCode
{
    public function render(string $payload, int $size = 240): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 8), new SvgImageBackEnd)))->writeString($payload);
    }
}

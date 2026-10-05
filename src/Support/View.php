<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class View
{
    public function render(string $template, array $data = []): void
    {
        $templateFile = VITSEO_ROOT . '/templates/' . $template . '.php';
        extract($data, EXTR_SKIP);
        include VITSEO_ROOT . '/templates/layout.php';
    }
}

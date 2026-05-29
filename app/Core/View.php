<?php
declare(strict_types=1);

namespace App\Core;

final class View {
    public static function render(string $template, array $data = [], string $layout = 'layout'): void {
        $viewFile = __DIR__ . '/../../views/' . $template . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "View file $viewFile not found.";
            exit;
        }

        extract($data);

        if ($layout) {
            ob_start();
            include $viewFile;
            $content = ob_get_clean();

            $layoutFile = __DIR__ . '/../../views/' . $layout . '.php';
            if (!file_exists($layoutFile)) {
                http_response_code(500);
                echo "Layout file $layoutFile not found.";
                exit;
            }
            include $layoutFile;
        } else {
            include $viewFile;
        }
    }
}

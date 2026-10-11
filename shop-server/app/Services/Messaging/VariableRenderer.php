<?php

namespace App\Services\Messaging;

use InvalidArgumentException;

class VariableRenderer
{
    /**
     * @param  array<int, string>  $allowed
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables = [], array $allowed = []): string
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template, $matches);
        $used = array_unique($matches[1] ?? []);

        if ($allowed !== []) {
            $unknown = array_diff($used, $allowed);
            if ($unknown !== []) {
                throw new InvalidArgumentException('Unknown template variables: '.implode(', ', $unknown));
            }
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $m) use ($variables): string {
                $key = $m[1];

                return (string) ($variables[$key] ?? '');
            },
            $template
        );
    }

    /**
     * @return array<int, string>
     */
    public function extract(string $template): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}

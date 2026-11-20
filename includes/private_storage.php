<?php

declare(strict_types=1);

/**
 * Retorna a raiz do armazenamento privado, sempre fora da pasta pública.
 */
function private_storage_path(string $relativePath = ''): string
{
    $configured = trim((string) ($_ENV['PRIVATE_STORAGE_PATH'] ?? ''));
    $root = $configured !== ''
        ? rtrim(str_replace('\\', '/', $configured), '/')
        : str_replace('\\', '/', dirname(__DIR__, 3) . '/private_storage/app_controle_contas_2');

    if ($relativePath === '') {
        return $root;
    }

    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (str_contains($relativePath, '../')) {
        throw new InvalidArgumentException('Caminho privado inválido.');
    }

    return $root . '/' . $relativePath;
}

function ensure_private_storage_directory(string $relativeDirectory): string
{
    $directory = private_storage_path($relativeDirectory);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o armazenamento privado.');
    }

    return $directory;
}


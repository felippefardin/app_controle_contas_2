<?php

function profile_avatar_catalog(): array
{
    return [
        'pessoa' => [
            'lorelei' => ['Aurora', 'Brisa', 'Jade', 'Sol', 'Nina', 'Luna', 'Íris', 'Cora', 'Léo', 'Theo', 'Gael', 'Ravi'],
            'pixel-art' => ['Pixel', 'Ari', 'Maya', 'Noa', 'Lia', 'Kai'],
        ],
        'empresa' => [
            'identicon' => ['Atlas', 'Nexo', 'Prisma', 'Vértice', 'Farol', 'Órbita', 'Ponte', 'Horizonte', 'Estrela', 'Norte', 'Eixo', 'Mosaico'],
            'shapes' => ['Círculo', 'Bloco', 'Fusão', 'Matriz', 'Enlace', 'Marco'],
        ],
    ];
}

function profile_avatar_url(string $choice): ?string
{
    foreach (profile_avatar_catalog() as $styles) {
        foreach ($styles as $style => $names) {
            foreach ($names as $index => $name) {
                $seed = $style . '-' . ($index + 1);
                if ($choice === 'dicebear:' . $seed) {
                    return 'https://api.dicebear.com/9.x/' . $style . '/svg?seed=' . $seed . '&size=128';
                }
            }
        }
    }

    return null;
}

function profile_image_url(?string $photo): string
{
    if ($photo !== null && str_starts_with($photo, 'dicebear:')) {
        return profile_avatar_url($photo) ?? '../img/usuarios/default-profile.png';
    }

    $file = $photo === null || $photo === '' ? 'default-profile.png' : basename($photo);
    return '../img/usuarios/' . rawurlencode($file);
}

function profile_save_uploaded_photo(?array $upload, int $userId, string $directory): ?string
{
    if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $tmpName = $upload['tmp_name'] ?? null;
    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($tmpName) || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Não foi possível receber a foto. Verifique o arquivo e o limite de upload do servidor.');
    }

    $image = @getimagesize($tmpName);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
    $extension = $extensions[$image[2] ?? 0] ?? null;
    if ($extension === null) {
        throw new RuntimeException('Selecione uma imagem válida no formato JPG, PNG ou GIF.');
    }

    $file = 'profile_' . $userId . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($tmpName, $directory . $file)) {
        throw new RuntimeException('Não foi possível salvar a foto no servidor.');
    }

    return $file;
}

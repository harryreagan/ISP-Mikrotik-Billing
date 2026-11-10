<?php

namespace App\Http\Controllers;

use Intervention\Image\Laravel\Facades\Image;

class AvatarController extends Controller
{
    public function generateAvatar($name)
    {
        // Extract initials from name
        // $initials = trim(collect(explode(' ', $name))->map(function ($segment) {
        //     return mb_substr($segment, 0, 1);
        // })->join(' '));
        $initials = collect(explode(' ', $name))->map(fn ($part) => strtoupper($part[0]))->join('');

        try {
            // Create avatar
            $fontPath = public_path('webfonts/Tinos-Regular.ttf');
            $image = Image::create(500, 500)->fill('#cef2ef')
                ->text($initials, 250, 250, function ($font) use ($fontPath) {
                    if (file_exists($fontPath)) {
                        $font->file($fontPath); // Font path
                    }
                    $font->size(230);
                    $font->color('#009e5c');
                    $font->stroke('#cef2ef', 1);
                    $font->align('center');
                    $font->valign('middle');
                    $font->lineHeight(1.6);
                    $font->angle(0);
                    $font->wrap(250);
                });

            // Save to local file
            $fileName = 'images/avatars/'.md5($name).'.png';
            $savePath = public_path($fileName);

            // Ensure the directory exists
            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $image->save($savePath);

            // Return image URL
            return asset($fileName);
        } catch (\Throwable $e) {
            // Resilient fallback to dynamic SVG avatar if image write fails
            return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=009e5c&background=cef2ef&size=500';
        }
    }
}

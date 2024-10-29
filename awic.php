<?php
/**
Plugin Name: Auto Webp Image Converter
Plugin URI: https://wordpress.org/plugins/auto-webp-image-converter/
Contributors: Farhadvn
tags: media, images, optimization, webp
Description: Simple plugin for webp support in wordpress. support JP(E)G and PNG using lib GD. zero configure.
Author: Farhad vn
Version: 1.0.1
Stable tag: 1.0.1
Tested up to: 5.8.2
Requires at least: 5.5.0
requires php: 5.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
**/
class awic_ImageConverter
{
    public function DetectImages($image_main)
    {
        if (is_numeric($image_main)) {
            $image_main = wp_get_original_image_path($image_main);
            $images = [pathinfo($image_main)["basename"]];
            $imagesize = getimagesize($image_main);
        } else {
            $imagesize = getimagesize(
                wp_upload_dir()["path"] . "/${image_main}"
            );
            $images = [$image_main];
        }
        $imagenameparts = pathinfo($image_main);
        $imagewidth = $imagesize[0];
        $imageheight = $imagesize[1];
        foreach (wp_get_registered_image_subsizes() as $size) {
            $destwidth = $size["width"];
            $destheight = $size["height"];
            switch ($destwidth) {
                case 9999:
                case 0:
                    $destwidth = round(
                        $imagewidth / ($imageheight / $destheight)
                    );
                    break;
            }
            switch ($destheight) {
                case 9999:
                case 0:
                    $destheight = round(
                        $imageheight / ($imagewidth / $destwidth)
                    );
                    break;
            }

            switch ($destwidth) {
                case 150:
                    $size = "-150x150.";
                    break;
                case $imagewidth >= $imageheight:
                    $destheight = round(
                        $imageheight * ($destwidth / $imagewidth)
                    );
                    $size = "-${destwidth}x${destheight}.";
                    break;
                case $imagewidth < $imageheight:
                    $destwidth = round(
                        $imagewidth * ($destheight / $imageheight)
                    );
                    $size = "-${destwidth}x${destheight}.";
                    break;
            }

            $image = "${imagenameparts["filename"]}${size}${imagenameparts["extension"]}";
            array_push($images, $image);
        }

        if ($imagewidth >= 2500 || $imageheight >= 2500) {
            $size = "-scaled.";
            $image = "${imagenameparts["filename"]}${size}${imagenameparts["extension"]}";
            array_push($images, $image);
        }
        return $images;
    }
}
function awic_CreateWebpFiles($attachment_id)
{
    if (isset($attachment_id["original_image"])) {
        $image_main = $attachment_id["original_image"];
    } elseif (
        isset($attachment_id["width"]) &&
        is_null($attachment_id["length_formatted"])
    ) {
        $image_main = pathinfo($attachment_id["file"])["basename"];
    } else {
        //not an image
        return;
    }
    $detect = new awic_ImageConverter();
    $images = $detect->DetectImages($image_main);
    foreach ($images as $image) {
        if (@is_file(wp_upload_dir()["path"] . "/${image}")) {
            $imagenameparts = pathinfo($image);
            $imagesource = wp_upload_dir()["path"] . "/";
            $webpindirectory =
                $imagesource . $imagenameparts["filename"] . ".webp";
            $sourceimage = $imagesource . $image;
            //webp output/ detect if webp is here
            switch (strtolower($imagenameparts["extension"])) {
                // detect the way to make webp by scanning the extension
                case "jpg":
                case "jpeg":
                    $imagecode = imagecreatefromjpeg($sourceimage);
                    break;
                case "png":
                    $imagecode = imagecreatefrompng($sourceimage);
                    imagepalettetotruecolor($imagecode);
                    //add support of png color system
                    imagealphablending($imagecode, true);
                    //add support of alpha system
                    imagesavealpha($imagecode, true);
                    //use same alpha as source if used
                    break;
            }
            //decoding image
            imagewebp($imagecode, $webpindirectory);
            // encoding and save webp version
            imagedestroy($imagecode);
        }
    }
    return $attachment_id;
}
add_action("wp_generate_attachment_metadata", "awic_CreateWebpFiles", 999, 1);
// automatic converting in upload time even in posts
function awic_CleanWebpFiles($post_id)
{
    if (wp_attachment_is_image($post_id)) {
        $image_main = $post_id;
        $detect = new awic_ImageConverter();
        $images = $detect->DetectImages($image_main);
        foreach ($images as $image) {
            if (@is_file(wp_upload_dir()["path"] . "/${image}")) {
                $imagenameparts = pathinfo($image);
                $imagesource = wp_upload_dir()["path"] . "/";
                $webpindirectory =
                    $imagesource . $imagenameparts["filename"] . ".webp";
                unlink($webpindirectory);
            }
        }
    }
    return $post_id;
}
add_action("delete_attachment", "awic_CleanWebpFiles", 1, 1);
// automatic clean after delete
//bye!
?>
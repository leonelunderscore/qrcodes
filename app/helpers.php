<?php
if (!function_exists('get_full_file_url')) {
    function get_full_file_url(string $file): string
    {
        return config('filesystems.disks.s3.url') . '/' . $file;
    }
}

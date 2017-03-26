<?php
/**
 * Helpers for ArchiveRepertory -
 * These functions are needed if the server is not fully compliant with Unicode.
 *
 * @package ArchiveRepertory
 */

/**
 * An ugly, non-ASCII-character safe replacement of escapeshellarg().
 *
 * @see http://www.php.net/manual/function.escapeshellarg.php#111919
 */
function escapeshellarg_special($string)
{
    return "'" . str_replace("'", "'\\''", $string) . "'";
}

/**
 * Get the base of a filename when it starts with an Unicode character.
 *
 * @param string $path
 *
 * @return string
 */
function basename_special($path)
{
    return preg_replace('/^.+[\\\\\\/]/', '', $path);
}

/**
 * Helper to manage unicode paths.
 *
 * @todo Manage all pathinfo dirname in all file systems.
 *
 * @param string $path
 * @param int $mode Pathinfo constants.
 * @return string
 */
function pathinfo_special($path, $mode)
{
    switch ($mode) {
        case PATHINFO_BASENAME:
            $result = preg_replace('/^.+[\\\\\\/]/', '', $path);
            break;
        case PATHINFO_FILENAME:
            $result = preg_replace('/^.+[\\\\\\/]/', '', $path);
            $positionExtension = strrpos($result, '.');
            if ($positionExtension) {
                $result = substr($result, 0, $positionExtension);
            }
            break;
        case PATHINFO_EXTENSION:
            $positionExtension = strrpos($path, '.');
            $result = $positionExtension
                ? substr($path, $positionExtension + 1)
                : '';
            break;
        case PATHINFO_DIRNAME:
            $positionDir = strrpos($path, '/');
            $result = $positionDir
                ? substr($path, 0, $positionDir - 1)
                : $path;
            break;
    }
    return $result;
}

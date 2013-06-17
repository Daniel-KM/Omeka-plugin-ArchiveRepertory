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
function escapeshellarg_special($string) {
    return "'" . str_replace("'", "'\\''", $string) . "'";
}

/**
 * Get the base of a filename when it starts with an Unicode character.
 *
 * @param string $path
 *
 * @return string
 */
function basename_special($path) {
    return preg_replace( '/^.+[\\\\\\/]/', '', $path);
}

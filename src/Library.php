<?php
// file generated with AI assistance: Claude Code - 2026-11-16 15:58:00 UTC

declare(strict_types=1);

namespace Dmstr\JedisonExtender;

/**
 * The third-party libraries the bundle ships as individually switchable
 * extensions (`jx/<name>/`, activated through `core.editors`).
 *
 * THE SINGLE SOURCE: configuration validation, the locator's filter and the
 * tests all read this list, so a new library is added in exactly one place.
 * Awesomplete is deliberately absent — it is part of `core` and always on.
 */
final class Library
{
    public const ALL = ['ace', 'choices', 'flatpickr', 'imask', 'jodit', 'quill', 'raty', 'tom-select'];

    private function __construct()
    {
    }
}

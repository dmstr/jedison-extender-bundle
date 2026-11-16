# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. Flatpickr (unmaintained since 2022-04, but
# the version Jedison targets).
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=flatpickr
VERSION=4.6.13
INTEGRITY='sha512-97PMG/aywoYpB4IvbvUJi0RQi8vearvU0oov1WW3k0WZPBMrTQVqekSX5CjSG/M4Q3i6A/0FKXC7RyAoAUUSPw=='
TARGET=jx/flatpickr/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/dist/flatpickr.min.js" \
        > "$TARGET/flatpickr.min.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/ *$/d' "$PKG/dist/flatpickr.min.css" \
        > "$TARGET/flatpickr.min.css"
    # Locales are not loaded by default; a host loads the one it needs.
    mkdir -p "$TARGET/l10n"
    cp "$PKG"/dist/l10n/*.js "$TARGET/l10n/"
    cp "$PKG/LICENSE.md" "$TARGET/LICENSE.md"
}

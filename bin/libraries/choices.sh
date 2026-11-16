# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. Choices.js; the Jedison demo loads the
# unpinned latest, which is 11.x.
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=choices.js
VERSION=11.2.4
INTEGRITY='sha512-3ESJ42LC1cFPIK2sRxa+Y1zEM5vpeOL/FrZHh6dqqqTHf23FStbjKhxP+eMC0F3O0xRUkhQmOOtUxIi83RxhTg=='
TARGET=jx/choices/vendor

install_files() {
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/public/assets/scripts/choices.min.js" \
        > "$TARGET/choices.min.js"
    sed '/^\/\*# sourceMappingURL=.*\*\/ *$/d' "$PKG/public/assets/styles/choices.min.css" \
        > "$TARGET/choices.min.css"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

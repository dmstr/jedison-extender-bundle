# file generated with AI assistance: Claude Code - 2026-11-16 16:02:59 UTC
#
# Recipe for bin/fetch-libraries. Raty 4.3.0 with its default star images.
# Trailing sourceMappingURL comments are dropped because no source maps are
# shipped and the browser would otherwise request a missing file.
PACKAGE=raty-js
VERSION=4.3.0
INTEGRITY='sha512-ZBnYsEg4YvcUbhdtLlBElmB4qjIiHYaZv5W0ThaKWLaS32xPCI9iPGIe35GuYLLrVIgUZ/IdS3+SO0NfPgXrDA=='
TARGET=jx/raty/vendor

install_files() {
    # build/raty.min.js (not the UMD one): it declares the class Raty that
    # Jedison checks with typeof. Only the star images are needed (starType img).
    sed '/^\/\/# sourceMappingURL=/d' "$PKG/build/raty.min.js" \
        > "$TARGET/raty.min.js"
    mkdir -p "$TARGET/images"
    cp "$PKG"/src/images/*.png "$TARGET/images/"
    cp "$PKG/LICENSE" "$TARGET/LICENSE"
}

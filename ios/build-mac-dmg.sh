#!/usr/bin/env bash
# Build a signed Mac (Catalyst) app and package it into a DMG, mirroring the
# tuneflow approach: xcodebuild archive → exportArchive (Developer ID) →
# hdiutil DMG → optional notarize + staple.
#
# Usage:
#   ./build-mac-dmg.sh
#   NOTARY_PROFILE=<keychain-profile> ./build-mac-dmg.sh   # also notarize+staple
#
# Set up the keychain profile once with:
#   xcrun notarytool store-credentials <name> --apple-id <id> --team-id 75C95MRFQ9 --password <app-specific-pw>
set -euo pipefail
cd "$(dirname "$0")"

SCHEME="TododelooMac"
PROJECT="Tododeloo.xcodeproj"
TEAM_ID="75C95MRFQ9"
SIGNING_IDENTITY="Developer ID Application: Fabrique Stereotique (75C95MRFQ9)"
BUILD_DIR="/tmp/tododeloo-macdmg"
ARCHIVE="$BUILD_DIR/Tododeloo.xcarchive"
EXPORT_DIR="$BUILD_DIR/export"
STAGING="$BUILD_DIR/staging"
FINAL_DMG="$HOME/Desktop/Tododeloo.dmg"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

echo "→ Archiveren (native macOS, Release)…"
xcodebuild -project "$PROJECT" -scheme "$SCHEME" -configuration Release \
    -destination 'platform=macOS' \
    -archivePath "$ARCHIVE" \
    archive -allowProvisioningUpdates \
    ENABLE_HARDENED_RUNTIME=YES

echo "→ Exporteren met Developer ID…"
EXPORT_PLIST="$BUILD_DIR/export.plist"
cat > "$EXPORT_PLIST" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>method</key><string>developer-id</string>
    <key>teamID</key><string>$TEAM_ID</string>
    <key>signingStyle</key><string>automatic</string>
    <key>destination</key><string>export</string>
</dict>
</plist>
PLIST

xcodebuild -exportArchive -archivePath "$ARCHIVE" -exportPath "$EXPORT_DIR" \
    -exportOptionsPlist "$EXPORT_PLIST" -allowProvisioningUpdates

echo "→ DMG samenstellen…"
rm -rf "$STAGING"
mkdir -p "$STAGING"
cp -R "$EXPORT_DIR/TododelooMac.app" "$STAGING/"
ln -s /Applications "$STAGING/Applications"

rm -f "$FINAL_DMG"
hdiutil create -volname "Tododeloo" -srcfolder "$STAGING" -ov -format UDZO "$FINAL_DMG"

echo "→ DMG signeren…"
codesign --force --sign "$SIGNING_IDENTITY" "$FINAL_DMG"

if [ -n "${NOTARY_PROFILE:-}" ]; then
    echo "→ Notariseren ($NOTARY_PROFILE)…"
    xcrun notarytool submit "$FINAL_DMG" --keychain-profile "$NOTARY_PROFILE" --wait
    xcrun stapler staple "$FINAL_DMG"
else
    echo "  (notarisatie overgeslagen — zet NOTARY_PROFILE om te notariseren)"
fi

echo "✓ Klaar: $FINAL_DMG"

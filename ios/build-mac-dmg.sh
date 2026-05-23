#!/usr/bin/env bash
# Build a signed native macOS app and package it into a DMG (+ a zip), mirroring
# the tuneflow approach: xcodebuild archive → exportArchive (Developer ID) →
# hdiutil DMG → optional notarize + staple → zip.
#
# This repo is public, so no signing config lives here. The Apple Team ID is read
# from the git-ignored Config/Local.xcconfig (the same file Xcode signs with), or
# from the DEVELOPMENT_TEAM env var; the Developer ID Application identity is
# auto-detected from your keychain. The notary credentials live only in the
# keychain (never in git).
#
# Usage:
#   ./build-mac-dmg.sh
#   NOTARY_PROFILE=<keychain-profile> ./build-mac-dmg.sh   # also notarize+staple
#
# One-time notary setup (stores an app-specific password in the keychain):
#   xcrun notarytool store-credentials <name> --apple-id <id> --team-id <TEAMID> --password <app-specific-pw>
set -euo pipefail
cd "$(dirname "$0")"

SCHEME="TododelooMac"
PROJECT="Tododeloo.xcodeproj"

# --- Resolve the Team ID without committing it ------------------------------
TEAM_ID="${DEVELOPMENT_TEAM:-}"
if [ -z "$TEAM_ID" ] && [ -f Config/Local.xcconfig ]; then
    TEAM_ID="$(sed -n 's/^[[:space:]]*DEVELOPMENT_TEAM[[:space:]]*=[[:space:]]*//p' Config/Local.xcconfig | tr -d '[:space:]')"
fi
if [ -z "$TEAM_ID" ]; then
    echo "✗ Geen Team ID gevonden. Zet DEVELOPMENT_TEAM in ios/Config/Local.xcconfig of als env var." >&2
    exit 1
fi

# --- Auto-detect the Developer ID Application identity for this team ---------
SIGNING_IDENTITY="$(security find-identity -v -p codesigning \
    | grep "Developer ID Application" | grep "($TEAM_ID)" \
    | head -1 | sed -E 's/^[^"]*"([^"]+)".*/\1/')"
if [ -z "$SIGNING_IDENTITY" ]; then
    echo "✗ Geen 'Developer ID Application' certificaat voor team $TEAM_ID in de keychain." >&2
    exit 1
fi
echo "→ Signing identity: $SIGNING_IDENTITY"

BUILD_DIR="/tmp/tododeloo-macdmg"
ARCHIVE="$BUILD_DIR/Tododeloo.xcarchive"
EXPORT_DIR="$BUILD_DIR/export"
STAGING="$BUILD_DIR/staging"
FINAL_DMG="$HOME/Desktop/Tododeloo.dmg"
FINAL_ZIP="$HOME/Desktop/Tododeloo.dmg.zip"

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

echo "→ Zippen…"
rm -f "$FINAL_ZIP"
ditto -c -k "$FINAL_DMG" "$FINAL_ZIP"

echo "✓ Klaar:"
echo "   $FINAL_DMG"
echo "   $FINAL_ZIP"

---
name: ios-app
description: "Use when building, running, installing, deploying, or debugging the Tododeloo iOS/Mac app in ios/, or touching its Xcode project, signing, Siri App Intents, or device install. Covers deploying to a physical iPhone (ios/deploy-iphone.sh), the simulator build command, Mac Catalyst being intentionally off, the Apple Team ID living in a git-ignored Local.xcconfig, file-system-synchronized groups (no pbxproj edits to add Swift files), bundle id com.tododeloo.app, the production API URL, and password autofill (apple-app-site-association / APPLE_APP_ID). Do NOT use for the Laravel backend or the Vue web app."
metadata:
  author: tododeloo
---

# Tododeloo iOS app

Native SwiftUI clients in `ios/`: the **iPhone app** (`Tododeloo` target, iOS 17+)
and a separate **native macOS app** (`TododelooMac` target, macOS 14+). Both talk
to the Laravel JSON API with Sanctum bearer tokens; default to the production URL.

The two share a **core** (Models / Networking / State / Support, all under
`ios/Tododeloo/`) and have **separate UI files** per platform — the iPhone UI lives
in `ios/Tododeloo/Views/`, the Mac UI in `ios/TododelooMac/`. The shared core is
cross-platform (UIKit usage gated with `#if canImport(UIKit)`). Pattern copied from
the sibling `tuneflow` project (shared core + per-platform UI).

## Deploy to a physical iPhone (reliable, one command)

```bash
cd ios && ./deploy-iphone.sh        # optional: ./deploy-iphone.sh <device-udid>
```

Builds (Debug, device), installs and launches via `xcrun devicectl`. Use this —
it is more reliable than Xcode's Run button in this project.

In Xcode instead: pick the real device (e.g. **iPhone van max**) in the
destination menu — NOT "Any iOS Device" (that only builds, never installs) — and
press **Run (⌘R)**, not Build (⌘B). Quit and reopen Xcode after editing
`project.pbxproj` so it reloads.

## Verify a build without a device

```bash
cd ios && xcodebuild build -scheme Tododeloo -sdk iphonesimulator \
  -destination 'generic/platform=iOS Simulator' CODE_SIGNING_ALLOWED=NO
```

## Native macOS app (`TododelooMac`)

Separate native target — NOT Mac Catalyst. Three-column `NavigationSplitView`
(sidebar · todo list · detail inspector), bundle id `com.tododeloo.mac`,
`SUPPORTED_PLATFORMS = macosx`, own entitlements `ios/TododelooMac/TododelooMac.entitlements`
(non-sandboxed for dev). UI files live in `ios/TododelooMac/`.

```bash
# Build + run on this Mac:
cd ios && xcodebuild -scheme TododelooMac -destination 'platform=macOS,arch=arm64' \
  -derivedDataPath /tmp/tododeloo-macnative -allowProvisioningUpdates build
open /tmp/tododeloo-macnative/Build/Products/Debug/TododelooMac.app
# Compile-only check: add CODE_SIGNING_ALLOWED=NO
# Package a signed DMG (Developer ID): ./build-mac-dmg.sh   (NOTARY_PROFILE=… to notarize)
```

The target was added with the **`xcodeproj` Ruby gem** (`ios/add-mac-target.rb`,
idempotent). The system gem (1.25) can't open objectVersion-77 projects — install
1.27+ user-side: `gem install --user-install xcodeproj` then run with
`GEM_PATH="$(ruby -e 'print Gem.user_dir'):$(gem env gemdir)"`. The Mac target uses
**explicit file references** to the shared core files + its own `TododelooMac/` UI;
the iOS synchronized group is untouched.

## Project facts & gotchas

- **Mac Catalyst is intentionally OFF** on the iPhone target (`SUPPORTS_MACCATALYST`
  absent). The Mac is served by the separate native `TododelooMac` target instead —
  do NOT enable Catalyst on the iPhone target (Xcode then auto-selects "My Mac" and
  the iPhone never gets installed).
- **File-system-synchronized groups** (objectVersion 77): new Swift files placed
  under `ios/Tododeloo/` are auto-included in the iPhone target. New files under
  `ios/TododelooMac/` (or new shared files the Mac target must compile) are NOT
  auto-added to the Mac target — add them to `add-mac-target.rb` and re-run it.
- **Team ID is local-only**: `ios/Config/Local.xcconfig` (git-ignored) holds
  `DEVELOPMENT_TEAM`, loaded via `Config/Signing.xcconfig`. On a fresh clone:
  `cp ios/Config/Local.xcconfig.example ios/Config/Local.xcconfig` and set the
  Team ID. Never hardcode `DEVELOPMENT_TEAM` in `project.pbxproj` — it was
  scrubbed from git history.
- **Bundle id** `com.tododeloo.app`, automatic signing.
- **API URL** defaults to `https://tododeloo.on-forge.com` (`AppConfig.swift`),
  overridable at runtime via the in-app Server sheet (stored in UserDefaults).
  DEBUG builds trust self-signed certs only for `.test` hosts (local Herd).

## Siri (App Intents)

- `QuickAddTodoIntent` + `TododelooShortcuts` register phrases like
  "Voeg toe aan Tododeloo"; Siri then asks for the task and it lands on today.
- iOS does NOT allow free-form text inside an App Shortcut trigger phrase
  (phrase parameters must be `AppEnum`/`AppEntity`). Adding a `String` param to a
  phrase makes `appintentsmetadataprocessor` fail the build. So one-breath
  "voeg <x> toe aan Tododeloo" is impossible via App Shortcuts — for that, use a
  Shortcuts-app shortcut with a "Dictate Text" step feeding the intent.
- The intent reads the token from the Keychain (shared with the app) and posts to
  `/api/quick-add`.

## Password autofill (Associated Domains)

- Entitlement `ios/Tododeloo.entitlements`: `webcredentials:tododeloo.on-forge.com`.
- Backend serves `/.well-known/apple-app-site-association` (route in
  `routes/web.php`); the app id comes from the `APPLE_APP_ID` env var. Set it on
  Forge: `APPLE_APP_ID=<TEAMID>.com.tododeloo.app`, deploy, then reinstall the app.

## When Xcode rewrites project.pbxproj

Opening the project can reformat `project.pbxproj`. Re-check afterwards that:
Mac Catalyst stays off, no `DEVELOPMENT_TEAM` line was re-added (it belongs in
`Local.xcconfig`), and the bundle id is still `com.tododeloo.app`.

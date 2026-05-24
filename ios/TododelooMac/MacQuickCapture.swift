import AppKit
import Carbon.HIToolbox
import SwiftUI

/// Owns the system-wide quick-capture hotkey (⌃⌥⌘Space) and the floating panel it
/// opens — capture a todo from anywhere without switching to the app, the
/// Akiflow/TickTick move. The Mac target is non-sandboxed, so a Carbon hotkey
/// works without extra permissions.
final class AppDelegate: NSObject, NSApplicationDelegate {
    private var hotKey: GlobalHotKey?
    private let capture = CapturePanelController()

    func applicationDidFinishLaunching(_ notification: Notification) {
        hotKey = GlobalHotKey(
            keyCode: UInt32(kVK_Space),
            modifiers: UInt32(controlKey | optionKey | cmdKey)
        ) { [weak self] in
            self?.capture.toggle()
        }
    }
}

/// A registered Carbon hot key. Holds its registration for the app's lifetime;
/// the shared C event handler routes presses back to the matching instance.
final class GlobalHotKey {
    private var ref: EventHotKeyRef?
    private let callback: () -> Void
    private let identifier: UInt32

    private static var registry: [UInt32: GlobalHotKey] = [:]
    private static var nextID: UInt32 = 1
    private static var handlerInstalled = false

    init(keyCode: UInt32, modifiers: UInt32, callback: @escaping () -> Void) {
        self.callback = callback
        self.identifier = Self.nextID
        Self.nextID += 1
        Self.registry[identifier] = self

        Self.installHandlerIfNeeded()

        let hotKeyID = EventHotKeyID(signature: OSType(0x5444_4C4F /* 'TDLO' */), id: identifier)
        RegisterEventHotKey(keyCode, modifiers, hotKeyID, GetApplicationEventTarget(), 0, &ref)
    }

    deinit {
        if let ref { UnregisterEventHotKey(ref) }
        Self.registry[identifier] = nil
    }

    private static func installHandlerIfNeeded() {
        guard !handlerInstalled else { return }
        handlerInstalled = true

        var spec = EventTypeSpec(eventClass: OSType(kEventClassKeyboard), eventKind: UInt32(kEventHotKeyPressed))
        InstallEventHandler(GetApplicationEventTarget(), { _, event, _ -> OSStatus in
            guard let event else { return noErr }
            var pressedID = EventHotKeyID()
            GetEventParameter(
                event,
                EventParamName(kEventParamDirectObject),
                EventParamType(typeEventHotKeyID),
                nil,
                MemoryLayout<EventHotKeyID>.size,
                nil,
                &pressedID
            )
            GlobalHotKey.registry[pressedID.id]?.callback()
            return noErr
        }, 1, &spec, nil, nil)
    }
}

/// Lazily-built floating panel that hosts the SwiftUI capture field.
final class CapturePanelController {
    private var panel: CapturePanel?

    func toggle() {
        if let panel, panel.isVisible {
            panel.orderOut(nil)
        } else {
            present()
        }
    }

    private func present() {
        let panel = panel ?? makePanel()
        self.panel = panel

        // Rebuild the SwiftUI content on every open so the field starts empty and
        // its `.onAppear` fires again — that re-request is what lands the cursor in
        // the field. A reused view's `.onAppear` only fires once, so focus would be
        // lost on the second and later opens.
        panel.contentViewController = NSHostingController(
            rootView: CaptureView { [weak self] in self?.panel?.orderOut(nil) }
        )

        NSApp.activate(ignoringOtherApps: true)
        panel.center()
        panel.makeKeyAndOrderFront(nil)
    }

    private func makePanel() -> CapturePanel {
        let panel = CapturePanel(
            contentRect: NSRect(x: 0, y: 0, width: 460, height: 76),
            styleMask: [.titled, .fullSizeContentView, .nonactivatingPanel],
            backing: .buffered,
            defer: false
        )
        panel.titleVisibility = .hidden
        panel.titlebarAppearsTransparent = true
        panel.isMovableByWindowBackground = true
        panel.isFloatingPanel = true
        panel.level = .floating
        panel.hidesOnDeactivate = true

        return panel
    }
}

/// A panel that is allowed to take keyboard focus. A `.nonactivatingPanel` does not
/// become the key window by default, so `makeKeyAndOrderFront` alone leaves the
/// capture field unable to become first responder — typing would go nowhere.
/// Opting in here is what makes the cursor actually land in the field.
final class CapturePanel: NSPanel {
    override var canBecomeKey: Bool { true }
    override var canBecomeMain: Bool { true }
}

/// The capture field itself: type a todo, press return, it lands via /quick-add
/// (Dutch parser included). Escape closes.
struct CaptureView: View {
    let onClose: () -> Void
    @State private var text = ""
    @State private var parse = true
    @State private var isBusy = false
    @FocusState private var focused: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 12) {
                AccentDot(size: 8)
                TextField("Snel toevoegen aan Tododeloo…", text: $text)
                    .textFieldStyle(.plain)
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(Theme.ink)
                    .focused($focused)
                    .onSubmit(submit)
                ParseModeToggle(parsing: $parse)
                if isBusy {
                    ProgressView().controlSize(.small)
                }
            }
            ParsePreviewStrip(text: text, parse: parse)
        }
        .padding(.horizontal, 20)
        .padding(.vertical, 18)
        .frame(width: 460)
        .background(Theme.background)
        .onAppear {
            // Defer one runloop tick: makeKeyAndOrderFront has run by now, so the
            // panel is key and the focus request actually sticks.
            DispatchQueue.main.async { focused = true }
        }
        .onExitCommand(perform: onClose)
    }

    private func submit() {
        let title = text.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !title.isEmpty else { onClose(); return }
        guard APIClient.shared.isAuthenticated else { onClose(); return }
        isBusy = true
        Task {
            _ = try? await APIClient.shared.quickAdd(title: title, parse: parse)
            isBusy = false
            text = ""
            onClose()
        }
    }
}
